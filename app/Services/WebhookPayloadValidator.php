<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\RecordTransaction;
use App\Enums\ProductType;
use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WebhookPayloadValidator
{
    public function __construct(private readonly RecordTransaction $recordTransaction) {}

    /** @param array<string, mixed> $payload */
    public function validateSucceeded(array $payload): void
    {
        Validator::make($payload, [
            'event_id' => ['required', 'string', 'max:100'],
            'event' => ['required', Rule::in(['payment.succeeded'])],
            'occurred_at' => ['required', 'date'],
            'transaction' => ['required', 'array'],
            'transaction.external_ref' => ['required', 'string', 'max:100'],
            'transaction.sale_type' => ['required', Rule::enum(SaleType::class)],
            'transaction.purchase_type' => ['required', Rule::enum(PurchaseType::class)],
            'transaction.product.code' => ['required', 'string', 'max:50'],
            'transaction.product.type' => ['required', Rule::enum(ProductType::class)],
            'transaction.referral_code' => ['nullable', 'string', 'max:20'],
            'transaction.base_amount' => ['required', 'integer', 'min:0'],
            'transaction.discount_amount' => ['required', 'integer', 'min:0'],
            'transaction.tax_amount' => ['required', 'integer', 'min:0'],
            'transaction.total_paid' => ['required', 'integer', 'min:0'],
            'transaction.currency' => ['required', Rule::in(['IDR'])],
            'transaction.paid_at' => ['required', 'date'],
            'transaction.subscription' => ['required_if:transaction.purchase_type,subscription', 'array'],
            'transaction.subscription.plan' => ['required_if:transaction.purchase_type,subscription', 'string', 'max:50'],
            'transaction.subscription.cycle' => ['required_if:transaction.purchase_type,subscription', 'integer', 'min:1', 'max:65535'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['required', 'email', 'max:150'],
        ])->validate();

        foreach (['base_amount', 'discount_amount', 'tax_amount', 'total_paid'] as $field) {
            if (! is_int($payload['transaction'][$field])) {
                throw ValidationException::withMessages(['transaction.'.$field => __('webhook.invalid_payload')]);
            }
        }

        if ($payload['transaction']['purchase_type'] === PurchaseType::Subscription->value &&
            ! is_int($payload['transaction']['subscription']['cycle'])) {
            throw ValidationException::withMessages(['transaction.subscription.cycle' => __('webhook.invalid_payload')]);
        }

        $product = Product::query()->where('code', $payload['transaction']['product']['code'])->first();

        if ($product === null || $product->type->value !== $payload['transaction']['product']['type']) {
            throw ValidationException::withMessages(['transaction.product.code' => __('webhook.unknown_product')]);
        }

        $this->recordTransaction->validateData($this->transactionData($payload, $product));
    }

    /** @param array<string, mixed> $payload */
    public function validateRefunded(array $payload): void
    {
        Validator::make($payload, [
            'event_id' => ['required', 'string', 'max:100'],
            'event' => ['required', Rule::in(['payment.refunded'])],
            'occurred_at' => ['required', 'date'],
            'transaction.external_ref' => ['required', 'string', 'max:100'],
            'transaction.refund_amount' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'transaction.refunded_at' => ['required', 'date'],
        ])->validate();

        if (! is_int($payload['transaction']['refund_amount'])) {
            throw ValidationException::withMessages(['transaction.refund_amount' => __('webhook.invalid_payload')]);
        }

        $transaction = Transaction::query()->where('external_ref', $payload['transaction']['external_ref'])->first();

        if ($transaction !== null && $payload['transaction']['refund_amount'] > $transaction->total_paid) {
            throw ValidationException::withMessages(['transaction.refund_amount' => __('webhook.refund_exceeds_payment')]);
        }
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function transactionData(array $payload, Product $product): array
    {
        $sale = $payload['transaction'];

        return [
            'external_ref' => $sale['external_ref'],
            'product_id' => $product->id,
            'referral_code' => $sale['referral_code'] ?? null,
            'source' => 'webhook',
            'purchase_type' => $sale['purchase_type'],
            'sale_type' => $sale['sale_type'],
            'subscription_plan' => $sale['subscription']['plan'] ?? null,
            'subscription_cycle' => $sale['subscription']['cycle'] ?? null,
            'customer_name' => $payload['customer']['name'],
            'customer_email' => $payload['customer']['email'],
            'base_amount' => $sale['base_amount'],
            'discount_amount' => $sale['discount_amount'],
            'tax_amount' => $sale['tax_amount'],
            'total_paid' => $sale['total_paid'],
            'paid_at' => $sale['paid_at'],
        ];
    }
}
