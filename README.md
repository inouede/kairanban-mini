# スマート回覧板 — 運用・セットアップマニュアル

> **対象読者:** システムを初めてサーバーに置く人、別のサーバーに移行する人、引き継ぎを受けた管理者

---

## 目次

1. [システム概要](#1-システム概要)
2. [動作要件](#2-動作要件)
3. [ディレクトリ構成](#3-ディレクトリ構成)
4. [初回セットアップ（新規サーバー）](#4-初回セットアップ新規サーバー)
5. [別サーバーへのコピー（流用・移行）](#5-別サーバーへのコピー流用移行)
6. [初期データとデフォルトアカウント](#6-初期データとデフォルトアカウント)
7. [VAPID 鍵の管理](#7-vapid-鍵の管理)
8. [システムリセット（地域移管）](#8-システムリセット地域移管)
9. [メンテナンス用スクリプト](#9-メンテナンス用スクリプト)
10. [ファイルパーミッション詳細](#10-ファイルパーミッション詳細)
11. [セキュリティ上の注意点](#11-セキュリティ上の注意点)
12. [トラブルシューティング](#12-トラブルシューティング)

---

## 1. システム概要

スマート回覧板は、町内会向けの Web ベース回覧板システムです。

| 項目 | 内容 |
|------|------|
| バックエンド | PHP（データベース不要、JSON ファイルで管理） |
| フロントエンド | React（コンパイル済み JS として同梱） |
| プッシュ通知 | Web Push API + VAPID（minishlink/web-push） |
| 認証 | セッションベース（Cookie） |
| 必要サーバー機能 | PHP 7.4+、Apache、HTTPS |

**データはすべて `kairanban/data/` 以下の JSON ファイルに保存されます。**
MySQL 等のデータベースは不要です。

---

## 2. 動作要件

### サーバー側

| 要件 | 内容 | 確認方法 |
|------|------|---------|
| PHP | **7.4 以上**（8.0+ 推奨） | `php -v` |
| PHP 拡張: json | 標準組み込み | `php -m \| grep json` |
| PHP 拡張: mbstring | 日本語処理に必須 | `php -m \| grep mbstring` |
| PHP 拡張: openssl | VAPID 署名に必須 | `php -m \| grep openssl` |
| PHP 拡張: curl | Web Push 送信に必須 | `php -m \| grep curl` |
| Web サーバー | **Apache**（.htaccess 有効） | `httpd -v` または `apache2 -v` |
| AllowOverride | **All** に設定済みであること | httpd.conf / apache2.conf を確認 |
| HTTPS | **必須**（Service Worker と Secure Cookie の要件） | SSL 証明書が設定済みであること |
| ファイル書き込み権限 | `data/` ディレクトリに Apache が書き込める | 後述のパーミッション設定 |

### クライアント側

- 対応ブラウザ: Chrome 80+、Firefox 75+、Safari 16.4+（iOS は「ホーム画面に追加」が必要）
- HTTPS 接続必須（http:// では動作しない）

---

## 3. ディレクトリ構成

```
kairanban-mini/                   ← リポジトリルート
├── README.md                     ← このファイル
├── .gitignore                    ← data/*.json, vapid.json 等を除外
│
└── kairanban/                    ← Web 公開するフォルダ本体
    ├── index.html                ← メインアプリ HTML
    ├── push-notifications.js     ← プッシュ通知クライアント
    ├── icon-192.png / icon-512.png
    │
    ├── assets/
    │   ├── index-BTCN-spV.js     ← コンパイル済み React アプリ
    │   ├── push-notifications.js ← プッシュ通知管理モジュール
    │   └── kairanban_subscription_sync.js  ← 購読同期スクリプト
    │
    ├── api/                      ← PHP バックエンド
    │   ├── config.php            ← ★ 中心設定ファイル（全 API が require）
    │   ├── auth.php              ← ログイン / ログアウト / セッション確認
    │   ├── notices.php           ← 回覧板 CRUD + コメント + リアクション
    │   ├── users.php             ← ユーザー管理（管理者専用）
    │   ├── departments.php       ← 班（所属）管理（管理者専用）
    │   ├── notifications.php     ← プッシュ購読登録・解除・通知取得
    │   ├── settings.php          ← システム設定 + 監査ログ
    │   ├── webpush.php           ← Web Push 送信ライブラリラッパー
    │   ├── vapid.php             ← VAPID 鍵読み込みユーティリティ
    │   ├── vapid-public-key.php  ← VAPID 公開鍵を返す API（認証不要）
    │   ├── debug.php             ← エラーログ閲覧・クリア（管理者専用）
    │   ├── generate-vapid-keys.php  ← VAPID 鍵生成（管理者専用）
    │   ├── reset_system.php      ← システム初期化（管理者専用）
    │   │
    │   └── [メンテナンス用スクリプト群・管理者専用]
    │       ├── check-subscriptions.php
    │       ├── check_subscription_details.php
    │       ├── check_notifications_structure.php
    │       ├── check_webpush_structure.php
    │       ├── cleanup-subscriptions.php
    │       ├── cleanup_expired_subscriptions.php
    │       ├── cleanup_orphaned_subscriptions.php
    │       ├── fix_subscriptions_structure.php
    │       └── remove_duplicate_subscriptions.php
    │
    ├── vendor/                   ← Composer 依存ライブラリ（リポジトリに同梱）
    │   └── minishlink/web-push   ← Web Push 実装ライブラリ
    │
    ├── composer.json             ← 依存定義（"minishlink/web-push": "^9.0"）
    │
    └── data/                     ← ★ サーバー上でのみ存在（git 管理外）
        ├── .htaccess             ← Web からの直接アクセスを完全遮断
        ├── vapid.json            ← VAPID 秘密鍵・公開鍵（git 管理外）
        ├── users.json            ← ユーザー情報（パスワードハッシュ含む）
        ├── notices.json          ← 回覧板データ
        ├── departments.json      ← 班（所属）リスト
        ├── settings.json         ← システム設定
        ├── subscriptions.json    ← プッシュ購読情報
        ├── audit_logs.json       ← 操作監査ログ
        ├── pending_notifications.json  ← 未読通知キュー
        └── error.log             ← PHP エラーログ
```

> **重要:** `data/` フォルダ以下のファイルは `.gitignore` で除外されています。
> サーバー上のデータはリポジトリに含まれません。

---

## 4. 初回セットアップ（新規サーバー）

### ステップ 1: ファイルをサーバーにアップロード

`kairanban/` フォルダ以下のすべてのファイルをサーバーの公開ディレクトリにアップロードします。

```
アップロード先の例:
  /var/www/html/kairanban/     → https://example.com/kairanban/
  /var/www/html/               → https://example.com/
  /home/user/public_html/      → https://example.com/
```

`vendor/` フォルダもリポジトリに同梱されているため、そのままアップロードしてください。
**Composer のインストールは不要です。**

---

### ステップ 2: `data/` ディレクトリのパーミッション設定

`data/` ディレクトリは PHP（Apache）が書き込めなければなりません。

#### 共有ホスティング（FTP のみの場合）

```bash
# data/ ディレクトリに書き込み権限を付与
chmod 755 kairanban/data/

# .htaccess は必ず 644 のまま（実行権限不要）
chmod 644 kairanban/data/.htaccess
```

> 755 で動作しない場合（Apache が別ユーザーで動作する環境）は 775 にしてください。
> **777 は使わないでください。** セキュリティリスクがあります。

#### VPS / 専用サーバー（SSH が使える場合）

```bash
# Apache のユーザーを確認
apachectl -S 2>&1 | grep "User:"
# または: ps aux | grep apache | head -2

# Apache ユーザー（通常 www-data または apache）に所有権を変更
sudo chown -R www-data:www-data kairanban/data/
sudo chmod 750 kairanban/data/

# .htaccess は読み取り専用で十分
sudo chmod 640 kairanban/data/.htaccess
```

---

### ステップ 3: `.htaccess` が有効か確認

`kairanban/data/.htaccess` がブラウザから直接アクセスできないことを確認します。

```
ブラウザで以下にアクセスして「403 Forbidden」が返ることを確認:
  https://example.com/kairanban/data/users.json
  → 403 Forbidden  ← これが正しい
  → 200 OK や JSON が見える ← 危険！Apache の AllowOverride 設定を確認
```

403 が返らない場合は、Apache の設定を確認してください：

```apache
# /etc/apache2/apache2.conf または /etc/httpd/conf/httpd.conf
<Directory /var/www/html>
    AllowOverride All    ← ここが None だと .htaccess が無効
</Directory>
```

---

### ステップ 4: 初回アクセス（データ自動生成）

ブラウザで `https://example.com/kairanban/` にアクセスすると、
`data/` が存在しない場合、PHP が自動的に以下を生成します：

| 生成されるファイル | 内容 |
|------------------|------|
| `data/departments.json` | 初期班：北班・南班・東班・西班 |
| `data/users.json` | 初期ユーザー 3 名（後述） |
| `data/settings.json` | デフォルト設定 |
| `data/notices.json` | 空の回覧板リスト |
| `data/audit_logs.json` | 空の監査ログ |

---

### ステップ 5: VAPID 鍵の生成

プッシュ通知を使用するには VAPID 鍵が必要です。

1. **管理者アカウントでログイン**（後述のデフォルトパスワードを参照）
2. ブラウザで以下にアクセス：
   ```
   https://example.com/kairanban/api/generate-vapid-keys.php
   ```
3. 画面に `publicKey` と `privateKey` が表示されれば成功
4. `data/vapid.json` が自動生成されます

> **注意:** この URL は管理者ログイン済みでないとアクセスできません。
> ただし、鍵の内容が画面に表示されるため、**表示後は必ずブラウザ履歴を削除**してください。

---

### ステップ 6: デフォルトパスワードの変更

**これが最重要の手順です。** 初期アカウントには既知の弱いパスワードが設定されています。

1. 管理者アカウントでログイン
2. 管理画面 → ユーザー管理 → 管理者アカウントを選択
3. **パスワードを強力なものに変更**（12文字以上、英数字記号混在推奨）
4. demo 用の一般ユーザーアカウント（後述）も削除または変更

---

## 5. 別サーバーへのコピー（流用・移行）

既存のシステムを別のサーバーや別のコミュニティ向けにコピーする場合の手順です。

### ステップ 1: リポジトリのファイルをアップロード

`data/` フォルダを**除いた**ファイル一式をアップロードします。

```
アップロード対象（リポジトリのファイル）:
  ✅ kairanban/api/
  ✅ kairanban/assets/
  ✅ kairanban/vendor/
  ✅ kairanban/index.html
  ✅ kairanban/push-notifications.js
  ✅ kairanban/icon-192.png / icon-512.png
  ✅ kairanban/composer.json

アップロードしないもの（古いデータが混入するため）:
  ❌ kairanban/data/vapid.json     ← 旧VAPID秘密鍵（別コミュニティに通知が届く）
  ❌ kairanban/data/users.json     ← 旧ユーザー情報
  ❌ kairanban/data/notices.json   ← 旧回覧板データ
  ❌ kairanban/data/subscriptions.json  ← 旧プッシュ購読情報
  ❌ kairanban/data/audit_logs.json
  ❌ kairanban/data/pending_notifications.json
  ❌ kairanban/data/departments.json
  ❌ kairanban/data/settings.json
```

> `.gitignore` の設定により、上記の `data/` 以下のファイルはリポジトリに含まれていません。
> リポジトリをクローンしてアップロードすれば、自動的に除外されます。

---

### ステップ 2: `data/` ディレクトリの作成

新しいサーバーに `data/` ディレクトリと `.htaccess` を手動で作成します。

```bash
# SSH が使える場合
mkdir -p /var/www/html/kairanban/data
chmod 755 /var/www/html/kairanban/data
```

FTP しか使えない場合は、FTP クライアントで `data/` フォルダを作成し、
パーミッションを `755` に設定してください。

次に、以下の内容で `data/.htaccess` を作成・アップロードします：

```apache
# Apache 2.4+
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Apache 2.2 フォールバック
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

---

### ステップ 3: パーミッション設定

[ステップ 2（初回セットアップ）](#ステップ-2-data-ディレクトリのパーミッション設定) と同じ手順で設定します。

---

### ステップ 4: 初回アクセスでデータ自動生成

ブラウザでアクセスするとデフォルトデータが自動生成されます。
[ステップ 4（初回セットアップ）](#ステップ-4-初回アクセスデータ自動生成) を参照。

---

### ステップ 5: VAPID 鍵を新たに生成

**この手順は必須です。** 旧サーバーの VAPID 鍵を使い回すと：
- 異なるコミュニティのユーザーに通知が届く場合がある
- プッシュ通知が動作しない場合がある

[ステップ 5（VAPID 鍵の生成）](#ステップ-5-vapid-鍵の生成) を参照して、新しい鍵を生成してください。

---

### ステップ 6: デフォルトパスワードを変更

[ステップ 6（初回セットアップ）](#ステップ-6-デフォルトパスワードの変更) を参照。

---

### ステップ 7: デモアカウントを削除・置き換え

自動生成される初期アカウント（次章参照）を削除し、
実際に使用するユーザーを管理画面から登録してください。

---

## 6. 初期データとデフォルトアカウント

`users.json` が存在しない状態で初回アクセスすると、以下のアカウントが自動生成されます（`config.php` の `initializeData()` 関数による）。

| ID | 名前 | メールアドレス | パスワード | 権限 |
|----|------|--------------|-----------|------|
| `admin1` | 町内 会長 | admin@example.com | **`admin`** | 管理者 |
| `user1` | 田中 太郎 | tanaka@example.com | **`user`** | 一般 |
| `user2` | 佐藤 花子 | sato@example.com | **`user`** | 一般 |

> **⚠️ 警告:** これらは既知のデモ用パスワードです。
> **本番運用前に必ず変更または削除してください。**

### 現在のサーバーの `users.json` について

現時点のサーバーにある `users.json` は上記の自動生成とは異なり、以下の 2 アカウントが存在します：

| ID | メールアドレス | 備考 |
|----|--------------|------|
| `admin1` | admin@example.com | 管理者。パスワードは個別に設定済み |
| `user_resident_guest` | resident@example.com | 一般住民デモアカウント |

> この `users.json` は `initializeData()` で生成されたものではなく、
> 手動で修正されたものです。新サーバーへのコピー時はこのファイルを持ち込まず、
> 初回アクセスで自動生成させてから改めてユーザー登録を行ってください。

---

## 7. VAPID 鍵の管理

VAPID 鍵はプッシュ通知に必須の公開鍵/秘密鍵ペアです。

### 鍵の保存場所

```
kairanban/data/vapid.json   ← サーバー上のみに存在（.gitignore で git 管理外）
```

```json
{
    "publicKey": "BOD-84nzA76...（公開鍵）",
    "privateKey": "RhR3I8w7u7...（秘密鍵）",
    "subject": "mailto:noreply@example.com"
}
```

### 鍵の生成手順

1. 管理者アカウントでログイン
2. `https://yourdomain.com/kairanban/api/generate-vapid-keys.php` にアクセス
3. 新しい鍵が生成され `data/vapid.json` に上書き保存される
4. **画面に秘密鍵が表示されるので、確認後すぐにブラウザ履歴を削除する**

### 鍵を変更した場合の影響

VAPID 鍵を変更すると、**既存のプッシュ購読（subscriptions.json）がすべて無効になります。**
変更後は全ユーザーが再度「通知を許可」の操作が必要になります。

### subject の変更

`generate-vapid-keys.php` にハードコードされているメールアドレスを
実際の管理者メールアドレスに変更することを推奨します：

```php
// kairanban/api/generate-vapid-keys.php の 16 行目
'subject' => 'mailto:noreply@inoue-de.com'  ← ここを変更
```

---

## 8. システムリセット（地域移管）

別の地域・町内会に展開する場合は、管理画面からシステムリセットを実行します。

### アクセス方法

```
https://yourdomain.com/kairanban/api/reset_system.php
```

管理者でログインした状態でアクセスしてください。

### リセットで削除されるデータ

| データ | 操作 |
|--------|------|
| 全回覧板の投稿 | 完全削除 |
| admin 以外の全ユーザー | 削除（admin1 と user_resident_guest は保持） |
| 全プッシュ購読情報 | admin1 のものを除いて削除 |
| 監査ログ | 完全クリア |
| 保留中の通知 | 完全クリア |
| エラーログ | 完全クリア |
| アップロード画像 | 完全削除 |

### ⚠️ 重要な注意事項

- **この操作は取り消せません**
- リセット後は必ず VAPID 鍵を新たに生成してください（旧コミュニティとの混在防止）
- リセット前にバックアップとして `data/` フォルダ全体をダウンロードすることを推奨

### リセット後の再セットアップ

1. `reset_system.php` でリセット実行
2. `generate-vapid-keys.php` で VAPID 鍵を新規生成
3. 管理者パスワードを変更
4. 管理画面でユーザーを新規登録
5. 管理画面で班（所属）を設定
6. 各ユーザーが「通知を許可」を再設定

---

## 9. メンテナンス用スクリプト

`api/` 以下に管理者専用のメンテナンス用スクリプトがあります。
**すべて管理者ログイン済みでないとアクセスできません。**

| スクリプト | 用途 |
|-----------|------|
| `debug.php?action=view` | エラーログの最新100行を表示 |
| `debug.php?action=clear` | エラーログをクリア |
| `check-subscriptions.php` | 全プッシュ購読の一覧表示（確認用） |
| `check_subscription_details.php` | 購読の詳細情報表示 |
| `cleanup_expired_subscriptions.php` | 期限切れ・重複購読の自動削除 |
| `cleanup_orphaned_subscriptions.php` | 削除済みユーザーの購読情報を削除 |
| `remove_duplicate_subscriptions.php` | 同一ユーザーの重複購読を削除 |
| `fix_subscriptions_structure.php` | subscriptions.json の構造修復 |

> `cleanup_subscriptions.php` は古いデータベース版の残骸で正常に動作しません。
> 使用しないでください。

---

## 10. ファイルパーミッション詳細

### 推奨設定一覧

| パス | パーミッション | 理由 |
|------|--------------|------|
| `kairanban/` | `755` | Web サーバーが読み取り可能 |
| `kairanban/api/` | `755` | 同上 |
| `kairanban/api/*.php` | `644` | 実行不要（PHP は Apache が処理） |
| `kairanban/assets/` | `755` | 同上 |
| `kairanban/vendor/` | `755` | 同上 |
| `kairanban/data/` | **`755`** | Apache が書き込める必要がある |
| `kairanban/data/.htaccess` | `644` | Apache が読み取り可能 |
| `kairanban/data/*.json` | `644` | Apache が読み書き可能 |
| `kairanban/data/vapid.json` | `600` | 所有者のみ読み取り可能（秘密鍵保護） |

### 一括設定コマンド（SSH が使える場合）

```bash
# プロジェクトルートで実行
cd /var/www/html/kairanban   # ← 実際のパスに置き換える

# ディレクトリを 755 に設定
find . -type d -exec chmod 755 {} \;

# ファイルを 644 に設定
find . -type f -exec chmod 644 {} \;

# data ディレクトリを Apache 所有に変更（Ubuntu/Debian の場合）
sudo chown -R www-data:www-data data/

# vapid.json（生成後）は 600 に制限
chmod 600 data/vapid.json
```

### Apache のユーザー確認

```bash
# Ubuntu / Debian
grep -i "^User" /etc/apache2/envvars
# → export APACHE_RUN_USER=www-data

# CentOS / RHEL / Amazon Linux
grep -i "^User" /etc/httpd/conf/httpd.conf
# → User apache

# いずれでもない場合
ps aux | grep httpd | grep -v grep | head -1 | awk '{print $1}'
```

---

## 11. セキュリティ上の注意点

### 本番環境で必ず確認するチェックリスト

- [ ] `data/users.json` の管理者パスワードを変更済みである
- [ ] デモアカウント（tanaka@example.com、sato@example.com 等）を削除済みである
- [ ] ブラウザから `https://yourdomain.com/kairanban/data/users.json` にアクセスして `403` が返ることを確認した
- [ ] VAPID 鍵を生成済みである（`data/vapid.json` が存在する）
- [ ] HTTPS で運用している（http:// では Service Worker とプッシュ通知が動作しない）
- [ ] Apache の `AllowOverride All` が設定されている

### セキュリティ設定の概要

| 設定 | 内容 |
|------|------|
| セッション Cookie | `HttpOnly`, `Secure`, `SameSite=Strict` — XSS・CSRF 対策 |
| パスワード | `password_hash()` + bcrypt — レインボーテーブル対策 |
| HSTS | `max-age=31536000; includeSubDomains` — HTTPS 強制 |
| data/ 保護 | `.htaccess` で全アクセス禁止 — 秘密鍵・ユーザーデータ保護 |
| セッション固定 | ログイン後に `session_regenerate_id(true)` 実行 |
| 入力検証 | `sanitize()` で HTMLエスケープ + 最大1000文字 |
| IDOR 対策 | 購読操作はセッションのユーザー ID のみ使用 |

### 既知の制約（対応は外部設定で行う）

| 制約 | 対応策 |
|------|--------|
| ログイン試行のレートリミットなし | Apache `mod_ratelimit` または WAF で制限を設ける |
| CSRF トークン未実装 | `SameSite=Strict` Cookie で実用上は十分に緩和済み |
| JSON ファイルへの同時書き込み競合 | `LOCK_EX` で軽減済みだが、重負荷では SQLite 移行を検討 |

---

## 12. トラブルシューティング

### プッシュ通知が届かない

1. `data/vapid.json` が存在するか確認
   - 存在しない → `generate-vapid-keys.php` で生成する
2. HTTPS で動作しているか確認
3. ブラウザの通知許可が「許可」になっているか確認
4. `debug.php?action=view` でエラーログを確認

### ログインできない

1. `data/users.json` が存在するか確認
2. 存在しない場合 → `data/` のパーミッションを確認し、再アクセスで自動生成させる
3. 存在するが入れない → `api/generate-vapid-keys.php` は使えないが、
   SSH が使えれば以下で新しいパスワードハッシュを生成できる：
   ```bash
   php -r "echo password_hash('新しいパスワード', PASSWORD_DEFAULT);"
   ```
   出力されたハッシュを `data/users.json` の `passwordHash` フィールドに貼り付ける

### `data/users.json` を直接編集する場合

```json
[
    {
        "id": "admin1",
        "name": "管理者名",
        "email": "admin@yourdomain.com",
        "role": "ADMIN",
        "department": "北班",
        "passwordHash": "$2y$10$..."   ← php -r で生成したハッシュを貼り付け
    }
]
```

> **注意:** JSON の構文エラー（末尾のカンマや引用符の欠落等）があると
> ログインが完全にできなくなります。編集後は JSON バリデーターで確認してください。
> （例: https://jsonlint.com/）

### Apache が `.htaccess` を読み込まない

```bash
# mod_rewrite と mod_authz_host が有効か確認
apache2ctl -M | grep -E "rewrite|authz"

# 有効化（Ubuntu/Debian）
sudo a2enmod rewrite
sudo a2enmod authz_host
sudo systemctl restart apache2

# httpd.conf または apache2.conf で AllowOverride を確認
<Directory /var/www/html>
    AllowOverride All   ← None になっていたら All に変更
</Directory>
```

### エラーログの確認方法

```bash
# SSH が使える場合
tail -f /var/www/html/kairanban/data/error.log

# ブラウザから（管理者ログイン後）
https://yourdomain.com/kairanban/api/debug.php?action=view
```

---

## バージョン情報

| 項目 | 内容 |
|------|------|
| Web Push ライブラリ | minishlink/web-push ^9.0 |
| 対応 PHP | 7.4 以上 |
| 最終更新 | 2026年3月 |
