<?php
// test_sim800c.php - TEST SIM800C MODULE
require_once 'config.php';
require_once 'arduino_sms.php';

echo "<style>
body { font-family: Arial; padding: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; }
.info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0; }
</style>";

echo "<h1>📡 SIM800C GSM Module Test</h1>";

$arduino = new ArduinoSMS();

// Test 1: Detect Arduino
echo "<h2>Test 1: Detect Arduino</h2>";
$detectedPort = ArduinoSMS::detectPort();
if ($detectedPort) {
    echo "<p class='success'>✅ Arduino detected on $detectedPort</p>";
} else {
    echo "<p class='error'>❌ Arduino not found</p>";
    echo "<div class='info'><strong>Fix:</strong> Connect Arduino via USB and check Device Manager</div>";
}

// Test 2: Connect
echo "<h2>Test 2: Connect to Arduino</h2>";
$result = $arduino->connect(ARDUINO_COM_PORT);
if ($result['success']) {
    echo "<p class='success'>✅ Connected to {$result['port']}</p>";
} else {
    echo "<p class='error'>❌ {$result['error']}</p>";
}

// Test 3: Check SIM800C Status
echo "<h2>Test 3: Check SIM800C Module</h2>";
$status = $arduino->checkStatus();
if ($status['connected']) {
    echo "<p class='success'>✅ SIM800C is responding</p>";
    echo "<pre>{$status['response']}</pre>";
} else {
    echo "<p class='error'>❌ SIM800C not responding</p>";
    echo "<div class='info'>";
    echo "<strong>Troubleshooting Checklist:</strong>";
    echo "<ol>";
    echo "<li>Power: SIM800C needs 3.7-4.2V (NOT 5V!)</li>";
    echo "<li>Wiring: RX→Pin7, TX→Pin8, GND→GND</li>";
    echo "<li>SIM Card: Inserted correctly with load</li>";
    echo "<li>Antenna: Connected to SIM800C</li>";
    echo "<li>LED: Should blink (indicates network)</li>";
    echo "</ol>";
    echo "</div>";
}

// Test 4: Send SMS
echo "<h2>Test 4: Send Test SMS</h2>";

if (isset($_POST['test_sms'])) {
    $phone = $_POST['phone'];
    $message = "Test SMS from SIM800C module - Time: " . date('H:i:s');
    
    echo "<p>Sending to: <strong>$phone</strong></p>";
    echo "<p>Message: <strong>$message</strong></p>";
    
    $result = $arduino->sendSMS($phone, $message);
    
    if ($result['success']) {
        echo "<p class='success'>✅ SMS sent via SIM800C!</p>";
        echo "<p>Message ID: {$result['id']}</p>";
        echo "<div class='info'><strong>📱 Check your phone in 10-30 seconds</strong></div>";
    } else {
        echo "<p class='error'>❌ Failed: {$result['error']}</p>";
    }
}

echo "<form method='POST'>";
echo "<label>Phone Number (639XXXXXXXXX):</label><br>";
echo "<input type='text' name='phone' value='639391817858' style='padding: 8px; width: 220px;'><br><br>";
echo "<button type='submit' name='test_sms' style='padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;'>📱 Send Test SMS</button>";
echo "</form>";

echo "<hr>";
echo "<h2>📋 Setup Guide</h2>";
echo "<div class='info'>";
echo "<h3>Hardware Connections:</h3>";
echo "<pre>";
echo "Arduino Uno  →  SIM800C Module\n";
echo "═══════════════════════════════\n";
echo "5V (via 3.7-4.2V regulator) → VCC\n";
echo "GND                         → GND\n";
echo "Pin 7                       → TXD\n";
echo "Pin 8                       → RXD\n";
echo "                              ANT (attach antenna)\n";
echo "</pre>";

echo "<h3>Important Notes:</h3>";
echo "<ul>";
echo "<li><strong>⚠️ NEVER connect 5V directly to SIM800C!</strong> Use voltage regulator or separate 3.7-4.2V supply</li>";
echo "<li>Use a <strong>good quality SIM card</strong> with active load</li>";
echo "<li>SIM800C LED should <strong>blink every 3 seconds</strong> when connected to network</li>";
echo "<li>Keep <strong>antenna connected</strong> for better signal</li>";
echo "</ul>";

echo "<h3>COM Port:</h3>";
echo "<p>Current config: <strong>" . ARDUINO_COM_PORT . "</strong></p>";
echo "<p>To change: Edit <code>config.php</code> and update <code>ARDUINO_COM_PORT</code></p>";
echo "</div>";

echo "<p><a href='send_sms.php'>→ Go to Send SMS</a></p>";
?>