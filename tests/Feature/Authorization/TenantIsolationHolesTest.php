<?php

namespace Tests\Feature\Authorization;

use App\Models\AdvisoryActivity;
use App\Models\FollowUpAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Readiness\ReadinessTestHelpers;
use Tests\TestCase;

/** One test per hole fixed in fix/tenant-isolation-audit. */
class TenantIsolationHolesTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_hole1_follow_up_responsible_user_must_belong_to_the_enterprise(): void
    {
        $a = $this->enterprise('A');
        $b = $this->enterprise('B');
        $advisor = $this->advisorOf($a);
        $smeB = $this->smeOf($b);
        $mfi = $this->mfiUserOf($this->institution());

        foreach ([$smeB, $mfi] as $outsider) {
            $this->actingAs($advisor)
                ->post(route('enterprises.follow-ups.store', $a), ['description' => 'x', 'responsible_user_id' => $outsider->id])
                ->assertSessionHasErrors('responsible_user_id');
        }
        $this->assertSame(0, FollowUpAction::query()->count());

        $smeA = $this->smeOf($a);
        $this->actingAs($advisor)
            ->post(route('enterprises.follow-ups.store', $a), ['description' => 'x', 'responsible_user_id' => $smeA->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, FollowUpAction::query()->count());
    }

    public function test_hole1_advisory_activity_responsible_user_must_belong_to_the_enterprise(): void
    {
        $a = $this->enterprise('A');
        $b = $this->enterprise('B');
        $advisor = $this->advisorOf($a);
        $smeB = $this->smeOf($b);

        $this->actingAs($advisor)
            ->post(route('enterprises.advisory.store', $a), ['activity_date' => '2026-10-01', 'responsible_user_id' => $smeB->id])
            ->assertSessionHasErrors('responsible_user_id');
        $this->assertSame(0, AdvisoryActivity::query()->count());

        $this->actingAs($advisor)
            ->post(route('enterprises.advisory.store', $a), ['activity_date' => '2026-10-01', 'responsible_user_id' => $advisor->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, AdvisoryActivity::query()->count());
    }

    public function test_hole2_document_upload_to_another_enterprise_is_403_before_validation(): void
    {
        Storage::fake('local');
        $a = $this->enterprise('A');
        $b = $this->enterprise('B');
        $smeA = $this->smeOf($a);

        // Empty body: previously answered 302 with validation errors (probing another tenant).
        $this->actingAs($smeA)->post(route('enterprises.documents.store', $b), [])->assertForbidden();
        $this->actingAs($smeA)->post(route('enterprises.documents.store', $b), [
            'file' => UploadedFile::fake()->image('r.jpg'), 'title' => 'x',
        ])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
