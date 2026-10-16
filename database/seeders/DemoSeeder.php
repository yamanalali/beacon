<?php

namespace Database\Seeders;

use App\Actions\Bookkeeping\CreateTransactionAction;
use App\Actions\Bookkeeping\RecordSettlementAction;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data for local/UAT use only. Idempotent: safe to run many times,
 * never deletes or overwrites existing data (except re-activating the
 * demo accounts themselves).
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Note: MFI institution linking is skipped — no institutions table on main yet.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';

    public const USERS = [
        'sme@beacon.test' => ['مستخدم منشأة تجريبي', UserRole::SmeUser],
        'advisor@beacon.test' => ['مستشار تجريبي', UserRole::BeaconAdvisor],
        'demo-admin@beacon.test' => ['مدير بيكون تجريبي', UserRole::BeaconAdmin],
        'mfi@beacon.test' => ['مستخدم مؤسسة تمويل تجريبي', UserRole::MfiUser],
        'tech@beacon.test' => ['مدير تقني تجريبي', UserRole::TechnicalAdmin],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder skipped in production.');

            return;
        }

        $users = [];
        foreach (self::USERS as $email => [$name, $role]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
            ]);
            $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $users[$role->value] = $user;
        }

        $sme = $users[UserRole::SmeUser->value];
        $advisor = $users[UserRole::BeaconAdvisor->value];
        $admin = $users[UserRole::BeaconAdmin->value];

        $enterprise = Enterprise::query()->firstOrCreate(['business_name' => 'ورشة الياسمين للخياطة (تجريبي)'], [
            'sector' => 'خياطة وألبسة',
            'location' => 'damascus',
            'founding_date' => '2019-03-01',
            'employee_count' => 6,
            'female_employee_count' => 4,
            'male_employee_count' => 2,
            'owner_name' => 'سلمى الأحمد',
            'contact_name' => 'سلمى الأحمد',
            'contact_phone' => '0930000000',
            'contact_email' => 'sme@beacon.test',
            'registration_status' => 'غير مسجلة رسمياً',
            'initial_digital_readiness_note' => 'تستخدم الهاتف الذكي وواتساب للتواصل مع الزبائن.',
            'initial_financial_readiness_note' => 'السجلات المالية ورقية وغير منتظمة.',
            'programme_stage' => 'التسجيل الأولي',
            'progress_summary' => 'بدأت بتسجيل المبيعات والمصاريف اليومية.',
            'created_by' => $admin->id,
        ]);
        $enterprise->members()->syncWithoutDetaching([$sme->id]);
        $enterprise->advisors()->syncWithoutDetaching([$advisor->id]);

        if (! FinancialTransaction::query()->where('enterprise_id', $enterprise->id)->exists()) {
            $this->seedTransactions($sme, $enterprise);
        }

        if (! Document::query()->where('enterprise_id', $enterprise->id)->exists()) {
            $this->seedDocument($sme, $enterprise);
        }
    }

    private function seedTransactions(User $sme, Enterprise $enterprise): void
    {
        $create = app(CreateTransactionAction::class);
        $settle = app(RecordSettlementAction::class);
        $month = now()->startOfMonth();
        $day = fn (int $d) => $month->copy()->addDays(min($d, now()->day - 1))->toDateString();

        $rows = [
            ['sale', 'general_sales', '450000', 'paid', null, 'زبائن المحل', 'بيع فساتين', 1],
            ['sale', 'general_sales', '300000', 'unpaid', null, 'بوتيك النور', 'طلبية بالدين', 2],
            ['sale', 'general_sales', '200000', 'partially_paid', '80000', 'أم خالد', 'تفصيل عباءات', 3],
            ['income', 'other_income', '50000', 'paid', null, null, 'تصليح ألبسة', 4],
            ['purchase', 'goods', '250000', 'unpaid', null, 'مورد الأقمشة', 'قماش وخيوط بالدين', 1],
            ['expense', 'rent', '150000', 'paid', null, 'صاحب المحل', 'إيجار الشهر', 0],
            ['expense', 'utilities', '35000', 'paid', null, null, 'فاتورة كهرباء', 5],
        ];

        $created = [];
        foreach ($rows as [$type, $category, $amount, $status, $paid, $counterparty, $description, $d]) {
            $created[] = $create->execute($sme, $enterprise, [
                'type' => $type,
                'transaction_date' => $day($d),
                'amount' => $amount,
                'currency' => 'SYP',
                'category' => $category,
                'payment_status' => $status,
                'paid_amount' => $paid,
                'counterparty' => $counterparty,
                'description' => $description,
            ]);
        }

        // Later collection on the credit sale (no double counting) and a USD sale.
        $settle->execute($sme, $created[1], ['settlement_date' => $day(6), 'amount' => '100000', 'note' => 'دفعة أولى']);
        $create->execute($sme, $enterprise, [
            'type' => 'sale', 'transaction_date' => $day(6), 'amount' => '40', 'currency' => 'USD',
            'category' => 'general_sales', 'payment_status' => 'paid', 'description' => 'بيع لزبون مغترب',
        ]);
    }

    private function seedDocument(User $sme, Enterprise $enterprise): void
    {
        $disk = config('documents.disk');
        $path = 'enterprises/'.$enterprise->id.'/documents/'.Str::random(40).'.pdf';
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        Storage::disk($disk)->put($path, $pdf);

        $document = new Document;
        $document->enterprise_id = $enterprise->id;
        $document->title = 'سجل تجاري (نموذج تجريبي)';
        $document->original_name = 'demo.pdf';
        $document->disk = $disk;
        $document->path = $path;
        $document->mime_type = 'application/pdf';
        $document->size = strlen($pdf);
        $document->uploaded_by = $sme->id;
        $document->save();
    }
}
