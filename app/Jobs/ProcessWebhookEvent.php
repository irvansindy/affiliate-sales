<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\HandleRefund;
use App\Actions\RecordTransaction;
use App\Enums\WebhookEventStatus;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Services\WebhookPayloadValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $eventId) {}

    public function handle(RecordTransaction $recordTransaction, HandleRefund $handleRefund, WebhookPayloadValidator $validator): void
    {
        DB::transaction(function () use ($recordTransaction, $handleRefund, $validator): void {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);

            if ($event->status === WebhookEventStatus::Processed) {
                return;
            }

            $payload = $event->payload;

            if ($event->event === 'payment.succeeded' &&
                ! Transaction::query()->where('external_ref', $payload['transaction']['external_ref'])->exists()) {
                $product = Product::query()->where('code', $payload['transaction']['product']['code'])->firstOrFail();
                $recordTransaction->handle($validator->transactionData($payload, $product));
            } elseif ($event->event === 'payment.refunded') {
                $sale = Transaction::query()->where('external_ref', $payload['transaction']['external_ref'])->firstOrFail();
                $handleRefund->handle($sale, $payload['transaction']['refund_amount'], $payload['transaction']['refunded_at']);
            }

            $event->update([
                'status' => WebhookEventStatus::Processed,
                'processed_at' => now(),
                'error' => null,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->eventId)->update([
            'status' => WebhookEventStatus::Failed,
            'error' => __('webhook.processing_failed'),
        ]);
    }
}
