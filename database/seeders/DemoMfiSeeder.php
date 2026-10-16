<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\MfiInstitution;
use App\Models\Referral;
use App\Models\ReferralConsent;
use Illuminate\Database\Seeder;

/**
 * Demo MFI institution, links the demo MFI user to it, and adds one shared referral
 * with a valid consent so /mfi/referrals has something to show. LOCAL only (via DemoSeeder). Idempotent.
 */
class DemoMfiSeeder extends Seeder
{
    public const NAME = 'مؤسسة التمويل الأصغر (تجريبي)';

    public const PURPOSE = 'إحالة تجريبية لعرض صندوق الإحالات لدى مؤسسة التمويل.';

    public function run(): void
    {
        $admin = DemoUsersSeeder::user(UserRole::BeaconAdmin);

        $institution = MfiInstitution::query()->firstOrCreate(['name_ar' => self::NAME], [
            'notes' => 'بيانات تجريبية للتطوير المحلي فقط.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        DemoUsersSeeder::user(UserRole::MfiUser)->forceFill(['mfi_institution_id' => $institution->id])->save();

        $enterprise = DemoEnterpriseSeeder::enterprise();
        $items = array_keys((array) config('readiness.disclosable_items'));

        $referral = Referral::query()->firstOrCreate([
            'enterprise_id' => $enterprise->id,
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => self::PURPOSE,
        ], [
            'status' => Referral::STATUS_SHARED,
            'disclosed_items' => $items,
            'created_by' => $admin->id,
            'shared_at' => now(),
        ]);

        if ($referral->activeConsent() === null) {
            $referral->consents()->create([
                'enterprise_id' => $enterprise->id,
                'mfi_institution_id' => $institution->id,
                'purpose_ar' => self::PURPOSE,
                'consent_version' => (string) config('readiness.consent.version'),
                'consent_text_ar' => (string) config('readiness.consent.text_ar'),
                'scope' => $referral->disclosed_items,
                'status' => ReferralConsent::STATUS_GRANTED,
                'granted_by' => DemoUsersSeeder::user(UserRole::SmeUser)->id,
                'granted_at' => now(),
            ]);
        }

        if (! $referral->statusChanges()->exists()) {
            $referral->statusChanges()->create(['from_status' => Referral::STATUS_DRAFT, 'to_status' => Referral::STATUS_SHARED, 'actor_id' => $admin->id]);
        }
    }
}
