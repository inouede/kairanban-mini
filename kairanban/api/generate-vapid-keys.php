<?php
require_once __DIR__ . '/config.php';

// 管理者のみ実行可能
checkAdmin();

use Minishlink\WebPush\VAPID;

header('Content-Type: text/plain; charset=UTF-8');

$keys = VAPID::createVapidKeys();

$data = [
'publicKey'  => $keys['publicKey'],
'privateKey' => $keys['privateKey'],
'subject'    => 'mailto:noreply@inoue-de.com'
];

$file = __DIR__ . '/../data/vapid.json';

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
chmod($file, 0600);

echo "✅ 正しい形式のVAPID鍵を生成しました\n\n";
echo "publicKey:\n{$data['publicKey']}\n\n";
echo "privateKey:\n{$data['privateKey']}\n";
