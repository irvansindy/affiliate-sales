<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\TestCase;

class WebhookTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_returns_401_without_storing_an_event(): void
    {
        config()->set('services.webhook.secret', 'test-secret');

        $this->call('POST', '/api/webhooks/transactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => (string) now()->timestamp,
            'HTTP_X_SIGNATURE' => 'sha256=invalid',
            'HTTP_IDEMPOTENCY_KEY' => 'evt-1',
        ], '{}')->assertUnauthorized();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_expired_timestamp_returns_401(): void
    {
        config()->set('services.webhook.secret', 'test-secret');

        $this->sendWebhook($this->successPayload(), now()->subMinutes(6)->timestamp)
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_future_timestamp_returns_401(): void
    {
        config()->set('services.webhook.secret', 'test-secret');

        $this->sendWebhook($this->successPayload(), now()->addMinutes(6)->timestamp)
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_missing_webhook_secret_fails_closed(): void
    {
        config()->set('services.webhook.secret', null);

        $this->sendWebhook($this->successPayload())->assertServiceUnavailable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_non_json_content_type_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();

        $this->sendWebhook($this->successPayload(), contentType: 'text/plain')->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_signature_is_checked_against_the_exact_raw_body(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $body = json_encode($this->successPayload(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        $this->call('POST', '/api/webhooks/transactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret'),
            'HTTP_IDEMPOTENCY_KEY' => 'evt-1',
        ], $body)->assertAccepted();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }

    public function test_valid_payment_is_saved_before_a_job_is_dispatched(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);

        $this->sendWebhook($this->successPayload())->assertAccepted();

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt-1',
            'event' => 'payment.succeeded',
            'status' => 'received',
        ]);
        $this->assertDatabaseCount('transactions', 0);
        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }

    public function test_invalid_idempotency_key_returns_422_without_storing_or_queueing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);

        $this->sendWebhook($this->successPayload(), idempotencyKey: 'another-key')
            ->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNotPushed(ProcessWebhookEvent::class);
    }

    public function test_unknown_product_returns_422_without_storing_or_queueing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        Queue::fake([ProcessWebhookEvent::class]);

        $this->sendWebhook($this->successPayload())->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNotPushed(ProcessWebhookEvent::class);
    }

    public function test_incorrect_total_returns_422_without_storing_or_queueing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $payload = $this->successPayload();
        $payload['transaction']['total_paid'] = 1;

        $this->sendWebhook($payload)
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Payload webhook tidak valid.']);

        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNotPushed(ProcessWebhookEvent::class);
    }

    public function test_product_type_mismatch_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $payload = $this->successPayload();
        $payload['transaction']['product']['type'] = 'mobile_app';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_subscription_without_plan_or_cycle_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $payload = $this->successPayload();
        $payload['transaction']['purchase_type'] = 'subscription';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_unsupported_currency_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $payload = $this->successPayload();
        $payload['transaction']['currency'] = 'USD';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_money_encoded_as_a_json_string_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $payload = $this->successPayload();
        $payload['transaction']['base_amount'] = '5000000';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_missing_required_customer_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $payload = $this->successPayload();
        unset($payload['customer']);

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_unknown_event_type_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $payload = $this->successPayload();
        $payload['event'] = 'payment.pending';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_duplicate_unprocessed_event_returns_200_and_requeues_safely(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $payload = $this->successPayload();

        $this->sendWebhook($payload)->assertAccepted();
        $this->sendWebhook($payload)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWebhookEvent::class, 2);

        $event = WebhookEvent::query()->sole();
        app()->call([new ProcessWebhookEvent($event->id), 'handle']);
        app()->call([new ProcessWebhookEvent($event->id), 'handle']);

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('processed', $event->fresh()->status->value);
    }

    public function test_success_job_records_one_transaction_and_commission_then_marks_event_processed(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $affiliateUser->id, 'status' => AffiliateStatus::Active]);
        Queue::fake([ProcessWebhookEvent::class]);
        $payload = $this->successPayload();
        $payload['transaction']['referral_code'] = $affiliate->referral_code;

        $this->sendWebhook($payload)->assertAccepted();
        $event = WebhookEvent::query()->sole();
        app()->call([new ProcessWebhookEvent($event->id), 'handle']);

        $transaction = Transaction::query()->sole();
        $this->assertSame($affiliate->id, $transaction->affiliate_id);
        $this->assertSame('webhook', $transaction->source->value);
        $this->assertSame(500_000, (int) $transaction->commission->amount);
        $this->assertSame('processed', $event->fresh()->status->value);
        $this->assertNotNull($event->fresh()->processed_at);

        app()->call([new ProcessWebhookEvent($event->id), 'handle']);

        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame(1, Commission::query()->count());
    }

    public function test_existing_external_reference_returns_200_without_saving_a_new_event(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $payload = $this->successPayload();

        $this->sendWebhook($payload)->assertAccepted();
        $event = WebhookEvent::query()->sole();
        app()->call([new ProcessWebhookEvent($event->id), 'handle']);
        $retry = $payload;
        $retry['event_id'] = 'evt-2';

        $this->sendWebhook($retry)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('transactions', 1);
        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }

    public function test_refund_event_cancels_unpaid_commission_through_the_job(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $affiliateUser->id, 'status' => AffiliateStatus::Active]);
        Queue::fake([ProcessWebhookEvent::class]);
        $salePayload = $this->successPayload();
        $salePayload['transaction']['referral_code'] = $affiliate->referral_code;
        $this->sendWebhook($salePayload)->assertAccepted();
        $saleEvent = WebhookEvent::query()->where('event_id', 'evt-1')->sole();
        app()->call([new ProcessWebhookEvent($saleEvent->id), 'handle']);

        $this->sendWebhook($this->refundPayload())->assertAccepted();
        $refundEvent = WebhookEvent::query()->where('event_id', 'evt-refund-1')->sole();
        $this->assertSame('received', $refundEvent->status->value);
        $this->assertSame('paid', Transaction::query()->sole()->status->value);
        app()->call([new ProcessWebhookEvent($refundEvent->id), 'handle']);

        $transaction = Transaction::query()->sole();
        $this->assertSame('refunded', $transaction->status->value);
        $this->assertSame(1_000_000, (int) $transaction->refund_amount);
        $this->assertSame('cancelled', $transaction->commission->status->value);
        $this->assertSame('processed', $refundEvent->fresh()->status->value);
    }

    public function test_refund_with_missing_amount_returns_422_without_storing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $payload = $this->refundPayload();
        unset($payload['transaction']['refund_amount']);

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_refund_amount_encoded_as_a_json_string_returns_422(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $payload = $this->refundPayload();
        $payload['transaction']['refund_amount'] = '1000000';

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_refund_before_sale_is_available_returns_503_for_sender_retry(): void
    {
        config()->set('services.webhook.secret', 'test-secret');

        $this->sendWebhook($this->refundPayload())->assertServiceUnavailable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_refund_larger_than_existing_payment_returns_422_without_storing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $this->sendWebhook($this->successPayload())->assertAccepted();
        $saleEvent = WebhookEvent::query()->sole();
        app()->call([new ProcessWebhookEvent($saleEvent->id), 'handle']);
        $payload = $this->refundPayload();
        $payload['transaction']['refund_amount'] = 5_550_001;

        $this->sendWebhook($payload)->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertSame('paid', Transaction::query()->sole()->status->value);
    }

    public function test_duplicate_refund_event_returns_200_without_reprocessing(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        Queue::fake([ProcessWebhookEvent::class]);
        $this->sendWebhook($this->successPayload())->assertAccepted();
        $saleEvent = WebhookEvent::query()->sole();
        app()->call([new ProcessWebhookEvent($saleEvent->id), 'handle']);
        $this->sendWebhook($this->refundPayload())->assertAccepted();
        $refundEvent = WebhookEvent::query()->where('event_id', 'evt-refund-1')->sole();
        app()->call([new ProcessWebhookEvent($refundEvent->id), 'handle']);
        $retry = $this->refundPayload();
        $retry['event_id'] = 'evt-refund-2';

        $this->sendWebhook($retry)->assertOk();

        $this->assertDatabaseCount('webhook_events', 2);
        $this->assertSame(1_000_000, (int) Transaction::query()->sole()->refund_amount);
        Queue::assertPushed(ProcessWebhookEvent::class, 2);
    }

    public function test_failed_commission_creation_rolls_back_sale_and_leaves_event_retryable(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $affiliateUser->id, 'status' => AffiliateStatus::Active]);
        Setting::query()->updateOrCreate(['key' => 'default_commission_rate'], ['value' => '-1']);
        Queue::fake([ProcessWebhookEvent::class]);
        $payload = $this->successPayload();
        $payload['transaction']['referral_code'] = $affiliate->referral_code;
        $this->sendWebhook($payload)->assertAccepted();
        $event = WebhookEvent::query()->sole();

        try {
            app()->call([new ProcessWebhookEvent($event->id), 'handle']);
            $this->fail('Pemrosesan komisi yang gagal harus membatalkan transaksi.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('transactions', 0);
            $this->assertDatabaseCount('commissions', 0);
            $this->assertSame('received', $event->fresh()->status->value);
        }
    }

    public function test_retry_redrives_a_saved_event_after_dispatch_or_processing_fails(): void
    {
        config()->set('services.webhook.secret', 'test-secret');
        $this->product();
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $affiliateUser->id, 'status' => AffiliateStatus::Active]);
        Setting::query()->updateOrCreate(['key' => 'default_commission_rate'], ['value' => '-1']);
        $payload = $this->successPayload();
        $payload['transaction']['referral_code'] = $affiliate->referral_code;

        $this->sendWebhook($payload)->assertInternalServerError();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('transactions', 0);
        Setting::query()->where('key', 'default_commission_rate')->update(['value' => '10']);

        $this->sendWebhook($payload)->assertOk();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('commissions', 1);
        $this->assertSame('processed', WebhookEvent::query()->sole()->status->value);
    }

    public function test_webhook_is_rate_limited(): void
    {
        config()->set('services.webhook.secret', 'test-secret');

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->sendWebhook($this->successPayload())->assertUnprocessable();
        }

        $this->sendWebhook($this->successPayload())->assertStatus(429);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    /** @return array<string, mixed> */
    private function successPayload(): array
    {
        return [
            'event_id' => 'evt-1',
            'event' => 'payment.succeeded',
            'occurred_at' => '2026-10-12T14:30:00+07:00',
            'transaction' => [
                'external_ref' => 'INV-001',
                'sale_type' => 'new',
                'purchase_type' => 'one_time',
                'product' => ['code' => 'WEB-PRO', 'type' => 'website'],
                'referral_code' => null,
                'base_amount' => 5_000_000,
                'discount_amount' => 0,
                'tax_amount' => 550_000,
                'total_paid' => 5_550_000,
                'currency' => 'IDR',
                'paid_at' => '2026-10-12T14:30:00+07:00',
            ],
            'customer' => ['name' => 'PT Contoh', 'email' => 'pelanggan@example.com'],
        ];
    }

    /** @return array<string, mixed> */
    private function refundPayload(): array
    {
        return [
            'event_id' => 'evt-refund-1',
            'event' => 'payment.refunded',
            'occurred_at' => '2026-10-20T09:00:00+07:00',
            'transaction' => [
                'external_ref' => 'INV-001',
                'refund_amount' => 1_000_000,
                'refunded_at' => '2026-10-20T09:00:00+07:00',
            ],
        ];
    }

    private function product(): Product
    {
        return Product::query()->create([
            'code' => 'WEB-PRO',
            'name' => 'Website Pro',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function sendWebhook(array $payload, ?int $timestamp = null, ?string $idempotencyKey = null, string $contentType = 'application/json'): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->timestamp;
        $idempotencyKey ??= $payload['event_id'];

        return $this->call('POST', '/api/webhooks/transactions', [], [], [], [
            'CONTENT_TYPE' => $contentType,
            'HTTP_X_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret'),
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
        ], $body);
    }
}
