<?php
// config.php - WITH ARDUINO GSM OPTION
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'news_website');

define('AI_API_ENDPOINT', 'https://llm.blackbox.ai/chat/completions');
define('AI_MODEL', 'openrouter/claude-sonnet-4');

// SMS Gateway Selection
define('SMS_GATEWAY', 'IPROG'); // Options: IPROG, ARDUINO, SEMAPHORE

// Arduino GSM Configuration
define('ARDUINO_COM_PORT', 'COM3'); // Change to your Arduino COM port (COM3, COM4, etc.)
define('ARDUINO_BAUD_RATE', 9600);

// IPROG Gateway
define('IPROG_API_TOKEN', 'pt9095b945e881ed969ad0320452b389205de17be4');

// Semaphore Gateway
define('SEMAPHORE_API_KEY', '2d32629d4e3a03ae509473d3470ae2ba');
define('SEMAPHORE_SENDER', 'SEMAPHORE');

// NewsData.io API
define('NEWSDATA_API_KEY', 'YOUR_NEWSDATA_API_KEY');
define('NEWSDATA_COUNTRY', 'ph');
define('NEWSDATA_LANGUAGE', 'en');

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

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

function checkAuth() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit();
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('F j, Y - g:i A', strtotime($date));
}
?>