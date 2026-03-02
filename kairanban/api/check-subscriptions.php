<?php
/**
 * 購読状況を確認
 */

require_once __DIR__ . '/config.php';

echo "=== 購読状況確認ツール ===\n\n";

$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);

echo "総購読数: " . count($subscriptions) . "件\n\n";

foreach ($subscriptions as $index => $sub) {
    echo "---【購読 #" . ($index + 1) . "】---\n";
    echo "ユーザー名: " . $sub['userName'] . "\n";
    echo "ユーザーID: " . $sub['userId'] . "\n";
    echo "登録日時: " . date('Y-m-d H:i:s', $sub['createdAt']) . "\n";
    echo "Endpoint: " . $sub['subscription']['endpoint'] . "\n";
    echo "Endpoint末尾: ..." . substr($sub['subscription']['endpoint'], -30) . "\n\n";
}

echo "=== チェック完了 ===\n";
?>
