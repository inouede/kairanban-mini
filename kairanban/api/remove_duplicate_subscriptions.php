<?php
/**
 * 同一ユーザーの重複購読を削除
 * 最新の購読のみを残す
 */

require_once __DIR__ . '/config.php';

echo "=== 重複購読削除ツール ===\n\n";

// 購読一覧を取得
$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
$beforeCount = count($subscriptions);

echo "削除前の購読数: {$beforeCount}件\n\n";

// ユーザーIDごとにグループ化
$userSubscriptions = [];
foreach ($subscriptions as $index => $sub) {
    $userId = $sub['userId'];
    
    if (!isset($userSubscriptions[$userId])) {
        $userSubscriptions[$userId] = [];
    }
    
    $userSubscriptions[$userId][] = [
        'index' => $index,
        'subscription' => $sub
    ];
}

// 重複を検出
$duplicates = [];
foreach ($userSubscriptions as $userId => $subs) {
    if (count($subs) > 1) {
        $duplicates[$userId] = $subs;
    }
}

if (empty($duplicates)) {
    echo "✅ 重複購読はありません\n";
} else {
    echo "⚠️  重複購読を発見:\n\n";
    
    $toDelete = [];
    
    foreach ($duplicates as $userId => $subs) {
        echo "---【ユーザー: {$userId}】---\n";
        echo "ユーザー名: {$subs[0]['subscription']['userName']}\n";
        echo "購読数: " . count($subs) . "件\n\n";
        
        // createdAtでソート（新しい順）
        usort($subs, function($a, $b) {
            return $b['subscription']['createdAt'] - $a['subscription']['createdAt'];
        });
        
        // 最新のものを残し、それ以外を削除対象に
        foreach ($subs as $i => $sub) {
            if ($i === 0) {
                echo "✅ 残す（最新）:\n";
            } else {
                echo "❌ 削除（古い）:\n";
                $toDelete[] = $sub['index'];
            }
            
            echo "  登録日時: " . date('Y-m-d H:i:s', $sub['subscription']['createdAt']) . "\n";
            echo "  Endpoint末尾: ..." . substr($sub['subscription']['subscription']['endpoint'], -30) . "\n\n";
        }
    }
    
    // 削除実行
    $cleanedSubscriptions = [];
    foreach ($subscriptions as $index => $sub) {
        if (!in_array($index, $toDelete)) {
            $cleanedSubscriptions[] = $sub;
        }
    }
    
    writeJsonFile(SUBSCRIPTIONS_FILE, $cleanedSubscriptions);
    
    $deletedCount = count($toDelete);
    echo "✅ {$deletedCount}件の重複購読を削除しました\n";
}

$afterCount = count($cleanedSubscriptions ?? $subscriptions);
echo "\n削除後の購読数: {$afterCount}件\n\n";

echo "=== 現在の購読一覧 ===\n\n";
$finalSubscriptions = $cleanedSubscriptions ?? $subscriptions;
foreach ($finalSubscriptions as $index => $sub) {
    echo "---【購読 #" . ($index + 1) . "】---\n";
    echo "ユーザー名: {$sub['userName']}\n";
    echo "ユーザーID: {$sub['userId']}\n";
    echo "登録日時: " . date('Y-m-d H:i:s', $sub['createdAt']) . "\n";
    $endpoint = $sub['subscription']['endpoint'];
    echo "Endpoint末尾: ..." . substr($endpoint, -30) . "\n\n";
}

echo "=== 完了 ===\n";
