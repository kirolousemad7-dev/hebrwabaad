<?php

namespace App\Console\Commands;

use App\Models\CustomerLoginOtp;
use App\Models\PhoneVerificationOtp;
use App\Models\SupplierLoginOtp;
use Illuminate\Console\Command;

class PruneExpiredOtpsCommand extends Command
{
    protected $signature = 'otp:prune-expired';

    protected $description = 'Delete expired or consumed phone, supplier, and customer login OTPs';

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

        $customer = CustomerLoginOtp::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $this->info("Pruned {$phone} phone, {$supplier} supplier, and {$customer} customer OTP(s).");

        return self::SUCCESS;
    }
}
