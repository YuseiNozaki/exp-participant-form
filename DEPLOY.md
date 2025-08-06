# AWS Elastic Beanstalk デプロイマニュアル

## 概要
このマニュアルでは、視覚探索実験予約システムをAWS Elastic Beanstalk（EB）を使用してGUI操作でデプロイする手順を説明します。

## 前提条件

### 必要なAWSサービス
- AWS Elastic Beanstalk
- Amazon RDS (MySQL)
- Amazon SES (Simple Email Service) ※推奨

### 事前準備
- AWSアカウントが作成済み
- AWS Management Consoleへのアクセス権限
- デプロイするアプリケーションファイル一式

## デプロイ手順

### ステップ 1: Amazon RDS データベースの作成

#### 1.1 RDS コンソールにアクセス
1. AWS Management Console にログイン
2. サービス検索で「RDS」を検索してクリック
3. 「データベースを作成」ボタンをクリック

#### 1.2 データベース設定
1. **エンジンのオプション**
   - データベース作成方法: 「標準作成」を選択
   - エンジンタイプ: 「MySQL」を選択
   - バージョン: 「MySQL 8.0.35」（最新の安定版）

2. **テンプレート**
   - 「無料利用枠」を選択（開発・テスト用）
   - または「本番稼働用」（本番環境用）

3. **設定**
   - DB インスタンス識別子: `exp-participant-db`
   - マスターユーザー名: `admin`
   - マスターパスワード: 安全なパスワードを設定（メモしておく）

4. **DB インスタンスクラス**
   - 無料利用枠の場合: `db.t3.micro`
   - 本番環境の場合: `db.t3.small` 以上

5. **ストレージ**
   - ストレージタイプ: 「汎用 SSD (gp2)」
   - 割り当てストレージ: 20 GB
   - ストレージの自動スケーリング: 有効

6. **接続**
   - VPC: デフォルト VPC
   - パブリックアクセス: 「はい」（EB からアクセスするため）
   - VPC セキュリティグループ: 「新しいセキュリティグループを作成」
   - セキュリティグループ名: `exp-participant-db-sg`

7. **データベース認証**
   - パスワード認証を選択

8. **追加設定**
   - 初期データベース名: `reservation_system`
   - バックアップ保持期間: 7日
   - 暗号化: 有効

9. 「データベースを作成」をクリック

#### 1.3 セキュリティグループの設定
1. RDS作成完了後、EC2コンソールに移動
2. 左メニューから「セキュリティグループ」を選択
3. `exp-participant-db-sg` を選択
4. 「インバウンドルール」タブで「ルールを編集」
5. 「ルールを追加」をクリック
   - タイプ: MySQL/Aurora (3306)
   - ソース: 後で EB のセキュリティグループを指定（一旦 0.0.0.0/0 で設定）
6. 「ルールを保存」

### ステップ 2: データベースの初期設定

#### 2.1 データベースへの接続
1. RDS インスタンスの詳細画面でエンドポイントをコピー
2. MySQL クライアント（MySQL Workbench、phpMyAdmin など）を使用して接続
   - ホスト: RDS エンドポイント
   - ポート: 3306
   - ユーザー名: admin
   - パスワード: 設定したマスターパスワード
   - データベース: reservation_system

#### 2.2 テーブルの作成
1. 以下のSQLを実行してテーブルを作成:

```sql
-- 管理者テーブル
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 時間枠テーブル
CREATE TABLE slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (date, start_time)
);

-- 参加者テーブル
CREATE TABLE participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 予約テーブル
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    slot_id INT NOT NULL,
    status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(id),
    FOREIGN KEY (slot_id) REFERENCES slots(id),
    UNIQUE KEY unique_reservation (participant_id, slot_id)
);
```

2. 管理者ユーザーを作成:

```sql
-- 管理者ユーザー追加 (ユーザー名: admin, パスワード: admin123)
INSERT INTO admin_users (username, password_hash) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
```

3. サンプル時間枠データを追加:

```sql
-- 2025年8月8日〜31日の時間枠データ
INSERT INTO slots (date, start_time, end_time) VALUES
('2025-08-08', '10:00:00', '11:00:00'),
('2025-08-08', '11:00:00', '12:00:00'),
('2025-08-08', '13:00:00', '14:00:00'),
('2025-08-08', '14:00:00', '15:00:00'),
('2025-08-08', '15:00:00', '16:00:00'),
('2025-08-09', '10:00:00', '11:00:00'),
('2025-08-09', '11:00:00', '12:00:00'),
('2025-08-09', '13:00:00', '14:00:00'),
('2025-08-09', '14:00:00', '15:00:00'),
('2025-08-09', '15:00:00', '16:00:00');
-- 必要に応じて他の日付も追加
```

### ステップ 3: アプリケーション ファイルの準備

#### 3.1 設定ファイルの編集
1. プロジェクトの `config/database.php` を編集:

```php
<?php
class Database {
    private static $host;
    private static $dbname = 'reservation_system';
    private static $username;
    private static $password;
    private static $pdo = null;
    
    public static function connect() {
        if (self::$pdo === null) {
            // 環境変数から設定を取得（EB の環境変数）
            self::$host = $_ENV['RDS_HOSTNAME'] ?? getenv('DB_HOST');
            self::$username = $_ENV['RDS_USERNAME'] ?? getenv('DB_USER');
            self::$password = $_ENV['RDS_PASSWORD'] ?? getenv('DB_PASS');
            self::$dbname = $_ENV['RDS_DB_NAME'] ?? getenv('DB_NAME') ?? 'reservation_system';
            
            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8";
                self::$pdo = new PDO($dsn, self::$username, self::$password);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw $e;
            }
        }
        return self::$pdo;
    }
}
?>
```

#### 3.2 デプロイ用設定ファイルの作成

1. プロジェクトルートに `.ebextensions` フォルダを作成
2. `.ebextensions/01-php.config` ファイルを作成:

```yaml
option_settings:
  aws:elasticbeanstalk:container:php:phpini:
    document_root: /public
    memory_limit: 256M
    max_execution_time: 60
    post_max_size: 10M
    upload_max_filesize: 10M
    
  aws:elasticbeanstalk:application:environment:
    COMPOSER_HOME: /root
```

3. `.ebextensions/02-commands.config` ファイルを作成:

```yaml
commands:
  01_install_composer:
    command: |
      if [ ! -f /usr/local/bin/composer ]; then
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
      fi
    ignoreErrors: true

container_commands:
  01_composer_install:
    command: "composer install --no-dev --optimize-autoloader"
    leader_only: true
```

4. `.ebextensions/03-https-redirect.config` ファイルを作成:

```yaml
files:
  "/etc/httpd/conf.d/ssl_rewrite.conf":
    mode: "000644"
    owner: root
    group: root
    content: |
      RewriteEngine On
      <If "-n '%{HTTP:X-Forwarded-Proto}' && %{HTTP:X-Forwarded-Proto} != 'https'">
      RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R,L]
      </If>
```

#### 3.3 アプリケーション アーカイブの作成
1. 不要なファイルを除外するため `.ebignore` ファイルを作成:

```
.git/
.gitignore
.DS_Store
Thumbs.db
tests/
*.md
/vendor/
/node_modules/
.env
.env.local
```

2. プロジェクトファイルを ZIP 形式でアーカイブ
   - Windowsの場合: プロジェクトフォルダを選択して右クリック → 「送る」→ 「圧縮フォルダー」
   - macOSの場合: プロジェクトフォルダを選択して右クリック → 「"フォルダ名"を圧縮」
   - **注意**: フォルダではなく、フォルダの中身を ZIP にする

### ステップ 4: Elastic Beanstalk アプリケーションの作成

#### 4.1 Elastic Beanstalk コンソールにアクセス
1. AWS Management Console で「Elastic Beanstalk」を検索
2. Elastic Beanstalk ダッシュボードを開く
3. 「アプリケーションの作成」をクリック

#### 4.2 アプリケーション設定
1. **アプリケーション情報**
   - アプリケーション名: `exp-participant-form`
   - アプリケーションタグ: 任意（例: Environment=Production）

2. **プラットフォーム**
   - プラットフォーム: 「PHP」を選択
   - プラットフォームブランチ: 「PHP 8.1 running on 64bit Amazon Linux 2」
   - プラットフォームバージョン: 推奨バージョンを選択

3. **アプリケーションコード**
   - 「コードをアップロード」を選択
   - 「ローカルファイル」を選択
   - 「ファイルを選択」で作成した ZIP ファイルを選択
   - バージョンラベル: `v1.0.0` (任意)

4. **プリセット**
   - 設定プリセット: 「単一インスタンス（無料利用枠対象）」または「高可用性」

5. 「アプリケーションの作成」をクリック

#### 4.3 環境作成の確認
- デプロイには 5-10 分程度かかります
- 進行状況はコンソールで確認できます
- 「Health」が「OK」になったら次のステップに進みます

### ステップ 5: 環境変数の設定

#### 5.1 環境設定にアクセス
1. 作成された環境の名前をクリック
2. 左メニューから「設定」をクリック
3. 「ソフトウェア」セクションの「編集」をクリック

#### 5.2 環境プロパティの追加
以下の環境変数を追加:

```
DB_HOST = [RDS エンドポイント]
DB_NAME = reservation_system
DB_USER = admin
DB_PASS = [RDS マスターパスワード]
RDS_HOSTNAME = [RDS エンドポイント]
RDS_USERNAME = admin  
RDS_PASSWORD = [RDS マスターパスワード]
RDS_DB_NAME = reservation_system
```

#### 5.3 設定の適用
1. 「適用」ボタンをクリック
2. 環境の更新が完了するまで待機（3-5分程度）

### ステップ 6: セキュリティグループの更新

#### 6.1 EB インスタンスのセキュリティグループを確認
1. EC2 コンソールに移動
2. 「インスタンス」でEB インスタンスを確認
3. セキュリティグループ名をメモ

#### 6.2 RDS セキュリティグループを更新
1. EC2 コンソールの「セキュリティグループ」に移動
2. RDS用のセキュリティグループ (`exp-participant-db-sg`) を選択
3. 「インバウンドルール」を編集
4. MySQL (3306) のソースを EB インスタンスのセキュリティグループに変更
5. 「ルールを保存」

### ステップ 7: 動作確認

#### 7.1 アプリケーションのアクセス
1. EB 環境の URL をコピー（例: http://exp-participant-form.us-west-2.elasticbeanstalk.com）
2. ブラウザでアクセス

#### 7.2 機能テスト
1. **参加者画面のテスト**
   - `[EB URL]/index.html` にアクセス
   - 参加者登録フォームが表示されることを確認
   - テスト予約を作成

2. **管理画面のテスト**
   - `[EB URL]/admin.html` にアクセス
   - ログイン（admin / admin123）
   - ダッシュボードが表示されることを確認
   - 時間枠の公開/非公開切替をテスト

3. **API のテスト**
   - ブラウザの開発者ツールでAPI呼び出しを確認
   - エラーがないことを確認

### ステップ 8: HTTPS の設定（推奨）

#### 8.1 SSL 証明書の設定
1. EB 環境の「設定」→「ロードバランサー」→「編集」
2. 「リスナー」セクションで「リスナーを追加」
3. プロトコル: HTTPS、ポート: 443
4. SSL 証明書: AWS Certificate Manager で証明書を作成・選択
5. 「適用」をクリック

#### 8.2 HTTP から HTTPS へのリダイレクト設定
- 前述の `.ebextensions/03-https-redirect.config` で自動設定済み

### ステップ 9: SES メール設定（推奨）

#### 9.1 SES コンソールでドメイン認証
1. SES コンソールにアクセス
2. 「ドメイン」または「メールアドレス」を認証
3. DNS レコードを設定（ドメインの場合）

#### 9.2 アプリケーションでのSES設定
1. IAM でSES送信権限を持つロールを作成
2. EB インスタンスにロールを割り当て
3. アプリケーションコードでSES SDK を使用するように変更

### ステップ 10: 監視とログの設定

#### 10.1 CloudWatch ログの有効化
1. EB 環境の「設定」→「ソフトウェア」→「編集」
2. 「ログファイル」セクションでログのローテーションを有効化
3. CloudWatch Logs への送信を有効化

#### 10.2 アラームの設定
1. CloudWatch コンソールでアラームを設定
2. CPU 使用率、レスポンス時間などを監視

## 運用・保守

### 定期的なタスク

#### 日次
- アプリケーションの動作確認
- ログの確認
- データベースの容量確認

#### 週次
- セキュリティ更新の確認
- バックアップの動作確認

#### 月次
- 使用量とコストの確認
- 性能の最適化検討

### アップデート手順

#### アプリケーションの更新
1. 新しい ZIP ファイルを準備
2. EB コンソールで「アップロードとデプロイ」
3. 新しいバージョンをアップロード
4. デプロイ実行

#### 環境の更新
1. EB プラットフォームの更新通知を確認
2. メンテナンス期間を設定
3. 環境の更新を実行

### トラブルシューティング

#### よくある問題

1. **500エラーが発生**
   - EB ログを確認
   - データベース接続を確認
   - 環境変数の設定を確認

2. **データベースに接続できない**
   - セキュリティグループの設定を確認
   - RDS インスタンスの状態を確認
   - 認証情報を確認

3. **メールが送信されない**
   - SES の設定を確認
   -送信制限を確認
   - IAM 権限を確認

#### ログの確認方法
1. EB コンソールで「ログ」を選択
2. 「最後の100行を要求」または「完全なログをダウンロード」
3. エラーメッセージを確認

#### ロールバック手順
1. EB コンソールで「アプリケーションバージョン」を選択
2. 前の安定バージョンを選択
3. 「デプロイ」をクリック

## まとめ

このマニュアルに従って、AWS Elastic Beanstalk を使用したGUI操作によるデプロイが完了します。デプロイ後は定期的な監視と保守を行い、安定したサービス提供を心がけてください。

問題が発生した場合は、AWS サポート、EB ログ、CloudWatch メトリクスを活用してトラブルシューティングを行ってください。