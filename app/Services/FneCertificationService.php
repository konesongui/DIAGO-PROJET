<?php

namespace App\Services;

use App\Models\FneCertificationLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FneCertificationService
{
    public function certify(object $document, string $documentType, array $payload): array
    {
        if ($document->fne_status === 'certified') {
            throw new RuntimeException('Cette facture est déjà certifiée par la FNE.');
        }
        if ($document->fne_status === 'pending') {
            throw new RuntimeException('Une certification FNE est déjà en cours pour cette facture.');
        }
        if (empty(config('fne.api_key'))) {
            throw new RuntimeException('La clé API FNE n’est pas configurée. Ajoutez FNE_API_KEY dans le fichier .env.');
        }

        $url = config('fne.environment') === 'production' && config('fne.production_url')
            ? config('fne.production_url')
            : config('fne.test_url');
        $log = FneCertificationLog::create([
            'entreprise_id' => $document->entreprise_id,
            'certifiable_type' => $document::class,
            'certifiable_id' => $document->id,
            'document_type' => $documentType,
            'request_payload' => $payload,
            'status' => 'pending',
        ]);
        $document->update(['fne_status' => 'pending', 'fne_error' => null]);

        try {
            $response = Http::timeout(config('fne.timeout'))->acceptJson()
                ->withToken(config('fne.api_key'))->post($url, $payload);
            $body = $response->json() ?: [];
            $success = $response->successful();
            $log->update([
                'response_payload' => $body,
                'http_code' => $response->status(),
                'status' => $success ? 'success' : 'failed',
                'error_message' => $success ? null : ($body['message'] ?? $body['error'] ?? 'Erreur API FNE'),
            ]);
            if (! $success) {
                throw new RuntimeException($log->error_message);
            }
            $document->update([
                'fne_status' => 'certified',
                'fne_reference' => data_get($body, 'reference', data_get($body, 'data.reference')),
                'fne_token' => data_get($body, 'token', data_get($body, 'data.token')),
                'fne_balance_sticker' => data_get($body, 'balance_sticker', data_get($body, 'data.balance_sticker')),
                'fne_response' => $body,
                'fne_certified_at' => now(),
                'fne_error' => null,
            ]);
            return [
                'reference' => data_get($body, 'reference', data_get($body, 'data.reference')),
                'token' => data_get($body, 'token', data_get($body, 'data.token')),
                'balance_sticker' => data_get($body, 'balance_sticker', data_get($body, 'data.balance_sticker')),
                'response' => $body,
            ];
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            $document->update(['fne_status' => 'failed', 'fne_error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
