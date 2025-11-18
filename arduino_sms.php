<?php
// arduino_sms.php - PHP TO ARDUINO COMMUNICATION

class ArduinoSMS {
    private $port;
    private $baudRate = 9600;
    private $timeout = 10;
    
    // Detect Arduino COM port (Windows)
    public static function detectPort() {
        // Try common Windows COM ports
        for ($i = 1; $i <= 20; $i++) {
            $port = "COM$i";
            if (file_exists("\\\\.\\$port")) {
                return $port;
            }
        }
        return null;
    }
    
    // Open serial connection
    public function connect($comPort = null) {
        if ($comPort === null) {
            $comPort = self::detectPort();
        }
        
        if ($comPort === null) {
            return ['success' => false, 'error' => 'Arduino not found. Check USB connection.'];
        }
        
        // Windows: Use mode command to configure COM port
        exec("mode $comPort BAUD=$this->baudRate PARITY=N DATA=8 STOP=1", $output, $result);
        
        $this->port = $comPort;
        return ['success' => true, 'port' => $comPort];
    }
    
    // Send SMS via Arduino
    public function sendSMS($phone, $message) {
        if (!$this->port) {
            $this->connect();
        }
        
        // Format phone number (639XXXXXXXXX for GSM)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
            $phone = '63' . substr($phone, 1);
        }
        
        // Validate
        if (strlen($phone) !== 12 || substr($phone, 0, 2) !== '63') {
            return ['success' => false, 'error' => 'Invalid phone: ' . $phone];
        }
        
        // Create command: SEND|phone|message
        $command = "SEND|$phone|$message\n";
        
        // Send to Arduino via COM port
        $fp = @fopen("\\\\.\\{$this->port}", "w+");
        
        if (!$fp) {
            return ['success' => false, 'error' => 'Cannot open COM port. Check Arduino connection.'];
        }
        
        fwrite($fp, $command);
        
        // Wait for response
        $startTime = time();
        $response = '';
        
        while ((time() - $startTime) < $this->timeout) {
            if (feof($fp)) break;
            $line = fgets($fp);
            if ($line) {
                $response .= $line;
                if (strpos($line, 'SUCCESS') !== false) {
                    fclose($fp);
                    return [
                        'success' => true,
                        'id' => 'arduino_' . time(),
                        'response' => trim($response)
                    ];
                }
                if (strpos($line, 'ERROR') !== false) {
                    fclose($fp);
                    return ['success' => false, 'error' => trim($response)];
                }
            }
            usleep(100000); // 0.1 second
        }
        
        fclose($fp);
        return ['success' => true, 'id' => 'arduino_timeout'];
    }
    
    // Check module status
    public function checkStatus() {
        if (!$this->port) {
            $this->connect();
        }
        
        $fp = @fopen("\\\\.\\{$this->port}", "w+");
        if (!$fp) {
            return ['connected' => false, 'error' => 'Cannot open COM port'];
        }
        
        fwrite($fp, "STATUS\n");
        sleep(2);
        
        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp);
            if (strpos($response, 'STATUS') !== false) break;
        }
        
        fclose($fp);
        
        return [
            'connected' => strpos($response, 'OK') !== false,
            'response' => trim($response)
        ];
    }
}
?>