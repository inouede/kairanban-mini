<?php
/**
 * 認証API
 * ログイン、ログアウト、セッション確認
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleLogin();
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleLogout();
            break;
            
        case 'check':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleCheckAuth();
            break;
            
        case 'current-user':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleCurrentUser();
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in auth.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * ログイン処理
 */
function handleLogin() {
    $data = getPostData();
    
    if (!isset($data['email']) || !isset($data['password'])) {
        sendError('メールアドレスとパスワードが必要です');
    }
    
    $email = sanitize($data['email']);
    $password = $data['password'];
    
    // ユーザーを検索
    $users = readJsonFile(USERS_FILE, []);
    $user = null;
    
    foreach ($users as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }
    
    if (!$user) {
        // セキュリティのため、ユーザーが存在しない場合も同じエラーメッセージ
        sendError('メールアドレスまたはパスワードが正しくありません', 401);
    }
    
    // パスワード検証
    if (!password_verify($password, $user['passwordHash'])) {
        sendError('メールアドレスまたはパスワードが正しくありません', 401);
    }
    
    // セッション固定攻撃対策: ログイン成功時にセッションIDを再生成
    session_regenerate_id(true);

    // セッションに保存
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_department'] = $user['department'] ?? '';
    
    // 監査ログに記録
    addAuditLog($user['id'], $user['name'], 'LOGIN', 'ユーザーがログインしました');
    
    // レスポンス（パスワードハッシュは除外）
    unset($user['passwordHash']);
    sendJson([
        'success' => true,
        'user' => $user
    ]);
}

/**
 * ログアウト処理
 */
function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $userName = $_SESSION['user_name'] ?? 'Unknown';
        
        // 監査ログに記録
        addAuditLog($userId, $userName, 'LOGOUT', 'ユーザーがログアウトしました');
    }
    
    // セッションを破棄
    session_unset();
    session_destroy();
    
    sendJson(['success' => true]);
}

/**
 * 認証状態チェック
 */
function handleCheckAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['authenticated' => false]);
    }
    
    // ユーザー情報を取得
    $users = readJsonFile(USERS_FILE, []);
    $user = null;
    
    foreach ($users as $u) {
        if ($u['id'] === $_SESSION['user_id']) {
            $user = $u;
            break;
        }
    }
    
    if (!$user) {
        // ユーザーが見つからない場合はセッションをクリア
        session_unset();
        session_destroy();
        sendJson(['authenticated' => false]);
    }
    
    // パスワードハッシュは除外
    unset($user['passwordHash']);
    
    sendJson([
        'authenticated' => true,
        'user' => $user
    ]);
}

/**
 * 現在ログイン中のユーザー情報を取得
 */
function handleCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        sendJson([
            'success' => false,
            'message' => 'ログインしていません',
            'user' => null
        ]);
        return;
    }
    
    sendJson([
        'success' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'department' => $_SESSION['user_department'] ?? ''
        ]
    ]);
}
