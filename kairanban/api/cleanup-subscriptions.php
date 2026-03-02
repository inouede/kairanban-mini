<?php
/**
 * 古い購読をクリーンアップ
 * 410 Goneエラーが出るsubscriptionを削除
 */

require_once __DIR__ . '/config.php';

echo "=== 購読クリーンアップツール ===\n\n";

$subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);

echo "現在の購読数: " . count($subscriptions) . "件\n\n";

// 410エラーになるendpointを削除
$filtered = array_filter($subscriptions, function($sub) {
    // ep6Myu6SNc4 を含むendpointを除外
    $isInvalid = strpos($sub['subscription']['endpoint'], 'ep6Myu6SNc4') !== false;
    
    if ($isInvalid) {
        echo "削除: " . $sub['userName'] . " (" . $sub['userId'] . ")\n";
        echo "  Endpoint: " . substr($sub['subscription']['endpoint'], -20) . "\n";
    }
    
    return !$isInvalid;
});

$filtered = array_values($filtered);

if (count($subscriptions) === count($filtered)) {
    echo "\n削除する購読はありませんでした。\n";
} else {
    writeJsonFile(SUBSCRIPTIONS_FILE, $filtered);
    echo "\nクリーンアップ完了！\n";
    echo "削除した購読: " . (count($subscriptions) - count($filtered)) . "件\n";
    echo "残った購読: " . count($filtered) . "件\n\n";
    
    echo "=== 残った購読一覧 ===\n";
    foreach ($filtered as $sub) {
        echo "- " . $sub['userName'] . " (" . $sub['userId'] . ")\n";
        echo "  Endpoint: ..." . substr($sub['subscription']['endpoint'], -20) . "\n";
    }
}
?>
