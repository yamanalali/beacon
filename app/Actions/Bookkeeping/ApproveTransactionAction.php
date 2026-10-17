<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\TransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveTransactionAction
{
    public function execute(User $actor, FinancialTransaction $transaction): void
    {
        DB::transaction(function () use ($actor, $transaction) {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($actor->can('approve', $locked), 403);
            $locked->forceFill(['status' => TransactionStatus::Posted, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            app(RecordAuditLog::class)->record($actor, 'transaction.approved', $locked, ['created_by' => $locked->created_by]);
        });
    }
}
