<?php
/**
 * subscriptions.json 修復ツール
 * オブジェクト形式を配列形式に変換
 */

require_once __DIR__ . '/config.php';

checkAdmin();

echo "=== subscriptions.json 修復ツール ===\n\n";

// 現在のデータを読み込み
$rawContent = file_get_contents(SUBSCRIPTIONS_FILE);
$data = json_decode($rawContent, true);

echo "現在のデータ構造:\n";
echo "タイプ: " . (is_array($data) ? "配列" : gettype($data)) . "\n";

// 配列かどうかチェック
if (isset($data[0]) || empty($data)) {
    echo "✅ 既に正しい配列形式です。修復不要。\n";
    exit;
}

// オブジェクト形式の場合、配列に変換
echo "⚠️  オブジェクト形式を検出。配列に変換します...\n\n";

$subscriptions = [];
foreach ($data as $key => $value) {
    if (is_array($value) && isset($value['userId'])) {
        $subscriptions[] = $value;
        echo "変換: キー '$key' → 配列インデックス " . (count($subscriptions) - 1) . "\n";
        echo "  ユーザー名: {$value['userName']}\n";
        echo "  ユーザーID: {$value['userId']}\n";
    }
}

echo "\n";

// バックアップ作成
$backupFile = SUBSCRIPTIONS_FILE . '.backup.' . date('YmdHis');
copy(SUBSCRIPTIONS_FILE, $backupFile);
echo "📦 バックアップ作成: " . basename($backupFile) . "\n\n";

// 修復したデータを保存
if (writeJsonFile(SUBSCRIPTIONS_FILE, $subscriptions)) {
    echo "✅ 修復完了！\n";
    echo "変換後の購読数: " . count($subscriptions) . "件\n\n";
    
    echo "=== 修復後のデータ ===\n";
    foreach ($subscriptions as $index => $sub) {
        echo "---【購読 #" . ($index + 1) . "】---\n";
        echo "ユーザー名: {$sub['userName']}\n";
        echo "ユーザーID: {$sub['userId']}\n";
        echo "\n";
    }
} else {
    echo "❌ 保存に失敗しました\n";
}

echo "=== 修復完了 ===\n";
