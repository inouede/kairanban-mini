<?php
/**
 * QR/NFCカード自動ログイン
 * メールアドレスでユーザーを認証してログイン
 */

require_once __DIR__ . '/api/config.php';

// GETパラメータからメールアドレスを取得
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// メールアドレスが指定されていない場合
if (empty($email)) {
    header('Location: index.html?error=invalid_email');
    exit;
}

// メールアドレスのサニタイズ
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// メールアドレスの形式チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.html?error=invalid_email');
    exit;
}

try {
    // ユーザーを検索
    $users = readJsonFile(USERS_FILE, []);
    $user = null;
    
    foreach ($users as $u) {
        if (isset($u['email']) && $u['email'] === $email) {
            $user = $u;
            break;
        }
    }
    
    // ユーザーが見つからない場合
    if (!$user) {
        header('Location: index.html?error=user_not_found');
        exit;
    }
    
    // セッションに保存（通常のログインと同じ形式）
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['login_method'] = 'qr_nfc'; // ログイン方法を記録
    
    // 監査ログに記録
    addAuditLog($user['id'], $user['name'], 'QR_NFC_LOGIN', 'QR/NFCでログインしました');
    
    // メイン画面にリダイレクト
    header('Location: index.html');
    exit;
    
} catch (Exception $e) {
    // エラーログに記録
    error_log("Auto-login error: " . $e->getMessage());
    
    // エラー時は通常のログイン画面へ
    header('Location: index.html?error=system_error');
    exit;
}
