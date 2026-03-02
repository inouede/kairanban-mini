<?php
/**
 * 共通設定ファイル
 * エラーハンドリング、セキュリティ設定、ユーティリティ関数
 */

// エラー表示設定（本番環境では必ずOFFにする）
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番環境では0
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../data/error.log');

// セッション設定
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict'
]);

// データディレクトリのパス
define('DATA_DIR', __DIR__ . '/../data/');

// JSONファイルのパス
define('USERS_FILE', DATA_DIR . 'users.json');
define('NOTICES_FILE', DATA_DIR . 'notices.json');
define('DEPARTMENTS_FILE', DATA_DIR . 'departments.json');
define('SETTINGS_FILE', DATA_DIR . 'settings.json');
define('AUDIT_LOGS_FILE', DATA_DIR . 'audit_logs.json');
define('SUBSCRIPTIONS_FILE', DATA_DIR . 'subscriptions.json');
define('PENDING_NOTIFICATIONS_FILE', DATA_DIR . 'pending_notifications.json');
define('USER_NOTIFICATION_SETTINGS_FILE', DATA_DIR . 'user_notification_settings.json');

// Composer autoload（minishlink/web-pushライブラリを使用）
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// VAPID鍵管理（自動生成）
require_once __DIR__ . '/vapid.php';
require_once __DIR__ . '/webpush.php';

// セキュリティヘッダー
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

/**
 * JSONファイルを安全に読み込む
 */
function readJsonFile($filepath, $default = []) {
    try {
        if (!file_exists($filepath)) {
            return $default;
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            error_log("Failed to read file: $filepath");
            return $default;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in $filepath: " . json_last_error_msg());
            return $default;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Exception in readJsonFile: " . $e->getMessage());
        return $default;
    }
}

/**
 * JSONファイルに安全に書き込む（ファイルロック使用）
 */
function writeJsonFile($filepath, $data) {
    try {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            error_log("JSON encode error: " . json_last_error_msg());
            return false;
        }
        
        // ディレクトリが存在しない場合は作成
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: $dir");
                return false;
            }
        }
        
        // 一時ファイルに書き込んでから置き換え（アトミック操作）
        $tempFile = $filepath . '.tmp';
        $result = file_put_contents($tempFile, $json, LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to temp file: $tempFile");
            return false;
        }
        
        if (!rename($tempFile, $filepath)) {
            error_log("Failed to rename temp file: $tempFile to $filepath");
            @unlink($tempFile);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Exception in writeJsonFile: " . $e->getMessage());
        return false;
    }
}

/**
 * レスポンスをJSON形式で返す
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンスを返す
 */
function sendError($message, $statusCode = 400) {
    sendJson(['error' => $message], $statusCode);
}

/**
 * 認証チェック
 */
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
        sendError('認証が必要です', 401);
    }
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'] ?? 'RESIDENT'
    ];
}

/**
 * 管理者権限チェック
 */
function checkAdmin() {
    $user = checkAuth();
    if ($user['role'] !== 'ADMIN') {
        sendError('管理者権限が必要です', 403);
    }
    return $user;
}

/**
 * 入力値のサニタイズ（最大長: デフォルト 1000 文字）
 */
function sanitize($value, $maxLength = 1000) {
    if (is_array($value)) {
        return array_map(function($v) use ($maxLength) {
            return sanitize($v, $maxLength);
        }, $value);
    }
    $trimmed = trim((string)$value);
    if (mb_strlen($trimmed, 'UTF-8') > $maxLength) {
        $trimmed = mb_substr($trimmed, 0, $maxLength, 'UTF-8');
    }
    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}

/**
 * POSTデータを取得
 */
function getPostData() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON data');
    }
    
    return $data;
}

/**
 * 監査ログを記録
 */
function addAuditLog($userId, $userName, $action, $details = '') {
    try {
        $logs = readJsonFile(AUDIT_LOGS_FILE, []);
        
        $newLog = [
            'id' => uniqid('log_', true),
            'userId' => $userId,
            'userName' => $userName,
            'action' => $action,
            'timestamp' => time() * 1000, // JavaScriptのタイムスタンプ形式
            'details' => $details
        ];
        
        array_unshift($logs, $newLog);
        
        // 最新1000件のみ保持
        $logs = array_slice($logs, 0, 1000);
        
        writeJsonFile(AUDIT_LOGS_FILE, $logs);
    } catch (Exception $e) {
        error_log("Failed to add audit log: " . $e->getMessage());
    }
}

/**
 * 初期データの作成
 */
function initializeData() {
    // 初期所属
    if (!file_exists(DEPARTMENTS_FILE)) {
        $initialDepartments = ['北班', '南班', '東班', '西班'];
        writeJsonFile(DEPARTMENTS_FILE, $initialDepartments);
    }
    
    // 初期ユーザー
    if (!file_exists(USERS_FILE)) {
        $initialUsers = [
            [
                'id' => 'admin1',
                'name' => '町内 会長',
                'email' => 'admin@example.com',
                'role' => 'ADMIN',
                'department' => '北班',
                'passwordHash' => password_hash('admin', PASSWORD_DEFAULT)
            ],
            [
                'id' => 'user1',
                'name' => '田中 太郎',
                'email' => 'tanaka@example.com',
                'role' => 'RESIDENT',
                'department' => '北班',
                'passwordHash' => password_hash('user', PASSWORD_DEFAULT)
            ],
            [
                'id' => 'user2',
                'name' => '佐藤 花子',
                'email' => 'sato@example.com',
                'role' => 'RESIDENT',
                'department' => '南班',
                'passwordHash' => password_hash('user', PASSWORD_DEFAULT)
            ]
        ];
        writeJsonFile(USERS_FILE, $initialUsers);
    }
    
    // 初期設定
    if (!file_exists(SETTINGS_FILE)) {
        $initialSettings = [
            'primaryColor' => '#3b82f6',
            'appName' => 'スマート回覧板',
            'allowComments' => true,
            'allowReactions' => true
        ];
        writeJsonFile(SETTINGS_FILE, $initialSettings);
    }
    
    // 初期回覧板（空配列）
    if (!file_exists(NOTICES_FILE)) {
        writeJsonFile(NOTICES_FILE, []);
    }
    
    // 初期監査ログ（空配列）
    if (!file_exists(AUDIT_LOGS_FILE)) {
        writeJsonFile(AUDIT_LOGS_FILE, []);
    }
}

// データの初期化
initializeData();
