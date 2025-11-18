<?php
// test_ai.php - TEST AI TRANSLATION
require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
</style>";

echo "<h1>🤖 AI Translation Test</h1>";

$testContent = "The Philippine Atmospheric, Geophysical and Astronomical Services Administration (PAGASA) has issued a weather advisory warning of heavy rainfall across the Visayas region starting tomorrow.";

echo "<h2>Test Content (English):</h2>";
echo "<pre>" . htmlspecialchars($testContent) . "</pre>";

echo "<h2>Attempting Translation to Hiligaynon...</h2>";

// Same function from send_sms.php
$data = [
    'model' => AI_MODEL,
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant that summarizes news content and translates it to Hiligaynon (Ilonggo). Your task is to:
1. Summarize the news content in 2-3 sentences (maximum 160 characters for SMS)
2. Translate the summary to Hiligaynon
3. Respond ONLY with the Hiligaynon translation, nothing else.
Keep the message clear, concise, and informative.'
        ],
        [
            'role' => 'user',
            'content' => "Summarize and translate this news to Hiligaynon:\n\n" . $testContent
        ]
    ]
];

echo "<h3>Request Details:</h3>";
echo "<pre>";
echo "Endpoint: " . AI_API_ENDPOINT . "\n";
echo "Model: " . AI_MODEL . "\n";
echo "</pre>";

$ch = curl_init(AI_API_ENDPOINT);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'customerId: cus_TEa79DTCHfkEKn',
    'Content-Type: application/json',
    'Authorization: Bearer xxx'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>Response:</h3>";
echo "<pre>";
echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($curlError ?: 'None') . "\n\n";
echo "Raw Response:\n";
echo htmlspecialchars($response);
echo "</pre>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    
    echo "<h3>Parsed Response:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if (isset($result['choices'][0]['message']['content'])) {
        $translation = trim($result['choices'][0]['message']['content']);
        echo "<h2 class='success'>✅ Translation Successful!</h2>";
        echo "<h3>Hiligaynon Translation:</h3>";
        echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 8px; font-size: 18px;'>";
        echo htmlspecialchars($translation);
        echo "</div>";
    } else {
        echo "<p class='error'>❌ Translation field not found in response</p>";
    }
} else {
    echo "<p class='error'>❌ AI API Error (HTTP $httpCode)</p>";
}

echo "<p style='margin-top: 20px;'><a href='send_sms.php'>← Back to Send SMS</a></p>";
?>