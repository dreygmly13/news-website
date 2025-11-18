<?php
// diagnose_semaphore.php - COMPLETE DIAGNOSIS
require_once 'config.php';

echo "<style>
body { font-family: Arial; padding: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
</style>";

echo "<h1>🔍 Semaphore Complete Diagnosis</h1>";

// TEST 1: API Key Validation
echo "<h2>Test 1: API Key Validation</h2>";
echo "<p>API Key: " . substr(SEMAPHORE_API_KEY, 0, 20) . "...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/account?apikey=' . SEMAPHORE_API_KEY);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $httpCode</p>";

if ($httpCode === 200) {
    $account = json_decode($response, true);
    echo "<p class='success'>✅ API Key is VALID</p>";
    echo "<pre>";
    echo "Account Name: " . $account['account_name'] . "\n";
    echo "Account Status: " . $account['status'] . "\n";
    echo "Credit Balance: ₱" . number_format($account['credit_balance'], 2) . "\n";
    echo "User ID: " . $account['user']['user_id'] . "\n";
    echo "Email: " . $account['user']['email'] . "\n";
    echo "</pre>";
    
    if ($account['credit_balance'] < 1) {
        echo "<p class='error'>❌ INSUFFICIENT BALANCE! You need at least ₱1 to send SMS.</p>";
        echo "<p><a href='https://semaphore.co/billing' target='_blank'>→ Add credits here</a></p>";
    } else {
        echo "<p class='success'>✅ Balance is sufficient</p>";
    }
    
    if ($account['status'] !== 'Active') {
        echo "<p class='error'>❌ Account status is not Active: {$account['status']}</p>";
    }
} else {
    echo "<p class='error'>❌ INVALID API KEY or connection error</p>";
    echo "<pre>$response</pre>";
    die();
}

// TEST 2: Send Test SMS
echo "<hr><h2>Test 2: Send Test SMS</h2>";

if (isset($_POST['send_test'])) {
    $testPhone = $_POST['phone'];
    $testMessage = "Test from News Portal - " . date('H:i:s');
    
    echo "<p>Sending to: <strong>$testPhone</strong></p>";
    echo "<p>Message: <strong>$testMessage</strong></p>";
    
    // Send via Semaphore
    $parameters = array(
        'apikey' => SEMAPHORE_API_KEY,
        'number' => $testPhone,
        'message' => $testMessage,
        'sendername' => SEMAPHORE_SENDER
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "<h3>Response Details:</h3>";
    echo "<pre>";
    echo "HTTP Code: $httpCode\n";
    echo "cURL Error: " . ($curlError ?: 'None') . "\n\n";
    echo "Raw Response:\n";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    $result = json_decode($output, true);
    
    if ($httpCode === 200) {
        if (is_array($result) && isset($result[0])) {
            if (isset($result[0]['message_id'])) {
                echo "<p class='success'>✅ SMS SENT SUCCESSFULLY!</p>";
                echo "<p><strong>Message ID:</strong> {$result[0]['message_id']}</p>";
                echo "<p><strong>Status:</strong> {$result[0]['status']}</p>";
                echo "<p><strong>Recipient:</strong> {$result[0]['recipient']}</p>";
                echo "<p><strong>Network:</strong> {$result[0]['network']}</p>";
                echo "<p>Check delivery status at: <a href='https://semaphore.co/messages' target='_blank'>Semaphore Dashboard</a></p>";
            } else {
                echo "<p class='error'>❌ FAILED: " . ($result[0]['message'] ?? 'Unknown error') . "</p>";
            }
        } else {
            echo "<p class='error'>❌ Unexpected response format</p>";
        }
    } elseif ($httpCode === 400) {
        echo "<p class='error'>❌ BAD REQUEST - Invalid parameters</p>";
        if (isset($result['message'])) {
            echo "<p>Error: {$result['message']}</p>";
        }
    } elseif ($httpCode === 401) {
        echo "<p class='error'>❌ UNAUTHORIZED - Invalid API key</p>";
    } elseif ($httpCode === 402) {
        echo "<p class='error'>❌ PAYMENT REQUIRED - Insufficient balance</p>";
    } else {
        echo "<p class='error'>❌ HTTP ERROR $httpCode</p>";
    }
}

echo "<hr><h3>Send Test SMS</h3>";
echo "<form method='POST'>";
echo "<label>Phone Number (try different formats):</label><br>";
echo "<input type='radio' name='phone' value='09391817858' checked> 09391817858 (Your number)<br>";
echo "<input type='radio' name='phone' value='09772506661'> 09772506661 (Alternative)<br>";
echo "<input type='radio' name='phone' value='09171234567'> 09171234567 (Test Globe)<br>";
echo "<input type='radio' name='phone' value='09051234567'> 09051234567 (Test Smart)<br><br>";
echo "<button type='submit' name='send_test' style='padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;'>📱 Send Test SMS</button>";
echo "</form>";

echo "<hr><h2>Possible Issues & Solutions:</h2>";
echo "<ol>";
echo "<li><strong>Low Balance:</strong> Add credits at <a href='https://semaphore.co/billing'>semaphore.co/billing</a></li>";
echo "<li><strong>Invalid Number:</strong> The number might be inactive or invalid</li>";
echo "<li><strong>Network Issue:</strong> Recipient's network might be blocking SMS</li>";
echo "<li><strong>Sender Name Issue:</strong> Try changing sender to 'INFO' or 'SEMAPHORE'</li>";
echo "<li><strong>DND List:</strong> Number might be on Do-Not-Disturb registry</li>";
echo "<li><strong>Account Not Verified:</strong> Check if account needs verification</li>";
echo "</ol>";

echo "<h2>Network-Specific Numbers to Test:</h2>";
echo "<ul>";
echo "<li><strong>Globe/TM:</strong> 0905, 0906, 0915, 0916, 0917, 0926, 0927, 0935, 0936, 0945, 0955, 0956, 0965, 0966, 0967, 0975, 0976, 0977, 0995, 0996, 0997</li>";
echo "<li><strong>Smart/TNT:</strong> 0907, 0908, 0909, 0910, 0911, 0912, 0913, 0914, 0918, 0919, 0920, 0921, 0928, 0929, 0930, 0938, 0939, 0946, 0947, 0948, 0949, 0950, 0951, 0961, 0970, 0981, 0989, 0992, 0998, 0999</li>";
echo "<li><strong>Sun:</strong> 0922, 0923, 0924, 0925, 0931, 0932, 0933, 0934, 0940, 0941, 0942, 0943, 0944</li>";
echo "</ul>";
?>