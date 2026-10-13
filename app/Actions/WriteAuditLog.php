<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WriteAuditLog
{
    /** @param array<string, mixed> $old
     * @param  array<string, mixed>  $new
     */
    public function handle(?User $actor, Model $subject, string $action, array $old, array $new): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'old' => $old,
            'new' => $new,
        ]);
    }
}
