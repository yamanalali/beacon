<?php

namespace Tests\Feature\Baseline;

use App\Actions\Baseline\ImportBaselineXlsxAction;
use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use App\Models\Enterprise;
use App\Models\EnterpriseBaselineProfile;
use App\Models\User;
use Database\Seeders\BaselineFieldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BaselineImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineFieldCatalogSeeder::class);
        $this->fixture = base_path('tests/Fixtures/baseline_mse_sample.xlsx');
        $this->assertFileExists($this->fixture);
    }

    public function test_import_creates_enterprise_and_baseline_answers(): void
    {
        $result = app(ImportBaselineXlsxAction::class)->execute(null, $this->fixture, false);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['errors']);

        $enterprise = Enterprise::query()->where('business_name', 'ركن المراقبة')->first();
        $this->assertNotNull($enterprise);
        $this->assertSame('0954202488', $enterprise->contact_phone);
        $this->assertSame('راتب محمد رجب', $enterprise->owner_name);
        $this->assertNotNull($enterprise->address_line);

        $profile = EnterpriseBaselineProfile::query()->where('enterprise_id', $enterprise->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('DA-1fcd8e6', $profile->external_mse_id);
        $this->assertArrayHasKey('col_s', $profile->answers);
        $this->assertSame('ركن المراقبة', $profile->answers['col_s']);
    }

    public function test_import_is_idempotent_by_mse_id(): void
    {
        $action = app(ImportBaselineXlsxAction::class);
        $action->execute(null, $this->fixture, false);
        $second = $action->execute(null, $this->fixture, false);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, Enterprise::query()->count());
        $this->assertSame(1, EnterpriseBaselineProfile::query()->count());
    }

    public function test_hidden_fields_are_omitted_from_show_props(): void
    {
        app(ImportBaselineXlsxAction::class)->execute(null, $this->fixture, false);
        $enterprise = Enterprise::query()->where('business_name', 'ركن المراقبة')->firstOrFail();

        BaselineProfileField::query()->where('section', 'identity')->update(['is_visible' => false]);
        BaselineProfileField::query()->where('key', 'col_s')->update(['is_visible' => true]);

        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $this->actingAs($admin)
            ->get(route('enterprises.show', $enterprise))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SMEs/Show')
                ->where('baseline.has_profile', true)
                ->has('baseline.sections')
                ->where('baseline.sections', function ($sections) {
                    $keys = collect($sections)->flatMap(fn ($s) => collect($s['fields'])->pluck('key'));

                    return $keys->contains('col_s')
                        && ! collect($sections)->contains(fn ($s) => $s['key'] === 'ops');
                }));
    }

    public function test_sme_cannot_view_another_enterprise_baseline(): void
    {
        app(ImportBaselineXlsxAction::class)->execute(null, $this->fixture, false);
        $enterprise = Enterprise::query()->where('business_name', 'ركن المراقبة')->firstOrFail();

        $other = Enterprise::query()->create([
            'business_name' => 'منشأة أخرى',
            'location' => 'damascus',
        ]);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $other->members()->attach($sme->id);

        $this->actingAs($sme)->get(route('enterprises.show', $enterprise))->assertForbidden();
    }

    public function test_admin_can_open_baseline_fields_and_import_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $this->actingAs($admin)->get(route('admin.baseline-fields.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.baseline.import'))->assertOk();

        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->actingAs($sme)->get(route('admin.baseline-fields.index'))->assertForbidden();
    }

    public function test_admin_can_toggle_field_visibility(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $field = BaselineProfileField::query()->where('key', 'col_s')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.baseline-fields.visibility'), [
            'fields' => [['id' => $field->id, 'is_visible' => false]],
        ])->assertRedirect();

        $this->assertFalse($field->fresh()->is_visible);
    }

    public function test_admin_can_upload_baseline_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $upload = new UploadedFile($this->fixture, 'baseline.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)
            ->post(route('admin.baseline.import.store'), ['file' => $upload])
            ->assertRedirect();

        $this->assertDatabaseHas('enterprises', ['business_name' => 'ركن المراقبة']);
    }
}
