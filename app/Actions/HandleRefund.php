<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Models\Commission;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class HandleRefund
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(Transaction $transaction, int $refundAmount, string $refundedAt): Transaction
    {
        Validator::make([
            'refund_amount' => $refundAmount,
            'refunded_at' => $refundedAt,
        ], [
            'refund_amount' => ['required', 'integer', 'min:1', 'max:'.$transaction->total_paid],
            'refunded_at' => ['required', 'date'],
        ])->validate();

        return DB::transaction(function () use ($transaction, $refundAmount, $refundedAt): Transaction {
            $sale = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($sale->status === TransactionStatus::Refunded) {
                return $sale;
            }

            if ($sale->status !== TransactionStatus::Paid) {
                throw ValidationException::withMessages(['external_ref' => __('webhook.refund_not_allowed')]);
            }

            $commission = Commission::query()->where('transaction_id', $sale->id)->lockForUpdate()->first();

            $sale->update([
                'status' => TransactionStatus::Refunded,
                'refund_amount' => $refundAmount,
                'refunded_at' => $refundedAt,
            ]);

            $this->auditLog->handle(null, $sale, 'transaction.refunded', ['status' => TransactionStatus::Paid->value], [
                'status' => TransactionStatus::Refunded->value,
                'refund_amount' => $refundAmount,
                'refunded_at' => $refundedAt,
            ]);

            if ($commission?->status === CommissionStatus::Pending || $commission?->status === CommissionStatus::Approved) {
                $oldStatus = $commission->status->value;
                $commission->forceFill(['status' => CommissionStatus::Cancelled])->save();
                $this->auditLog->handle(null, $commission, 'commission.cancelled_by_refund', ['status' => $oldStatus], [
                    'status' => CommissionStatus::Cancelled->value,
                    'refund_amount' => $refundAmount,
                ]);
            } elseif ($commission?->status === CommissionStatus::Paid) {
                $this->auditLog->handle(null, $commission, 'commission.refund_after_paid', ['status' => CommissionStatus::Paid->value], [
                    'status' => CommissionStatus::Paid->value,
                    'refund_amount' => $refundAmount,
                ]);
            }

            return $sale;
        });
    }
}
