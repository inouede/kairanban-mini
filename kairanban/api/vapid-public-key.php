<?php
/**
 * VAPID公開鍵取得API
 * クライアント側でVAPID公開鍵を取得するためのエンドポイント
 */
require_once __DIR__ . '/vapid.php';

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    // VAPID鍵を取得（自動生成）
    $vapidKeys = getVapidKeys();
    
    if ($vapidKeys && isset($vapidKeys['publicKey'])) {
        sendJson([
            'success' => true,
            'publicKey' => $vapidKeys['publicKey']
        ]);
    } else {
        sendError('VAPID公開鍵の取得に失敗しました', 500);
    }
} catch (Exception $e) {
    error_log("VAPID公開鍵取得エラー: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}
