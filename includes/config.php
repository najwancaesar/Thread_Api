<?php
// Enable detailed errors for debugging (remove or disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ------------------------------------------------------------
// App base path helpers (so links work from / and /pages/*)
// ------------------------------------------------------------

function getAppBasePath() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    // If currently running from /pages/* or /api/*, base is their parent.
    if (preg_match('#^(.*)/(pages|api)/[^/]+$#', $script, $m)) {
        $base = $m[1];
        $base = ($base === '') ? '/' : rtrim($base, '/') . '/';
        return $base;
    }

    $dir = dirname($script);
    if ($dir === '\\' || $dir === '.') {
        $dir = '/';
    }
    return rtrim(str_replace('\\', '/', $dir), '/') . '/';
}

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', getAppBasePath());
}

function appUrl($path = '') {
    return APP_BASE_PATH . ltrim($path, '/');
}

function apiUrlPath($endpoint = '') {
    return APP_BASE_PATH . 'api/' . ltrim($endpoint, '/');
}

// Database configuration (load from api/config.php to keep a single source)
$__api_db_cfg = require __DIR__ . '/../api/config.php';
define('DB_HOST', $__api_db_cfg['host'] ?? 'localhost');
define('DB_NAME', $__api_db_cfg['dbname'] ?? 'kmiprodm_aioa_tread');
define('DB_USER', $__api_db_cfg['user'] ?? 'root'); // ganti dengan user database jika perlu
define('DB_PASS', $__api_db_cfg['pass'] ?? ''); // ganti dengan password database jika perlu

// Site configuration
define('SITE_NAME', 'Thread Extruder Monitoring System');
define('SITE_URL', 'http://localhost/tread-extruder/'); // ganti dengan URL Anda

// API configuration
define('API_BASE_URL', SITE_URL . 'api/');

// Machine configuration
define('MACHINE_ID', 'EXTRUDER_01');
define('MACHINE_NAME', 'Thread Extruder Tread/Sidewall');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database connection function
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Helper functions
function now() {
    return date('Y-m-d H:i:s');
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i:s', strtotime($datetime));
}

function getShift() {
    $hour = date('H');
    if ($hour >= 6 && $hour < 14) return 'A';
    if ($hour >= 14 && $hour < 22) return 'B';
    return 'C';
}

function getMachineStatus() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT status_bit 
        FROM machine_events 
        WHERE metric_code = 'STATUS_MON' 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result ? (int)$result['status_bit'] : 0;
}
?>