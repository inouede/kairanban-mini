<?php
/**
 * 期限切れ購読と重複Endpointの自動削除ツール
 * 410 Goneエラーを防ぐ
 */

require_once __DIR__ . '/config.php';

checkAdmin();

echo "=== 購読クリーンアップツール ===\n\n";

// ユーザー一覧を取得
$users = readJsonFile(USERS_FILE, []);
$userIds = array_column($users, 'id');

// 購読一覧を取得
$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
$beforeCount = count($subscriptions);

echo "クリーンアップ前の購読数: {$beforeCount}件\n\n";

// 削除対象を特定
$toDelete = [];
$endpointMap = [];

foreach ($subscriptions as $index => $sub) {
    $endpoint = $sub['subscription']['endpoint'];
    $shortEndpoint = substr($endpoint, -30);
    
    // 1. 孤立した購読（ユーザーが存在しない）
    if (!in_array($sub['userId'], $userIds)) {
        $toDelete[$index] = "孤立購読（ユーザー削除済み）: {$sub['userName']}";
        continue;
    }
    
    // 2. 重複Endpoint検出
    if (isset($endpointMap[$shortEndpoint])) {
        // 既に同じEndpointが存在する
        $existing = $endpointMap[$shortEndpoint];
        
        // 古い方を削除（createdAtが小さい方）
        if ($sub['createdAt'] < $existing['createdAt']) {
            $toDelete[$index] = "重複Endpoint（古い方）: {$sub['userName']} vs {$existing['userName']}";
        } else {
            $toDelete[$existing['index']] = "重複Endpoint（古い方）: {$existing['userName']} vs {$sub['userName']}";
            $endpointMap[$shortEndpoint] = [
                'index' => $index,
                'userName' => $sub['userName'],
                'createdAt' => $sub['createdAt']
            ];
        }
    } else {
        $endpointMap[$shortEndpoint] = [
            'index' => $index,
            'userName' => $sub['userName'],
            'createdAt' => $sub['createdAt']
        ];
    }
}

// 3. 期限切れEndpoint（手動で追加）
// 410 Goneエラーが出たEndpointを削除
$expiredEndpoints = [
    'caDoWHSkoYo:APA91bF__UyFmu4tdKjsfrQ2Cu8_oaHPDhcshizyd7OCqJ28D3WHGPKUrSMVt5MzZ3FbE6QCfkfLd4FmU1YISDMbGoBL7LBFBU3Hy9bDWYPbiSgyzC8djyxIcWb3BADcL9qpLXWHyhfR',
    'eiaitmGs8PI:APA91bGw19GoEqSefrQTK3yG3djozze-nDwTTD2_2iH9XJVmNtJydzQs_e2TFjVnVc6Si1pNEejkMHKvlXQNHXs8_MSBmKO1YulsZBL5t82fnAHmMt40WE6k3192Njm26LuHExzdlsjx'
];

foreach ($subscriptions as $index => $sub) {
    $endpoint = $sub['subscription']['endpoint'];
    foreach ($expiredEndpoints as $expiredEndpoint) {
        if (strpos($endpoint, $expiredEndpoint) !== false) {
            if (!isset($toDelete[$index])) {
                $toDelete[$index] = "期限切れEndpoint（410 Gone）: {$sub['userName']}";
            }
        }
    }
}

// 削除実行
if (empty($toDelete)) {
    echo "✅ 削除対象の購読はありません。すべて正常です。\n";
} else {
    echo "⚠️  削除対象の購読を発見:\n\n";
    foreach ($toDelete as $index => $reason) {
        echo "---【削除対象 #{$index}】---\n";
        echo "理由: {$reason}\n";
        echo "ユーザー名: {$subscriptions[$index]['userName']}\n";
        echo "ユーザーID: {$subscriptions[$index]['userId']}\n";
        echo "Endpoint末尾: ..." . substr($subscriptions[$index]['subscription']['endpoint'], -30) . "\n\n";
    }
    
    // 削除実行
    $cleanedSubscriptions = [];
    foreach ($subscriptions as $index => $sub) {
        if (!isset($toDelete[$index])) {
            $cleanedSubscriptions[] = $sub;
        }
    }
    
    writeJsonFile(SUBSCRIPTIONS_FILE, $cleanedSubscriptions);
    
    $deletedCount = $beforeCount - count($cleanedSubscriptions);
    echo "✅ {$deletedCount}件の問題のある購読を削除しました\n";
}

$afterCount = count($cleanedSubscriptions ?? $subscriptions);
echo "\nクリーンアップ後の購読数: {$afterCount}件\n\n";

echo "=== 現在の正常な購読一覧 ===\n\n";
$finalSubscriptions = $cleanedSubscriptions ?? $subscriptions;
foreach ($finalSubscriptions as $index => $sub) {
    echo "---【購読 #" . ($index + 1) . "】---\n";
    echo "ユーザー名: {$sub['userName']}\n";
    echo "ユーザーID: {$sub['userId']}\n";
    echo "登録日時: " . date('Y-m-d H:i:s', $sub['createdAt']) . "\n";
    $endpoint = $sub['subscription']['endpoint'];
    echo "Endpoint末尾: ..." . substr($endpoint, -30) . "\n\n";
}

echo "=== クリーンアップ完了 ===\n";
