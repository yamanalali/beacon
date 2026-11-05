<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessRuleSet;
use App\Models\User;
use App\Support\ReadinessExampleTemplate;
use App\Support\ReadinessRuleSetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReadinessTemplateTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
        $this->admin = $this->user(UserRole::BeaconAdmin);
    }

    public function test_example_definition_passes_validator(): void
    {
        $def = ReadinessExampleTemplate::definition();
        $this->assertSame(ReadinessRuleSetValidator::FORMAT, $def['format']);
        $errors = ReadinessRuleSetValidator::errors($def, true, true);
        $this->assertSame([], $errors);
    }

    public function test_admin_can_download_excel_and_json_templates(): void
    {
        $excel = $this->actingAs($this->admin)->get(route('admin.readiness.templates.excel-example'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $excel->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($excel->getContent()));

        $blank = $this->actingAs($this->admin)->get(route('admin.readiness.templates.excel-blank'));
        $blank->assertOk();

        $json = $this->actingAs($this->admin)->get(route('admin.readiness.templates.json-example'));
        $json->assertOk()->assertJsonPath('format', ReadinessRuleSetValidator::FORMAT);
    }

    public function test_non_admin_cannot_download_templates(): void
    {
        $this->actingAs($this->user(UserRole::BeaconAdvisor))
            ->get(route('admin.readiness.templates.excel-example'))
            ->assertForbidden();
    }

    public function test_excel_example_roundtrips_through_import(): void
    {
        $bytes = ReadinessExampleTemplate::excelBytes();
        $parsed = ReadinessExampleTemplate::definitionFromExcel($this->tempXlsx($bytes));
        $this->assertSame(ReadinessExampleTemplate::definition()['name_ar'], $parsed['name_ar']);
        $this->assertCount(3, $parsed['pillars']);
        $this->assertCount(5, $parsed['criteria']);
        $this->assertCount(3, $parsed['bands']);
        $this->assertSame([], ReadinessRuleSetValidator::errors($parsed, true, true));

        $file = UploadedFile::fake()->createWithContent('example.xlsx', $bytes);
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_DRAFT)->firstOrFail();
        $this->assertSame('مثال جاهزية — للمنشآت الصغيرة', $draft->name_ar);
        $this->assertSame(3, $draft->pillars()->count());
        $this->assertSame(5, $draft->criteria()->count());
        $this->assertSame(3, $draft->bands()->count());
    }

    private function tempXlsx(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);

        return $path;
    }
}
