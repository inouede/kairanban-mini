<?php
/**
 * Web Push送信ライブラリ（minishlink/web-push使用）
 */
require_once __DIR__ . '/vapid.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Web Push通知を送信
 */
function sendWebPush($subscription, $payload) {
    try {
        // vendorがロードされているか確認
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            error_log("[WebPush] エラー: minishlink/web-pushライブラリがロードされていません");
            error_log("[WebPush] composer install --no-dev を実行してください");
            return false;
        }
        
        error_log("[WebPush] 送信開始");
        
        // VAPID鍵を取得
        $vapidKeys = getVapidKeys();
        if (!$vapidKeys) {
            error_log("[WebPush] エラー: VAPID鍵の取得に失敗");
            return false;
        }
        
        error_log("[WebPush] VAPID鍵取得成功");
        
        // サブスクリプション情報を解析
        if (is_string($subscription)) {
            $subscription = json_decode($subscription, true);
        }
        
        if (!isset($subscription['endpoint']) || 
            !isset($subscription['keys']['p256dh']) || 
            !isset($subscription['keys']['auth'])) {
            error_log("[WebPush] エラー: サブスクリプション情報が不正です");
            return false;
        }
        
        // WebPushインスタンスを作成
        $auth = [
            'VAPID' => [
                'subject' => $vapidKeys['subject'],
                'publicKey' => $vapidKeys['publicKey'],
                'privateKey' => $vapidKeys['privateKey'],
            ]
        ];
        
        $webPush = new WebPush($auth);
        
        // Subscriptionオブジェクトを作成
        $pushSubscription = Subscription::create([
            'endpoint' => $subscription['endpoint'],
            'keys' => [
                'p256dh' => $subscription['keys']['p256dh'],
                'auth' => $subscription['keys']['auth']
            ]
        ]);
        
        // ペイロードをJSON文字列に変換
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        
        error_log("[WebPush] Endpoint: " . $subscription['endpoint']);
        error_log("[WebPush] Payload: " . $payload);
        
        // 通知を送信
        $report = $webPush->sendOneNotification($pushSubscription, $payload);
        
        // 結果を確認
        if ($report->isSuccess()) {
            error_log("[WebPush] ✅ 送信成功");
            return true;
        } else {
            error_log("[WebPush] ❌ 送信失敗");
            error_log("[WebPush] 理由: " . $report->getReason());
            
            if ($report->getResponse()) {
                error_log("[WebPush] HTTPステータス: " . $report->getResponse()->getStatusCode());
            }
            
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[WebPush] 例外: " . $e->getMessage());
        error_log("[WebPush] トレース: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * 複数の購読に一括送信
 */
function sendWebPushBatch($subscriptions, $payload) {
    try {
        // vendorがロードされているか確認
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            error_log("[WebPush] エラー: minishlink/web-pushライブラリがロードされていません");
            return ['success' => 0, 'failure' => count($subscriptions)];
        }
        
        // VAPID鍵を取得
        $vapidKeys = getVapidKeys();
        if (!$vapidKeys) {
            error_log("[WebPush] エラー: VAPID鍵の取得に失敗");
            return ['success' => 0, 'failure' => count($subscriptions)];
        }
        
        // WebPushインスタンスを作成
        $auth = [
            'VAPID' => [
                'subject' => $vapidKeys['subject'],
                'publicKey' => $vapidKeys['publicKey'],
                'privateKey' => $vapidKeys['privateKey'],
            ]
        ];
        
        $webPush = new WebPush($auth);
        
        // ペイロードをJSON文字列に変換
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        
        // 各購読に通知をキュー
        foreach ($subscriptions as $sub) {
            if (is_string($sub)) {
                $sub = json_decode($sub, true);
            }
            
            if (!isset($sub['endpoint']) || 
                !isset($sub['keys']['p256dh']) || 
                !isset($sub['keys']['auth'])) {
                continue;
            }
            
            $pushSubscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['keys']['p256dh'],
                    'auth' => $sub['keys']['auth']
                ]
            ]);
            
            $webPush->queueNotification($pushSubscription, $payload);
        }
        
        // 一括送信
        $reports = $webPush->flush();
        
        // 結果を集計
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                $successCount++;
            } else {
                $failureCount++;
                error_log("[WebPush] 送信失敗: " . $report->getReason());
            }
        }
        
        error_log("[WebPush] 一括送信完了: 成功 {$successCount}, 失敗 {$failureCount}");
        
        return [
            'success' => $successCount,
            'failure' => $failureCount
        ];
        
    } catch (Exception $e) {
        error_log("[WebPush] 一括送信エラー: " . $e->getMessage());
        return ['success' => 0, 'failure' => count($subscriptions)];
    }
}
