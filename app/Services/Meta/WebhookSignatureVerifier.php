<?php

namespace App\Services\Meta;

class WebhookSignatureVerifier
{
    public function verify(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.whatsapp.app_secret');

        if (! is_string($secret) || blank($secret)) {
            return true;
        }

        if (! is_string($signatureHeader) || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
