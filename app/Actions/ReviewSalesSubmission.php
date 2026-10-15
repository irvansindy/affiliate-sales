<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseType;
use App\Enums\SalesSubmissionStatus;
use App\Models\SalesSubmission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReviewSalesSubmission
{
    public function __construct(
        private readonly RecordTransaction $recordTransaction,
        private readonly WriteAuditLog $auditLog,
    ) {}

    /** @param array<string, mixed> $payment */
    public function approve(User $admin, SalesSubmission $submission, array $payment): Transaction
    {
        Gate::forUser($admin)->authorize('approve', $submission);

        return DB::transaction(function () use ($admin, $submission, $payment): Transaction {
            $locked = SalesSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->ensurePending($locked);
            $locked->load('affiliate.user');

            if (! Storage::disk('local')->exists($locked->proof_path)) {
                throw ValidationException::withMessages(['proof_path' => __('sales_submissions.validation.proof')]);
            }

            if (! $locked->affiliate->isActive() ||
                strcasecmp($locked->customer_email, $locked->affiliate->user->email) === 0) {
                throw ValidationException::withMessages(['affiliate_id' => __('sales_submissions.validation.affiliate')]);
            }

            $transaction = $this->recordTransaction->handle([
                'external_ref' => $payment['external_ref'] ?? null,
                'base_amount' => $payment['base_amount'] ?? null,
                'discount_amount' => $payment['discount_amount'] ?? null,
                'tax_amount' => $payment['tax_amount'] ?? null,
                'total_paid' => $payment['total_paid'] ?? null,
                'paid_at' => $payment['paid_at'] ?? null,
                'subscription_plan' => $payment['subscription_plan'] ?? null,
                'subscription_cycle' => $locked->purchase_type === PurchaseType::Subscription ? 1 : null,
                'product_id' => $locked->product_id,
                'referral_code' => $locked->affiliate->referral_code,
                'source' => 'manual',
                'purchase_type' => $locked->purchase_type->value,
                'sale_type' => 'new',
                'customer_name' => $locked->customer_name,
                'customer_email' => $locked->customer_email,
            ], $admin);

            if ($transaction->affiliate_id !== $locked->affiliate_id || $transaction->needs_review) {
                throw ValidationException::withMessages(['affiliate_id' => __('sales_submissions.validation.affiliate')]);
            }

            $locked->forceFill([
                'transaction_id' => $transaction->id,
                'status' => SalesSubmissionStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $this->auditLog->handle($admin, $locked, 'sales_submission.approved', ['status' => 'pending'], [
                'status' => 'approved',
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    public function reject(User $admin, SalesSubmission $submission, string $reason): void
    {
        Gate::forUser($admin)->authorize('reject', $submission);
        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();

        DB::transaction(function () use ($admin, $submission, $validated): void {
            $locked = SalesSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->ensurePending($locked);
            $locked->forceFill([
                'status' => SalesSubmissionStatus::Rejected,
                'rejection_reason' => $validated['reason'],
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            $this->auditLog->handle($admin, $locked, 'sales_submission.rejected', ['status' => 'pending'], [
                'status' => 'rejected',
                'reason' => $validated['reason'],
            ]);
        });
    }

    private function ensurePending(SalesSubmission $submission): void
    {
        if ($submission->status !== SalesSubmissionStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('sales_submissions.validation.already_reviewed')]);
        }
    }
}
