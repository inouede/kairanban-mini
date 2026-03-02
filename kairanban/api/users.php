<?php
/**
 * ユーザー管理API
 * ユーザーの取得、作成、更新、削除
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleList();
            break;
            
        case 'create':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleCreate();
            break;
            
        case 'update':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleUpdate();
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleDelete();
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in users.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * ユーザーリストを取得
 */
function handleList() {
    checkAuth();
    $users = readJsonFile(USERS_FILE, []);
    
    // パスワードハッシュを除外
    $users = array_map(function($user) {
        unset($user['passwordHash']);
        return $user;
    }, $users);
    
    sendJson(['users' => $users]);
}

/**
 * 新しいユーザーを作成
 */
function handleCreate() {
    checkAdmin();
    $data = getPostData();
    
    // バリデーション
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['department'])) {
        sendError('名前、メールアドレス、パスワード、所属は必須です');
    }
    
    $users = readJsonFile(USERS_FILE, []);
    
    // メールアドレスの重複チェック
    foreach ($users as $user) {
        if ($user['email'] === $data['email']) {
            sendError('このメールアドレスは既に使用されています');
        }
    }
    
    $newUser = [
        'id' => uniqid('user_', true),
        'name' => sanitize($data['name']),
        'email' => sanitize($data['email']),
        'role' => isset($data['role']) && $data['role'] === 'ADMIN' ? 'ADMIN' : 'RESIDENT',
        'department' => sanitize($data['department']),
        'passwordHash' => password_hash($data['password'], PASSWORD_DEFAULT)
    ];
    
    $users[] = $newUser;
    
    if (!writeJsonFile(USERS_FILE, $users)) {
        sendError('ユーザーの保存に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'CREATE_USER', "ユーザーを作成: {$newUser['name']}");
    
    // レスポンス（パスワードハッシュは除外）
    unset($newUser['passwordHash']);
    sendJson([
        'success' => true,
        'user' => $newUser
    ]);
}

/**
 * ユーザーを更新
 */
function handleUpdate() {
    checkAdmin();
    $data = getPostData();
    
    if (!isset($data['userId'])) {
        sendError('userIdが必要です');
    }
    
    $users = readJsonFile(USERS_FILE, []);
    $updated = false;
    
    foreach ($users as &$user) {
        if ($user['id'] === $data['userId']) {
            // 更新可能なフィールドのみ更新
            if (isset($data['name'])) {
                $user['name'] = sanitize($data['name']);
            }
            if (isset($data['email'])) {
                // メールアドレスの重複チェック（自分以外）
                foreach ($users as $u) {
                    if ($u['id'] !== $data['userId'] && $u['email'] === $data['email']) {
                        sendError('このメールアドレスは既に使用されています');
                    }
                }
                $user['email'] = sanitize($data['email']);
            }
            if (isset($data['department'])) {
                $user['department'] = sanitize($data['department']);
            }
            if (isset($data['role'])) {
                $user['role'] = $data['role'] === 'ADMIN' ? 'ADMIN' : 'RESIDENT';
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $user['passwordHash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $updated = true;
            
            // セッション情報も更新（自分自身の場合）
            if ($user['id'] === $_SESSION['user_id']) {
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
            }
            
            break;
        }
    }
    
    if (!$updated) {
        sendError('ユーザーが見つかりません', 404);
    }
    
    if (!writeJsonFile(USERS_FILE, $users)) {
        sendError('ユーザーの更新に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'UPDATE_USER', "ユーザーを更新: ID {$data['userId']}");
    
    sendJson(['success' => true]);
}

/**
 * ユーザーを削除
 */
function handleDelete() {
    checkAdmin();
    $data = getPostData();
    
    if (!isset($data['userId'])) {
        sendError('userIdが必要です');
    }
    
    // 自分自身は削除できない
    if ($data['userId'] === $_SESSION['user_id']) {
        sendError('自分自身を削除することはできません');
    }
    
    $users = readJsonFile(USERS_FILE, []);
    $originalCount = count($users);
    
    $deletedUserName = '';
    foreach ($users as $user) {
        if ($user['id'] === $data['userId']) {
            $deletedUserName = $user['name'];
            break;
        }
    }
    
    $users = array_filter($users, function($user) use ($data) {
        return $user['id'] !== $data['userId'];
    });
    
    if (count($users) === $originalCount) {
        sendError('ユーザーが見つかりません', 404);
    }
    
    // インデックスを再割り当て
    $users = array_values($users);
    
    if (!writeJsonFile(USERS_FILE, $users)) {
        sendError('ユーザーの削除に失敗しました', 500);
    }
    
    // ★★★ 追加: subscriptions.jsonからも該当ユーザーの購読を削除 ★★★
    $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
    $beforeCount = count($subscriptions);
    
    $subscriptions = array_filter($subscriptions, function($sub) use ($data) {
        return $sub['userId'] !== $data['userId'];
    });
    $subscriptions = array_values($subscriptions);
    
    $deletedSubscriptions = $beforeCount - count($subscriptions);
    
    if ($deletedSubscriptions > 0) {
        writeJsonFile(SUBSCRIPTIONS_FILE, $subscriptions);
        error_log("[users.php] ユーザー削除: {$deletedUserName} の購読情報を {$deletedSubscriptions} 件削除しました");
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'DELETE_USER', "ユーザーを削除: {$deletedUserName}");
    
    sendJson(['success' => true]);
}
