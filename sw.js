/**
 * Service Worker - 防災lessQ & 回覧板
 * プッシュ通知を処理
 */

const CACHE_NAME = 'lessq-v1';

// インストール時
self.addEventListener('install', (event) => {
    console.log('[SW] インストール');
    self.skipWaiting();
});

// アクティベーション時
self.addEventListener('activate', (event) => {
    console.log('[SW] アクティベーション');
    event.waitUntil(self.clients.claim());
});

// プッシュ通知受信
self.addEventListener('push', (event) => {
    console.log('[SW] プッシュ通知を受信:', event);
    
    if (!event.data) {
        console.warn('[SW] プッシュデータがありません');
        return;
    }
    
    let notification;
    try {
        notification = event.data.json();
        console.log('[SW] ペイロード:', notification);
    } catch (error) {
        console.error('[SW] JSONパース失敗:', error);
        notification = {
            title: '新しい通知',
            body: event.data.text()
        };
    }
    
    // 通知オプション
    const options = {
        body: notification.body || '',
        icon: notification.icon || '/lessq5/icon-192.png',
        badge: '/lessq5/icon-192.png',
        data: {
            url: notification.url || '/lessq5/',
            source: notification.source || 'unknown'
        },
        tag: notification.tag || 'default',
        requireInteraction: false,
        vibrate: [200, 100, 200],
        // ★★★ 重要: Androidで確実に表示されるように設定 ★★★
        renotify: true,
        silent: false
    };
    
    console.log('[SW] 通知を表示:', notification.title, options);
    
    // ★★★ 必ず通知を表示 ★★★
    event.waitUntil(
        self.registration.showNotification(notification.title || '新しい通知', options)
            .then(() => {
                console.log('[SW] ✅ 通知表示成功');
            })
            .catch((error) => {
                console.error('[SW] ❌ 通知表示失敗:', error);
            })
    );
});

// 通知クリック
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] 通知がクリックされました');
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || '/lessq5/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // 既に開いているウィンドウがあればフォーカス
                for (const client of clientList) {
                    if (client.url.includes(urlToOpen) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // なければ新しいウィンドウを開く
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// フェッチイベント（オフライン対応は最小限）
self.addEventListener('fetch', (event) => {
    // 特別な処理は不要、ブラウザのデフォルト動作に任せる
});

console.log('[SW] Service Worker読み込み完了');
