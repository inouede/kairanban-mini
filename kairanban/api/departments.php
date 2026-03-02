<?php
/**
 * 所属管理API
 * 所属の取得、作成、更新、削除
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
    error_log("Exception in departments.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * 所属リストを取得
 */
function handleList() {
    checkAuth();
    $departments = readJsonFile(DEPARTMENTS_FILE, []);
    sendJson(['departments' => $departments]);
}

/**
 * 新しい所属を作成
 */
function handleCreate() {
    checkAdmin();
    $data = getPostData();
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        sendError('所属名が必要です');
    }
    
    $departments = readJsonFile(DEPARTMENTS_FILE, []);
    $newDepartment = sanitize($data['name']);
    
    // 重複チェック
    if (in_array($newDepartment, $departments)) {
        sendError('この所属は既に存在します');
    }
    
    $departments[] = $newDepartment;
    
    if (!writeJsonFile(DEPARTMENTS_FILE, $departments)) {
        sendError('所属の保存に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'CREATE_DEPARTMENT', "所属を作成: {$newDepartment}");
    
    sendJson([
        'success' => true,
        'department' => $newDepartment
    ]);
}

/**
 * 所属を更新（名前変更）
 */
function handleUpdate() {
    checkAdmin();
    $data = getPostData();
    
    if (!isset($data['oldName']) || !isset($data['newName'])) {
        sendError('oldNameとnewNameが必要です');
    }
    
    $oldName = sanitize($data['oldName']);
    $newName = sanitize($data['newName']);
    
    if (empty($newName)) {
        sendError('新しい所属名が必要です');
    }
    
    $departments = readJsonFile(DEPARTMENTS_FILE, []);
    
    // 存在チェック
    if (!in_array($oldName, $departments)) {
        sendError('所属が見つかりません', 404);
    }
    
    // 重複チェック（名前が変わる場合のみ）
    if ($oldName !== $newName && in_array($newName, $departments)) {
        sendError('この所属名は既に存在します');
    }
    
    // 所属名を更新
    $departments = array_map(function($dept) use ($oldName, $newName) {
        return $dept === $oldName ? $newName : $dept;
    }, $departments);
    
    if (!writeJsonFile(DEPARTMENTS_FILE, $departments)) {
        sendError('所属の更新に失敗しました', 500);
    }
    
    // ユーザーの所属も更新
    $users = readJsonFile(USERS_FILE, []);
    $usersUpdated = false;
    
    foreach ($users as &$user) {
        if ($user['department'] === $oldName) {
            $user['department'] = $newName;
            $usersUpdated = true;
        }
    }
    
    if ($usersUpdated) {
        if (!writeJsonFile(USERS_FILE, $users)) {
            error_log("Failed to update users after department rename");
        }
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'UPDATE_DEPARTMENT', "所属を更新: {$oldName} → {$newName}");
    
    sendJson(['success' => true]);
}

/**
 * 所属を削除
 */
function handleDelete() {
    checkAdmin();
    $data = getPostData();
    
    if (!isset($data['name'])) {
        sendError('所属名が必要です');
    }
    
    $name = sanitize($data['name']);
    
    $departments = readJsonFile(DEPARTMENTS_FILE, []);
    $originalCount = count($departments);
    
    $departments = array_filter($departments, function($dept) use ($name) {
        return $dept !== $name;
    });
    
    if (count($departments) === $originalCount) {
        sendError('所属が見つかりません', 404);
    }
    
    // インデックスを再割り当て
    $departments = array_values($departments);
    
    if (!writeJsonFile(DEPARTMENTS_FILE, $departments)) {
        sendError('所属の削除に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($_SESSION['user_id'], $_SESSION['user_name'], 'DELETE_DEPARTMENT', "所属を削除: {$name}");
    
    sendJson(['success' => true]);
}
