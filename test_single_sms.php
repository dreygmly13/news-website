<?php
require_once 'config.php';

if (isset($_POST['test'])) {
    $phone = $_POST['phone'];
    $message = $_POST['message'];
    
    // Clean phone
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
        $phone = '0' . substr($phone, 2);
    }
    
    echo "<h2>Sending Test SMS</h2>";
    echo "<p>Phone: $phone</p>";
    echo "<p>Message: $message</p>";
    
    $parameters = array(
        'apikey' => SEMAPHORE_API_KEY,
        'number' => $phone,
        'message' => $message,
        'sendername' => SEMAPHORE_SENDER
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<h3>Response (HTTP $httpCode):</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $data = json_decode($output, true);
    if (isset($data[0]['message_id'])) {
        echo "<p style='color:green'><strong>✅ SMS Queued!</strong></p>";
        echo "<p>Message ID: {$data[0]['message_id']}</p>";
        echo "<p>Check delivery status at: <a href='https://semaphore.co/messages'>semaphore.co/messages</a></p>";
    }
}
?>
<h1>Test Single SMS</h1>
<form method="POST">
    <label>Phone (09XXXXXXXXX):</label><br>
    <input type="text" name="phone" value="09391817858"><br><br>
    
    <label>Message:</label><br>
    <textarea name="message" rows="3" cols="40">Test message from News Portal</textarea><br><br>
    
    <button type="submit" name="test">Send Test SMS</button>
</form>