<?php

/**
 * Configuration Manager for Ecocash Client Preferences
 * 
 * Manages user preferences and configuration settings using session storage.
 * Provides secure storage and retrieval of API credentials and user preferences.
 */

class ConfigManager
{
    private const SESSION_KEY = 'ecocash_preferences';
    private const DEFAULT_PREFERENCES = [
        'api_key' => '',
        'mode' => 'sandbox',
        'default_currency' => 'USD',
        'default_timeout' => 30,
        'enable_logging' => true,
        'transaction_history' => [],
    ];

    /**
     * Initialize session if not already started
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get all preferences
     * 
     * @return array
     */
    public function getAllPreferences(): array
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = self::DEFAULT_PREFERENCES;
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Get a specific preference value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPreference(string $key, $default = null)
    {
        $preferences = $this->getAllPreferences();
        return $preferences[$key] ?? $default;
    }

    /**
     * Set a preference value
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setPreference(string $key, $value): void
    {
        $preferences = $this->getAllPreferences();
        $preferences[$key] = $value;
        $_SESSION[self::SESSION_KEY] = $preferences;
    }

    /**
     * Update multiple preferences at once
     * 
     * @param array $preferences
     * @return void
     */
    public function updatePreferences(array $preferences): void
    {
        $current = $this->getAllPreferences();
        $_SESSION[self::SESSION_KEY] = array_merge($current, $preferences);
    }

    /**
     * Reset preferences to defaults
     * 
     * @return void
     */
    public function resetPreferences(): void
    {
        $_SESSION[self::SESSION_KEY] = self::DEFAULT_PREFERENCES;
    }

    /**
     * Add a transaction to history
     * 
     * @param array $transaction
     * @return void
     */
    public function addTransaction(array $transaction): void
    {
        $history = $this->getPreference('transaction_history', []);
        array_unshift($history, array_merge($transaction, [
            'timestamp' => date('Y-m-d H:i:s'),
            'id' => uniqid('txn_', true),
        ]));
        // Keep only last 50 transactions
        $history = array_slice($history, 0, 50);
        $this->setPreference('transaction_history', $history);
    }

    /**
     * Get transaction history
     * 
     * @param int $limit
     * @return array
     */
    public function getTransactionHistory(int $limit = 10): array
    {
        $history = $this->getPreference('transaction_history', []);
        return array_slice($history, 0, $limit);
    }

    /**
     * Clear transaction history
     * 
     * @return void
     */
    public function clearTransactionHistory(): void
    {
        $this->setPreference('transaction_history', []);
    }

    /**
     * Validate API key format (basic validation)
     * 
     * @param string $apiKey
     * @return bool
     */
    public function validateApiKey(string $apiKey): bool
    {
        return !empty($apiKey) && strlen($apiKey) >= 10;
    }

    /**
     * Check if configuration is complete
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        $apiKey = $this->getPreference('api_key', '');
        return $this->validateApiKey($apiKey);
    }
}

