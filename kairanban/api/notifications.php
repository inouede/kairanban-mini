<?php
/**
 * プッシュ通知管理API
 * 購読登録、購読確認、通知送信
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'subscribe':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleSubscribe();
            break;
            
        case 'check-user-subscription':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleCheckUserSubscription();
            break;
            
        case 'unsubscribe':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleUnsubscribe();
            break;
            
        case 'send-notification':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleSendNotification();
            break;
            
        case 'get-notifications':
            if ($method !== 'GET') {
                sendError('GETメソッドが必要です', 405);
            }
            handleGetNotifications();
            break;
            
        case 'mark-read':
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            handleMarkNotificationRead();
            break;

        case 'update-email': // 追加
            if ($method !== 'POST') {
                sendError('POSTメソッドが必要です', 405);
            }
            // 何もせず、成功レスポンスを返す
            sendJson(['success' => true, 'message' => 'Email update action received.']);
            break;
            
        default:
            sendError('無効なアクションです', 400);
    }
} catch (Exception $e) {
    error_log("Exception in notifications.php: " . $e->getMessage());
    sendError('サーバーエラーが発生しました', 500);
}

/**
 * ★★★ sendWebPushNotification() 関数の定義 ★★★
 * 既存のwebpush.phpのsendWebPushBatch()を使用
 */
function sendWebPushNotification($subscriptions, $title, $body, $url = null, $icon = null) {
    // ペイロード作成
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => $icon ?? './kairanban/icon-192.png',
        'url' => $url ?? './',
        'source' => 'kairanban'
    ]);
    
    // sendWebPushBatch 関数を使用
    if (function_exists('sendWebPushBatch')) {
        return sendWebPushBatch($subscriptions, $payload);
    }
    
    // sendWebPushBatchが存在しない場合、sendWebPushを使って個別送信
    if (function_exists('sendWebPush')) {
        $results = [];
        foreach ($subscriptions as $subscription) {
            $result = sendWebPush($subscription, $payload);
            $results[] = $result;
        }
        return $results;
    }
    
    // それでもなければエラー
    error_log("[sendWebPushNotification] webpush.phpの関数が見つかりません");
    throw new Exception('WebPush関数が利用できません');
}

/**
 * プッシュ通知購読を登録
 */
function handleSubscribe() {
    checkAuth();
    $data = getPostData();
    
    if (!isset($data['subscription'])) {
        sendError('subscriptionが必要です');
    }
    
    $subscription = $data['subscription'];
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_name'];
    
    // 購読情報を読み込み
    $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
    
    // 同じendpointの既存購読を削除（重複防止）
    $endpoint = $subscription['endpoint'];
    $subscriptions = array_filter($subscriptions, function($sub) use ($endpoint) {
        return $sub['subscription']['endpoint'] !== $endpoint;
    });
    $subscriptions = array_values($subscriptions);
    
    // 新しい購読を追加
    $newSubscription = [
        'userId' => $userId,
        'userName' => $userName,
        'subscription' => $subscription,
        'createdAt' => time()
    ];
    
    $subscriptions[] = $newSubscription;
    
    if (!writeJsonFile(SUBSCRIPTIONS_FILE, $subscriptions)) {
        sendError('購読の保存に失敗しました', 500);
    }
    
    error_log("[notifications.php] ✅ 購読登録: {$userName} (ID: {$userId})");
    
    // Welcome通知を送信（オプション - エラーが出ても続行）
    try {
        sendWebPushNotification(
            [$subscription],
            '回覧板システムへようこそ！',
            "{$userName} さん、通知設定ありがとうございます！"
        );
        error_log("[notifications.php] ✅ Welcome通知送信成功");
    } catch (Exception $e) {
        error_log("[notifications.php] ⚠️ Welcome通知送信失敗（続行）: " . $e->getMessage());
        // エラーが出ても購読登録は成功としてレスポンス
    }
    
    sendJson([
        'success' => true,
        'message' => '購読を登録しました'
    ]);
}

/**
 * ★★★ 新規: 購読が現在のユーザーに紐づいているか確認 ★★★
 */
function handleCheckUserSubscription() {
    checkAuth();
    $data = getPostData();
    
    if (!isset($data['subscription']) || !isset($data['userId'])) {
        sendError('subscriptionとuserIdが必要です');
    }
    
    $subscription = $data['subscription'];
    $userId = $data['userId'];
    $endpoint = $subscription['endpoint'];
    
    // 購読情報を読み込み
    $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
    
    // このendpointを持つ購読を探す
    foreach ($subscriptions as $sub) {
        if ($sub['subscription']['endpoint'] === $endpoint) {
            // 見つかった - ユーザーIDが一致するか確認
            $isSubscribed = ($sub['userId'] === $userId);
            
            sendJson([
                'success' => true,
                'isSubscribed' => $isSubscribed,
                'registeredUserId' => $sub['userId'],
                'registeredUserName' => $sub['userName'],
                'currentUserId' => $userId
            ]);
            return;
        }
    }
    
    // 見つからなかった
    sendJson([
        'success' => true,
        'isSubscribed' => false,
        'message' => 'この購読は登録されていません'
    ]);
}

/**
 * プッシュ通知購読を解除
 */
function handleUnsubscribe() {
    checkAuth();
    $data = getPostData();
    
    if (!isset($data['endpoint'])) {
        sendError('endpointが必要です');
    }
    
    $endpoint = $data['endpoint'];
    
    // 購読情報を読み込み
    $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
    $originalCount = count($subscriptions);
    
    // 該当する購読を削除
    $subscriptions = array_filter($subscriptions, function($sub) use ($endpoint) {
        return $sub['subscription']['endpoint'] !== $endpoint;
    });
    $subscriptions = array_values($subscriptions);
    
    if (count($subscriptions) === $originalCount) {
        sendError('購読が見つかりません', 404);
    }
    
    if (!writeJsonFile(SUBSCRIPTIONS_FILE, $subscriptions)) {
        sendError('購読の削除に失敗しました', 500);
    }
    
    error_log("[notifications.php] 購読解除: " . $endpoint);
    
    sendJson([
        'success' => true,
        'message' => '購読を解除しました'
    ]);
}

/**
 * プッシュ通知を送信
 */
function handleSendNotification() {
    checkAdmin(); // 一般ユーザーによる任意通知送信を防止
    $data = getPostData();

    if (!isset($data['title']) || !isset($data['body'])) {
        sendError('titleとbodyが必要です');
    }
    
    // ★★★ targetUserIdsを取得（オプション） ★★★
    $targetUserIds = $data['targetUserIds'] ?? null;
    
    // 購読情報を読み込み
    $subscriptions = readJsonFile(SUBSCRIPTIONS_FILE, []);
    
    // ★★★ targetUserIdsが指定されている場合は対象ユーザーに絞り込む ★★★
    if ($targetUserIds !== null && is_array($targetUserIds) && count($targetUserIds) > 0) {
        $subscriptions = array_filter($subscriptions, function($sub) use ($targetUserIds) {
            return in_array($sub['userId'], $targetUserIds);
        });
        $subscriptions = array_values($subscriptions);
        error_log("[notifications.php] 対象ユーザー数: " . count($targetUserIds) . "人に絞り込み → 実際の購読数: " . count($subscriptions) . "件");
    }
    
    if (empty($subscriptions)) {
        sendError('購読者が見つかりません', 404);
    }
    
    // 購読情報のみを抽出
    $pushSubscriptions = array_map(function($sub) {
        return $sub['subscription'];
    }, $subscriptions);
    
    // プッシュ通知を送信
    try {
        $results = sendWebPushNotification(
            $pushSubscriptions,
            $data['title'],
            $data['body'],
            $data['url'] ?? null,
            $data['icon'] ?? null
        );
        
        $successCount = 0;
        foreach ($results as $result) {
            if (is_array($result) && isset($result['success']) && $result['success']) {
                $successCount++;
            } elseif ($result === true) {
                $successCount++;
            }
        }
        
        $totalCount = count($results);
        error_log("[notifications.php] ✅ 通知送信完了: {$successCount}/{$totalCount}件成功");
        
        sendJson([
            'success' => true,
            'sent' => $successCount,
            'total' => count($results),
            'message' => '通知を送信しました'
        ]);
    } catch (Exception $e) {
        error_log("[notifications.php] ❌ 通知送信エラー: " . $e->getMessage());
        sendError('通知の送信に失敗しました', 500);
    }
}

/**
 * 未読通知一覧を取得
 */
function handleGetNotifications() {
    $user = checkAuth();
    $userId = $user['id'];
    
    $notifications = readJsonFile(PENDING_NOTIFICATIONS_FILE, []);
    
    // 現在のユーザーの通知のみ返す
    $userNotifications = array_values(array_filter($notifications, function($n) use ($userId) {
        return $n['userId'] === $userId;
    }));
    
    sendJson([
        'success' => true,
        'notifications' => $userNotifications
    ]);
}

/**
 * 通知を既読にする
 */
function handleMarkNotificationRead() {
    $user = checkAuth();
    $userId = $user['id'];
    $data = getPostData();
    
    if (!isset($data['notificationId'])) {
        sendError('notificationIdが必要です');
    }
    
    $notificationId = $data['notificationId'];
    $notifications = readJsonFile(PENDING_NOTIFICATIONS_FILE, []);
    
    foreach ($notifications as &$n) {
        if ($n['id'] === $notificationId && $n['userId'] === $userId) {
            $n['read'] = true;
            break;
        }
    }
    unset($n);

    if (!writeJsonFile(PENDING_NOTIFICATIONS_FILE, $notifications)) {
        sendError('既読状態の保存に失敗しました', 500);
    }
    
    sendJson(['success' => true]);
}
