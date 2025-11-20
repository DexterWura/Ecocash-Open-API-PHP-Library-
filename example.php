<?php
/**
 * Ecocash Open API - Professional Example Application
 * 
 * A comprehensive example demonstrating payment, refund, and transaction lookup
 * capabilities with user preference management and transaction history.
 */

// Start session and include dependencies
session_start();
require_once 'EcocashClient.php';
require_once 'ConfigManager.php';

use Ecocash\EcocashClient;
use Ecocash\EcocashException;
use Ecocash\EcocashValidationException;
use Ecocash\EcocashNetworkException;

$config = new ConfigManager();
$action = $_GET['action'] ?? 'dashboard';
$message = null;
$messageType = null;
$response = null;
$error = null;

// Handle preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $preferences = [
        'api_key' => trim($_POST['api_key'] ?? ''),
        'mode' => $_POST['mode'] ?? 'sandbox',
        'default_currency' => $_POST['default_currency'] ?? 'USD',
        'default_timeout' => (int)($_POST['default_timeout'] ?? 30),
        'enable_logging' => isset($_POST['enable_logging']),
    ];
    
    if ($config->validateApiKey($preferences['api_key'])) {
        $config->updatePreferences($preferences);
        $message = 'Preferences updated successfully!';
        $messageType = 'success';
    } else {
        $message = 'Invalid API key. Please enter a valid API key.';
        $messageType = 'danger';
    }
}

// Handle payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if (!$config->isConfigured()) {
        $error = 'Please configure your API key in Settings first.';
        $action = 'settings';
    } else {
        try {
            $apiKey = $config->getPreference('api_key');
            $mode = $config->getPreference('mode', 'sandbox');
            $timeout = $config->getPreference('default_timeout', 30);
            
            $client = new EcocashClient($apiKey, $mode);
            $client->setTimeout($timeout);
            
            $msisdn = EcocashClient::normalizeMsisdn($_POST['msisdn'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $reason = $_POST['reason'] ?? 'Payment';
            $currency = $_POST['currency'] ?? $config->getPreference('default_currency', 'USD');
            $sourceReference = !empty($_POST['source_reference']) ? $_POST['source_reference'] : null;
            
            if ($amount <= 0) {
                throw new EcocashValidationException('Amount must be greater than zero.');
            }
            
            $response = $client->payment($msisdn, $amount, $reason, $currency, $sourceReference);
            
            // Add to transaction history
            $config->addTransaction([
                'type' => 'payment',
                'status' => 'success',
                'msisdn' => $msisdn,
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
                'response' => $response,
            ]);
            
            $message = 'Payment processed successfully!';
            $messageType = 'success';
        } catch (EcocashValidationException $e) {
            $error = "Validation Error: " . $e->getMessage();
            $config->addTransaction([
                'type' => 'payment',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        } catch (EcocashNetworkException $e) {
            $error = "Network Error: " . $e->getMessage();
            $config->addTransaction([
                'type' => 'payment',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        } catch (EcocashException $e) {
            $error = "Ecocash Error: " . $e->getMessage();
            $config->addTransaction([
                'type' => 'payment',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}

// Handle refund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    if (!$config->isConfigured()) {
        $error = 'Please configure your API key in Settings first.';
        $action = 'settings';
    } else {
        try {
            $apiKey = $config->getPreference('api_key');
            $mode = $config->getPreference('mode', 'sandbox');
            $timeout = $config->getPreference('default_timeout', 30);
            
            $client = new EcocashClient($apiKey, $mode);
            $client->setTimeout($timeout);
            
            $originalEcocashRef = $_POST['original_ecocash_ref'] ?? '';
            $refundCorrelator = $_POST['refund_correlator'] ?? '';
            $sourceMobileNumber = EcocashClient::normalizeMsisdn($_POST['source_mobile_number'] ?? '');
            $amount = (float)($_POST['refund_amount'] ?? 0);
            $clientName = $_POST['client_name'] ?? '';
            $currency = $_POST['refund_currency'] ?? $config->getPreference('default_currency', 'USD');
            $reason = $_POST['refund_reason'] ?? 'Refund';
            
            if (empty($originalEcocashRef) || empty($refundCorrelator)) {
                throw new EcocashValidationException('Original Ecocash Reference and Refund Correlator are required.');
            }
            
            if ($amount <= 0) {
                throw new EcocashValidationException('Refund amount must be greater than zero.');
            }
            
            $response = $client->refund(
                $originalEcocashRef,
                $refundCorrelator,
                $sourceMobileNumber,
                $amount,
                $clientName,
                $currency,
                $reason
            );
            
            $config->addTransaction([
                'type' => 'refund',
                'status' => 'success',
                'original_ref' => $originalEcocashRef,
                'amount' => $amount,
                'currency' => $currency,
                'response' => $response,
            ]);
            
            $message = 'Refund processed successfully!';
            $messageType = 'success';
        } catch (EcocashValidationException $e) {
            $error = "Validation Error: " . $e->getMessage();
        } catch (EcocashNetworkException $e) {
            $error = "Network Error: " . $e->getMessage();
        } catch (EcocashException $e) {
            $error = "Ecocash Error: " . $e->getMessage();
        }
    }
}

// Handle transaction lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_transaction'])) {
    if (!$config->isConfigured()) {
        $error = 'Please configure your API key in Settings first.';
        $action = 'settings';
    } else {
        try {
            $apiKey = $config->getPreference('api_key');
            $mode = $config->getPreference('mode', 'sandbox');
            $timeout = $config->getPreference('default_timeout', 30);
            
            $client = new EcocashClient($apiKey, $mode);
            $client->setTimeout($timeout);
            
            $sourceMobileNumber = EcocashClient::normalizeMsisdn($_POST['lookup_msisdn'] ?? '');
            $sourceReference = $_POST['lookup_source_reference'] ?? '';
            
            if (empty($sourceReference)) {
                throw new EcocashValidationException('Source Reference is required for lookup.');
            }
            
            $response = $client->lookup($sourceMobileNumber, $sourceReference);
            
            $config->addTransaction([
                'type' => 'lookup',
                'status' => 'success',
                'msisdn' => $sourceMobileNumber,
                'source_reference' => $sourceReference,
                'response' => $response,
            ]);
            
            $message = 'Transaction lookup completed!';
            $messageType = 'success';
        } catch (EcocashValidationException $e) {
            $error = "Validation Error: " . $e->getMessage();
        } catch (EcocashNetworkException $e) {
            $error = "Network Error: " . $e->getMessage();
        } catch (EcocashException $e) {
            $error = "Ecocash Error: " . $e->getMessage();
        }
    }
}

// Handle clear history
if (isset($_GET['clear_history'])) {
    $config->clearTransactionHistory();
    $message = 'Transaction history cleared.';
    $messageType = 'success';
    $action = 'history';
}

// Get current preferences for display
$currentPreferences = $config->getAllPreferences();
$transactionHistory = $config->getTransactionHistory(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecocash API - Professional Example</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0066cc;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-container {
            padding: 2rem 0;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0052a3 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .nav-pills .nav-link {
            border-radius: 10px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        
        .nav-pills .nav-link.active {
            background: var(--primary-color);
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background: rgba(0, 102, 204, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 0.75rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0052a3 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .transaction-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        
        .transaction-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .transaction-item.success {
            border-left-color: var(--success-color);
        }
        
        .transaction-item.failed {
            border-left-color: var(--danger-color);
        }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .status-badge {
            font-size: 0.85rem;
        }
        
        pre {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.85rem;
        }
        
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="?action=dashboard">
                <i class="fa-solid fa-wallet"></i> <strong>Ecocash API</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard">
                            <i class="fa-solid fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'payment' ? 'active' : '' ?>" href="?action=payment">
                            <i class="fa-solid fa-money-bill-transfer"></i> Payment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'refund' ? 'active' : '' ?>" href="?action=refund">
                            <i class="fa-solid fa-rotate-left"></i> Refund
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'lookup' ? 'active' : '' ?>" href="?action=lookup">
                            <i class="fa-solid fa-magnifying-glass"></i> Lookup
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'history' ? 'active' : '' ?>" href="?action=history">
                            <i class="fa-solid fa-clock-rotate-left"></i> History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action === 'settings' ? 'active' : '' ?>" href="?action=settings">
                            <i class="fa-solid fa-gear"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'dashboard'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="info-box">
                        <h2><i class="fa-solid fa-chart-line"></i> Dashboard</h2>
                        <p class="mb-0">Welcome to the Ecocash Open API Professional Example. Configure your settings and start processing transactions.</p>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <?php
                $stats = [
                    ['label' => 'Total Transactions', 'value' => count($transactionHistory), 'icon' => 'fa-list'],
                    ['label' => 'Successful', 'value' => count(array_filter($transactionHistory, fn($t) => ($t['status'] ?? '') === 'success')), 'icon' => 'fa-check-circle'],
                    ['label' => 'Failed', 'value' => count(array_filter($transactionHistory, fn($t) => ($t['status'] ?? '') === 'failed')), 'icon' => 'fa-times-circle'],
                    ['label' => 'Configured', 'value' => $config->isConfigured() ? 'Yes' : 'No', 'icon' => 'fa-gear'],
                ];
                foreach ($stats as $stat):
                ?>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <i class="fa-solid <?= $stat['icon'] ?> fa-2x text-primary mb-2"></i>
                        <div class="stat-value"><?= htmlspecialchars($stat['value']) ?></div>
                        <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fa-solid fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="?action=payment" class="btn btn-primary w-100 mb-2">
                                <i class="fa-solid fa-money-bill-transfer"></i> Process Payment
                            </a>
                            <a href="?action=refund" class="btn btn-warning w-100 mb-2">
                                <i class="fa-solid fa-rotate-left"></i> Process Refund
                            </a>
                            <a href="?action=lookup" class="btn btn-info w-100 mb-2">
                                <i class="fa-solid fa-magnifying-glass"></i> Lookup Transaction
                            </a>
                            <a href="?action=settings" class="btn btn-secondary w-100">
                                <i class="fa-solid fa-gear"></i> Configure Settings
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left"></i> Recent Transactions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactionHistory)): ?>
                                <p class="text-muted text-center">No transactions yet.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($transactionHistory, 0, 5) as $txn): ?>
                                    <div class="transaction-item <?= ($txn['status'] ?? '') === 'success' ? 'success' : 'failed' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= ucfirst($txn['type'] ?? 'unknown') ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($txn['timestamp'] ?? '') ?></small>
                                            </div>
                                            <span class="badge bg-<?= ($txn['status'] ?? '') === 'success' ? 'success' : 'danger' ?> status-badge">
                                                <?= ucfirst($txn['status'] ?? 'unknown') ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <a href="?action=history" class="btn btn-sm btn-outline-primary w-100 mt-2">View All</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'payment'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="fa-solid fa-money-bill-transfer"></i> Process Payment</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!$config->isConfigured()): ?>
                                <div class="alert alert-warning">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    Please configure your API key in <a href="?action=settings">Settings</a> first.
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="process_payment" value="1">
                                
                                <div class="mb-3">
                                    <label for="msisdn" class="form-label">
                                        <i class="fa-solid fa-phone"></i> Mobile Number (MSISDN)
                                    </label>
                                    <input type="text" class="form-control" id="msisdn" name="msisdn" 
                                           placeholder="263774222475 or 0774222475" required>
                                    <small class="text-muted">Enter Zimbabwean mobile number with or without country code</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="amount" class="form-label">
                                        <i class="fa-solid fa-dollar-sign"></i> Amount
                                    </label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" 
                                           id="amount" name="amount" placeholder="10.00" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="currency" class="form-label">
                                        <i class="fa-solid fa-coins"></i> Currency
                                    </label>
                                    <select class="form-select" id="currency" name="currency">
                                        <option value="USD" <?= $currentPreferences['default_currency'] === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                        <option value="ZWL" <?= $currentPreferences['default_currency'] === 'ZWL' ? 'selected' : '' ?>>ZWL - Zimbabwean Dollar</option>
                                        <option value="ZiG" <?= $currentPreferences['default_currency'] === 'ZiG' ? 'selected' : '' ?>>ZiG - Zimbabwe Gold</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">
                                        <i class="fa-solid fa-comment"></i> Payment Reason
                                    </label>
                                    <input type="text" class="form-control" id="reason" name="reason" 
                                           placeholder="Payment for service" value="Payment">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="source_reference" class="form-label">
                                        <i class="fa-solid fa-fingerprint"></i> Source Reference (Optional)
                                    </label>
                                    <input type="text" class="form-control" id="source_reference" name="source_reference" 
                                           placeholder="Leave empty to auto-generate UUID">
                                    <small class="text-muted">Must be a valid UUID v4 if provided</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100" <?= !$config->isConfigured() ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-paper-plane"></i> Process Payment
                                </button>
                            </form>
                            
                            <?php if ($response): ?>
                                <div class="mt-4">
                                    <h5>Response:</h5>
                                    <pre><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'refund'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="fa-solid fa-rotate-left"></i> Process Refund</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!$config->isConfigured()): ?>
                                <div class="alert alert-warning">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    Please configure your API key in <a href="?action=settings">Settings</a> first.
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="process_refund" value="1">
                                
                                <div class="mb-3">
                                    <label for="original_ecocash_ref" class="form-label">
                                        <i class="fa-solid fa-receipt"></i> Original Ecocash Transaction Reference
                                    </label>
                                    <input type="text" class="form-control" id="original_ecocash_ref" 
                                           name="original_ecocash_ref" placeholder="UUID from original transaction" required>
                                    <small class="text-muted">The UUID reference from the original payment transaction</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="refund_correlator" class="form-label">
                                        <i class="fa-solid fa-hashtag"></i> Refund Correlator
                                    </label>
                                    <input type="text" class="form-control" id="refund_correlator" 
                                           name="refund_correlator" placeholder="Unique refund identifier" required>
                                    <small class="text-muted">A unique identifier for this refund (UUID recommended)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="source_mobile_number" class="form-label">
                                        <i class="fa-solid fa-phone"></i> Recipient Mobile Number
                                    </label>
                                    <input type="text" class="form-control" id="source_mobile_number" 
                                           name="source_mobile_number" placeholder="263774222475" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="refund_amount" class="form-label">
                                        <i class="fa-solid fa-dollar-sign"></i> Refund Amount
                                    </label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" 
                                           id="refund_amount" name="refund_amount" placeholder="10.00" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="refund_currency" class="form-label">
                                        <i class="fa-solid fa-coins"></i> Currency
                                    </label>
                                    <select class="form-select" id="refund_currency" name="refund_currency">
                                        <option value="ZiG" selected>ZiG - Zimbabwe Gold</option>
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="ZWL">ZWL - Zimbabwean Dollar</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="client_name" class="form-label">
                                        <i class="fa-solid fa-user"></i> Client Name
                                    </label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" 
                                           placeholder="Client name (optional)">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="refund_reason" class="form-label">
                                        <i class="fa-solid fa-comment"></i> Refund Reason
                                    </label>
                                    <input type="text" class="form-control" id="refund_reason" name="refund_reason" 
                                           placeholder="Reason for refund" value="Refund">
                                </div>
                                
                                <button type="submit" class="btn btn-warning w-100" <?= !$config->isConfigured() ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-rotate-left"></i> Process Refund
                                </button>
                            </form>
                            
                            <?php if ($response): ?>
                                <div class="mt-4">
                                    <h5>Response:</h5>
                                    <pre><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'lookup'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="fa-solid fa-magnifying-glass"></i> Transaction Lookup</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!$config->isConfigured()): ?>
                                <div class="alert alert-warning">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    Please configure your API key in <a href="?action=settings">Settings</a> first.
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="lookup_transaction" value="1">
                                
                                <div class="mb-3">
                                    <label for="lookup_msisdn" class="form-label">
                                        <i class="fa-solid fa-phone"></i> Mobile Number (MSISDN)
                                    </label>
                                    <input type="text" class="form-control" id="lookup_msisdn" name="lookup_msisdn" 
                                           placeholder="263774222475" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lookup_source_reference" class="form-label">
                                        <i class="fa-solid fa-fingerprint"></i> Source Reference
                                    </label>
                                    <input type="text" class="form-control" id="lookup_source_reference" 
                                           name="lookup_source_reference" placeholder="UUID from payment transaction" required>
                                    <small class="text-muted">The UUID reference used in the original payment</small>
                                </div>
                                
                                <button type="submit" class="btn btn-info w-100" <?= !$config->isConfigured() ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-magnifying-glass"></i> Lookup Transaction
                                </button>
                            </form>
                            
                            <?php if ($response): ?>
                                <div class="mt-4">
                                    <h5>Transaction Status:</h5>
                                    <pre><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'history'): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fa-solid fa-clock-rotate-left"></i> Transaction History</h4>
                            <?php if (!empty($transactionHistory)): ?>
                                <a href="?action=history&clear_history=1" class="btn btn-sm btn-outline-light" 
                                   onclick="return confirm('Are you sure you want to clear all transaction history?')">
                                    <i class="fa-solid fa-trash"></i> Clear History
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactionHistory)): ?>
                                <p class="text-center text-muted py-5">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                                    No transaction history available.
                                </p>
                            <?php else: ?>
                                <?php foreach ($transactionHistory as $txn): ?>
                                    <div class="transaction-item <?= ($txn['status'] ?? '') === 'success' ? 'success' : 'failed' ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="fa-solid fa-<?= 
                                                        ($txn['type'] ?? '') === 'payment' ? 'money-bill-transfer' : 
                                                        (($txn['type'] ?? '') === 'refund' ? 'rotate-left' : 'magnifying-glass')
                                                    ?>"></i>
                                                    <?= ucfirst($txn['type'] ?? 'unknown') ?> Transaction
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fa-solid fa-clock"></i> <?= htmlspecialchars($txn['timestamp'] ?? '') ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?= ($txn['status'] ?? '') === 'success' ? 'success' : 'danger' ?> status-badge">
                                                <?= ucfirst($txn['status'] ?? 'unknown') ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (isset($txn['amount'])): ?>
                                            <p class="mb-1">
                                                <strong>Amount:</strong> <?= htmlspecialchars($txn['currency'] ?? '') ?> <?= number_format($txn['amount'], 2) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($txn['msisdn'])): ?>
                                            <p class="mb-1">
                                                <strong>MSISDN:</strong> <?= htmlspecialchars($txn['msisdn']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($txn['reason'])): ?>
                                            <p class="mb-1">
                                                <strong>Reason:</strong> <?= htmlspecialchars($txn['reason']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($txn['error'])): ?>
                                            <p class="mb-1 text-danger">
                                                <strong>Error:</strong> <?= htmlspecialchars($txn['error']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($txn['response'])): ?>
                                            <details class="mt-2">
                                                <summary class="btn btn-sm btn-outline-secondary">View Response</summary>
                                                <pre class="mt-2"><?= htmlspecialchars(json_encode($txn['response'], JSON_PRETTY_PRINT)) ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="fa-solid fa-gear"></i> Settings & Preferences</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_preferences" value="1">
                                
                                <div class="mb-3">
                                    <label for="api_key" class="form-label">
                                        <i class="fa-solid fa-key"></i> API Key <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="api_key" name="api_key" 
                                           value="<?= htmlspecialchars($currentPreferences['api_key']) ?>" 
                                           placeholder="Enter your Ecocash API key" required>
                                    <small class="text-muted">Get your API key from <a href="https://developers.ecocash.co.zw/" target="_blank">Ecocash Developer Portal</a></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mode" class="form-label">
                                        <i class="fa-solid fa-toggle-on"></i> Environment Mode
                                    </label>
                                    <select class="form-select" id="mode" name="mode">
                                        <option value="sandbox" <?= $currentPreferences['mode'] === 'sandbox' ? 'selected' : '' ?>>
                                            Sandbox (Testing)
                                        </option>
                                        <option value="live" <?= $currentPreferences['mode'] === 'live' ? 'selected' : '' ?>>
                                            Live (Production)
                                        </option>
                                    </select>
                                    <small class="text-muted">Use sandbox for testing, live for production transactions</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_currency" class="form-label">
                                        <i class="fa-solid fa-coins"></i> Default Currency
                                    </label>
                                    <select class="form-select" id="default_currency" name="default_currency">
                                        <option value="USD" <?= $currentPreferences['default_currency'] === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                        <option value="ZWL" <?= $currentPreferences['default_currency'] === 'ZWL' ? 'selected' : '' ?>>ZWL - Zimbabwean Dollar</option>
                                        <option value="ZiG" <?= $currentPreferences['default_currency'] === 'ZiG' ? 'selected' : '' ?>>ZiG - Zimbabwe Gold</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_timeout" class="form-label">
                                        <i class="fa-solid fa-hourglass-half"></i> Request Timeout (seconds)
                                    </label>
                                    <input type="number" min="5" max="120" class="form-control" 
                                           id="default_timeout" name="default_timeout" 
                                           value="<?= htmlspecialchars($currentPreferences['default_timeout']) ?>">
                                    <small class="text-muted">Minimum: 5 seconds, Maximum: 120 seconds</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_logging" 
                                               name="enable_logging" <?= $currentPreferences['enable_logging'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_logging">
                                            Enable Transaction Logging
                                        </label>
                                    </div>
                                    <small class="text-muted">Store transaction history in session</small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-solid fa-save"></i> Save Preferences
                                    </button>
                                    <a href="?action=settings&reset=1" class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to reset all preferences to defaults?')">
                                        <i class="fa-solid fa-rotate-left"></i> Reset to Defaults
                                    </a>
                                </div>
                            </form>
                            
                            <?php if (isset($_GET['reset'])): ?>
                                <?php
                                $config->resetPreferences();
                                header('Location: ?action=settings&message=reset');
                                exit;
                                ?>
                            <?php endif; ?>
                            
                            <hr class="my-4">
                            
                            <div class="alert alert-info">
                                <h6><i class="fa-solid fa-info-circle"></i> Configuration Status</h6>
                                <p class="mb-0">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?= $config->isConfigured() ? 'success' : 'warning' ?>">
                                        <?= $config->isConfigured() ? 'Configured' : 'Not Configured' ?>
                                    </span>
                                </p>
                                <?php if ($config->isConfigured()): ?>
                                    <p class="mb-0 mt-2">
                                        <strong>Mode:</strong> <?= ucfirst($currentPreferences['mode']) ?><br>
                                        <strong>Default Currency:</strong> <?= htmlspecialchars($currentPreferences['default_currency']) ?><br>
                                        <strong>Timeout:</strong> <?= htmlspecialchars($currentPreferences['default_timeout']) ?> seconds
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
