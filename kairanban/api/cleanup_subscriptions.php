<?php
/**
 * 購読クリーンアップスクリプト
 * 
 * 特定ユーザーの古い購読をすべて削除
 * 
 * 使用方法:
 * 1. このファイルを /lessq5/kairanban/api/cleanup_subscriptions.php として保存
 * 2. ブラウザでアクセス: https://mee-q.com/lessq5/kairanban/api/cleanup_subscriptions.php?user_id=user_6982c804078122.75138613
 * 3. 赤松Androidで再登録
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
checkAdmin();

// ログ出力
function logMessage($message) {
    error_log('[Cleanup] ' . $message);
    echo $message . "\n";
}

// データベース接続
require_once __DIR__ . '/db.php';

// GETパラメータからユーザーIDを取得
$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo "使用方法: ?user_id=ユーザーID\n";
    echo "例: ?user_id=user_6982c804078122.75138613\n";
    exit;
}

logMessage("購読クリーンアップ開始: " . $userId);

try {
    // 現在の購読を確認
    $stmt = $pdo->prepare("SELECT id, endpoint, created_at FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("現在の購読数: " . count($subscriptions));
    
    foreach ($subscriptions as $sub) {
        logMessage("購読ID: " . $sub['id'] . ", Endpoint: " . substr($sub['endpoint'], 0, 50) . "..., 作成日: " . $sub['created_at']);
    }
    
    // すべての購読を削除
    $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
    $result = $stmt->execute([$userId]);
    $deletedCount = $stmt->rowCount();
    
    logMessage("✅ 削除完了: " . $deletedCount . "件");
    
    echo "\n---\n";
    echo "購読クリーンアップ完了\n";
    echo "削除した購読数: " . $deletedCount . "件\n";
    echo "\n次のステップ:\n";
    echo "1. 赤松Androidでログアウト\n";
    echo "2. 防災lessQを開く\n";
    echo "3. 通知許可\n";
    echo "4. 回覧板にログイン\n";
    echo "5. 購読確認: check_subscription_details.php\n";
    
} catch (Exception $e) {
    logMessage("❌ エラー: " . $e->getMessage());
    echo "エラーが発生しました: " . $e->getMessage();
}
