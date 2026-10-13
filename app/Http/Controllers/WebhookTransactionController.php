<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Services\WebhookPayloadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WebhookTransactionController extends Controller
{
    public function __invoke(Request $request, WebhookPayloadValidator $validator): JsonResponse
    {
        $secret = config('services.webhook.secret');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $sentAt = filter_var($timestamp, FILTER_VALIDATE_INT);

        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => __('webhook.unavailable')], 503);
        }

        if ($sentAt === false || $sentAt < now()->subMinutes(5)->timestamp || $sentAt > now()->addMinutes(5)->timestamp || ! is_string($signature)) {
            return response()->json(['message' => __('webhook.unauthorized')], 401);
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => __('webhook.unauthorized')], 401);
        }

        if (! $request->isJson()) {
            return response()->json(['message' => __('webhook.invalid_payload')], 422);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || array_is_list($payload)) {
            return response()->json(['message' => __('webhook.invalid_payload')], 422);
        }

        if (! isset($payload['event_id']) || ! is_string($payload['event_id']) ||
            $request->header('Idempotency-Key') !== $payload['event_id']) {
            return response()->json(['message' => __('webhook.invalid_payload')], 422);
        }

        $existingEvent = WebhookEvent::query()->where('event_id', $payload['event_id'])->first();

        if ($existingEvent !== null) {
            if ($existingEvent->status !== WebhookEventStatus::Processed) {
                ProcessWebhookEvent::dispatch($existingEvent->id)->afterCommit();
            }

            return response()->json(['message' => __('webhook.duplicate')]);
        }

        $externalRef = $payload['transaction']['external_ref'] ?? null;

        if (($payload['event'] ?? null) === 'payment.succeeded' && is_string($externalRef) &&
            Transaction::query()->where('external_ref', $externalRef)->exists()) {
            return response()->json(['message' => __('webhook.duplicate')]);
        }

        if (($payload['event'] ?? null) === 'payment.refunded' && is_string($externalRef) &&
            Transaction::query()->where('external_ref', $externalRef)->where('status', TransactionStatus::Refunded->value)->exists()) {
            return response()->json(['message' => __('webhook.duplicate')]);
        }

        try {
            match ($payload['event'] ?? null) {
                'payment.succeeded' => $validator->validateSucceeded($payload),
                'payment.refunded' => $validator->validateRefunded($payload),
                default => throw ValidationException::withMessages(['event' => __('webhook.invalid_payload')]),
            };
        } catch (ValidationException $exception) {
            return response()->json(['message' => __('webhook.invalid_payload')], 422);
        }

        if ($payload['event'] === 'payment.refunded' &&
            ! Transaction::query()->where('external_ref', $externalRef)->exists()) {
            return response()->json(['message' => __('webhook.sale_not_ready')], 503);
        }

        $event = WebhookEvent::query()->createOrFirst(['event_id' => $payload['event_id']], [
            'event' => $payload['event'],
            'payload' => $payload,
        ]);

        if (! $event->wasRecentlyCreated) {
            if ($event->status !== WebhookEventStatus::Processed) {
                ProcessWebhookEvent::dispatch($event->id)->afterCommit();
            }

            return response()->json(['message' => __('webhook.duplicate')]);
        }

        ProcessWebhookEvent::dispatch($event->id)->afterCommit();

        return response()->json(['message' => __('webhook.accepted')], 202);
    }
}
