<?php
// test_infobip.php - TEST YOUR INFOBIP SETUP
require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .test-section { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 8px; }
</style>";

echo "<h1>📱 Infobip Configuration Test</h1>";

// Display Configuration
echo "<div class='test-section'>";
echo "<h2>1️⃣ Configuration Details</h2>";
echo "<strong>API Key:</strong> " . substr(INFOBIP_API_KEY, 0, 20) . "...<br>";
echo "<strong>Base URL:</strong> " . INFOBIP_BASE_URL . "<br>";
echo "<strong>Sender ID:</strong> " . INFOBIP_SENDER . "<br>";
echo "</div>";

// Test 1: Check Account Balance
echo "<div class='test-section'>";
echo "<h2>2️⃣ Testing API Connection (Account Balance)</h2>";

$url = INFOBIP_BASE_URL . '/account/1/balance';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: App ' . INFOBIP_API_KEY,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<p class='error'>❌ Connection Error: " . curl_error($ch) . "</p>";
} else {
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "<p class='success'>✅ Successfully connected to Infobip!</p>";
        echo "<strong>Account Balance:</strong> " . $data['balance'] . " " . $data['currency'] . "<br>";
    } else {
        echo "<p class='error'>❌ API Error (HTTP $httpCode)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
curl_close($ch);
echo "</div>";

// Test 2: Send Test SMS
echo "<div class='test-section'>";
echo "<h2>3️⃣ Send Test SMS</h2>";
echo "<form method='POST'>";
echo "<label><strong>Enter Philippine Number (with +63):</strong></label><br>";
echo "<input type='text' name='test_phone' placeholder='+639XXXXXXXXX' value='+639772506661' style='padding: 8px; width: 250px; margin: 10px 0;'><br>";
echo "<button type='submit' name='send_test' style='padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer;'>📱 Send Test SMS</button>";
echo "</form>";

if (isset($_POST['send_test'])) {
    $testPhone = $_POST['test_phone'];
    $testMessage = "Test message from News Portal. This is a test SMS using Infobip API. Time: " . date('h:i:s A');
    
    $url = INFOBIP_BASE_URL . '/sms/2/text/advanced';
    
    $payload = [
        'messages' => [
            [
                'from' => INFOBIP_SENDER,
                'destinations' => [
                    ['to' => $testPhone]
                ],
                'text' => $testMessage
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: App ' . INFOBIP_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "<p class='error'>❌ Error: " . curl_error($ch) . "</p>";
    } else {
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            echo "<p class='success'>✅ SMS Sent Successfully!</p>";
            echo "<strong>Message ID:</strong> " . ($responseData['messages'][0]['messageId'] ?? 'N/A') . "<br>";
            echo "<strong>Status:</strong> " . ($responseData['messages'][0]['status']['groupName'] ?? 'N/A') . "<br>";
            echo "<div class='info'><strong>Message sent to:</strong> $testPhone<br><strong>Content:</strong> $testMessage</div>";
        } else {
            echo "<p class='error'>❌ Failed to send SMS (HTTP $httpCode)</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
    curl_close($ch);
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>✅ Next Steps</h2>";
echo "<ol>";
echo "<li>If balance check passed: Your API key is working correctly ✅</li>";
echo "<li>If test SMS sent: Check your phone for the message 📱</li>";
echo "<li>Ready to use: <a href='send_sms.php'>Go to Send SMS Page</a></li>";
echo "<li>If you see errors: Check your Infobip portal for detailed logs</li>";
echo "</ol>";
echo "</div>";
?>