<?php
/**
 * 孤立した購読情報のクリーンアップツール
 * users.jsonに存在しないユーザーのsubscriptionsを削除
 */

require_once __DIR__ . '/config.php';

checkAdmin();

echo "=== 購読クリーンアップツール（孤立データ削除） ===\n\n";

// ユーザー一覧を取得
$users = readJsonFile(USERS_FILE, []);
$userIds = array_column($users, 'id');

echo "登録ユーザー数: " . count($userIds) . "人\n";
echo "ユーザーID一覧:\n";
foreach ($users as $user) {
    echo "  - {$user['id']} ({$user['name']})\n";
}
echo "\n";

// 購読一覧を取得
$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
$beforeCount = count($subscriptions);

echo "購読数（クリーンアップ前）: {$beforeCount}件\n\n";

// 存在しないユーザーの購読を特定
$orphanedSubscriptions = [];
foreach ($subscriptions as $index => $sub) {
    if (!in_array($sub['userId'], $userIds)) {
        $orphanedSubscriptions[] = [
            'index' => $index,
            'userId' => $sub['userId'],
            'userName' => $sub['userName']
        ];
    }
}

if (empty($orphanedSubscriptions)) {
    echo "✅ 孤立した購読はありません。すべて正常です。\n";
} else {
    echo "⚠️  孤立した購読を発見:\n\n";
    foreach ($orphanedSubscriptions as $orphan) {
        echo "---【孤立購読】---\n";
        echo "ユーザーID: {$orphan['userId']}\n";
        echo "ユーザー名: {$orphan['userName']}\n";
        echo "→ このユーザーはusers.jsonに存在しません\n\n";
    }
    
    // 削除実行（配列として保持）
    $cleanedSubscriptions = [];
    foreach ($subscriptions as $sub) {
        if (in_array($sub['userId'], $userIds)) {
            $cleanedSubscriptions[] = $sub; // 明示的に配列に追加
        }
    }
    
    writeJsonFile(SUBSCRIPTIONS_FILE, $cleanedSubscriptions);
    
    $deletedCount = $beforeCount - count($cleanedSubscriptions);
    echo "✅ {$deletedCount}件の孤立した購読を削除しました\n";
    
    $subscriptions = $cleanedSubscriptions;
}

$afterCount = count($subscriptions);
echo "\n購読数（クリーンアップ後）: {$afterCount}件\n\n";

echo "=== 現在の正常な購読一覧 ===\n\n";
foreach ($subscriptions as $index => $sub) {
    echo "---【購読 #" . ($index + 1) . "】---\n";
    echo "ユーザー名: {$sub['userName']}\n";
    echo "ユーザーID: {$sub['userId']}\n";
    echo "登録日時: " . date('Y-m-d H:i:s', $sub['createdAt']) . "\n";
    $endpoint = $sub['subscription']['endpoint'];
    echo "Endpoint末尾: ..." . substr($endpoint, -30) . "\n\n";
}

echo "=== クリーンアップ完了 ===\n";
