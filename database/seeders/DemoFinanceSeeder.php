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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Sample transactions, payments and documents so finance pages/reports look full for demos. */
class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $sme = DemoUsersSeeder::user(UserRole::SmeUser);
        $enterprise = DemoEnterpriseSeeder::enterprise();

        $this->seedTransactions($sme, $enterprise);

        if (! Document::query()->where('enterprise_id', $enterprise->id)->exists()) {
            $this->seedDocument($sme, $enterprise);
        }
    }

    private function seedTransactions(User $sme, Enterprise $enterprise): void
    {
        $create = app(CreateTransactionAction::class);
        $settle = app(RecordSettlementAction::class);
        $thisMonth = now()->startOfMonth();
        $prevMonth = now()->subMonthNoOverflow()->startOfMonth();
        $day = function (int $d, $month) {
            $last = min($d, $month->daysInMonth);

            return $month->copy()->day($last)->toDateString();
        };

        $rows = [
            // Previous month (for trend comparison on the home hero).
            ['sale', 'general_sales', '380000', 'paid', null, 'بوتيك الأمل', 'مبيعات شهر سابق', $day(5, $prevMonth), 'SYP'],
            ['sale', 'general_sales', '120000', 'paid', null, 'أم سامي', 'تفصيل فستان زفاف', $day(12, $prevMonth), 'SYP'],
            ['expense', 'rent', '150000', 'paid', null, 'صاحب المحل', 'إيجار الشهر السابق', $day(1, $prevMonth), 'SYP'],
            ['expense', 'utilities', '28000', 'paid', null, null, 'فاتورة كهرباء سابقة', $day(8, $prevMonth), 'SYP'],
            ['purchase', 'goods', '90000', 'paid', null, 'مورد الأقمشة', 'قماش قطن', $day(15, $prevMonth), 'SYP'],

            // Current month — income / sales.
            ['sale', 'general_sales', '450000', 'paid', null, 'زبائن المحل', 'بيع فساتين نقداً', $day(1, $thisMonth), 'SYP'],
            ['sale', 'general_sales', '300000', 'unpaid', null, 'بوتيك النور', 'طلبية بالدين', $day(2, $thisMonth), 'SYP'],
            ['sale', 'general_sales', '200000', 'partially_paid', '80000', 'أم خالد', 'تفصيل عباءات', $day(3, $thisMonth), 'SYP'],
            ['sale', 'general_sales', '175000', 'paid', null, 'محلات الشام', 'طلبية أطفال', $day(7, $thisMonth), 'SYP'],
            ['income', 'other_income', '50000', 'paid', null, null, 'تصليح ألبسة', $day(4, $thisMonth), 'SYP'],
            ['income', 'other_income', '25000', 'paid', null, 'زبون عابر', 'تعديل قياسات', $day(9, $thisMonth), 'SYP'],
            ['sale', 'general_sales', '40', 'paid', null, 'زبون مغترب', 'بيع بضاعة نقداً', $day(5, $thisMonth), 'USD'],
            ['sale', 'general_sales', '85', 'paid', null, null, 'طلبية تصدير صغيرة', $day(10, $thisMonth), 'USD'],

            // Current month — expenses / purchases.
            ['purchase', 'goods', '250000', 'unpaid', null, 'مورد الأقمشة', 'قماش وخيوط بالدين', $day(1, $thisMonth), 'SYP'],
            ['purchase', 'goods', '60000', 'paid', null, 'سوق الحميدية', 'أزرار وسحّابات', $day(6, $thisMonth), 'SYP'],
            ['expense', 'rent', '150000', 'paid', null, 'صاحب المحل', 'إيجار الشهر', $day(1, $thisMonth), 'SYP'],
            ['expense', 'utilities', '35000', 'paid', null, null, 'فاتورة كهرباء', $day(5, $thisMonth), 'SYP'],
            ['expense', 'utilities', '18000', 'paid', null, null, 'فاتورة مياه', $day(8, $thisMonth), 'SYP'],
            ['expense', 'transport', '22000', 'paid', null, null, 'أجور توصيل طلبيات', $day(9, $thisMonth), 'SYP'],
            ['expense', 'other_expense', '15000', 'paid', null, null, 'مستلزمات ورشة', $day(11, $thisMonth), 'SYP'],
        ];

        $creditSale = null;
        foreach ($rows as [$type, $category, $amount, $status, $paid, $counterparty, $description, $date, $currency]) {
            if ($this->hasDemoRow($enterprise, $description, $date)) {
                continue;
            }

            $tx = $create->execute($sme, $enterprise, [
                'type' => $type,
                'transaction_date' => $date,
                'amount' => $amount,
                'currency' => $currency,
                'category' => $category,
                'payment_status' => $status,
                'paid_amount' => $paid,
                'counterparty' => $counterparty,
                'description' => $description,
            ]);

            if ($description === 'طلبية بالدين') {
                $creditSale = $tx;
            }
        }

        if ($creditSale !== null && $creditSale->settlements()->doesntExist()) {
            $settle->execute($sme, $creditSale, [
                'settlement_date' => $day(6, $thisMonth),
                'amount' => '100000',
                'note' => 'دفعة أولى',
            ]);
        }
    }

    private function hasDemoRow(Enterprise $enterprise, string $description, string $date): bool
    {
        return FinancialTransaction::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('description', $description)
            ->whereDate('transaction_date', $date)
            ->exists();
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
