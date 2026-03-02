<?php
/**
 * 購読状況の詳細確認ツール
 * ユーザーとEndpointの対応を確認
 */

require_once __DIR__ . '/config.php';

checkAdmin();

echo "=== 購読状況の詳細確認 ===\n\n";

// ユーザー一覧を取得
$users = readJsonFile(USERS_FILE, []);
echo "【登録ユーザー一覧】\n";
foreach ($users as $user) {
    echo "---\n";
    echo "ユーザー名: {$user['name']}\n";
    echo "ユーザーID: {$user['id']}\n";
    echo "メールアドレス: {$user['email']}\n";
}

echo "\n\n【購読情報一覧】\n";

// 購読一覧を取得
$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);

if (empty($subscriptions)) {
    echo "⚠️  購読が1件も登録されていません！\n";
} else {
    echo "購読数: " . count($subscriptions) . "件\n\n";
    
    foreach ($subscriptions as $index => $sub) {
        echo "---【購読 #" . ($index + 1) . "】---\n";
        echo "ユーザー名: {$sub['userName']}\n";
        echo "ユーザーID: {$sub['userId']}\n";
        
        // ユーザーが存在するかチェック
        $userExists = false;
        foreach ($users as $user) {
            if ($user['id'] === $sub['userId']) {
                $userExists = true;
                echo "ユーザー存在: ✅ 正常\n";
                break;
            }
        }
        
        if (!$userExists) {
            echo "ユーザー存在: ❌ 孤立データ（このユーザーは削除されています）\n";
        }
        
        $endpoint = $sub['subscription']['endpoint'];
        echo "Endpoint末尾: ..." . substr($endpoint, -30) . "\n";
        echo "登録日時: " . date('Y-m-d H:i:s', $sub['createdAt']) . "\n\n";
    }
    
    // 重複Endpoint検出
    echo "\n【重複Endpointチェック】\n";
    $endpointMap = [];
    foreach ($subscriptions as $sub) {
        $endpoint = $sub['subscription']['endpoint'];
        $shortEndpoint = substr($endpoint, -30);
        
        if (!isset($endpointMap[$shortEndpoint])) {
            $endpointMap[$shortEndpoint] = [];
        }
        $endpointMap[$shortEndpoint][] = $sub['userName'] . " (" . $sub['userId'] . ")";
    }
    
    $duplicateFound = false;
    foreach ($endpointMap as $endpoint => $users) {
        if (count($users) > 1) {
            $duplicateFound = true;
            echo "⚠️  重複Endpoint検出: ...{$endpoint}\n";
            echo "   使用ユーザー:\n";
            foreach ($users as $user) {
                echo "   - {$user}\n";
            }
            echo "\n";
        }
    }
    
    if (!$duplicateFound) {
        echo "✅ 重複Endpointはありません\n";
    }
}

echo "\n=== 確認完了 ===\n";
