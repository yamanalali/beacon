<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\Referral;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPackageFieldsTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
        CarbonImmutable::setTestNow('2026-10-16 12:00:00');
        $this->travelTo('2026-10-16 12:00:00');
    }

    private function tx(Enterprise $e, string $type, string $amount, string $date, string $currency = 'SYP', string $status = 'posted'): void
    {
        (new FinancialTransaction)->forceFill([
            'enterprise_id' => $e->id, 'type' => $type, 'transaction_date' => $date, 'amount' => $amount,
            'currency' => $currency, 'category' => 'cat', 'payment_status' => 'paid', 'status' => $status,
        ])->save();
    }

    private function doc(Enterprise $e, string $title, string $mime, ?string $archivedAt = null): void
    {
        (new Document)->forceFill([
            'enterprise_id' => $e->id, 'title' => $title, 'original_name' => 'secret-name.pdf', 'disk' => 'local',
            'path' => 'documents/secret/path.pdf', 'mime_type' => $mime, 'size' => 10, 'archived_at' => $archivedAt,
        ])->save();
    }

    /** @return array{Enterprise, User, Referral} */
    private function referral(array $items, ?Enterprise $enterprise = null): array
    {
        $enterprise ??= $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $this->institution()->id, 'purpose_ar' => 'تمويل', 'disclosed_items' => $items,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return [$enterprise, $advisor, Referral::query()->latest('id')->firstOrFail()];
    }

    public function test_profile_has_arabic_labels_and_keeps_raw_codes(): void
    {
        $e = $this->enterprise();
        $e->forceFill(['sector' => 'agriculture', 'registration_status' => 'registered'])->save();
        [, $advisor, $r] = $this->referral(['enterprise_profile'], $e);

        $this->actingAs($advisor)->get(route('referrals.show', $r))->assertOk()
            ->assertInertia(fn ($p) => $p->where('package.enterprise_profile.sector', 'agriculture')
                ->where('package.enterprise_profile.sector_label', 'الزراعة')
                ->where('package.enterprise_profile.registration_status', 'registered')
                ->where('package.enterprise_profile.registration_status_label', 'مسجّلة'));
    }

    public function test_free_text_values_are_shown_as_entered(): void
    {
        $e = $this->enterprise();
        $e->forceFill(['sector' => 'صناعة الصابون', 'registration_status' => null])->save();
        [, $advisor, $r] = $this->referral(['enterprise_profile'], $e);

        $this->actingAs($advisor)->get(route('referrals.show', $r))
            ->assertInertia(fn ($p) => $p->where('package.enterprise_profile.sector_label', 'صناعة الصابون')
                ->where('package.enterprise_profile.registration_status_label', null));
    }

    public function test_financial_summary_posted_in_period_per_currency_this_enterprise_only(): void
    {
        $e = $this->enterprise();
        $other = $this->enterprise('منشأة أخرى');
        $this->tx($e, 'sale', '1000.10', '2026-10-01');
        $this->tx($e, 'income', '0.20', '2026-01-15');
        $this->tx($e, 'expense', '300.05', '2026-05-01');
        $this->tx($e, 'purchase', '100.00', '2025-10-17'); // first day of window
        $this->tx($e, 'sale', '50', '2026-09-01', 'USD');
        $this->tx($e, 'expense', '70.5', '2026-09-02', 'USD');
        // excluded
        $this->tx($e, 'sale', '9999', '2025-10-16');               // outside period
        $this->tx($e, 'sale', '8888', '2026-10-02', 'SYP', 'superseded'); // not posted
        $this->tx($other, 'sale', '7777', '2026-10-02');           // other enterprise
        [, $advisor, $r] = $this->referral(['financial_summary'], $e);

        $this->actingAs($advisor)->get(route('referrals.show', $r))->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('package.financial_summary.period', ['from' => '2025-10-17', 'to' => '2026-10-16'])
                ->where('package.financial_summary.currencies', [
                    ['currency' => 'SYP', 'income_total' => '1000.30', 'expense_total' => '400.05', 'net' => '600.25', 'transaction_count' => 4],
                    ['currency' => 'USD', 'income_total' => '50.00', 'expense_total' => '70.50', 'net' => '-20.50', 'transaction_count' => 2],
                ]));
    }

    public function test_financial_summary_empty_and_hidden_without_consent(): void
    {
        [$e, $advisor, $r] = $this->referral(['financial_summary', 'documents']);
        $this->actingAs($advisor)->get(route('referrals.show', $r))
            ->assertInertia(fn ($p) => $p->where('package.financial_summary.currencies', [])->where('package.documents', []));

        $this->tx($e, 'sale', '5', '2026-10-01');
        $this->doc($e, 'رخصة', 'application/pdf');
        [, , $r2] = $this->referral(['enterprise_profile'], $e);
        $this->actingAs($advisor)->get(route('referrals.show', $r2))
            ->assertInertia(fn ($p) => $p->missing('package.financial_summary')->missing('package.documents'));
    }

    public function test_documents_have_only_name_type_date(): void
    {
        $e = $this->enterprise();
        $this->doc($e, 'السجل التجاري', 'application/pdf');
        $this->doc($e, 'صورة المحل', 'image/jpeg');
        $this->doc($e, 'قديم', 'application/pdf', '2026-10-01 00:00:00');
        $this->doc($this->enterprise('غيرها'), 'مستند منشأة أخرى', 'application/pdf');
        [, $advisor, $r] = $this->referral(['documents'], $e);

        $res = $this->actingAs($advisor)->get(route('referrals.show', $r))->assertOk();
        $docs = $res->viewData('page')['props']['package']['documents'];
        $this->assertCount(2, $docs);
        foreach ($docs as $d) {
            $this->assertSame(['name', 'type', 'date'], array_keys($d));
            $this->assertSame('2026-10-16', $d['date']);
        }
        $this->assertEqualsCanonicalizing(['السجل التجاري', 'صورة المحل'], array_column($docs, 'name'));
        $this->assertEqualsCanonicalizing(['ملف PDF', 'صورة'], array_column($docs, 'type'));
        $json = json_encode($docs, JSON_UNESCAPED_UNICODE);
        foreach (['secret', 'documents/', 'http', 'public_id', '"id"'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_cross_tenant_access_still_forbidden(): void
    {
        [, , $r] = $this->referral(['enterprise_profile', 'financial_summary', 'documents']);
        $this->actingAs($this->smeOf($this->enterprise('ب')))->get(route('referrals.show', $r))->assertForbidden();
        $this->actingAs($this->mfiUserOf($this->institution('مؤسسة ب')))->get(route('referrals.show', $r))->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdvisor))->get(route('referrals.show', $r))->assertForbidden();
    }
}
