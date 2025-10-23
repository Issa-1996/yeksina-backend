<?php

namespace App\Services;

use App\Models\Delivery;

class SecurityCodeService
{
    public function generateCode(): int
    {
        do {
            $code = rand(1000, 9999);
        } while (Delivery::where('security_code', $code)->where('created_at', '>=', now()->subHours(24))->exists());
        
        return $code;
    }

    public function sendCodeSMS(string $phoneNumber, int $code): bool
    {
        \Illuminate\Support\Facades\Log::info("SMS envoyé à {$phoneNumber}: Code: {$code}");
        return true;
    }
}