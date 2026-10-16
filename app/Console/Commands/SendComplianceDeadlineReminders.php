<?php

namespace App\Console\Commands;

use App\Reminders\ComplianceDeadlineReminderService;
use Illuminate\Console\Command;

class SendComplianceDeadlineReminders extends Command
{
    protected $signature = 'reminders:compliance-deadlines';

    protected $description = 'إرسال تذكيرات داخل المنصة بمواعيد الامتثال القريبة';

    public function handle(ComplianceDeadlineReminderService $service): int
    {
        $count = $service->run();
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
