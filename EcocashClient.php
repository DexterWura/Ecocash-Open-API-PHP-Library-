<?php

/**
 * Ecocash Open API PHP Client
 *
 * A PSR-4 compatible PHP client for the Ecocash Open API, providing a
 * streamlined interface for payments, refunds, and transaction lookups.
 *
 * - PHP 8.1+
 * - Uses cURL internally (no external dependencies)
 * - Adheres to modern PHP standards and practices.
 *
 * Usage:
 * Install with Composer: `composer require your-namespace/ecocash-client`
 * or include the file directly.
 *
 * Author: Generated for Dexter Wurayayi
 * License: MIT
 */

namespace Ecocash;

use Exception;
use JsonException;

// Custom exception classes for clearer error handling
class EcocashException extends Exception {}
class EcocashValidationException extends EcocashException {}
class EcocashNetworkException extends EcocashException {}

class EcocashClient
{
    private const BASE_URL = 'https://developers.ecocash.co.zw/api/ecocash_pay';
    private const TIMEOUT = 30;
    
    // Class properties are now readonly for immutability
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $mode;
    private int $timeout;

    /**
     * @param string $apiKey Your X-API-KEY from Ecocash.
     * @param string $mode   'sandbox' or 'live'.
     * @param string|null $baseUrl Optional base URL override.
     */
    public function __construct(string $apiKey, string $mode = 'sandbox', ?string $baseUrl = null)
    {
        // Use a match expression for clean, explicit mode validation
        $this->mode = match (strtolower($mode)) {
            'live' => 'live',
            default => 'sandbox',
        };

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?? self::BASE_URL, '/');
        $this->timeout = self::TIMEOUT;
    }

    /**
     * Set the request timeout in seconds.
     *
     * @param int $seconds Minimum timeout is 5 seconds.
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeout = max(5, $seconds);
    }

    /**
     * Do a C2B (Customer-to-Business) instant payment.
     *
     * @param string $customerMsisdn The customer's mobile number (e.g., '263774222475').
     * @param float $amount The payment amount.
     * @param string $reason A description for the payment.
     * @param string $currency The currency code (e.g., 'USD').
     * @param string|null $sourceReference A unique UUID for the transaction.
     *
     * @return array The decoded JSON response from the API.
     *
     * @throws EcocashValidationException If input parameters are invalid.
     * @throws EcocashException If the API request fails.
     */
    public function payment(string $customerMsisdn, float $amount, string $reason = 'Payment', string $currency = 'USD', ?string $sourceReference = null): array
    {
        $sourceReference ??= $this->generateUuidV4();
        if (!$this->isValidUuid($sourceReference)) {
            throw new EcocashValidationException('`sourceReference` must be a valid UUID.');
        }

        $payload = [
            'customerMsisdn' => self::normalizeMsisdn($customerMsisdn),
            'amount' => $this->normalizeAmount($amount),
            'reason' => $reason,
            'currency' => $currency,
            'sourceReference' => $sourceReference,
        ];

        $path = "/api/v2/payment/instant/c2b/{$this->mode}";
        return $this->request('POST', $path, $payload);
    }

    /**
     * Issue an instant C2B refund.
     *
     * @param string $originalEcocashRef The original Ecocash transaction reference (UUID).
     * @param string $refundCorrelator A merchant-generated unique correlator.
     * @param string $sourceMobileNumber The recipient's mobile number.
     * @param float $amount The refund amount.
     * @param string $clientName The name of the client.
     * @param string $currency The currency code (e.g., 'ZiG').
     * @param string $reason A reason for the refund.
     *
     * @return array The decoded JSON response from the API.
     *
     * @throws EcocashValidationException If input parameters are invalid.
     * @throws EcocashException If the API request fails.
     */
    public function refund(string $originalEcocashRef, string $refundCorrelator, string $sourceMobileNumber, float $amount, string $clientName = '', string $currency = 'ZiG', string $reason = ''): array
    {
        if (!$this->isValidUuid($originalEcocashRef)) {
            throw new EcocashValidationException('`originalEcocashTransactionReference` must be a valid UUID.');
        }
        
        $payload = [
            'originalEcocashTransactionReference' => $originalEcocashRef,
            'refundCorrelator' => $refundCorrelator,
            'sourceMobileNumber' => self::normalizeMsisdn($sourceMobileNumber),
            'amount' => $this->normalizeAmount($amount),
            'clientName' => $clientName,
            'currency' => $currency,
            'reasonForRefund' => $reason,
        ];

        $path = "/api/v2/refund/instant/c2b/{$this->mode}";
        return $this->request('POST', $path, $payload);
    }

    /**
     * Look up the status of a C2B transaction.
     *
     * @param string $sourceMobileNumber The mobile number associated with the transaction.
     * @param string $sourceReference The unique UUID used for the payment.
     *
     * @return array The decoded JSON response from the API.
     *
     * @throws EcocashValidationException If the UUID is invalid.
     * @throws EcocashException If the API request fails.
     */
    public function lookup(string $sourceMobileNumber, string $sourceReference): array
    {
        if (!$this->isValidUuid($sourceReference)) {
            throw new EcocashValidationException('`sourceReference` must be a valid UUID.');
        }

        $payload = [
            'sourceMobileNumber' => self::normalizeMsisdn($sourceMobileNumber),
            'sourceReference' => $sourceReference,
        ];

        $path = "/api/v1/transaction/c2b/status/{$this->mode}";
        return $this->request('POST', $path, $payload);
    }

    /**
     * Normalizes a mobile number to the Zimbabwean international format (e.g., '26377xxxxxxx').
     *
     * @param string $msisdn The mobile number to normalize.
     * @return string The normalized mobile number.
     */
    public static function normalizeMsisdn(string $msisdn): string
    {
        $msisdn = preg_replace('/[^0-9]/', '', $msisdn);
        
        if (str_starts_with($msisdn, '0')) {
            return '263' . ltrim($msisdn, '0');
        }

        return $msisdn;
    }
    
    /**
     * Generic HTTP request helper using cURL.
     *
     * @throws EcocashNetworkException If cURL fails to connect.
     * @throws EcocashException If the API returns an error or invalid JSON.
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init();
        $url = "{$this->baseUrl}{$path}";
        
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new EcocashException('Failed to encode payload to JSON: ' . $e->getMessage(), 0, $e);
        }

        $headers = [
            'Content-Type: application/json',
            "X-API-KEY: {$this->apiKey}",
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new EcocashNetworkException("cURL error: {$curlErr}");
        }

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // API may return non-JSON responses for some errors
            if ($httpCode >= 400) {
                 throw new EcocashException("HTTP {$httpCode}: {$responseBody}");
            }
            throw new EcocashException("Invalid JSON response (HTTP {$httpCode}): " . $e->getMessage(), 0, $e);
        }

        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? $decoded['responseMessage'] ?? $decoded['error'] ?? 'Unknown error';
            throw new EcocashException(sprintf('HTTP %d: %s', $httpCode, $message));
        }

        return $decoded;
    }

    /**
     * Normalizes a float amount to two decimal places.
     */
    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
    
    /**
     * Validates a UUID v4 string.
     */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Generates a version 4 UUID.
     */
    private function generateUuidV4(): string
    {
        return sprintf(
            '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}