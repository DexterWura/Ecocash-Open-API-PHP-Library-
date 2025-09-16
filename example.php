<?php
// Include the Ecocash client library
require_once 'EcocashClient.php';

use Ecocash\EcocashClient;
use Ecocash\EcocashException;
use Ecocash\EcocashValidationException;
use Ecocash\EcocashNetworkException;

$response = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = 'YOUR_API_KEY_HERE'; // Replace with your actual API key
    $client = new EcocashClient($apiKey, 'sandbox'); // Use 'live' for production

    $msisdn = EcocashClient::normalizeMsisdn($_POST['msisdn']);
    $amount = (float) $_POST['amount'];
    $reason = $_POST['reason'] ?? 'Payment';
    $currency = $_POST['currency'] ?? 'USD';

    try {
        // The method signature remains the same, so no changes are needed here.
        $response = $client->payment($msisdn, $amount, $reason, $currency);
    } catch (EcocashValidationException $e) {
        $error = "Validation Error: " . $e->getMessage();
    } catch (EcocashNetworkException $e) {
        $error = "Network Error: " . $e->getMessage();
    } catch (EcocashException $e) {
        // This is the general catch-all for other API or client errors.
        $error = "Ecocash Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecocash Payment Example</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fa-solid fa-wallet"></i> Ecocash Payment</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($response): ?>
                            <div class="alert alert-success">
                                <h5>Payment Successful!</h5>
                                <pre><?php echo htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger">
                                <h5>Error:</h5>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="msisdn" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="msisdn" name="msisdn" placeholder="263774222475" required>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="10.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason</label>
                                <input type="text" class="form-control" id="reason" name="reason" placeholder="Payment for service">
                            </div>
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="USD" selected>USD</option>
                                    <option value="ZWL">ZWL</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-paper-plane"></i> Pay</button>
                        </form>
                    </div>
                </div>
                <p class="text-center mt-3 text-muted">
                    Demo using <strong>Ecocash Open API PHP Client</strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>