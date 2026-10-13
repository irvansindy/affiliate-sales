<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AttributionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;

final class RecordTransaction
{
    public function __construct(
        private readonly AttributionService $attribution,
        private readonly CreateCommissionForTransaction $createCommission,
        private readonly WriteAuditLog $auditLog,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, ?User $actor = null): Transaction
    {
        $validated = $this->validateData($data);

        if ($validated['source'] === TransactionSource::Manual->value) {
            if ($actor === null) {
                throw new AuthorizationException;
            }

            Gate::forUser($actor)->authorize('create', Transaction::class);
        }

        return DB::transaction(function () use ($validated, $actor): Transaction {
            $attribution = $this->attribution->resolve(
                $validated['referral_code'] ?? null,
                $validated['customer_email'],
            );

            $transaction = Transaction::query()->create([
                'external_ref' => $validated['external_ref'],
                'product_id' => $validated['product_id'],
                'affiliate_id' => $attribution['affiliate_id'],
                'source' => $validated['source'],
                'purchase_type' => $validated['purchase_type'],
                'sale_type' => $validated['sale_type'],
                'subscription_plan' => $validated['subscription_plan'] ?? null,
                'subscription_cycle' => $validated['subscription_cycle'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'base_amount' => $validated['base_amount'],
                'discount_amount' => $validated['discount_amount'],
                'tax_amount' => $validated['tax_amount'],
                'total_paid' => $validated['total_paid'],
                'status' => TransactionStatus::Paid,
                'paid_at' => $validated['paid_at'],
                'needs_review' => $attribution['needs_review'],
                'review_note' => $attribution['review_note'],
            ]);

            if ($actor !== null && $validated['source'] === TransactionSource::Manual->value) {
                $this->auditLog->handle($actor, $transaction, 'transaction.recorded', [], [
                    'external_ref' => $transaction->external_ref,
                    'affiliate_id' => $transaction->affiliate_id,
                    'base_amount' => $transaction->base_amount,
                    'total_paid' => $transaction->total_paid,
                    'needs_review' => $transaction->needs_review,
                ]);
            }

            $this->createCommission->handle($transaction);

            return $transaction;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateData(array $data): array
    {
        return Validator::make($data, [
            'external_ref' => ['required', 'string', 'max:100', Rule::unique('transactions', 'external_ref')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'referral_code' => ['nullable', 'string'],
            'source' => ['required', Rule::enum(TransactionSource::class)],
            'purchase_type' => ['required', Rule::enum(PurchaseType::class)],
            'sale_type' => ['required', Rule::enum(SaleType::class)],
            'subscription_plan' => ['required_if:purchase_type,subscription', 'nullable', 'string', 'max:50'],
            'subscription_cycle' => ['required_if:purchase_type,subscription', 'nullable', 'integer', 'min:1', 'max:65535'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:150'],
            'base_amount' => ['required', 'integer', 'min:0', 'max:'.intdiv(PHP_INT_MAX - 5000, 10000)],
            'discount_amount' => ['required', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'tax_amount' => ['required', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'total_paid' => ['required', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'paid_at' => ['required', 'date'],
        ])->after(function (LaravelValidator $validator) use ($data): void {
            if (! $validator->errors()->hasAny(['purchase_type', 'sale_type', 'subscription_cycle'])) {
                if ($data['purchase_type'] === PurchaseType::OneTime->value && $data['sale_type'] !== SaleType::NewSale->value) {
                    $validator->errors()->add('sale_type', __('transactions.validation.one_time_sale_type'));
                }

                if ($data['purchase_type'] === PurchaseType::Subscription->value &&
                    $data['sale_type'] === SaleType::NewSale->value &&
                    (int) $data['subscription_cycle'] !== 1) {
                    $validator->errors()->add('subscription_cycle', __('transactions.validation.initial_subscription_cycle'));
                }
            }

            if ($validator->errors()->hasAny(['base_amount', 'discount_amount', 'tax_amount', 'total_paid'])) {
                return;
            }

            $baseAmount = (int) $data['base_amount'];
            $discountAmount = (int) $data['discount_amount'];
            $taxAmount = (int) $data['tax_amount'];

            if ($discountAmount > $baseAmount) {
                $validator->errors()->add('discount_amount', __('transactions.validation.discount_exceeds_base'));

                return;
            }

            $afterDiscount = $baseAmount - $discountAmount;

            if ($taxAmount > PHP_INT_MAX - $afterDiscount || (int) $data['total_paid'] !== $afterDiscount + $taxAmount) {
                $validator->errors()->add('total_paid', __('transactions.validation.total_mismatch'));
            }
        })->validate();
    }
}
