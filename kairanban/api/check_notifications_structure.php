<?php
/**
 * 既存notifications.phpの構造確認ツール
 * どの関数を使っているか確認
 */

echo "=== notifications.php構造確認 ===\n\n";

$notificationsFile = __DIR__ . '/notifications.php';

if (!file_exists($notificationsFile)) {
    echo "❌ notifications.phpが見つかりません\n";
    exit;
}

echo "✅ notifications.phpを発見\n\n";

$content = file_get_contents($notificationsFile);

echo "【関数定義】\n";
preg_match_all('/function\s+(\w+)\s*\(/i', $content, $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $func) {
        echo "- {$func}()\n";
    }
} else {
    echo "関数定義が見つかりません\n";
}

echo "\n【require/include】\n";
preg_match_all('/(require|include)(_once)?\s+[\'"](.+?)[\'"]/i', $content, $matches);
if (!empty($matches[3])) {
    foreach ($matches[3] as $file) {
        echo "- {$file}\n";
    }
} else {
    echo "require/includeが見つかりません\n";
}

echo "\n【case文（アクション）】\n";
preg_match_all('/case\s+[\'"](\w+)[\'"]\s*:/i', $content, $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $action) {
        echo "- {$action}\n";
    }
} else {
    echo "caseが見つかりません\n";
}

echo "\n【通知送信に使われている関数呼び出し】\n";
$patterns = [
    'sendWebPushNotification',
    'webpushSend',
    'webPushSend',
    'pushSend',
    'sendPush',
    'sendNotification'
];

foreach ($patterns as $pattern) {
    if (preg_match('/' . $pattern . '\s*\(/i', $content)) {
        echo "✅ 使用: {$pattern}()\n";
    }
}

echo "\n=== 確認完了 ===\n";
