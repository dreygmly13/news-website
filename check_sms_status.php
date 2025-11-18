<?php
// check_sms_status.php - CHECK SMS DELIVERY STATUS
require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #2196F3; color: white; }
    tr:hover { background: #f5f5f5; }
</style>";

echo "<h1>📱 SMS Delivery Status Checker</h1>";

// Form to check specific message
echo "<form method='POST' style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Check SMS by Message ID</h3>";
echo "<input type='text' name='message_id' placeholder='Enter Message ID' style='padding: 10px; width: 400px;'>";
echo "<button type='submit' name='check_status' style='padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; margin-left: 10px; cursor: pointer;'>Check Status</button>";
echo "</form>";

if (isset($_POST['check_status']) && !empty($_POST['message_id'])) {
    $messageId = trim($_POST['message_id']);
    
    echo "<h2>Checking Message ID: $messageId</h2>";
    
    // Query Infobip API for message status
    $url = INFOBIP_BASE_URL . "/sms/1/logs?messageId=" . urlencode($messageId);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: App ' . INFOBIP_API_KEY,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['results'][0])) {
            $msg = $data['results'][0];
            
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td><strong>Status</strong></td><td class='" . 
                ($msg['status']['groupName'] === 'DELIVERED' ? 'success' : 'error') . "'>" . 
                $msg['status']['groupName'] . " - " . $msg['status']['description'] . "</td></tr>";
            echo "<tr><td><strong>To</strong></td><td>" . $msg['to'] . "</td></tr>";
            echo "<tr><td><strong>From</strong></td><td>" . $msg['from'] . "</td></tr>";
            echo "<tr><td><strong>Text</strong></td><td>" . htmlspecialchars($msg['text']) . "</td></tr>";
            echo "<tr><td><strong>Sent At</strong></td><td>" . $msg['sentAt'] . "</td></tr>";
            echo "<tr><td><strong>Done At</strong></td><td>" . ($msg['doneAt'] ?? 'Pending') . "</td></tr>";
            echo "<tr><td><strong>Price</strong></td><td>" . ($msg['price']['pricePerMessage'] ?? '0') . " " . ($msg['price']['currency'] ?? '') . "</td></tr>";
            echo "</table>";
            
            // Diagnosis
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>🔍 Diagnosis:</h3>";
            
            if ($msg['status']['groupName'] === 'DELIVERED') {
                echo "<p class='success'>✅ Message was delivered successfully!</p>";
                echo "<p>If you didn't receive it, check:</p>";
                echo "<ul>";
                echo "<li>Phone signal strength</li>";
                echo "<li>SMS inbox is not full</li>";
                echo "<li>Message might be in spam/blocked folder</li>";
                echo "<li>SIM card is active</li>";
                echo "</ul>";
            } elseif ($msg['status']['groupName'] === 'PENDING') {
                echo "<p class='warning'>⏳ Message is still pending delivery. Wait a few minutes.</p>";
            } elseif ($msg['status']['groupName'] === 'UNDELIVERABLE') {
                echo "<p class='error'>❌ Message could not be delivered.</p>";
                echo "<p><strong>Reason:</strong> " . $msg['status']['description'] . "</p>";
                echo "<ul>";
                echo "<li>Phone number might be invalid or inactive</li>";
                echo "<li>Network issue with recipient's carrier</li>";
                echo "<li>Number might be ported to different carrier</li>";
                echo "</ul>";
            } else {
                echo "<p class='error'>❌ Delivery failed: " . $msg['status']['description'] . "</p>";
            }
            echo "</div>";
        } else {
            echo "<p class='error'>❌ No message found with this ID</p>";
        }
    } else {
        echo "<p class='error'>❌ Failed to check status (HTTP $httpCode)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

// Show recent messages from database
echo "<h2>📋 Recent SMS from Database</h2>";

$db = getDB();
$logs = $db->query("
    SELECT sl.*, s.name, s.phone_number 
    FROM sms_logs sl
    JOIN subscribers s ON sl.subscriber_id = s.id
    ORDER BY sl.sent_at DESC
    LIMIT 10
");

if ($logs->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Time</th><th>Recipient</th><th>Phone</th><th>Status</th><th>Message</th></tr>";
    while ($log = $logs->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . formatDate($log['sent_at']) . "</td>";
        echo "<td>" . htmlspecialchars($log['name']) . "</td>";
        echo "<td>" . htmlspecialchars($log['phone_number']) . "</td>";
        echo "<td><span class='" . ($log['status'] === 'sent' ? 'success' : 'error') . "'>" . 
            strtoupper($log['status']) . "</span></td>";
        echo "<td>" . htmlspecialchars(substr($log['message'], 0, 50)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No SMS logs found in database.</p>";
}

// Query Infobip for recent messages
echo "<h2>📡 Recent Messages from Infobip API</h2>";

$url = INFOBIP_BASE_URL . "/sms/1/logs?limit=10";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: App ' . INFOBIP_API_KEY,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['results']) && count($data['results']) > 0) {
        echo "<table>";
        echo "<tr><th>Message ID</th><th>To</th><th>Status</th><th>Sent At</th><th>Actions</th></tr>";
        
        foreach ($data['results'] as $msg) {
            $statusClass = $msg['status']['groupName'] === 'DELIVERED' ? 'success' : 
                          ($msg['status']['groupName'] === 'PENDING' ? 'warning' : 'error');
            
            echo "<tr>";
            echo "<td>" . $msg['messageId'] . "</td>";
            echo "<td>" . $msg['to'] . "</td>";
            echo "<td><span class='$statusClass'>" . $msg['status']['groupName'] . "</span><br>";
            echo "<small>" . $msg['status']['description'] . "</small></td>";
            echo "<td>" . $msg['sentAt'] . "</td>";
            echo "<td><form method='POST' style='margin:0;'>";
            echo "<input type='hidden' name='message_id' value='" . $msg['messageId'] . "'>";
            echo "<button type='submit' name='check_status' style='padding: 5px 10px; background: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer;'>Check Details</button>";
            echo "</form></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No messages found in Infobip logs.</p>";
    }
} else {
    echo "<p class='error'>Failed to fetch logs from Infobip (HTTP $httpCode)</p>";
}

echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>💡 Common Reasons for Not Receiving SMS:</h3>";
echo "<ol>";
echo "<li><strong>Network Delay:</strong> SMS can take 1-5 minutes to arrive</li>";
echo "<li><strong>Invalid Number:</strong> Number format must be +639XXXXXXXXX</li>";
echo "<li><strong>Carrier Blocking:</strong> Some carriers block SMS from unknown senders</li>";
echo "<li><strong>Sender ID Issue:</strong> Try changing sender ID to a numeric value</li>";
echo "<li><strong>Trial Account:</strong> Some trial accounts have delivery restrictions</li>";
echo "<li><strong>DND List:</strong> Number might be on Do Not Disturb registry</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='send_sms.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>← Back to Send SMS</a></p>";
?>