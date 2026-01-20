<?php
// config.php - WITH WORKING SIM800C INTEGRATION
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'news_website');

// AI API Configuration
define('AI_API_ENDPOINT', 'https://llm.blackbox.ai/chat/completions');
define('AI_MODEL', 'openrouter/claude-sonnet-4');

// SMS Gateway Configuration
define('SMS_GATEWAY', 'IPROG'); // Default gateway: IPROG, ARDUINO, SEMAPHORE

// IPROG Configuration
define('IPROG_API_TOKEN', 'pt9095b945e881ed969ad0320452b389205de17be4');

// USB SIM800C Configuration (via PHP Service)
define('SIM800C_SERVICE_URL', 'http://localhost/news-website/sms_service.php'); // PHP service endpoint
define('SIM800C_ENABLED', true); // Set to false to disable SIM800C option
define('ARDUINO_COM_PORT', 'COM3');
// Semaphore Configuration
define('SEMAPHORE_API_KEY', '2d32629d4e3a03ae509473d3470ae2ba');
define('SEMAPHORE_SENDER', 'SEMAPHORE');

// NewsData.io Configuration
define('NEWSDATA_API_KEY', '');
define('NEWSDATA_COUNTRY', 'ph');
define('NEWSDATA_LANGUAGE', 'en');

// Admin Credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Database Connection
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Check Authentication
function checkAuth() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit();
    }
}

// Sanitize Input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format Date
function formatDate($date) {
    return date('F j, Y - g:i A', strtotime($date));
}
?>