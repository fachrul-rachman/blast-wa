<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Services\Meta\WebhookSignatureVerifier;
use App\Services\Meta\WhatsAppWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = config('services.whatsapp.webhook_verify_token');
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        if (
            $mode === 'subscribe'
            && is_string($verifyToken)
            && hash_equals($verifyToken, (string) $token)
        ) {
            return response((string) $challenge);
        }

        abort(403);
    }

    public function handle(
        Request $request,
        WebhookSignatureVerifier $signatureVerifier,
        WhatsAppWebhookProcessor $processor,
    ): JsonResponse {
        $payload = $request->getContent();

        if (! $signatureVerifier->verify($payload, $request->header('X-Hub-Signature-256'))) {
            return response()->json(['ok' => false], 401);
        }

        $decoded = json_decode($payload, true);

        if (is_array($decoded)) {
            $processor->process($decoded);
        }

        return response()->json(['ok' => true]);
    }
}
