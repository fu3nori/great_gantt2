# Great Gantt

Laravel 12 / Blade / Bootstrap / Vanilla JavaScript / Reverb で構築した、リアルタイムWBS・ガントチャート／チケット管理アプリです。

## ローカル起動

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

リアルタイム同期とメールQueueを動かす場合は、別ターミナルで次も起動します。

```powershell
php artisan reverb:start
php artisan queue:work
```

開発中にViteを利用する場合は `npm run dev` を実行してください。

## デモアカウント

| 権限 | メールアドレス | パスワード |
|---|---|---|
| Owner | `demo@example.com` | `password` |
| PM | `pm@example.com` | `password` |
| Member | `member@example.com` | `password` |
| System Admin | `admin@example.com` | `password` |

## テスト

```powershell
php artisan test
php vendor/bin/pint --test
npm run build
```

既定のローカルDBはSQLite、メール送信先はログです。本番では `.env` でMySQL/PostgreSQL、メールTransport、Reverbキーを設定してください。招待・コメント・進捗通知はQueue経由で処理されます。
