<?php
// sms_gateways.php - WITH ARDUINO GSM SUPPORT
require_once 'arduino_sms.php';

class SMSGateway {
    
    public static function send($phone, $message, $priority = false) {
        $gateway = SMS_GATEWAY;
        
        switch ($gateway) {
            case 'ARDUINO':
                return self::sendViaArduino($phone, $message);
            case 'IPROG':
                return self::sendViaIPROG($phone, $message);
            case 'SEMAPHORE':
                return self::sendViaSemaphore($phone, $message, $priority);
            default:
                return ['success' => false, 'error' => 'Invalid gateway'];
        }
    }
    
    // Arduino GSM Module
    private static function sendViaArduino($phone, $message) {
        $arduino = new ArduinoSMS();
        $result = $arduino->sendSMS($phone, $message);
        
        if ($result['success']) {
            $result['gateway'] = 'Arduino GSM';
        }
        
        return $result;
    }
    
    // IPROG Gateway
    private static function sendViaIPROG($phone, $message) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
            $phone = '63' . substr($phone, 1);
        }
        
        if (strlen($phone) !== 12) {
            return ['success' => false, 'error' => 'Invalid phone'];
        }
        
        $url = 'https://www.iprogsms.com/api/v1/sms_messages';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'api_token' => IPROG_API_TOKEN,
            'message' => $message,
            'phone_number' => $phone
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            if (isset($result['success']) || isset($result['id']) || !isset($result['error'])) {
                return ['success' => true, 'id' => $result['data']['id'] ?? 'iprog_' . time()];
            }
        }
        
        return ['success' => false, 'error' => 'IPROG API error'];
    }
    
    // Semaphore Gateway
    private static function sendViaSemaphore($phone, $message, $priority) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
            $phone = '0' . substr($phone, 2);
        }
        
        if (strlen($phone) !== 11) {
            return ['success' => false, 'error' => 'Invalid phone'];
        }
        
        $url = $priority 
            ? 'https://semaphore.co/api/v4/priority'
            : 'https://semaphore.co/api/v4/messages';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'apikey' => SEMAPHORE_API_KEY,
            'number' => $phone,
            'message' => $message,
            'sendername' => SEMAPHORE_SENDER
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data[0]['message_id'])) {
                return ['success' => true, 'id' => $data[0]['message_id']];
            }
        }
        
        return ['success' => false, 'error' => 'Semaphore error'];
    }
}
?>