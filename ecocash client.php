<?php
/**
 * Ecocash Open API PHP Client
 *
 * PSR-4 compatible single-file library for Payments, Refunds and Transaction Lookup.
 * - PHP 7.4+
 * - Uses cURL internally (no external dependencies)
 *
 * Usage: place under namespace Ecocash and autoload via Composer or require directly.
 *
 * Author: Generated for Dexter Wurayayi
 * License: MIT (adjust as needed)
 */

namespace Ecocash;

use Exception;

class EcocashException extends Exception {}
class EcocashValidationException extends EcocashException {}

class EcocashClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $mode; // 'sandbox' or 'live'
    private int $timeout = 30;

    /**
     * Constructor
     *
     * @param string $apiKey   X-API-KEY provided by Ecocash
     * @param string $mode    'sandbox' or 'live' (default: sandbox)
     * @param string|null $baseUrl  Optional base URL override
     */
    public function __construct(string $apiKey, string $mode = 'sandbox', ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->mode = $mode === 'live' ? 'live' : 'sandbox';
        $this->baseUrl = rtrim($baseUrl ?? 'https://developers.ecocash.co.zw/api/ecocash_pay/', '/');
    }

    /**
     * Set request timeout seconds
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeout = max(5, $seconds);
    }

    /**
     * Do a payment (C2B instant)
     *
     * @param string $customerMsisdn (e.g. 263774222475)
     * @param float  $amount
     * @param string $reason
     * @param string $currency
     * @param string $sourceReference UUID string
     * @return array decoded JSON response
     * @throws EcocashException
     */
    public function payment(string $customerMsisdn, float $amount, string $reason = 'Payment', string $currency = 'USD', string $sourceReference = ''): array
    {
        if ($sourceReference === '') {
            $sourceReference = $this->generateUuidV4();
        }
        if (!$this->isValidUuid($sourceReference)) {
            throw new EcocashValidationException('sourceReference must be a valid UUID');
        }

        $payload = [
            'customerMsisdn' => $customerMsisdn,
            'amount' => $this->normalizeAmount($amount),
            'reason' => $reason,
            'currency' => $currency,
            'sourceReference' => $sourceReference,
        ];

        $path = sprintf('/api/v2/payment/instant/c2b/%s', $this->mode);
        return $this->request('POST', $path, $payload);
    }

    /**
     * Issue a refund (instant C2B refund)
     *
     * @param string $origEcocashRef  original Ecocash transaction reference (UUID)
     * @param string $refundCorrelator merchant generated correlator
     * @param string $sourceMobileNumber recipient mobile number (e.g. 263774222475)
     * @param float $amount
     * @param string $clientName
     * @param string $currency
     * @param string $reason
     * @return array
     * @throws EcocashException
     */
    public function refund(string $origEcocashRef, string $refundCorrelator, string $sourceMobileNumber, float $amount, string $clientName = '', string $currency = 'ZiG', string $reason = ''): array
    {
        if (!$this->isValidUuid($origEcocashRef)) {
            throw new EcocashValidationException('origionalEcocashTransactionReference must be a valid UUID');
        }

        $payload = [
            'origionalEcocashTransactionReference' => $origEcocashRef,
            'refundCorelator' => $refundCorrelator,
            'sourceMobileNumber' => $sourceMobileNumber,
            'amount' => $this->normalizeAmount($amount),
            'clientName' => $clientName,
            'currency' => $currency,
            'reasonForRefund' => $reason,
        ];

        $path = sprintf('/api/v2/refund/instant/c2b/%s', $this->mode);
        return $this->request('POST', $path, $payload);
    }

    /**
     * Lookup a transaction status
     *
     * @param string $sourceMobileNumber
     * @param string $sourceReference UUID
     */
    public function lookup(string $sourceMobileNumber, string $sourceReference): array
    {
        if (!$this->isValidUuid($sourceReference)) {
            throw new EcocashValidationException('sourceReference must be a valid UUID');
        }

        $payload = [
            'sourceMobileNumber' => $sourceMobileNumber,
            'sourceReference' => $sourceReference,
        ];

        $path = sprintf('/api/v1/transaction/c2b/status/%s', $this->mode);
        return $this->request('POST', $path, $payload);
    }

    /**
     * Generic HTTP request helper using cURL
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $ch = curl_init();

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new EcocashException('Failed to encode payload to JSON');
        }

        $headers = [
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apiKey,
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Optional: follow redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $responseBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($responseBody === false) {
            throw new EcocashException('cURL error: ' . $curlErr);
        }

        $decoded = json_decode($responseBody, true);

        // If it's not JSON, return raw body in standardized array
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Some endpoints may return plain text or HTML for errors
            throw new EcocashException("Invalid JSON response (HTTP {$httpCode}): {$responseBody}");
        }

        // Map HTTP codes to exceptions as appropriate
        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? $decoded['responseMessage'] ?? ($decoded['error'] ?? 'Request failed');
            throw new EcocashException(sprintf('HTTP %d: %s', $httpCode, $message));
        }

        return $decoded;
    }

    /**
     * Normalize amount to number (two decimal places) - keeps floats but formats for JSON
     */
    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Validate UUID v4-ish pattern (basic)
     */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid);
    }

    /**
     * Generate a v4 UUID (for convenience)
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        // set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Helpful helper to safely format a mobile number if user sends +263 or 263...
     * (This tries to keep whatever is provided, but can be extended.)
     */
    public static function normalizeMsisdn(string $msisdn): string
    {
        $s = preg_replace('/[^0-9]/', '', $msisdn);
        if (strlen($s) === 10 && strpos($s, '0') === 0) {
            // convert 0774222475 -> 263774222475 (example)
            // This is region-specific; leave to integrator but provide an example
            return '263' . ltrim($s, '0');
        }
        return $s;
    }
}

// End of file
