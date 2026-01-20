<?php
/**
 * ================================================================
 * USB SIM800C v3 SMS Service - PURE PHP VERSION
 * ================================================================
 * Complete standalone service for USB SIM800C module
 * Replaces Python Flask service with pure PHP implementation
 * Version: 1.0
 * ================================================================
 */

// ============================================
// CONFIGURATION
// ============================================
define('DEFAULT_PORT', 'COM3');
define('DEFAULT_BAUD', 115200); // USB SIM800C typically uses 115200

$SIM800C_PORT = DEFAULT_PORT;
$SIM800C_BAUD = DEFAULT_BAUD;

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Auto-detect USB SIM800C COM port
 */
function find_usb_port() {
    echo "\n🔍 Scanning for USB devices...\n";
    
    // On Windows, we can check available COM ports
    // This is a simplified version - in production you might want to use WMI
    $ports = ['COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'COM10'];
    
    foreach ($ports as $port) {
        $handle = @fopen($port . ':', 'r+b');
        if ($handle) {
            echo "  Found: $port\n";
            fclose($handle);
            // Try to send AT command to verify it's a modem
            $handle = @fopen($port . ':', 'r+b');
            if ($handle) {
                stream_set_timeout($handle, 1);
                fwrite($handle, "AT\r\n");
                usleep(500000); // 0.5 seconds
                $response = fread($handle, 1024);
                fclose($handle);
                
                if (strpos($response, 'OK') !== false) {
                    echo "  ✅ Selected: $port (responds to AT)\n";
                    return $port;
                }
            }
        }
    }
    
    echo "  ⚠️ No USB device detected, using default: " . DEFAULT_PORT . "\n";
    return DEFAULT_PORT;
}

/**
 * Send AT command and get response
 */
function send_at_command($handle, $command, $wait_time = 1) {
    echo "  TX: $command\n";
    
    // Clear input buffer
    stream_get_contents($handle);
    
    // Send command with proper line ending
    fwrite($handle, $command . "\r\n");
    usleep($wait_time * 1000000); // Convert to microseconds
    
    // Read response
    $response = '';
    $timeout = time() + $wait_time;
    while (time() < $timeout) {
        $chunk = fread($handle, 1024);
        if ($chunk) {
            $response .= $chunk;
        }
        usleep(100000); // 0.1 seconds
    }
    
    echo "  RX: " . trim($response) . "\n";
    return $response;
}

/**
 * Initialize and check SIM800C module
 */
function initialize_sim800c($handle) {
    echo "\n📡 Initializing SIM800C module...\n";
    
    // Test basic communication
    $resp = send_at_command($handle, 'AT', 1);
    if (strpos($resp, 'OK') === false) {
        return [false, "Module not responding to AT command"];
    }
    echo "  ✅ Module responding\n";
    
    // Disable echo
    send_at_command($handle, 'ATE0', 1);
    
    // Set SMS text mode
    send_at_command($handle, 'AT+CMGF=1', 1);
    echo "  ✅ SMS text mode set\n";
    
    // Check network registration
    $resp = send_at_command($handle, 'AT+CREG?', 2);
    
    if (strpos($resp, '+CREG: 0,1') !== false) {
        echo "  ✅ Registered on home network\n";
        return [true, "Ready"];
    } elseif (strpos($resp, '+CREG: 0,5') !== false) {
        echo "  ✅ Registered (roaming)\n";
        return [true, "Ready"];
    } elseif (strpos($resp, '+CREG: 0,2') !== false) {
        echo "  ⏳ Searching for network...\n";
        return [false, "Searching for network"];
    } else {
        echo "  ❌ Not registered on network\n";
        return [false, "SIM card not registered on network"];
    }
}

/**
 * Check GSM signal quality
 */
function check_signal_strength($handle) {
    $resp = send_at_command($handle, 'AT+CSQ', 1);
    
    if (preg_match('/\+CSQ:\s*(\d+)/', $resp, $matches)) {
        $signal = intval($matches[1]);
        $percentage = round(($signal / 31) * 100);
        echo "  📶 Signal strength: $signal/31 ($percentage%)\n";
        
        if ($signal < 5) {
            return [false, "Signal too weak: $signal/31"];
        } elseif ($signal < 10) {
            echo "  ⚠️ Signal is weak but usable\n";
        }
        
        return [true, "Signal OK: $signal/31"];
    }
    
    return [false, "Cannot read signal strength"];
}

// ============================================
// SMS SENDING FUNCTION
// ============================================

/**
 * Send SMS via USB SIM800C module
 * 
 * @param string $phone_number Recipient phone (format: 09XXXXXXXXX or 639XXXXXXXXX)
 * @param string $message_text SMS message (max 160 characters)
 * @return array Result with success status and details
 */
function send_sms_via_sim800c($phone_number, $message_text) {
    global $SIM800C_PORT, $SIM800C_BAUD;
    
    // Convert 09XXXXXXXXX to 639XXXXXXXXX if needed
    if (substr($phone_number, 0, 2) === '09' && strlen($phone_number) === 11) {
        $phone_number = '63' . substr($phone_number, 1);
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📤 SMS SEND REQUEST\n";
    echo "   To: $phone_number\n";
    echo "   Message: $message_text\n";
    echo str_repeat("=", 60) . "\n";
    
    $handle = null;
    
    try {
        // Open serial connection
        echo "\n📌 Opening $SIM800C_PORT @ $SIM800C_BAUD baud...\n";
        $handle = @fopen($SIM800C_PORT . ':', 'r+b');
        
        if (!$handle) {
            throw new Exception("Cannot open COM port: $SIM800C_PORT");
        }
        
        // Set timeout
        stream_set_timeout($handle, 10);
        stream_set_blocking($handle, true);
        
        // Give module time to initialize
        usleep(2000000); // 2 seconds
        
        // Initialize module
        list($init_success, $init_message) = initialize_sim800c($handle);
        
        if (!$init_success) {
            if ($handle) fclose($handle);
            echo "❌ Initialization failed: $init_message\n";
            return [
                'success' => false,
                'message' => $init_message,
                'details' => 'Module initialization failed'
            ];
        }
        
        // Check signal strength
        list($signal_ok, $signal_msg) = check_signal_strength($handle);
        if (!$signal_ok) {
            echo "⚠️ Warning: $signal_msg\n";
        }
        
        // Start SMS sending sequence
        echo "\n📨 Starting SMS send sequence...\n";
        
        // Clear buffers
        stream_get_contents($handle);
        
        // Send AT+CMGS command
        $sms_command = 'AT+CMGS="' . $phone_number . '"';
        echo "  Sending: $sms_command\n";
        fwrite($handle, $sms_command . "\r\n");
        usleep(1000000); // 1 second
        
        // Wait for '>' prompt
        $prompt_response = '';
        $timeout = time() + 5;
        
        while (time() < $timeout) {
            $chunk = fread($handle, 1024);
            if ($chunk) {
                $prompt_response .= $chunk;
                if (strpos($prompt_response, '>') !== false) {
                    echo "  ✅ Got '>' prompt\n";
                    break;
                }
            }
            usleep(100000); // 0.1 seconds
        }
        
        if (strpos($prompt_response, '>') === false) {
            if ($handle) fclose($handle);
            echo "  ❌ No '>' prompt received\n";
            echo "  Response was: $prompt_response\n";
            return [
                'success' => false,
                'message' => 'Module did not respond with > prompt',
                'details' => $prompt_response
            ];
        }
        
        // Send message text
        echo "  Sending message text...\n";
        fwrite($handle, $message_text);
        usleep(500000); // 0.5 seconds
        
        // Send Ctrl+Z (ASCII 26) to actually send the SMS
        echo "  Sending Ctrl+Z (send command)...\n";
        fwrite($handle, chr(26));
        
        // Wait for send confirmation
        echo "  ⏳ Waiting for send confirmation (15 sec timeout)...\n";
        $confirmation = '';
        $timeout = time() + 15;
        
        while (time() < $timeout) {
            $chunk = fread($handle, 1024);
            if ($chunk) {
                $confirmation .= $chunk;
                
                // Check for success indicators
                if (strpos($confirmation, '+CMGS:') !== false || strpos($confirmation, 'OK') !== false) {
                    if ($handle) fclose($handle);
                    echo "  ✅ SMS SENT SUCCESSFULLY!\n";
                    echo "  Confirmation: " . trim($confirmation) . "\n";
                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully',
                        'message_id' => 'sim800c_' . time(),
                        'details' => trim($confirmation)
                    ];
                }
                
                // Check for errors
                if (strpos($confirmation, 'ERROR') !== false) {
                    if ($handle) fclose($handle);
                    echo "  ❌ Module returned ERROR\n";
                    echo "  Response: $confirmation\n";
                    return [
                        'success' => false,
                        'message' => 'Module returned error',
                        'details' => trim($confirmation)
                    ];
                }
            }
            usleep(500000); // 0.5 seconds
        }
        
        // Timeout
        if ($handle) fclose($handle);
        echo "  ⏰ Timeout waiting for confirmation\n";
        echo "  Last response: $confirmation\n";
        return [
            'success' => false,
            'message' => 'Timeout waiting for send confirmation',
            'details' => trim($confirmation) ?: 'No response from module'
        ];
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'details' => $e->getMessage()
        ];
    } finally {
        if ($handle && is_resource($handle)) {
            fclose($handle);
        }
    }
}

// ============================================
// HTTP ENDPOINT HANDLERS
// ============================================

/**
 * Handle HTTP requests
 */
function handle_request() {
    global $SIM800C_PORT, $SIM800C_BAUD;
    
    // Set JSON response header
    header('Content-Type: application/json');
    
    // Get request method and path
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remove query string and base path
    $path = strtok($path, '?');
    
    // Route requests
    if ($path === '/' || $path === '/sms_service.php') {
        // Service info endpoint
        echo json_encode([
            'service' => 'USB SIM800C v3 SMS Service',
            'version' => '1.0',
            'language' => 'PHP',
            'port' => $SIM800C_PORT,
            'baud' => $SIM800C_BAUD,
            'status' => 'Running',
            'endpoints' => [
                '/send' => 'POST - Send SMS',
                '/status' => 'GET - Check module status'
            ]
        ]);
        
    } elseif ($path === '/send' && $method === 'POST') {
        // Send SMS endpoint
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $phone = $data['phone'] ?? '';
        $message = $data['message'] ?? '';
        
        if (empty($phone) || empty($message)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing phone or message parameter'
            ]);
            return;
        }
        
        $result = send_sms_via_sim800c($phone, $message);
        echo json_encode($result);
        
    } elseif ($path === '/status' && $method === 'GET') {
        // Check SIM800C status endpoint
        global $SIM800C_PORT, $SIM800C_BAUD;
        
        try {
            $handle = @fopen($SIM800C_PORT . ':', 'r+b');
            
            if (!$handle) {
                echo json_encode([
                    'connected' => false,
                    'error' => 'Cannot open port: ' . $SIM800C_PORT
                ]);
                return;
            }
            
            stream_set_timeout($handle, 5);
            usleep(1000000); // 1 second
            
            // Test basic communication
            $resp = send_at_command($handle, 'AT', 1);
            if (strpos($resp, 'OK') === false) {
                fclose($handle);
                echo json_encode([
                    'connected' => false,
                    'error' => 'Module not responding'
                ]);
                return;
            }
            
            // Check network registration
            $resp = send_at_command($handle, 'AT+CREG?', 2);
            $registered = (strpos($resp, '+CREG: 0,1') !== false || strpos($resp, '+CREG: 0,5') !== false);
            
            // Check signal strength
            $sig_resp = send_at_command($handle, 'AT+CSQ', 1);
            $signal = 0;
            $signal_percentage = 0;
            if (preg_match('/\+CSQ:\s*(\d+)/', $sig_resp, $matches)) {
                $signal = intval($matches[1]);
                $signal_percentage = round(($signal / 31) * 100);
            }
            
            fclose($handle);
            
            echo json_encode([
                'connected' => true,
                'registered' => $registered,
                'signal' => $signal,
                'signal_percentage' => $signal_percentage,
                'port' => $SIM800C_PORT
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'connected' => false,
                'error' => $e->getMessage()
            ]);
        }
        
    } else {
        // 404 Not Found
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found'
        ]);
    }
}

// ============================================
// MAIN - START SERVICE
// ============================================

if (php_sapi_name() === 'cli') {
    // Running from command line
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "  USB SIM800C v3 SMS HTTP Service (PHP)\n";
    echo str_repeat("=", 60) . "\n";
    
    // Auto-detect COM port
    echo "\n📍 Detecting COM port...\n";
    global $SIM800C_PORT;
    $SIM800C_PORT = find_usb_port();
    
    echo "\n📌 Configuration:\n";
    echo "   Port: $SIM800C_PORT\n";
    echo "   Baud: $SIM800C_BAUD\n";
    
    // Test connection
    echo "\n🧪 Testing connection...\n";
    try {
        $handle = @fopen($SIM800C_PORT . ':', 'r+b');
        
        if (!$handle) {
            throw new Exception("Cannot open COM port: $SIM800C_PORT");
        }
        
        stream_set_timeout($handle, 5);
        usleep(2000000); // 2 seconds
        
        $resp = send_at_command($handle, 'AT', 1);
        
        if (strpos($resp, 'OK') !== false) {
            echo "  ✅ SIM800C module responding\n";
            
            // Check network registration
            $resp = send_at_command($handle, 'AT+CREG?', 2);
            
            if (strpos($resp, '+CREG: 0,1') !== false || strpos($resp, '+CREG: 0,5') !== false) {
                echo "  ✅ Registered on cellular network\n";
            } elseif (strpos($resp, '+CREG: 0,2') !== false) {
                echo "  ⏳ Searching for network (wait 30-60 seconds)\n";
            } else {
                echo "  ❌ NOT registered on network\n";
                echo "\n  📋 Troubleshooting checklist:\n";
                echo "     □ SIM card inserted correctly\n";
                echo "     □ SIM has active service (not expired)\n";
                echo "     □ SIM has load/credits\n";
                echo "     □ GSM antenna connected\n";
                echo "     □ Module has network signal\n";
                echo "     □ LED blinking = connected to network\n";
                echo "     □ Try SIM in phone first to verify it works\n";
            }
            
            // Check signal
            $sig_resp = send_at_command($handle, 'AT+CSQ', 1);
            if (preg_match('/\+CSQ:\s*(\d+)/', $sig_resp, $matches)) {
                $sig = intval($matches[1]);
                $percentage = round(($sig / 31) * 100);
                echo "  📶 Signal strength: $sig/31 ($percentage%)\n";
                
                if ($sig < 5) {
                    echo "  ⚠️ WARNING: Signal very weak!\n";
                } elseif ($sig < 10) {
                    echo "  ⚠️ Signal weak but usable\n";
                }
            }
        } else {
            echo "  ❌ Module not responding\n";
            echo "  Check: USB connection, COM port, module power\n";
        }
        
        fclose($handle);
        
    } catch (Exception $e) {
        echo "  ❌ Cannot open COM port: " . $e->getMessage() . "\n";
        echo "\n  📋 Check:\n";
        echo "     □ SIM800C USB connected to computer\n";
        echo "     □ Correct COM port (check Device Manager)\n";
        echo "     □ USB drivers installed\n";
        echo "     □ No other program using the port\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "  To use this service, access via web server:\n";
    echo "  http://localhost/news-website/sms_service.php\n";
    echo str_repeat("=", 60) . "\n";
    echo "\n🚀 Service ready!\n";
    echo "   (Press Ctrl+C to stop)\n\n";
    
} else {
    // Running from web server
    handle_request();
}
?>
