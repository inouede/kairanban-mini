/**
 * プッシュ通知管理モジュール
 * VAPID公開鍵はサーバーから自動取得
 */

class PushNotificationManager {
    constructor() {
        this.swRegistration = null;
        this.vapidPublicKey = null;
    }

/**
 * 初期化
 */
async initialize() {
    if (!('serviceWorker' in navigator)) {
        console.warn('[Push] このブラウザはService Workerをサポートしていません');
        return false;
    }

    if (!('PushManager' in window)) {
        console.warn('[Push] このブラウザはプッシュ通知をサポートしていません');
        return false;
    }

    try {
        // VAPID公開鍵をサーバーから取得
        await this.fetchVapidPublicKey();
        
        // ★★★ 動的にルートパスを計算 ★★★
        // 現在のパスから防災lessQのルートディレクトリを取得
        const currentPath = window.location.pathname; // 例: "/lessq2/kairanban/"
        const rootPath = currentPath.split('/kairanban/')[0]; // 例: "/lessq2"
        const swPath = rootPath + '/sw.js'; // 例: "/lessq2/sw.js"
        const swScope = rootPath + '/'; // 例: "/lessq2/"
        
        console.log('[Push] ルートパス:', rootPath);
        console.log('[Push] Service Workerパス:', swPath);
        console.log('[Push] スコープ:', swScope);
        
        // 既存のService Worker登録を取得
        this.swRegistration = await navigator.serviceWorker.getRegistration(swScope);
        
        if (!this.swRegistration) {
            // まだ登録されていない場合のみ登録
            console.log('[Push] Service Workerを新規登録します');
            this.swRegistration = await navigator.serviceWorker.register(swPath, {
                scope: swScope
            });
            console.log('[Push] Service Worker登録成功');
        } else {
            console.log('[Push] 既存のService Worker登録を使用します');
        }

        // Service Workerがアクティブになるのを待つ
        await navigator.serviceWorker.ready;
        console.log('[Push] Service Workerがready状態になりました');

        return true;
    } catch (error) {
        console.error('[Push] 初期化失敗:', error);
        return false;
    }
}

    /**
     * VAPID公開鍵をサーバーから取得
     */
    async fetchVapidPublicKey() {
        try {
            const response = await fetch('./api/vapid-public-key.php');
            const data = await response.json();
            
            if (data.success && data.publicKey) {
                this.vapidPublicKey = data.publicKey;
                console.log('[Push] VAPID公開鍵を取得しました');
                return true;
            } else {
                console.error('[Push] VAPID公開鍵の取得に失敗:', data);
                return false;
            }
        } catch (error) {
            console.error('[Push] VAPID公開鍵の取得エラー:', error);
            return false;
        }
    }

    /**
     * プッシュ通知を再登録（ワンクリック解除→再登録）
     */
    async resubscribeToPush() {
        if (!this.vapidPublicKey) {
            alert('❌ VAPID公開鍵が取得できていません');
            return false;
        }

        if (!this.swRegistration) {
            alert('❌ Service Workerが登録されていません');
            return false;
        }

        try {
            // 通知許可をリクエスト
            const permission = await Notification.requestPermission();
            console.log('[Push] 通知許可の結果:', permission);

            if (permission !== 'granted') {
                alert('❌ 通知が許可されませんでした');
                return false;
            }

            const registration = await navigator.serviceWorker.ready;

            // 1. 既存のサブスクリプションを削除
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                console.log('[Push] 🗑️ 既存の購読を解除中...');
                await existingSubscription.unsubscribe();
                console.log('[Push] ✅ 購読を解除しました');
            }

            // 2. 新しいサブスクリプションを作成
            console.log('[Push] 🔄 新しい購読を作成中...');
            const vapidPublicKeyUint8 = this.urlBase64ToUint8Array(this.vapidPublicKey);
            const newSubscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidPublicKeyUint8
            });

            console.log('[Push] ✅ 新しい購読を作成しました');

            // 3. サーバーに送信
            const result = await this.sendSubscriptionToServer(newSubscription);

            if (result) {
                this.showToast('✅ プッシュ通知を有効にしました');
                return true;
            } else {
                this.showToast('❌ サーバーへの登録に失敗しました');
                return false;
            }
        } catch (error) {
            console.error('[Push] ❌ 再登録に失敗:', error);
            this.showToast('❌ 再登録に失敗しました: ' + error.message);
            return false;
        }
    }

    /**
     * サブスクリプションをサーバーに送信
     */
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('./api/notifications.php?action=subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });

            if (!response.ok) {
                throw new Error(`サーバーが${response.status}を返しました`);
            }

            const data = await response.json();

            if (data.success) {
                console.log('[Push] ✅ 購読情報をサーバーに保存しました');
                return true;
            } else {
                console.error('[Push] ❌ 購読情報の保存に失敗:', data);
                return false;
            }
        } catch (error) {
            console.error('[Push] ❌ サーバーとの通信に失敗:', error);
            return false;
        }
    }

    /**
     * VAPID公開鍵をUint8Arrayに変換
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * トースト通知を表示
     */
    showToast(message) {
        const existingToast = document.querySelector('.push-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'push-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// グローバルインスタンスを作成
const pushManager = new PushNotificationManager();

// アニメーション用のCSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { transform: translateX(-50%) translateY(20px); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
    @keyframes slideDown {
        from { transform: translateX(-50%) translateY(0); opacity: 1; }
        to { transform: translateX(-50%) translateY(20px); opacity: 0; }
    }
`;
document.head.appendChild(style);

// エクスポート
window.pushManager = pushManager;
