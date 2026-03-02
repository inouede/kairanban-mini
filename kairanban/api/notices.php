<?php
/**
 * 回覧板API
 * 回覧板の取得、作成、更新、削除
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
            
        case 'mark-read':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleMarkRead();
            break;
            
        case 'add-comment':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleAddComment();
            break;
            
        case 'toggle-reaction':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleToggleReaction();
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in notices.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * 回覧板リストを取得
 */
function handleList() {
    checkAuth();
    $notices = readJsonFile(NOTICES_FILE, []);
    sendJson(['notices' => $notices]);
}

/**
 * 新しい回覧板を作成
 */
function handleCreate() {
    $user = checkAdmin();
    $data = getPostData();
    
    // バリデーション
    if (!isset($data['title']) || !isset($data['content']) || !isset($data['targetDepartment'])) {
        sendError('タイトル、本文、配信先は必須です');
    }
    
    $notices = readJsonFile(NOTICES_FILE, []);
    
    $newNotice = [
        'id' => uniqid('notice_', true),
        'title' => sanitize($data['title']),
        'content' => sanitize($data['content']),
        'authorId' => $user['id'],
        'targetDepartment' => sanitize($data['targetDepartment']),
        'createdAt' => time() * 1000,
        'attachments' => $data['attachments'] ?? [],
        'readBy' => [],
        'comments' => [],
        'reactions' => [],
        'importance' => $data['importance'] ?? 'NORMAL',
        'isArchived' => false
    ];
    
    array_unshift($notices, $newNotice);
    
    if (!writeJsonFile(NOTICES_FILE, $notices)) {
        sendError('回覧板の保存に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($user['id'], $_SESSION['user_name'], 'CREATE_NOTICE', "回覧板を作成: {$newNotice['title']}");
    
    // 通知を送信（エラーが出ても回覧板作成は成功）
    try {
        sendNoticeNotification($newNotice, 'new');
    } catch (Exception $e) {
        error_log("通知送信エラー（回覧板作成時）: " . $e->getMessage());
    }
    
    sendJson([
        'success' => true,
        'notice' => $newNotice
    ]);
}

/**
 * 回覧板を更新
 */
function handleUpdate() {
    $user = checkAdmin(); // title/content/isArchived の変更は管理者のみ
    $data = getPostData();

    if (!isset($data['noticeId'])) {
        sendError('noticeIdが必要です');
    }

    $notices = readJsonFile(NOTICES_FILE, []);
    $updated = false;

    foreach ($notices as &$notice) {
        if ($notice['id'] === $data['noticeId']) {
            // 管理者のみ変更できるフィールド
            if (isset($data['title'])) {
                $notice['title'] = sanitize($data['title']);
            }
            if (isset($data['content'])) {
                $notice['content'] = sanitize($data['content']);
            }
            if (isset($data['isArchived'])) {
                $notice['isArchived'] = (bool)$data['isArchived'];
            }
            // comments / reactions は専用エンドポイント (add-comment / toggle-reaction) で操作する
            $updated = true;
            break;
        }
    }
    unset($notice);

    if (!$updated) {
        sendError('回覧板が見つかりません', 404);
    }

    if (!writeJsonFile(NOTICES_FILE, $notices)) {
        sendError('回覧板の更新に失敗しました', 500);
    }

    sendJson(['success' => true]);
}

/**
 * 回覧板を削除
 */
function handleDelete() {
    $user = checkAdmin();
    $data = getPostData();
    
    if (!isset($data['noticeId'])) {
        sendError('noticeIdが必要です');
    }
    
    $notices = readJsonFile(NOTICES_FILE, []);
    $originalCount = count($notices);
    
    $notices = array_filter($notices, function($notice) use ($data) {
        return $notice['id'] !== $data['noticeId'];
    });
    
    if (count($notices) === $originalCount) {
        sendError('回覧板が見つかりません', 404);
    }
    
    // インデックスを再割り当て
    $notices = array_values($notices);
    
    if (!writeJsonFile(NOTICES_FILE, $notices)) {
        sendError('回覧板の削除に失敗しました', 500);
    }
    
    // 監査ログに記録
    addAuditLog($user['id'], $_SESSION['user_name'], 'DELETE_NOTICE', "回覧板を削除: ID {$data['noticeId']}");
    
    sendJson(['success' => true]);
}

/**
 * 既読マークを付ける
 */
function handleMarkRead() {
    $user = checkAuth();
    $data = getPostData();
    
    if (!isset($data['noticeId'])) {
        sendError('noticeIdが必要です');
    }
    
    $notices = readJsonFile(NOTICES_FILE, []);
    $updated = false;
    
    foreach ($notices as &$notice) {
        if ($notice['id'] === $data['noticeId']) {
            if (!in_array($user['id'], $notice['readBy'])) {
                $notice['readBy'][] = $user['id'];
                $updated = true;
            }
            break;
        }
    }
    
    if ($updated) {
        if (!writeJsonFile(NOTICES_FILE, $notices)) {
            sendError('既読状態の保存に失敗しました', 500);
        }
    }
    
    sendJson(['success' => true]);
}

/**
 * コメントを追加
 */
function handleAddComment() {
    $user = checkAuth();
    $data = getPostData();
    
    if (!isset($data['noticeId']) || !isset($data['text'])) {
        sendError('noticeIdとtextが必要です');
    }
    
    $notices = readJsonFile(NOTICES_FILE, []);
    $updated = false;
    
    foreach ($notices as &$notice) {
        if ($notice['id'] === $data['noticeId']) {
            $newComment = [
                'id' => uniqid('comment_', true),
                'userId' => $user['id'],
                'userName' => $_SESSION['user_name'],
                'text' => sanitize($data['text']),
                'createdAt' => time() * 1000
            ];
            
            if (!isset($notice['comments'])) {
                $notice['comments'] = [];
            }
            
            $notice['comments'][] = $newComment;
            $updated = true;
            break;
        }
    }
    
    if (!$updated) {
        sendError('回覧板が見つかりません', 404);
    }
    
    if (!writeJsonFile(NOTICES_FILE, $notices)) {
        sendError('コメントの保存に失敗しました', 500);
    }
    
    sendJson(['success' => true]);
}

/**
 * リアクションの切り替え
 */
function handleToggleReaction() {
    $user = checkAuth();
    $data = getPostData();
    
    if (!isset($data['noticeId']) || !isset($data['emoji'])) {
        sendError('noticeIdとemojiが必要です');
    }
    
    $notices = readJsonFile(NOTICES_FILE, []);
    $updated = false;
    
    foreach ($notices as &$notice) {
        if ($notice['id'] === $data['noticeId']) {
            if (!isset($notice['reactions'])) {
                $notice['reactions'] = [];
            }
            
            // 既存のリアクションを検索
            $found = false;
            $notice['reactions'] = array_filter($notice['reactions'], function($reaction) use ($user, $data, &$found) {
                if ($reaction['userId'] === $user['id'] && $reaction['emoji'] === $data['emoji']) {
                    $found = true;
                    return false; // 削除
                }
                return true;
            });
            
            // 見つからなかった場合は追加
            if (!$found) {
                $notice['reactions'][] = [
                    'userId' => $user['id'],
                    'emoji' => sanitize($data['emoji'])
                ];
            }
            
            // インデックスを再割り当て
            $notice['reactions'] = array_values($notice['reactions']);
            
            $updated = true;
            break;
        }
    }
    
    if (!$updated) {
        sendError('回覧板が見つかりません', 404);
    }
    
    if (!writeJsonFile(NOTICES_FILE, $notices)) {
        sendError('リアクションの保存に失敗しました', 500);
    }
    
    sendJson(['success' => true]);
}

/**
 * 回覧板の通知を送信
 */
function sendNoticeNotification($notice, $type = 'new', $targetUserIds = null) {
    try {
        // ユーザー一覧を取得
        $users = readJsonFile(USERS_FILE, []);
        
        // 対象ユーザーを特定
        if ($targetUserIds !== null) {
            // 指定されたユーザーのみ
            $targetUsers = array_filter($users, function($user) use ($targetUserIds) {
                return in_array($user['id'], $targetUserIds);
            });
        } else {
            // 全員に送る
            $targetUsers = $users;
        }

        
        // 通知タイトルとメッセージ
        if ($type === 'new') {
            $title = '新しい回覧板';
            $body = "「{$notice['title']}」が配信されました。";
        } else {
            $title = '回覧板の催促';
            $body = "「{$notice['title']}」の確認をお願いします。";
        }
        
        // 各ユーザーに通知を送信
        // ループ外で一括読み込みし、最後に1回だけ書き込む（通知欠損・多重書き込みを防止）
        $notifications = readJsonFile(PENDING_NOTIFICATIONS_FILE, []);
        $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
        $pushPayload = [
            'title' => $title,
            'body'  => $body,
            'source' => 'kairanban',
            'icon' => './kairanban/icon-192.png',
            'url' => './kairanban/'
        ];

        foreach ($targetUsers as $user) {
            // ブラウザ通知用のデータを追記
            $notifications[] = [
                'id' => uniqid('notif_', true),
                'userId' => $user['id'],
                'title' => $title,
                'body' => $body,
                'noticeId' => $notice['id'],
                'createdAt' => time(),
                'read' => false
            ];

            // PUSH送信
            foreach ($subscriptions as $sub) {
                if ($sub['userId'] === $user['id']) {
                    try {
                        sendWebPush($sub['subscription'], $pushPayload);
                    } catch (Exception $e) {
                        error_log("PUSH送信失敗: " . $e->getMessage());
                    }
                }
            }
        }

        // 最新100件のみ保持してから1回だけ書き込む
        $notifications = array_slice($notifications, -100);
        writeJsonFile(PENDING_NOTIFICATIONS_FILE, $notifications);
        
        return true;
    } catch (Exception $e) {
        error_log("通知送信エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * 催促通知を送信
 */
function sendReminderNotification($noticeId, $userIds) {
    try {
        $notices = readJsonFile(NOTICES_FILE, []);
        $users = readJsonFile(USERS_FILE, []);
        
        // 回覧板を取得
        $notice = null;
        foreach ($notices as $n) {
            if ($n['id'] === $noticeId) {
                $notice = $n;
                break;
            }
        }
        
        if (!$notice) {
            return false;
        }
        
        // 対象ユーザーに通知を送信（指定したユーザーのみ）
        sendNoticeNotification($notice, 'reminder', $userIds);
        
        return true;
    } catch (Exception $e) {
        error_log("催促通知エラー: " . $e->getMessage());
        return false;
    }
}
