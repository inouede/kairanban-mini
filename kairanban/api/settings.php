<?php
/**
 * システム設定API
 * システム設定の取得と更新
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleGet();
            break;
            
        case 'update':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleUpdate();
            break;
            
        case 'logs':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleGetLogs();
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in settings.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * システム設定を取得
 */
function handleGet() {
    checkAuth();
    $settings = readJsonFile(SETTINGS_FILE, [
        'primaryColor' => '#3b82f6',
        'appName' => 'スマート回覧板',
        'allowComments' => true,
        'allowReactions' => true
    ]);
    sendJson(['settings' => $settings]);
}

/**
 * システム設定を更新
 */
function handleUpdate() {
    checkAdmin();
    $data = getPostData();
    
    $settings = readJsonFile(SETTINGS_FILE, []);
    
    // 更新可能なフィールドのみ更新
    if (isset($data['primaryColor'])) {
        // CSS カラーコードのホワイトリスト検証（#RRGGBB または #RGB）
        if (!preg_match('/^#[0-9A-Fa-f]{3}(?:[0-9A-Fa-f]{3})?$/', $data['primaryColor'])) {
            sendError('primaryColor は #RRGGBB 形式で指定してください');
        }
        $settings['primaryColor'] = $data['primaryColor'];
    }
    if (isset($data['appName'])) {
        $settings['appName'] = sanitize($data['appName']);
    }
    if (isset($data['allowComments'])) {
        $settings['allowComments'] = (bool)$data['allowComments'];
    }
    if (isset($data['allowReactions'])) {
        $settings['allowReactions'] = (bool)$data['allowReactions'];
    }
    
    if (!writeJsonFile(SETTINGS_FILE, $settings)) {
        sendError('設定の保存に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'UPDATE_SETTINGS', 'システム設定を更新');
    
    sendJson(['success' => true]);
}

/**
 * 監査ログを取得（最新200件）
 */
function handleGetLogs() {
    checkAdmin();
    $logs = readJsonFile(AUDIT_LOGS_FILE, []);
    // 最新200件のみ返す（大量データによるメモリ枯渇防止）
    $logs = array_slice($logs, -200);
    sendJson(['logs' => $logs]);
}
