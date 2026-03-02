/**
 * 防災lessQで登録した購読をサーバーに同期する
 * 
 * このスクリプトを回覧板のindex.htmlの</body>の直前、
 * または既存のpush-notifications.jsに追加してください
 */

(function() {
  'use strict';
  
  console.log('[購読同期] 初期化開始');
  
  // ============================================
  // 購読同期機能
  // ============================================
  async function syncPushSubscriptionToServer() {
    try {
      console.log('[購読同期] 同期処理開始');
      
      // Service Worker & PushManager チェック
      if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.log('[購読同期] プッシュ通知非対応');
        return;
      }
      
      // ユーザーがログインしているか確認
      const userCheck = await fetch('./api/auth.php?action=current-user', {
        credentials: 'same-origin'
      });
      const userData = await userCheck.json();
      
      if (!userData.success || !userData.user) {
        console.log('[購読同期] ユーザー未ログイン');
        return;
      }
      
      const currentUserId = userData.user.id;
      const currentUserName = userData.user.name;
      console.log('[購読同期] ログイン中:', currentUserName, '(ID:', currentUserId, ')');
      
      // Service Workerの準備を待つ
      const registration = await navigator.serviceWorker.ready;
      console.log('[購読同期] Service Worker準備完了');
      
      // 現在の購読を取得
      const subscription = await registration.pushManager.getSubscription();
      
      if (!subscription) {
        console.log('[購読同期] 現在の購読なし');
        return;
      }
      
      console.log('[購読同期] 現在の購読あり:', subscription.endpoint.slice(0, 50) + '...');
      
      // サーバーに既に登録されているか確認
      const checkResponse = await fetch('./api/notifications.php?action=check-user-subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          subscription: subscription,
          userId: currentUserId
        })
      });
      
      if (checkResponse.ok) {
        const checkData = await checkResponse.json();
        
        if (checkData.success && checkData.isSubscribed) {
          console.log('[購読同期] ✅ 既にサーバーに登録済み');
          return;
        }
        
        console.log('[購読同期] サーバーに未登録 - 登録を実行');
      }
      
      // サーバーに登録
      const registerResponse = await fetch('./api/notifications.php?action=subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ subscription: subscription })
      });
      
      if (registerResponse.ok) {
        const registerData = await registerResponse.json();
        console.log('[購読同期] ✅✅✅ サーバー登録成功:', registerData);
        
        // localStorageを更新
        localStorage.setItem('lessq_push_subscription', JSON.stringify(subscription));
        localStorage.setItem('lessq_push_registered', 'true');
        
        console.log('[購読同期] ✅ localStorage更新完了');
      } else {
        console.error('[購読同期] ❌ サーバー登録失敗:', registerResponse.status);
      }
      
    } catch (error) {
      console.error('[購読同期] エラー:', error);
    }
  }
  
  // ============================================
  // ログイン検知
  // ============================================
  function startSyncMonitor() {
    console.log('[購読同期] ログイン検知開始');
    
    let syncAttempted = false;
    
    const observer = new MutationObserver(async () => {
      // ユーザー名要素を探す（ログインしている証拠）
      const userNameElement = document.querySelector('[class*="text-sm font-semibold"], [class*="font-bold text-slate-800"]');
      
      if (userNameElement && userNameElement.textContent && !syncAttempted) {
        const userName = userNameElement.textContent.trim();
        console.log('[購読同期] ログイン検知:', userName);
        
        syncAttempted = true;
        
        // 3秒待ってから同期実行（Service Workerが完全に準備されるまで）
        setTimeout(async () => {
          await syncPushSubscriptionToServer();
        }, 3000);
      }
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
  
  // ============================================
  // 初期化
  // ============================================
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      console.log('[購読同期] DOMContentLoaded');
      setTimeout(() => {
        startSyncMonitor();
      }, 2000);
    });
  } else {
    console.log('[購読同期] DOMすでに読み込み済み');
    setTimeout(() => {
      startSyncMonitor();
    }, 2000);
  }
  
  console.log('[購読同期] 初期化完了');
})();
