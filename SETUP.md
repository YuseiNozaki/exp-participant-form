# 視覚探索実験予約システム - セットアップガイド

## 概要
このシステムは2025年8月8日〜8月31日の視覚探索実験参加者の予約管理を行うWebアプリケーションです。

## 技術要件

### サーバー要件
- PHP 7.4 以上
- MySQL 5.7 以上 または MariaDB 10.3 以上
- Apache または Nginx
- SMTP サーバー（メール送信用）

### AWS Elastic Beanstalk デプロイ用
- PHP 8.0 以上対応の Elastic Beanstalk 環境
- RDS MySQL インスタンス
- SES（Simple Email Service）推奨

## セットアップ手順

### 1. ファイルのアップロード
```bash
# プロジェクトファイルをサーバーにアップロード
# public/ フォルダがドキュメントルートになるように設定
```

### 2. データベースのセットアップ
```bash
# MySQL にログイン
mysql -u root -p

# データベースとテーブルの作成
source database/schema.sql

# 初期時間枠データの投入
source database/init_slots.sql
```

### 3. データベース設定の変更
`config/database.php` を編集して、実際のデータベース情報を設定：

```php
private static $host = 'your-database-host';
private static $dbname = 'your-database-name';
private static $username = 'your-username';
private static $password = 'your-password';
```

### 4. Webサーバーの設定

#### Apache の場合
`public/.htaccess` を作成：
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api.php/$1 [QSA,L]

# セキュリティヘッダー
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx の場合
サーバー設定に追加：
```nginx
location /api/ {
    try_files $uri $uri/ /api.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### 5. リマインダーメールの自動送信設定
crontab に以下を追加：
```bash
# 毎日12:00にリマインダーメール送信
0 12 * * * /usr/bin/php /path/to/project/scripts/send_reminders.php
```

### 6. 権限設定
```bash
# ファイル権限の設定
chmod 755 scripts/send_reminders.php
chmod 644 public/*.php
chmod 644 public/*.html
```

## AWS Elastic Beanstalk デプロイ

### 1. アプリケーション設定
1. Elastic Beanstalk アプリケーションを作成
2. PHP 8.0 プラットフォームを選択
3. `public/` フォルダをドキュメントルートに設定

### 2. RDS データベース設定
1. RDS MySQL インスタンスを作成
2. セキュリティグループでEB環境からのアクセスを許可
3. データベース接続情報を環境変数で設定

### 3. 環境変数の設定
Elastic Beanstalk 環境で以下の環境変数を設定：
```
DB_HOST=your-rds-endpoint
DB_NAME=reservation_system
DB_USER=your-db-username
DB_PASS=your-db-password
```

### 4. デプロイ設定ファイル
`.ebextensions/01-php.config` を作成：
```yaml
option_settings:
  aws:elasticbeanstalk:container:php:phpini:
    document_root: /public
    memory_limit: 256M
    max_execution_time: 60
    
  aws:elasticbeanstalk:application:environment:
    DB_HOST: your-rds-endpoint
    DB_NAME: reservation_system
```

## セキュリティ設定

### 1. 管理者パスワードの変更
データベースで管理者パスワードを変更：
```sql
UPDATE admin_users 
SET password_hash = '$2y$10$新しいハッシュ' 
WHERE username = 'admin';
```

### 2. セッションセキュリティ
`php.ini` または `.htaccess` で設定：
```
session.cookie_secure = 1
session.cookie_httponly = 1
session.use_strict_mode = 1
```

### 3. ファイルアクセス制限
重要なディレクトリへの直接アクセスを制限：
```apache
<Directory "/path/to/project/config">
    Deny from all
</Directory>

<Directory "/path/to/project/src">
    Deny from all
</Directory>
```

## テスト実行

### PHPUnit テストの実行
```bash
# テスト用データベースの準備
mysql -u root -p
CREATE DATABASE reservation_system_test;

# テスト実行
./vendor/bin/phpunit
```

### 手動テスト
1. `/index.html` - 参加者予約画面のテスト
2. `/admin.html` - 管理画面のテスト（admin/admin123）
3. メール送信機能のテスト

## 運用開始前のチェックリスト

- [ ] データベースが正常に作成されている
- [ ] 時間枠データが正しく投入されている
- [ ] 参加者予約フローが正常に動作する
- [ ] 管理画面で時間枠の公開/非公開切替ができる
- [ ] 予約確認メールが送信される
- [ ] リマインダーメールのcronジョブが設定されている
- [ ] SSL証明書が設定されている（HTTPS）
- [ ] 管理者パスワードが変更されている
- [ ] バックアップ体制が整っている

## トラブルシューティング

### データベース接続エラー
1. `config/database.php` の設定を確認
2. MySQL サービスが起動しているか確認
3. ファイアウォール設定を確認

### メール送信エラー
1. SMTP サーバー設定を確認
2. PHP の `mail()` 関数が有効か確認
3. メールログを確認

### 権限エラー
1. ファイル・ディレクトリの権限を確認
2. SELinux 設定を確認（CentOS/RHEL）

## サポート
技術的な問題が発生した場合は、以下の情報と共にお問い合わせください：
- サーバー環境（OS、PHP、MySQL バージョン）
- エラーメッセージ
- 実行した手順