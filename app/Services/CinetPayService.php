<?php

namespace App\Services;

use App\Models\Entreprise;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CinetPayService
{
    public function isConfigured(): bool
    {
        return filter_var($this->getConfigValue('enabled', config('cinetpay.enabled', false)), FILTER_VALIDATE_BOOLEAN)
            && ! empty($this->getConfigValue('site_id', config('cinetpay.site_id')))
            && ! empty($this->getConfigValue('api_key', config('cinetpay.api_key')));
    }

    public function buildPaymentRequest(Entreprise $entreprise, int $durationMonths, float $amount, string $reference): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('La passerelle CinetPay n’est pas configurée.');
        }

        $payload = [
            'apikey' => $this->getConfigValue('api_key', config('cinetpay.api_key')),
            'site_id' => $this->getConfigValue('site_id', config('cinetpay.site_id')),
            'amount' => (int) round($amount),
            'currency' => $this->getConfigValue('currency', config('cinetpay.currency', 'XOF')),
            'transaction_id' => $reference,
            'description' => sprintf('Renouvellement abonnement %s - %s mois', $entreprise->name, $durationMonths),
            'notify_url' => $this->getConfigValue('notify_url', config('cinetpay.notify_url')) ?: url('/setting/general/renew/callback'),
            'return_url' => $this->getConfigValue('return_url', config('cinetpay.return_url')) ?: url('/setting/general/renew/callback'),
            'channels' => $this->getConfigValue('channels', config('cinetpay.channels', 'ALL')),
            'customer_name' => $entreprise->name,
            'customer_email' => $entreprise->users()->first()?->email ?? '',
        ];

        $response = Http::withOptions(['verify' => false])
            ->asForm()
            ->timeout(30)
            ->post($this->getConfigValue('api_url', config('cinetpay.api_url', 'https://api.cinetpay.com/v2/payment')), $payload);

        $data = $response->json() ?? [];
        $paymentUrl = data_get($data, 'data.payment_url')
            ?? data_get($data, 'payment_url')
            ?? data_get($data, 'url')
            ?? data_get($data, 'data.redirect_url');

        if (! empty($paymentUrl)) {
            return ['payment_url' => $paymentUrl, 'response' => $data];
        }

        $code = (string) data_get($data, 'code', '');
        if (in_array($code, ['00', 'SUCCESS', 'success', 'OK', 'ok'], true) || data_get($data, 'status') === 'success') {
            return [
                'payment_url' => $this->getConfigValue('return_url', config('cinetpay.return_url')) ?: url('/setting/general/renew/callback'),
                'response' => $data,
            ];
        }

        throw new RuntimeException(data_get($data, 'message') ?? 'La demande de paiement CinetPay a échoué.');
    }

    protected function getConfigValue(string $key, $default = null)
    {
        $envKey = 'CINETPAY_' . strtoupper(str_replace(['-', '.'], '_', $key));
        $envValue = env($envKey);

        if ($envValue !== null && $envValue !== '') {
            return $envValue;
        }

        if (auth()->check()) {
            $settings = auth()->user()->entreprise?->settings ?? [];
            $gateway = data_get($settings, 'payment_gateways.cinetpay', []);
            if (is_array($gateway) && array_key_exists($key, $gateway)) {
                return $gateway[$key];
            }
        }

        return $default;
    }
}
