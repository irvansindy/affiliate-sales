<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Models\Commission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('commissions:mark-ready')]
#[Description('Menandai komisi yang melewati masa tunggu agar siap ditinjau admin.')]
class MarkCommissionsReady extends Command
{
    public function handle(): int
    {
        $readyAt = now();
        $count = DB::transaction(fn (): int => Commission::query()
            ->where('status', CommissionStatus::Pending->value)
            ->whereNull('ready_for_review_at')
            ->whereNotNull('approvable_at')
            ->where('approvable_at', '<=', $readyAt)
            ->whereHas('transaction', fn ($query) => $query->where('status', TransactionStatus::Paid->value))
            ->update(['ready_for_review_at' => $readyAt]), 3);

        $this->info(__('commissions.marked_ready', ['count' => $count]));

        return self::SUCCESS;
    }
}
