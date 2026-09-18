<?php

namespace App\Console\Commands;

use App\Models\PhoneVerificationOtp;
use App\Models\SupplierLoginOtp;
use Illuminate\Console\Command;

class PruneExpiredOtpsCommand extends Command
{
    protected $signature = 'otp:prune-expired';

    protected $description = 'Delete expired or consumed phone and supplier login OTPs';

    public function handle(): int
    {
        $phone = PhoneVerificationOtp::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $supplier = SupplierLoginOtp::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $this->info("Pruned {$phone} phone OTP(s) and {$supplier} supplier OTP(s).");

        return self::SUCCESS;
    }
}
