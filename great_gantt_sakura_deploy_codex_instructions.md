# Great Gantt 本番デプロイ修正指示書（さくらのサーバー / WordPress共存）

## 目的

Laravel製 `Great Gantt` を、さくらのサーバー上で以下のURLから正常に利用できるようにする。

```text
https://ugsf.org/great-gantt
```

現在、`ugsf.org` のDocumentRoot直下ではWordPressが稼働している。

LaravelアプリはWordPressとは別アプリとして、WordPressと同じDocumentRoot配下のサブディレクトリに配置する。

想定構成:

```text
DocumentRoot/
├── .htaccess                 # WordPress用。Great Gantt除外ルールを追加する
├── index.php                 # WordPress
├── wp-admin/
├── wp-content/
├── wp-includes/
├── entry/
└── great-gantt/
    ├── index.php             # Laravelへの入口用shim
    ├── .env
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/
    │   ├── index.php
    │   ├── .htaccess         # Laravel標準
    │   ├── build/
    │   └── ...
    ├── resources/
    ├── routes/
    ├── storage/
    └── vendor/
```

---

# 1. 現在発生している問題

Laravelそのものへのアクセスは既に成功している。

以下のURL:

```text
https://ugsf.org/great-gantt
```

からLaravelの処理に到達できることは確認済み。

一時的なテストルートとして、

```php
Route::get('/', function () {
    return 'Great Gantt Laravel OK!';
});
```

を設定したところ、正常にテキストが表示された。

つまり以下は正常動作している。

- Apache
- PHP 8.3
- Laravel 12
- WordPressとの振り分け
- `/great-gantt` からLaravelへの到達

ただし `resources/views/home.blade.php` を直接表示するように変更したところ、

```text
Undefined variable $projects
```

が発生した。

`home.blade.php` は `$projects` がControllerから渡されることを前提としているため、

```php
Route::get('/', function () {
    return view('home');
});
```

という実装は禁止する。

---

# 2. Laravelトップページの修正

## 要件

`https://ugsf.org/great-gantt`

へアクセスした際に、Great Gantt本来のプロジェクト一覧トップページを表示すること。

`home.blade.php` は以下に存在する。

```text
resources/views/home.blade.php
```

このBladeでは少なくとも以下の変数を使用している。

```php
$projects
```

例:

```php
{{ $projects->count() }}
{{ $projects->sum(fn($p) => $p->tasks->count()) }}
```

したがって、BladeをRouteから直接返してはいけない。

---

## 優先対応

既存のルーティングとControllerを確認すること。

まず以下を確認する。

```bash
php artisan route:list
```

特に以下の既存ルートを調べる。

```text
projects.index
home
dashboard
```

また、`ProjectController@index` 等が既に `$projects` を取得して `home` Viewへ渡しているか確認する。

例えば既存実装が以下のようになっている場合:

```php
public function index()
{
    $projects = Project::with([
        'tasks',
        'members.user',
    ])
    ->whereHas('members', function ($query) {
        $query->where('user_id', auth()->id());
    })
    ->get();

    return view('home', compact('projects'));
}
```

この既存処理を再利用すること。

---

## 推奨ルーティング

### projects.index が既に存在する場合

`/` から既存のプロジェクト一覧へリダイレクトする。

```php
Route::get('/', function () {
    return redirect()->route('projects.index');
});
```

これは既存の認証・Policy・Query・Controller処理を再利用できるため最優先とする。

---

### projects.index が存在しない場合

適切なControllerへ直接接続する。

例:

```php
use App\Http\Controllers\ProjectController;

Route::get('/', [ProjectController::class, 'index'])->name('home');
```

`ProjectController@index` では、ログインユーザーが閲覧可能なプロジェクトを取得し、

```php
return view('home', compact('projects'));
```

でViewへ渡すこと。

---

## 注意事項

以下は禁止。

```php
Route::get('/', function () {
    return view('home');
});
```

理由:

`home.blade.php` が `$projects` を要求しているため。

また、既存のControllerに同等の処理がある場合、新しい重複処理を作らず既存コードを再利用すること。

---

# 3. APP_URL

本番 `.env` は以下とする。

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ugsf.org/great-gantt
```

`APP_URL` に `/public` を含めない。

誤:

```env
APP_URL=https://ugsf.org/great-gantt/public
```

正:

```env
APP_URL=https://ugsf.org/great-gantt
```

---

# 4. WordPressとの共存について

## 重要

`ugsf.org` のDocumentRoot直下はWordPressである。

WordPressの `.htaccess` には以下のようなcatch-allルールが存在する。

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

このため何も対策しないと、

```text
/great-gantt/*
```

へのアクセスまでWordPressの `index.php` に渡される可能性がある。

したがって、WordPress用Rewriteの前でGreat Ganttを除外する必要がある。

---

# 5. DocumentRoot直下 `.htaccess`

WordPressが置かれているDocumentRoot直下の `.htaccess` に、WordPressブロックより前に以下を置くこと。

```apache
<IfModule mod_rewrite.c>
RewriteEngine On

# -------------------------------------------------
# Laravel Great Gantt
# -------------------------------------------------

# /great-gantt/public/... に直接アクセスされた場合は
# public を隠したURLへリダイレクト
RewriteCond %{THE_REQUEST} \s/+great-gantt/public(?:[/\s?]|$) [NC]
RewriteRule ^great-gantt/public(?:/(.*))?$ /great-gantt/$1 [R=302,L,NE]

# Laravel public配下のビルド済み静的ファイル
RewriteRule ^great-gantt/build/(.*)$ great-gantt/public/build/$1 [L]

# storage:link を使用している場合
RewriteRule ^great-gantt/storage/(.*)$ great-gantt/public/storage/$1 [L]

# favicon / robots
RewriteRule ^great-gantt/favicon\.ico$ great-gantt/public/favicon.ico [L]
RewriteRule ^great-gantt/robots\.txt$ great-gantt/public/robots.txt [L]

# Great Gantt本体
RewriteRule ^great-gantt(?:/.*)?$ great-gantt/index.php [L,QSA]

# 既存の /entry もWordPressから除外
RewriteRule ^entry(?:/.*)?$ - [L]

</IfModule>
```

その後に既存WordPressブロックを残す。

例:

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

---

# 6. great-gantt/index.php

`great-gantt` プロジェクト直下に以下を配置する。

```php
<?php

require __DIR__ . '/public/index.php';
```

ファイル位置:

```text
DocumentRoot/great-gantt/index.php
```

これをHTTP上の入口として利用する。

URL:

```text
https://ugsf.org/great-gantt
```

↓

内部的に:

```text
great-gantt/index.php
```

↓

```text
great-gantt/public/index.php
```

↓

Laravel

という流れにする。

---

# 7. Laravel public/.htaccess

以下のファイルはLaravel標準のRewrite設定を維持すること。

```text
great-gantt/public/.htaccess
```

Laravel 12系の標準に準じて以下とする。

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

`public/.htaccess` にWordPress用ルールを書かないこと。

---

# 8. Blade / asset URLの確認

サブディレクトリ配下で稼働するため、BladeやJavaScript内に以下のようなDocumentRoot前提の絶対パスがないか確認する。

避ける:

```html
<script src="/build/assets/app.js"></script>
<link href="/build/assets/app.css">
<a href="/projects">
```

Laravelのヘルパーを優先する。

```php
{{ asset('build/assets/...') }}
{{ route('projects.index') }}
{{ url('/projects') }}
```

Viteを使用している場合は原則:

```php
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

を使用する。

ただし本番生成されるURLが

```text
https://ugsf.org/build/...
```

にならず、

```text
https://ugsf.org/great-gantt/build/...
```

になることを必ず確認すること。

必要であればVite/Laravel側のbase path設定を調整する。

---

# 9. キャッシュ削除

コード・Route・`.env` 修正後は以下を実行する。

```bash
php artisan optimize:clear
```

必要に応じて本番キャッシュを再生成する。

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

ただしデバッグ中は `route:cache` を無理に使用しなくてよい。

---

# 10. storageの権限

以下がPHPから書き込み可能であることを確認する。

```text
storage/
bootstrap/cache/
```

さくらのサーバー環境の権限に合わせること。

不用意に `777` にせず、必要最小限のパーミッションを設定する。

---

# 11. セキュリティ上の注意

今回の配置ではLaravelプロジェクト一式がDocumentRoot配下に存在するため、以下のファイルがHTTPから直接取得されないことを確認する。

```text
.env
composer.json
composer.lock
artisan
storage/logs/*
database/*
routes/*
config/*
vendor/*
```

特に以下が取得できる状態は重大な問題。

```text
https://ugsf.org/great-gantt/.env
```

必ず403または404となること。

可能であれば最終的には、

- Laravel本体をDocumentRoot外に置く
- `public` のみをWeb公開する

構成に移行するのが望ましい。

ただし今回は既存のさくらのサーバー構成を大きく変更せず、

```text
https://ugsf.org/great-gantt
```

で正常稼働させることを優先する。

---

# 12. 動作確認

修正後に以下を確認する。

## トップページ

```text
https://ugsf.org/great-gantt
```

期待結果:

- LaravelのGreat Ganttトップページが表示される
- `$projects` 未定義エラーが発生しない
- WordPressの404ページに遷移しない

---

## Laravel Route

```bash
php artisan route:list
```

`GET /` または `/` から到達可能な既存ルートが正しく登録されていること。

---

## プロジェクト詳細など

例:

```text
https://ugsf.org/great-gantt/projects
https://ugsf.org/great-gantt/projects/1
```

URLから `/public` が見えないこと。

---

## CSS / JavaScript

Chrome DevToolsのNetworkを確認し、

```text
404
```

になっているCSS/JS/imageが存在しないこと。

特に `/build/*` を確認すること。

---

## WordPress

以下の既存WordPressページが壊れていないこと。

```text
https://ugsf.org/
```

および既存固定ページ・投稿ページ。

---

## entry

既存の

```text
https://ugsf.org/entry/
```

もWordPressに奪われず、従来通り動作すること。

---

# 13. 完了条件

以下をすべて満たしたら作業完了。

- `https://ugsf.org/great-gantt` でGreat Ganttトップページが表示される
- `home.blade.php` に `$projects` が正しく渡される
- Laravelの既存Controller / Policy /認証処理を壊さない
- URLに `/public` が出ない
- CSS / JavaScript /画像が正常にロードされる
- WordPressが従来通り動作する
- `/entry` が従来通り動作する
- `.env` がWeb経由で取得できない
- `APP_URL=https://ugsf.org/great-gantt`
- Laravel標準 `public/.htaccess` を維持する
- 修正後 `php artisan optimize:clear` を実行する

---

# Codexへの実装方針

既存コードを最初に調査し、最小限の変更で対応すること。

特にトップページについては、

1. `routes/web.php`
2. `ProjectController`
3. `home.blade.php`
4. Project / ProjectMember関連Model
5. 認証・Policy
6. `vite.config.js`
7. `.env.example`

を確認してから修正する。

既に `$projects` を生成するControllerが存在する場合、それを再利用する。

既存設計を無視して新規Queryや重複Controllerを作らないこと。

また、ローカル開発環境は例として以下である。

```text
C:\xampp\htdocs\great_gantt
```

本番はLinux/さくらのサーバーであり、Windows固有の絶対パスをコードに埋め込まないこと。

Laravel標準の、

```php
base_path()
public_path()
storage_path()
resource_path()
```

等を利用し、Windows/Linux両環境で動作する実装を維持すること。
