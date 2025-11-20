# Ecocash Open API PHP Client

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://www.php.net/)  
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)  
[![Status](https://img.shields.io/badge/stability-stable-brightgreen.svg)]()

A professional, dependency-free PHP library to integrate directly with **Ecocash Open API** for seamless mobile money transactions in Zimbabwe. This library provides a clean, modern interface for processing payments, refunds, and transaction lookups.

---

## ✨ Features

- ✅ **PSR-4 compatible** - Follows modern PHP standards  
- ✅ **PHP 8.1+** - Built with modern PHP features  
- ✅ **Zero Dependencies** - Uses only built-in PHP cURL extension  
- ✅ **Sandbox & Live** - Support for both testing and production environments  
- ✅ **Built-in UUID Generator** - Automatic transaction reference generation  
- ✅ **MSISDN Normalization** - Automatic mobile number formatting  
- ✅ **Structured Exception Handling** - Clear error types for better debugging  
- ✅ **Professional Example Application** - Full-featured demo with preferences management  
- ✅ **Transaction History** - Built-in transaction tracking and logging  
- ✅ **Configurable Timeouts** - Customizable request timeout settings  

---

## 📋 Requirements

- PHP **8.1 or higher**
- cURL extension enabled (`ext-curl`)
- An **Ecocash API Key** (get from [Ecocash Developer Portal](https://developers.ecocash.co.zw/))
- Session support (for the example application)

---

## 📦 Installation

### Option 1: Direct Include (Quick Start)

Simply download the files and include them in your project:

```php
require_once 'EcocashClient.php';
```

### Option 2: Composer (Recommended for Production)

```bash
composer require your-namespace/ecocash-client
```

Then use the autoloader:

```php
require_once 'vendor/autoload.php';
```

---

## 🚀 Quick Start

### Basic Usage

```php
<?php
require_once 'EcocashClient.php';

use Ecocash\EcocashClient;
use Ecocash\EcocashException;
use Ecocash\EcocashValidationException;
use Ecocash\EcocashNetworkException;

// Initialize the client
$apiKey = 'YOUR_API_KEY_HERE';
$client = new EcocashClient($apiKey, 'sandbox'); // Use 'live' for production

try {
    // Process a payment
    $response = $client->payment(
        '263774222475',  // Mobile number
        10.00,           // Amount
        'Payment for service', // Reason
        'USD'            // Currency
    );
    
    echo "Payment successful! Transaction ID: " . $response['transactionReference'];
} catch (EcocashValidationException $e) {
    echo "Validation Error: " . $e->getMessage();
} catch (EcocashNetworkException $e) {
    echo "Network Error: " . $e->getMessage();
} catch (EcocashException $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

---

## 📚 API Documentation

### Initialization

```php
$client = new EcocashClient(
    string $apiKey,        // Your Ecocash API key
    string $mode = 'sandbox', // 'sandbox' or 'live'
    ?string $baseUrl = null  // Optional: Custom base URL
);
```

### Methods

#### 1. Process Payment (C2B Instant)

Initiate a customer-to-business instant payment.

```php
public function payment(
    string $customerMsisdn,      // Customer mobile number
    float $amount,               // Payment amount
    string $reason = 'Payment',  // Payment description
    string $currency = 'USD',    // Currency code (USD, ZWL, ZiG)
    ?string $sourceReference = null // Optional: Custom UUID reference
): array
```

**Example:**

```php
$response = $client->payment(
    '263774222475',           // Mobile number (with or without country code)
    25.50,                    // Amount
    'Payment for order #123', // Reason
    'USD',                    // Currency
    null                      // Auto-generate UUID if null
);

// Response structure:
// [
//     'transactionReference' => 'uuid',
//     'status' => 'success',
//     'message' => '...',
//     ...
// ]
```

**Mobile Number Formats Supported:**
- `263774222475` (with country code)
- `0774222475` (local format - automatically normalized)
- `+263774222475` (with plus sign)

#### 2. Process Refund (C2B Instant)

Issue an instant refund for a previous transaction.

```php
public function refund(
    string $originalEcocashRef,  // Original transaction UUID
    string $refundCorrelator,    // Unique refund identifier (UUID)
    string $sourceMobileNumber,  // Recipient mobile number
    float $amount,               // Refund amount
    string $clientName = '',     // Client name (optional)
    string $currency = 'ZiG',    // Currency code
    string $reason = ''          // Refund reason
): array
```

**Example:**

```php
$response = $client->refund(
    '550e8400-e29b-41d4-a716-446655440000', // Original transaction UUID
    '660e8400-e29b-41d4-a716-446655440001', // Refund correlator (UUID)
    '263774222475',                          // Recipient mobile number
    25.50,                                   // Refund amount
    'John Doe',                              // Client name
    'ZiG',                                   // Currency
    'Refund for cancelled order'             // Reason
);
```

#### 3. Transaction Lookup

Check the status of a transaction.

```php
public function lookup(
    string $sourceMobileNumber, // Mobile number used in transaction
    string $sourceReference     // Source reference UUID from payment
): array
```

**Example:**

```php
$response = $client->lookup(
    '263774222475',                          // Mobile number
    '550e8400-e29b-41d4-a716-446655440000'  // Source reference UUID
);

// Response includes transaction status, amount, currency, etc.
```

#### 4. Utility Methods

##### Normalize Mobile Number

```php
$normalized = EcocashClient::normalizeMsisdn('0774222475');
// Returns: '263774222475'
```

##### Set Request Timeout

```php
$client->setTimeout(60); // Set timeout to 60 seconds (minimum: 5 seconds)
```

---

## 🎨 Professional Example Application

This repository includes a comprehensive example application (`example.php`) that demonstrates all features with a professional interface.

### Features of the Example App

- 📊 **Dashboard** - Overview with statistics and quick actions
- 💰 **Payment Processing** - Full payment form with validation
- 💸 **Refund Processing** - Complete refund functionality
- 🔍 **Transaction Lookup** - Status checking for transactions
- 📜 **Transaction History** - View past transactions with details
- ⚙️ **Settings & Preferences** - Configure API key, mode, currency, and more

### Running the Example

1. **Start a PHP development server:**
   ```bash
   php -S localhost:8000
   ```

2. **Open in browser:**
   ```
   http://localhost:8000/example.php
   ```

3. **Configure your settings:**
   - Navigate to the **Settings** page
   - Enter your Ecocash API key
   - Select your environment (Sandbox/Live)
   - Configure default preferences

4. **Start processing transactions:**
   - Use the **Payment** page to process payments
   - Use the **Refund** page to issue refunds
   - Use the **Lookup** page to check transaction status
   - View all transactions in the **History** page

### Configuration Manager

The example application includes a `ConfigManager` class for managing user preferences:

```php
require_once 'ConfigManager.php';

$config = new ConfigManager();

// Get preferences
$apiKey = $config->getPreference('api_key');
$mode = $config->getPreference('mode', 'sandbox');

// Set preferences
$config->setPreference('api_key', 'your-api-key');
$config->updatePreferences([
    'mode' => 'live',
    'default_currency' => 'USD',
]);

// Transaction history
$config->addTransaction([
    'type' => 'payment',
    'status' => 'success',
    'amount' => 10.00,
]);

$history = $config->getTransactionHistory(10);
```

---

## 🔧 Configuration

### Environment Modes

- **Sandbox**: Use for testing and development
  ```php
  $client = new EcocashClient($apiKey, 'sandbox');
  ```

- **Live**: Use for production transactions
  ```php
  $client = new EcocashClient($apiKey, 'live');
  ```

### Supported Currencies

- `USD` - US Dollar
- `ZWL` - Zimbabwean Dollar
- `ZiG` - Zimbabwe Gold

### Request Timeout

Default timeout is 30 seconds. You can customize it:

```php
$client->setTimeout(60); // 60 seconds
```

---

## ⚠️ Exception Handling

The library provides three exception types for better error handling:

### EcocashValidationException

Thrown when input parameters are invalid.

```php
try {
    $client->payment('invalid', -10, 'Payment');
} catch (EcocashValidationException $e) {
    // Handle validation errors
    echo "Invalid input: " . $e->getMessage();
}
```

### EcocashNetworkException

Thrown when network/connection errors occur.

```php
try {
    $client->payment('263774222475', 10.00);
} catch (EcocashNetworkException $e) {
    // Handle network errors
    echo "Network error: " . $e->getMessage();
}
```

### EcocashException

General exception for API errors and other issues.

```php
try {
    $client->payment('263774222475', 10.00);
} catch (EcocashException $e) {
    // Handle general errors
    echo "Error: " . $e->getMessage();
}
```

---

## 📝 Complete Example

```php
<?php
require_once 'EcocashClient.php';

use Ecocash\EcocashClient;
use Ecocash\EcocashException;
use Ecocash\EcocashValidationException;
use Ecocash\EcocashNetworkException;

$apiKey = 'YOUR_API_KEY_HERE';
$client = new EcocashClient($apiKey, 'sandbox');
$client->setTimeout(45);

try {
    // Process payment
    $paymentResponse = $client->payment(
        '263774222475',
        50.00,
        'Payment for order #12345',
        'USD'
    );
    
    echo "Payment successful!\n";
    echo "Transaction Reference: " . $paymentResponse['transactionReference'] . "\n";
    
    // Lookup transaction
    $lookupResponse = $client->lookup(
        '263774222475',
        $paymentResponse['sourceReference']
    );
    
    echo "Transaction Status: " . $lookupResponse['status'] . "\n";
    
    // Process refund if needed
    if ($needsRefund) {
        $refundResponse = $client->refund(
            $paymentResponse['transactionReference'],
            'unique-refund-uuid-here',
            '263774222475',
            50.00,
            'Customer Name',
            'USD',
            'Refund for cancelled order'
        );
        
        echo "Refund processed successfully!\n";
    }
    
} catch (EcocashValidationException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (EcocashNetworkException $e) {
    echo "Network Error: " . $e->getMessage() . "\n";
} catch (EcocashException $e) {
    echo "Ecocash Error: " . $e->getMessage() . "\n";
}
?>
```

---

## 🔒 Security Best Practices

1. **Never commit API keys** to version control
2. **Use environment variables** for sensitive data:
   ```php
   $apiKey = getenv('ECOCASH_API_KEY');
   ```
3. **Validate all user input** before processing
4. **Use HTTPS** in production
5. **Store transaction references** securely for refunds
6. **Implement proper logging** for audit trails
7. **Use Sandbox mode** for testing

---

## 🐛 Troubleshooting

### Common Issues

**Issue: "cURL error: Could not resolve host"**
- **Solution**: Check your internet connection and DNS settings

**Issue: "HTTP 401: Unauthorized"**
- **Solution**: Verify your API key is correct and active

**Issue: "Validation Error: Invalid UUID"**
- **Solution**: Ensure source references are valid UUID v4 format

**Issue: "Network Error: Connection timeout"**
- **Solution**: Increase timeout using `setTimeout()` or check network connectivity

**Issue: "Invalid mobile number format"**
- **Solution**: Use the `normalizeMsisdn()` method or provide number in international format

---

## 📖 Additional Resources

- [Ecocash Developer Portal](https://developers.ecocash.co.zw/)
- [Ecocash API Documentation](https://developers.ecocash.co.zw/docs)
- [PHP cURL Documentation](https://www.php.net/manual/en/book.curl.php)

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 👤 Author

**Dexter Wurayayi**

- GitHub: [@yourusername](https://github.com/yourusername)
- Email: your.email@example.com

---

## 🙏 Acknowledgments

- Ecocash for providing the Open API
- All contributors who help improve this library

---

## 📞 Support

For support, please:
1. Check the [Troubleshooting](#-troubleshooting) section
2. Review the [Ecocash Developer Portal](https://developers.ecocash.co.zw/)
3. Open an issue on GitHub

---

**Made with ❤️ for the Zimbabwean developer community**
