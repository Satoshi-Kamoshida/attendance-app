# attendance-app

<div align="right">
<p><strong>開発者：鴨志田 悟</strong></p>
<p><strong>開発開始日：2026年9月18日</strong></p>
<p><strong>開発完了締切日：2026年11月2日</strong></p>
<p><strong>提出日：2026年_月_日</strong></p>
</div>

## 概要

勤怠管理システムのアプリケーションです。下記を実装致しました。

1.

## 使用技術

- PHP 8.2
- Laravel 10.x
- MySQL 8.4
- Nginx
- Vite
- Docker
- Laravel Sail
- phpMyAdmin
- mailpit
- Fortify

## ローカル開発環境URL

| サービス   | URL                   | 用途             |
| ---------- | --------------------- | ---------------- |
| Laravel    | http://localhost      | アプリケーション |
| phpMyAdmin | http://localhost:8080 | データベース管理 |
| Mailpit    | http://localhost:8025 | メール確認       |

## 画面一覧（動作確認用）

| 画面ID | 画面名称                             | パス                                                                              |
| ------ | ------------------------------------ | --------------------------------------------------------------------------------- |
| PG01   | 会員登録画面（一般ユーザー）         | http://localhost/register                                                         |
| PG02   | ログイン画面（一般ユーザー）         | http://localhost/login                                                            |
| PG03   | 勤怠登録画面（一般ユーザー）         | http://localhost/attendance                                                       |
| PG04   | 勤怠一覧画面（一般ユーザー）         | http://localhost/attendance/list                                                  |
| PG05   | 勤怠詳細画面（一般ユーザー）         | http://localhost/attendance/detail/{id}                                           |
| PG06   | 申請一覧画面（一般ユーザー）         | http://localhost/stamp_correction_request/list                                    |
| PG07   | ログイン画面（管理者）               | http://localhost/admin/login                                                      |
| PG08   | 勤怠一覧画面（管理者）               | http://localhost/admin/attendance/list                                            |
| PG09   | 勤怠詳細画面（管理者）               | http://localhost/admin/attendance/{id}                                            |
| PG10   | スタッフ一覧画面（管理者）           | http://localhost/admin/staff/list                                                 |
| PG11   | スタッフ別勤怠一覧画面（管理者）     | http://localhost/admin/attendance/staff/{id}                                      |
| PG12   | 申請一覧画面（管理者）               | http://localhost/stamp_correction_request/list                                    |
| PG13   | 修正申請承認画面（管理者）           | http://localhost/stamp_correction_request/approve/{attendance_correct_request_id} |
| PG14   | マイ勤怠レポート画面（一般ユーザー） | http://localhost/attendance/report                                                |

## 環境構築

### 1. Laravelプロジェクトの作成

作業ディレクトリを作成後、Laravel 10.xを指定してプロジェクトを作成

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" -w /var/www/html \
  -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
  laravelsail/php82-composer:latest \
  composer create-project laravel/laravel:^10.0 attendance-app
```

### 2. Laravel Sailの導入

MySQL/mailpitを指定してSailの設定ファイルをパブリッシュ

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php82-composer:latest \
  php artisan sail:install --with=mysql,mailpit
```

**※エイリアスの設定・アプリケーションキーの生成**<br>

1. Sailをバックグラウンドで起動<br>

```bash
./vendor/bin/sail up -d
```

2. エイリアスを設定して 'sail' だけでコマンドを実行できるようにする

```bash
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
```

3. アプリケーションキーの生成

```bash
sail artisan key:generate
```

### 3. .env ファイルの設定確認

.envファイルが下記と一致している事を確認する。

```text
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### 4. PHP / Laravel / MySQLのバージョン確認

確認方法

```bash
sail php --version
sail artisan --version
sail mysql --version
```

> ⚠️ 技術スタックが指定と異なった為、下記要領にてPHPを変更

compose.yamlを確認<br>
↓<br>
PHP 8.5 → 8.2<br>
↓<br>
Dockerイメージを再ビルド<br>

```bash
sail down
sail build --no-cache
sail up -d
```

↓<br>
バージョン確認<br>

### 5. データベース接続確認

LaravelからMySQLへ接続できることを確認します。

```bash
sail artisan migrate --seed
```

### 6. phpMyAdminの設定

phpMyAdminをDocker Composeに追加し、ブラウザからMySQLデータベースを確認

**追加したコード（MySQLと同じインデントへ）**

```yaml
phpmyadmin:
    image: "phpmyadmin:latest"
    ports:
        - "${FORWARD_PHPMYADMIN_PORT:-8080}:80"
    environment:
        PMA_HOST: mysql
        PMA_USER: "${DB_USERNAME}"
        PMA_PASSWORD: "${DB_PASSWORD}"
    networks:
        - sail
    depends_on:
        - mysql
```

### 7. フロントエンド環境

#### Viteの起動

1. NPM依存パッケージのインストール

```bash
sail npm install
```

2. vite開発サーバーの起動
    > ⚠️ 注意点：CSS・JavaScriptなどのフロントエンド処理が発生する場合に、別のターミナルで起動する。

```bash
sail npm run dev
```

#### 提供bladeを移入し、vite.config.jsを下記に置き換える。

```js
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/js/app.js",
                "resources/css/sanitize.css",
                "resources/css/common.css",
                "resources/css/auth/verify-email.css",
                "resources/css/user/register.css",
                "resources/css/user/user-login.css",
                "resources/css/user/attendance-register.css",
                "resources/css/user/user-attendance-list.css",
                "resources/css/user/user-detail.css",
                "resources/css/user/user-application-list.css",
                "resources/css/admin/admin-login.css",
                "resources/css/admin/admin-attendance-list.css",
                "resources/css/admin/admin-detail.css",
                "resources/css/admin/admin-application-list.css",
                "resources/css/admin/admin-application-detail.css",
                "resources/css/admin/staff-list.css",
                "resources/css/admin/staff-attendance-list.css",
                "resources/css/reports/index.css", // ※ 応用（マイ勤怠レポート）用。
            ],
            refresh: true,
        }),
    ],
});
```

#### Bladeファイルの配置

提供されたBladeファイルをクローンして、デフォルトのresourcesディレクトリへ置換する<br>
指定開発フローに従い、下記の順番でクローン→置換<br>

1. 基本機能実装時

```bash
git clone -b basic <提供リポジトリURL>
```

2. 応用機能実装時

```bash
git clone -b advanced <提供リポジトリURL>
```

### 8. 動作確認

下記のローカル開発環境URLを入力し、ブラウザよりアクセス。<br>
Laravel / phpMyAdmin / mailpit
