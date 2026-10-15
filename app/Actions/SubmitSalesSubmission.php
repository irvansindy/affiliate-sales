<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseType;
use App\Models\SalesSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SubmitSalesSubmission
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): SalesSubmission
    {
        Gate::forUser($actor)->authorize('create', SalesSubmission::class);
        $affiliate = $actor->affiliate()->firstOrFail();

        $validated = Validator::make($data, [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'purchase_type' => ['required', Rule::enum(PurchaseType::class)],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:150'],
            'claimed_ref' => ['nullable', 'string', 'max:100'],
            'proof_path' => ['required', 'string', 'max:255'],
        ])->validate();

        $path = $validated['proof_path'];
        $disk = Storage::disk('local');
        if (preg_match('#^sales-proofs/'.$affiliate->id.'/[A-Za-z0-9._-]+$#D', $path) !== 1 ||
            ! $disk->exists($path) ||
            ! in_array($disk->mimeType($path), ['application/pdf', 'image/jpeg', 'image/png'], true) ||
            $disk->size($path) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['proof_path' => __('sales_submissions.validation.proof')]);
        }

        if (strcasecmp($validated['customer_email'], $actor->email) === 0) {
            throw ValidationException::withMessages(['customer_email' => __('sales_submissions.validation.self_referral')]);
        }

        return DB::transaction(function () use ($actor, $affiliate, $validated): SalesSubmission {
            $submission = SalesSubmission::query()->create([
                ...$validated,
                'affiliate_id' => $affiliate->id,
            ]);

            $this->auditLog->handle($actor, $submission, 'sales_submission.submitted', [], [
                'affiliate_id' => $submission->affiliate_id,
                'product_id' => $submission->product_id,
                'status' => $submission->status->value,
            ]);

            return $submission;
        });
    }
}
