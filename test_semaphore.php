<?php
// test_semaphore.php - TEST SEMAPHORE SETUP
require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .test-section { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 8px; }
</style>";

echo "<h1>📱 Semaphore Configuration Test</h1>";

// Display Configuration
echo "<div class='test-section'>";
echo "<h2>1️⃣ Configuration Details</h2>";
echo "<strong>API Key:</strong> " . substr(SEMAPHORE_API_KEY, 0, 20) . "...<br>";
echo "<strong>Sender Name:</strong> " . SEMAPHORE_SENDER . "<br>";
echo "</div>";

// Test: Check Account Balance
echo "<div class='test-section'>";
echo "<h2>2️⃣ Check Account Balance</h2>";

$url = 'https://api.semaphore.co/api/v4/account';
$ch = curl_init($url . '?apikey=' . SEMAPHORE_API_KEY);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "<p class='success'>✅ Successfully connected to Semaphore!</p>";
    echo "<strong>Account Status:</strong> " . $data['status'] . "<br>";
    echo "<strong>Credit Balance:</strong> ₱" . $data['credit_balance'] . "<br>";
    echo "<strong>Account Name:</strong> " . $data['account_name'] . "<br>";
} else {
    echo "<p class='error'>❌ Failed to connect (HTTP $httpCode)</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
echo "</div>";

// Test: Send Test SMS
echo "<div class='test-section'>";
echo "<h2>3️⃣ Send Test SMS</h2>";
echo "<form method='POST'>";
echo "<label><strong>Enter Philippine Number:</strong></label><br>";
echo "<input type='text' name='test_phone' placeholder='09XXXXXXXXX or +639XXXXXXXXX' value='09772506661' style='padding: 8px; width: 250px; margin: 10px 0;'><br>";
echo "<button type='submit' name='send_test' style='padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;'>📱 Send Test SMS</button>";
echo "</form>";

if (isset($_POST['send_test'])) {
    $testPhone = $_POST['test_phone'];
    
    // Clean phone number
    $testPhone = preg_replace('/[^0-9+]/', '', $testPhone);
    if (substr($testPhone, 0, 1) === '+') {
        $testPhone = substr($testPhone, 1);
    }
    
    $testMessage = "Test SMS from News Portal using Semaphore! Time: " . date('h:i:s A');
    
    $url = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey' => SEMAPHORE_API_KEY,
        'number' => $testPhone,
        'message' => $testMessage,
        'sendername' => SEMAPHORE_SENDER
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if (is_array($responseData) && isset($responseData[0]['message_id'])) {
            echo "<p class='success'>✅ SMS Sent Successfully!</p>";
            echo "<strong>Message ID:</strong> " . $responseData[0]['message_id'] . "<br>";
            echo "<strong>Status:</strong> Queued for delivery<br>";
            echo "<div style='background: #e8f5e9; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Check your phone at:</strong> $testPhone<br>";
            echo "<strong>Message:</strong> $testMessage";
            echo "</div>";
        } else {
            echo "<p class='error'>❌ Error: " . ($responseData['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Failed to send (HTTP $httpCode)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>✅ Next Steps</h2>";
echo "<ol>";
echo "<li>If balance check passed: Your API key is working ✅</li>";
echo "<li>If test SMS sent: Check your phone within 30 seconds 📱</li>";
echo "<li>Ready to use: <a href='send_sms.php'>Go to Send SMS Page</a></li>";
echo "</ol>";
echo "</div>";
?>