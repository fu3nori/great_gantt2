# Great Gantt：さくらのレンタルサーバ デプロイ手順

この手順書は、Great Gantt（Laravel 12 / Vite）を「さくらのレンタルサーバ」へ WinSCP で配置するためのものです。サーバーでは Node.js / npm を一切使用せず、JavaScript と CSS はローカルPCでビルドしてからアップロードします。

確認日：2026年8月31日

## 1. この手順の前提

### 1.1 必要なプラン

この手順では、SSH/SFTP、PHP CLI、MySQLを使うため、原則として「スタンダード」以上が必要です。ライトプランではSSHおよびCRONを利用できないため、この手順どおりには作業できません。

さくらの公式仕様で確認した主な点は次のとおりです。

- サーバーはFreeBSDの共用環境で、root権限はありません。
- Webサーバーはnginx＋Apache 2.4系で、`.htaccess` と `mod_rewrite` を利用できます。
- PHP 8系とPHP CLIを利用できます。Great GanttはPHP 8.2以上が必要です。
- MySQL 8.0、InnoDB、utf8mb4を利用できます。データベースはスタンダード以上で提供されます。
- SSH/SFTPはスタンダード以上で利用でき、初期アカウントで接続します。
- ホームは `/home/アカウント名/`、通常のWeb領域は `/home/アカウント名/www/` です。

契約サーバーによって選択できるPHPの版が異なることがあるため、コントロールパネルで実際の選択肢を確認し、PHP 8.3以上の利用可能な版を選びます。最低条件はPHP 8.2です。

### 1.2 レンタルサーバー上で行わないこと

サーバーでは次を実行しません。

```text
npm install
npm ci
npm run dev
npm run build
php artisan serve
php artisan reverb:start
php artisan queue:work を常駐させること
```

LaravelはApacheから実行されるため、通常の「起動コマンド」はありません。ファイル配置、設定、Composer、マイグレーションが完了し、ドメインのWeb公開フォルダーを `public` に向けた時点で起動状態になります。

### 1.3 ReverbとQueueの扱い

さくらのレンタルサーバは共用環境であり、利用者がWebSocket用ポートを公開したり、リバースプロキシやSupervisorを設定したりできません。このため、本手順では次の構成にします。

```dotenv
BROADCAST_CONNECTION=log
QUEUE_CONNECTION=sync
```

- 招待メールや通知はWebリクエスト内で同期送信されるため、Queue Workerは不要です。
- 保存、WBS、ガントチャート、チケット、ログインなどの通常機能は利用できます。
- 別ブラウザへの即時リアルタイム反映は無効になります。相手側では画面再読み込み後に変更が反映されます。
- リアルタイム同期が必須なら、外部のPusher互換サービスを別途契約・設定するか、VPS等へ移行してください。

フロントエンドは `VITE_REVERB_APP_KEY` が空ならWebSocket接続を開始しない実装になっています。

## 2. 作業前に用意する値

以下を実際の値に置き換えてください。

| 項目 | この手順内の例 | 確認場所 |
| --- | --- | --- |
| さくらのアカウント名 | `example` | 初期ドメインの `example.sakura.ne.jp` の `example` 部分 |
| 初期ドメイン | `example.sakura.ne.jp` | 契約完了メール／コントロールパネル |
| 公開ドメイン | `gantt.example.jp` | ドメイン/SSL |
| アプリ配置先 | `/home/example/www/great-gantt` | 本手順で作成 |
| Web公開フォルダー | `great-gantt/public` | ドメイン/SSLの基本設定 |
| DBホスト名 | `mysql0000.db.sakura.ne.jp` | Webサイト/データ → データベース |
| DB名 | `実際のデータベース名` | 同上 |
| DBユーザー名 | `実際の接続ユーザー名` | 同上 |
| DBポート | `3306` | 固定値 |
| DBパスワード | `実際の接続パスワード` | DB作成時に設定した値 |

重要：さくらのレンタルサーバのDBは通常、Webサーバーとは別ホストです。`DB_HOST=127.0.0.1` や `DB_HOST=localhost` にはせず、コントロールパネルに表示された `mysql....db.sakura.ne.jp` をそのまま指定します。

## 3. サーバーコントロールパネルの事前設定

### 3.1 PHPを設定する

1. サーバーコントロールパネルへ初期ドメインまたは追加済みドメインでログインします。
2. 「スクリプト設定」→「言語バージョン設定」を開きます。
3. 利用可能なPHP 8.3以上を選びます。選べない場合でもPHP 8.2以上が必須です。
4. スタンダード以上では、利用可能ならモジュールモードを選びます。

Web側とSSH側のPHPが一致するかは、アップロード後に `php -v` でも確認します。

### 3.2 ドメインとSSLを準備する

1. 「ドメイン/SSL」で公開ドメインを追加します。
2. DNSをさくらのレンタルサーバへ向けます。
3. 無料SSL（Let's Encrypt）などを設定します。
4. Web公開フォルダーの変更は、アプリのセットアップ完了後に行います。

### 3.3 DB情報を控える

本番DB自体は作成済みの前提です。「Webサイト/データ」→「データベース」で、次の4項目を正確に控えます。

- データベースサーバーのホスト名
- データベース名
- データベース接続ユーザー名
- データベース接続パスワード

既存データが入っている場合は、マイグレーション前に必ずコントロールパネルまたはphpMyAdminからバックアップを取得してください。

## 4. ローカルPCで本番成果物を準備する

以下は `C:\xampp\htdocs\great_gantt` でPowerShellを開いて実行します。

### 4.1 本番用フロントエンド設定を作る

`.env.production` をローカルだけに作ります。このファイルは `.gitignore` の対象であり、サーバーへアップロードしません。

```powershell
Set-Location C:\xampp\htdocs\great_gantt
Copy-Item .env.example .env.production
notepad .env.production
```

`.env.production` の `VITE_` 項目を次のようにします。さくらのレンタルサーバ単体構成ではReverbキーを空にします。

```dotenv
VITE_APP_NAME="Great Gantt"
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`.env.production` に本番DBパスワードを入れる必要はありません。Viteは `VITE_` で始まる値をブラウザ向けファイルへ埋め込むため、秘密情報を `VITE_` 変数へ入れてはいけません。

### 4.2 Node.jsでビルドする

```powershell
Set-Location C:\xampp\htdocs\great_gantt
npm ci
npm run build
```

次の確認が `True` になることを確認します。

```powershell
Test-Path public\build\manifest.json
Get-ChildItem public\build\assets
```

`public/build/manifest.json` と `public/build/assets/` は本番動作に必須です。`public/build` はGit管理対象外でも、WinSCPでアップロードする成果物には必ず含めます。

### 4.3 ローカルテストを行う

```powershell
composer validate --strict
php artisan test
```

少なくともテストとフロントエンドビルドが成功してからアップロードします。

### 4.4 WinSCPへ渡すアップロード用フォルダーを作る

プロジェクト一式を直接選別する代わりに、アップロード専用フォルダーを1つ作ります。以後は、このフォルダー内をWinSCPで丸ごとアップロードします。

```powershell
Set-Location C:\xampp\htdocs\great_gantt

$ProjectDir = (Get-Location).Path
$UploadDir = Join-Path (Split-Path $ProjectDir -Parent) ("great_gantt_upload_" + (Get-Date -Format "yyyyMMdd_HHmmss"))
New-Item -ItemType Directory -Path $UploadDir

robocopy $ProjectDir $UploadDir /E /XD node_modules vendor .git .idea .vscode .fleet .nova .zed /XF .env .env.backup .env.production .phpunit.result.cache *.sqlite *.log npm-debug.log yarn-error.log

if ($LASTEXITCODE -ge 8) {
    throw "アップロード用フォルダーの作成に失敗しました。robocopyの出力を確認してください。"
}

Test-Path (Join-Path $UploadDir "public\build\manifest.json")
Test-Path (Join-Path $UploadDir "public\.htaccess")
$UploadDir
```

最後の2つの `Test-Path` が両方 `True` であることを確認します。表示された `$UploadDir` が、WinSCPでアップロードするローカル側フォルダーです。

アップロード用フォルダーから除外するものは次のとおりです。

- `node_modules`：Windows用依存物を含み、サーバーでは不要
- `vendor`：サーバー側のComposerで作り直す
- ローカルの `.env` / `.env.production`：ローカル設定や秘密情報を本番へ持ち込まない
- `.git`、IDE設定、ローカルSQLite、テストログ

それ以外は、`app`、`bootstrap`、`config`、`database`、`public`、`resources`、`routes`、`storage`、`artisan`、`composer.json`、`composer.lock` 等を含めて丸ごとアップロードします。

## 5. WinSCPで全ファイルをアップロードする

### 5.1 WinSCPの接続設定

スタンダード以上ではSFTPを推奨します。

| WinSCP項目 | 設定値 |
| --- | --- |
| 転送プロトコル | SFTP |
| ホスト名 | 初期ドメイン（例：`example.sakura.ne.jp`） |
| ポート番号 | `22` |
| ユーザー名 | 初期ドメインのアカウント部分（例：`example`） |
| パスワード | サーバーパスワード |
| リモートディレクトリ | `/home/example/www` または `www` |

### 5.2 アップロード先

リモート側に次のフォルダーを作成します。

```text
/home/example/www/great-gantt
```

ローカル側では、前章で作った `great_gantt_upload_日時` フォルダーを開きます。そのフォルダー自体をもう一段作るのではなく、「中にある全ファイル・全フォルダー」をリモートの `great-gantt` 直下へアップロードします。

正しい配置は次の形です。

```text
/home/example/www/great-gantt/artisan
/home/example/www/great-gantt/app/
/home/example/www/great-gantt/bootstrap/
/home/example/www/great-gantt/composer.json
/home/example/www/great-gantt/composer.lock
/home/example/www/great-gantt/public/index.php
/home/example/www/great-gantt/public/.htaccess
/home/example/www/great-gantt/public/build/manifest.json
```

次のように1階層深くならないよう注意します。

```text
誤り：/home/example/www/great-gantt/great_gantt_upload_20260831/artisan
```

WinSCPで隠しファイルを表示し、`public/.htaccess` が転送されていることを必ず確認してください。

## 6. SSHでComposerを準備する（初回のみ）

PowerShellまたはWinSCPのターミナル機能から接続します。

```powershell
ssh example@example.sakura.ne.jp
```

ログイン後、さくら標準のcshからbashへ切り替えます。

```bash
bash
php -v
php -m
```

`php -v` が8.2未満なら先へ進まず、コントロールパネルのPHP設定を見直します。`php -m` では少なくとも `pdo_mysql`、`mbstring`、`openssl`、`tokenizer`、`xml`、`ctype`、`fileinfo` を確認します。

Composerがすでに使えるか確認します。

```bash
composer --version
```

使える場合はこの章の残りを省略できます。`command not found` の場合は、Composer公式のインストーラーをユーザー領域へ入れます。以下はチェックサムを動的に検証する手順です。

```bash
cd ~
mkdir -p bin

EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo "Composer installer checksum mismatch"
    exit 1
fi

php composer-setup.php --install-dir=bin --filename=composer
rm composer-setup.php
chmod 755 ~/bin/composer
~/bin/composer --version
```

以後の例では、環境差を避けるため `~/bin/composer` と記載します。もともと `composer` コマンドが使える場合は読み替えて構いません。

## 7. サーバー側でComposerを実行する

アプリ直下へ移動し、ロックファイルに固定された本番用PHPパッケージをインストールします。

```bash
cd /home/example/www/great-gantt
~/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
~/bin/composer check-platform-reqs --no-dev
```

`composer update` は本番サーバーで実行しません。依存バージョンが変わるためです。必ず `composer.lock` と `composer install` を使います。

Composerのダウンロードが通信エラーになる場合は、さくら側の国外IPアドレスフィルターや一時的な外向き通信制限も確認します。

## 8. 本番用 `.env` を作成してDBを指定する

### 8.1 `.env` の作成

初回だけ、サーバー上でサンプルをコピーします。

```bash
cd /home/example/www/great-gantt
cp .env.example .env
chmod 600 .env
```

WinSCPでリモート表示を更新すると `.env` が見えるため、右クリックの「編集」で開きます。次の内容を基準に書き換えます。

```dotenv
APP_NAME="Great Gantt"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://gantt.example.jp

APP_LOCALE=ja
APP_FALLBACK_LOCALE=ja
APP_FAKER_LOCALE=ja_JP

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql0000.db.sakura.ne.jp
DB_PORT=3306
DB_DATABASE=実際のデータベース名
DB_USERNAME=実際のデータベース接続ユーザー名
DB_PASSWORD="実際のデータベース接続パスワード"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=実際のSMTPホスト
MAIL_PORT=587
MAIL_USERNAME="実際のSMTPユーザー名"
MAIL_PASSWORD="実際のSMTPパスワード"
MAIL_FROM_ADDRESS=noreply@example.jp
MAIL_FROM_NAME="${APP_NAME}"
```

### 8.2 DB設定の意味

| 設定 | 入力するもの |
| --- | --- |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | コントロールパネル記載のDBサーバー名。`127.0.0.1` ではない |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | 作成済み本番DBの正確な名前 |
| `DB_USERNAME` | DBの接続ユーザー名。SFTPのユーザー名とは別物として確認する |
| `DB_PASSWORD` | DB作成時に設定した接続パスワード |

パスワードに `#`、空白、`=` などが含まれる場合は、例のように値全体をダブルクォートで囲みます。ダブルクォート自体やバックスラッシュを含む特殊なパスワードではエスケープが必要になるため、設定後の接続確認を必ず行ってください。

ローカルの `.env` をアップロードして上書きしてはいけません。また、`.env` を `public` フォルダー内へ置いてはいけません。

### 8.3 メールをまだ使わない場合

SMTP情報が未準備なら、一時的に次の設定でも画面動作を確認できます。

```dotenv
MAIL_MAILER=log
```

この場合、招待メール等は送信されず `storage/logs/laravel.log` に記録されます。本番運用開始前に実際のSMTPへ変更してください。

## 9. 権限、APP_KEY、DB接続、マイグレーション

### 9.1 書き込み権限を整える

```bash
cd /home/example/www/great-gantt
find storage bootstrap/cache -type d -exec chmod 755 {} \;
find storage bootstrap/cache -type f -exec chmod 644 {} \;
chmod -R u+rwX storage bootstrap/cache
chmod 600 .env
```

`chmod -R 777` は使用しません。

### 9.2 APP_KEYを初回だけ生成する

```bash
php artisan key:generate --force
```

このコマンドは初回だけです。更新時にAPP_KEYを再生成すると、既存セッションや暗号化済みデータを読めなくなります。

### 9.3 DB接続を確認する

```bash
php artisan config:clear
php artisan migrate:status
```

`config:clear` は古い設定キャッシュだけを削除し、現在の `.env` を次のコマンドへ反映させます。初回は `CACHE_STORE=database` が使用する `cache` テーブルもまだ存在しないため、この時点では `php artisan optimize:clear` を実行しません。`optimize:clear` はアプリケーションキャッシュにもアクセスするので、マイグレーション前に実行すると `Table '...cache' doesn't exist` で失敗します。

初回の `migrate:status` では `Migration table not found` と表示される場合がありますが、DBへ接続できていれば次へ進めます。`Access denied` や `Connection refused` が出る場合は、マイグレーションを行わず `.env` の `DB_HOST`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`、`DB_PORT=3306` を再確認します。

### 9.4 マイグレーションファイルを実行する

既存DBのバックアップを確認してから実行します。

```bash
php artisan migrate --force
php artisan migrate:status
```

2026年9月2日追加のHOME招待機能では、`2026_09_02_000000_add_name_to_project_invitations_table.php` が実行対象です。既存の招待データを保持したまま氏名欄を追加します。`migrate:status` でこのファイルが `Ran` になっていることを確認してください。

すべての行が `Ran` になれば完了です。本番DBでは次を実行しないでください。

```text
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:seed
php artisan migrate --seed
```

このリポジトリのSeederにはデモユーザーが含まれる可能性があるため、本番でSeederは実行しません。

### 9.5 キャッシュをクリアしてLaravelを最適化する

```bash
php artisan optimize:clear
php artisan optimize
```

マイグレーションによって `cache` テーブルが作成された後なので、ここでは `optimize:clear` を安全に実行できます。初回デプロイの順番は、必ず `config:clear` → DB接続確認 → `migrate --force` → `optimize:clear` → `optimize` とします。

## 10. Web公開フォルダーを `public` に設定する

ここが最重要です。プロジェクト直下ではなく `public` だけを公開します。

1. サーバーコントロールパネルの「ドメイン/SSL」→「ドメイン/SSL」を開きます。
2. 公開ドメインの「設定」→「基本設定」を開きます。
3. 「Web公開フォルダー」に `great-gantt/public` を入力します。
4. 保存し、反映を待ちます。
5. SSLが有効であることを確認します。

公開先は次の対応になります。

```text
https://gantt.example.jp/
  ↓
/home/example/www/great-gantt/public/
```

`great-gantt` を公開フォルダーにすると `.env`、`vendor`、アプリのソースが公開領域へ入るため危険です。必ず `great-gantt/public` にします。

## 11. 起動確認

ブラウザで次を確認します。

```text
https://gantt.example.jp/up
https://gantt.example.jp/
```

確認項目は次のとおりです。

- `/up` がHTTP 200を返す。
- CSS、アイコン、JavaScriptが正しく表示される。
- `/register-owner` から初期オーナーを登録できる。
- ログインできる。
- プロジェクト、WBS、タスクを作成・更新できる。
- ページ再読み込み後もDBの内容が残る。
- SMTP設定済みなら招待メールを送信できる。
- `APP_DEBUG=false` になっている。

初期オーナー登録後、第三者に登録させない運用が必要なら、公開登録の無効化を別途実装してください。

## 12. 2回目以降の更新手順

更新時もNode.js処理はローカルだけで行います。

### 12.1 ローカル

```powershell
Set-Location C:\xampp\htdocs\great_gantt
npm ci
npm run build
composer validate --strict
php artisan test
```

第4章と同じ方法で、新しい日時のアップロード用フォルダーを作ります。

### 12.2 サーバー

先にメンテナンスモードへ入れます。`--secret` の値は毎回十分長いランダム値へ変更してください。

```bash
cd /home/example/www/great-gantt
php artisan down --secret="十分に長いランダムなメンテナンス用文字列"
```

WinSCPで新しいアップロード用フォルダーの中身を `great-gantt` 直下へ上書きします。

注意事項：

- サーバー上の `.env` は上書き・削除しません。
- サーバー上の `storage` 内の運用データやログを一括削除しません。
- WinSCPの「ミラー」や「同期時にリモート側の余分なファイルを削除」は使いません。`.env` と `vendor` が消える危険があります。
- `public/build` は新しいビルド成果物で上書きします。

アップロード後に実行します。

```bash
cd /home/example/www/great-gantt
~/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
~/bin/composer check-platform-reqs --no-dev
php artisan config:clear
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up
```

`config:clear` を先に単独で実行することで、現在の `.env` をマイグレーションへ確実に反映します。その後、DBテーブルが揃った状態で `optimize:clear` と `optimize` を実行します。最後に `/up`、ログイン、主要画面を確認します。更新時も `npm` コマンドと `key:generate` はサーバーで実行しません。

## 13. よくある問題

### 500 Internal Server Error

```bash
cd /home/example/www/great-gantt
tail -n 100 storage/logs/laravel.log
php artisan config:clear
php artisan migrate:status
```

マイグレーションがすべて `Ran` で、DB接続にも問題がないことを確認してから次を実行します。

```bash
php artisan optimize:clear
php artisan optimize
```

`Migration table not found` または未実行のマイグレーションがある場合は、先に第9.4章の `php artisan migrate --force` を実行します。主に `.env` の誤り、APP_KEY未生成、`storage` / `bootstrap/cache` の権限、Composer失敗を確認します。本番で `APP_DEBUG=true` にしてエラー画面を公開しないでください。

### トップは開くが、ログイン等が404になる

- Web公開フォルダーが `great-gantt/public` か確認する。
- `public/.htaccess` がアップロード済みか確認する。
- ファイル名が `.htaccess.txt` になっていないか確認する。

### `Vite manifest not found`

ローカルで `npm run build` を再実行し、次をアップロードします。

```text
public/build/manifest.json
public/build/assets/*
```

サーバーでnpmを実行して解決しようとしないでください。

### DBへ接続できない

- `DB_HOST` は `mysql....db.sakura.ne.jp` 形式の実値か。
- `DB_PORT=3306` か。
- DB名とDBユーザー名を取り違えていないか。
- DBパスワードの引用符を含めて `.env` の書式が正しいか。
- コントロールパネルの対象DBとDBユーザーが一致しているか。
- 設定変更後に `php artisan config:clear` を実行してから、`php artisan migrate:status` で再確認したか。

ローカルPCからさくらの共用DBへ直接接続できないのは通常の仕様です。接続確認は、同じレンタルサーバー内のLaravel、SSH上のMySQLクライアント、またはphpMyAdminから行います。

### `could not find driver`

CLI側PHPで `pdo_mysql` が有効か確認します。

```bash
php -m | grep pdo_mysql
php -v
```

Web側とCLI側で違うPHP版を使っていないか、コントロールパネルの言語バージョン設定も確認します。

### メールが届かない

- `MAIL_MAILER=log` のままでは実際のメールは送信されません。
- SMTPホスト、587/465等のポート、ユーザー名、パスワード、暗号化方式を提供元の指示どおりに設定します。
- `QUEUE_CONNECTION=sync` か確認します。
- `storage/logs/laravel.log` を確認します。
- `.env` 変更後は `php artisan config:clear` を先に単独で実行し、続いて `php artisan optimize:clear` と `php artisan optimize` を実行します。
- 招待リンクのホスト名は `APP_URL` から生成されるため、`APP_URL=https://実際の公開ドメイン` になっているか確認します。

### 別ブラウザへ変更が即時反映されない

本手順ではReverbを無効化しているため正常です。画面を再読み込みしてください。即時同期が必要なら外部WebSocketサービスまたは常駐プロセスを運用できるサーバーが必要です。

## 14. 公式・準公式資料

- [さくらのレンタルサーバ 基本仕様](https://help.sakura.ad.jp/rs/2251/)
- [PHPのバージョンを変更したい](https://help.sakura.ad.jp/rs/2241/)
- [WinSCPを利用したい](https://help.sakura.ad.jp/rs/2789/)
- [SSHを利用したい](https://help.sakura.ad.jp/rs/2247/)
- [Web公開フォルダーを設定したい](https://help.sakura.ad.jp/purpose_beginner/2867/)
- [SSL証明書を導入する流れ](https://help.sakura.ad.jp/rs/2309/)
- [SSHでMySQLへインポートする際の接続情報例](https://help.sakura.ad.jp/rs/2908/)
- [さくらのナレッジ：さくらのレンタルサーバでLaravelを動かす方法](https://knowledge.sakura.ad.jp/35587/)
- [Composer公式 Download](https://getcomposer.org/download/)
- [Laravel 12 Deployment](https://laravel.com/docs/12.x/deployment)
