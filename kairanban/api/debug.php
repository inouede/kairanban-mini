<?php
/**
 * エラーログ確認API
 * 管理者のみアクセス可能
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

try {
    // 管理者権限チェック
    $user = checkAdmin();
    
    switch ($action) {
        case 'view':
            handleViewLogs();
            break;
            
        case 'clear':
            handleClearLogs();
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in debug.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * エラーログを表示
 */
function handleViewLogs() {
    $logFile = DATA_DIR . 'error.log';
    
    if (!file_exists($logFile)) {
        sendJson(['logs' => '（エラーログはまだありません）']);
    }
    
    $logs = file_get_contents($logFile);
    
    // 最新100行のみ取得
    $lines = explode("\n", $logs);
    $lines = array_slice($lines, -100);
    $logs = implode("\n", $lines);
    
    sendJson(['logs' => $logs]);
}

/**
 * エラーログをクリア
 */
function handleClearLogs() {
    $logFile = DATA_DIR . 'error.log';
    
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    
    sendJson(['success' => true, 'message' => 'エラーログをクリアしました']);
}
