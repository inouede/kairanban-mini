<?php
/**
 * システム初期化スクリプト（JSONベース）
 * 管理者（admin@example.com）専用
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインチェック
$isLoggedIn = isset($_SESSION['user_id']);
$currentUserEmail = $_SESSION['user_email'] ?? null;

// 管理者チェック
if (!$isLoggedIn || $currentUserEmail !== 'admin@example.com') {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>アクセス拒否</title>
        <style>
            body { font-family: sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .container { background: white; border-radius: 16px; padding: 40px; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔒 アクセス拒否</h1>
            <p>管理者（admin@example.com）のみがアクセスできます。</p>
            <p><a href="../">← 回覧板に戻る</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// データディレクトリ
$dataDir = __DIR__ . '/../data/';

// 画像削除
function deleteUploadedImages() {
    $dir = __DIR__ . '/../uploads/';
    $count = 0;
    if (is_dir($dir)) {
        foreach (glob($dir . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
                $count++;
            }
        }
    }
    return $count;
}

// 初期化実行
$success = null;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset']) && $_POST['confirm_text'] === 'RESET') {
    try {
        // 1. 回覧板の投稿を削除
        $noticesFile = $dataDir . 'notices.json';
        if (file_exists($noticesFile)) {
            $notices = json_decode(file_get_contents($noticesFile), true) ?: [];
            $noticeCount = count($notices);
            file_put_contents($noticesFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $results[] = "✅ 投稿削除: {$noticeCount}件";
        } else {
            $results[] = "✅ 投稿削除: 0件";
        }
        
        // 2. ユーザーを削除（admin以外）
        $usersFile = $dataDir . 'users.json';
        if (file_exists($usersFile)) {
            $users = json_decode(file_get_contents($usersFile), true) ?: [];
            $beforeCount = count($users);
            $users = array_filter($users, function($user) {
                return $user['email'] === 'admin@example.com' || $user['id'] === 'user_resident_guest';
            });
            $users = array_values($users); // 配列を再インデックス
            file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $deletedCount = $beforeCount - count($users);
            $results[] = "✅ ユーザー削除: {$deletedCount}件";
        } else {
            $results[] = "✅ ユーザー削除: 0件";
        }
        
        // 3. 購読情報を削除（admin以外）
        $subsFile = $dataDir . 'subscriptions.json';
        if (file_exists($subsFile)) {
            $subs = json_decode(file_get_contents($subsFile), true) ?: [];
            $beforeCount = count($subs);
            $subs = array_filter($subs, function($sub) {
                return isset($sub['userId']) && $sub['userId'] === 'admin1';
            });
            $subs = array_values($subs);
            file_put_contents($subsFile, json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $deletedCount = $beforeCount - count($subs);
            $results[] = "✅ 購読削除: {$deletedCount}件";
        } else {
            $results[] = "✅ 購読削除: 0件";
        }
        
        // 4. 監査ログをクリア
        $auditFile = $dataDir . 'audit_logs.json';
        if (file_exists($auditFile)) {
            $logs = json_decode(file_get_contents($auditFile), true) ?: [];
            $logCount = count($logs);
            file_put_contents($auditFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $results[] = "✅ 監査ログクリア: {$logCount}件";
        } else {
            $results[] = "✅ 監査ログクリア: 0件";
        }
        
        // 5. 保留通知をクリア
        $pendingFile = $dataDir . 'pending_notifications.json';
        if (file_exists($pendingFile)) {
            $pending = json_decode(file_get_contents($pendingFile), true) ?: [];
            $pendingCount = count($pending);
            file_put_contents($pendingFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $results[] = "✅ 保留通知クリア: {$pendingCount}件";
        } else {
            $results[] = "✅ 保留通知クリア: 0件";
        }
        
        // 6. ログファイルをクリア
        $logCount = 0;
        foreach (glob($dataDir . '*.log') as $log) {
            @file_put_contents($log, '');
            $logCount++;
        }
        $results[] = "✅ ログクリア: {$logCount}件";
        
        // 7. アップロード画像を削除
        $imageCount = deleteUploadedImages();
        $results[] = "✅ 画像削除: {$imageCount}件";
        
        $success = true;
    } catch (Exception $e) {
        $results[] = "❌ エラー: " . $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システム初期化</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .box { background: white; border-radius: 16px; padding: 40px; max-width: 800px; width: 100%; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); max-height: 90vh; overflow-y: auto; }
        h1 { color: #1f2937; margin-bottom: 10px; font-size: 28px; }
        .sub { color: #6b7280; margin-bottom: 30px; font-size: 14px; }
        .warn { background: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin-bottom: 24px; border-radius: 4px; }
        .warn h2 { color: #dc2626; font-size: 18px; margin-bottom: 8px; }
        .warn ul { color: #991b1b; margin-left: 20px; font-size: 14px; line-height: 1.8; }
        .info { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 16px; margin-bottom: 24px; border-radius: 4px; }
        .info h3 { color: #0284c7; font-size: 16px; margin-bottom: 8px; }
        .info ul, .info ol { color: #075985; margin-left: 20px; font-size: 14px; line-height: 1.8; }
        .code { background: #1f2937; color: #e5e7eb; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; margin: 12px 0; }
        input[type="text"] { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; margin: 8px 0; }
        input[type="text"]:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; background: #dc2626; color: white; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #b91c1c; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; }
        .ok { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px; margin-bottom: 24px; border-radius: 4px; }
        .ok h3 { color: #16a34a; font-size: 18px; margin-bottom: 12px; }
        .ok ul { list-style: none; color: #166534; font-size: 14px; line-height: 2; }
        a { color: #667eea; text-decoration: none; font-weight: 600; }
        .step { display: inline-block; background: #0284c7; color: white; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; font-weight: bold; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($success === true): ?>
            <div class="ok">
                <h3>✅ システム初期化完了</h3>
                <ul><?php foreach ($results as $r) echo "<li>$r</li>"; ?></ul>
            </div>
            
            <div class="warn">
                <h2>⚠️ 必須：VAPIDキーの変更</h2>
                <p style="font-weight: 600; margin: 12px 0;">新しい地域で運用する前に、必ずVAPIDキーを変更してください。</p>
            </div>
            
            <div class="info">
                <h3><span class="step">1</span>VAPIDキーペアを生成</h3>
                <p>以下のURLにアクセス:</p>
                <div class="code">https://web-push-codelab.glitch.me/</div>
                <p>「Generate Keys」ボタンをクリックして、Public KeyとPrivate Keyをコピー</p>
            </div>
            
            <div class="info">
                <h3><span class="step">2</span>防災lessQのVAPIDキーを更新</h3>
                <p>ファイル: /lessq5/index.html</p>
                <div class="code">vapidPublicKey: '新しいPublic Key'</div>
            </div>
            
            <div class="info">
                <h3><span class="step">3</span>回覧板のVAPIDキーを更新（2箇所）</h3>
                <p>ファイル1: /lessq5/kairanban/assets/push-notifications.js</p>
                <div class="code">vapidPublicKey: '新しいPublic Key'</div>
                <p style="margin-top: 12px;">ファイル2: /lessq5/kairanban/api/webpush.php</p>
                <div class="code">VAPID公開鍵・秘密鍵を更新</div>
            </div>
            
            <div class="info">
                <h3><span class="step">4</span>テスト配信</h3>
                <ol>
                    <li>新しいユーザーを登録</li>
                    <li>プッシュ通知を許可</li>
                    <li>回覧板を配信</li>
                    <li>通知が届くことを確認 ✅</li>
                </ol>
            </div>
            
            <p style="margin-top: 20px;"><a href="../">← 回覧板に戻る</a></p>
            
        <?php elseif ($success === false): ?>
            <div class="warn">
                <h2>❌ エラー発生</h2>
                <ul><?php foreach ($results as $r) echo "<li>$r</li>"; ?></ul>
            </div>
            <p><a href="reset_system.php">← もう一度試す</a></p>
            
        <?php else: ?>
            <h1>🔄 システム初期化</h1>
            <p class="sub">管理者専用 - 新しい地域への展開時に使用</p>
            
            <div class="warn">
                <h2>⚠️ VAPIDキーの変更が必須です</h2>
                <p>初期化後、必ずVAPIDキーを変更してください。変更しないと：</p>
                <ul>
                    <li>通知の混在：旧地域のユーザーに新地域の通知が届く</li>
                    <li>個人情報の漏洩：地域間で情報が混在</li>
                </ul>
            </div>
            
            <div class="warn">
                <h2>⚠️ この操作は取り消せません</h2>
                <ul>
                    <li>すべての回覧板の投稿が削除されます</li>
                    <li>admin以外のすべてのユーザーが削除されます</li>
                    <li>すべての購読情報が削除されます（admin以外）</li>
                    <li>すべてのログファイルがクリアされます</li>
                    <li>アップロード画像がすべて削除されます</li>
                </ul>
            </div>
            
            <div class="info">
                <h3>✅ 保持される情報</h3>
                <ul>
                    <li>admin（町内会長）のアカウント</li>
                    <li>システムファイル</li>
                </ul>
            </div>
            
            <form method="POST" onsubmit="return confirm('本当にシステムを初期化しますか？\n\nこの操作は取り消せません。\nすべてのデータと画像が削除されます。\n\n初期化後、必ずVAPIDキーを変更してください。')">
                <label style="font-weight: 600; color: #374151; margin-bottom: 8px; display: block;">確認のため「<strong>RESET</strong>」と入力してください:</label>
                <input type="text" id="txt" name="confirm_text" placeholder="RESET と入力" required>
                <input type="hidden" name="confirm_reset" value="1">
                <button type="submit" class="btn" id="btn" disabled>システムを初期化する</button>
            </form>
            
            <p style="margin-top: 20px;"><a href="../">← キャンセル</a></p>
        <?php endif; ?>
    </div>
    
    <script>
        const txt = document.getElementById('txt');
        const btn = document.getElementById('btn');
        if (txt && btn) {
            txt.addEventListener('input', () => btn.disabled = (txt.value !== 'RESET'));
        }
    </script>
</body>
</html>
