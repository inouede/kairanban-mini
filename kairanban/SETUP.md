# 回覧板システム - プッシュ通知修正版

## ✅ 修正内容

プッシュ通知機能を**pushpwaの動作実績のある実装**（minishlink/web-push）に置き換えました。

### 変更したファイル
- `composer.json` - 新規追加
- `api/config.php` - vendor autoload追加
- `api/vapid.php` - minishlink/web-push使用版に置き換え
- `api/webpush.php` - minishlink/web-push使用版に置き換え

## 🚀 セットアップ方法

### ステップ1: vendorディレクトリを用意

**方法A: pushpwaからコピー（推奨・5分）**
```
pushpwa/vendor/ → pushmada/vendor/ にコピー
```

**方法B: Composerでインストール（10分）**
```bash
cd /path/to/pushmada
composer install --no-dev
```

### ステップ2: FTPでアップロード

`pushmada/` フォルダ全体をサーバーにアップロード

### ステップ3: アクセス

ブラウザで `https://あなたのドメイン/pushmada/` にアクセス

**完了！** VAPID鍵が自動生成され、プッシュ通知が動作します。

## 📱 動作確認

1. ログイン（既存のアカウント）
2. プロフィール設定で通知を有効化
3. 回覧板を投稿
4. プッシュ通知が届くことを確認

## ⚠️ 重要

- **vendor/ディレクトリが必須**です
- **HTTPSで動作**している必要があります
- iPhoneはホーム画面に追加が必要です

## 🔧 トラブルシューティング

**通知が届かない場合:**
1. `data/error.log` を確認
2. vendorディレクトリが存在するか確認
3. data/vapid.jsonが生成されているか確認

**エラーログの確認:**
```bash
tail -f data/error.log
```

## 📦 必要なファイル

- vendor/ - minishlink/web-pushライブラリ（約3MB）
- 他すべてのファイル

すべて揃えてFTPアップロードすれば動作します。
