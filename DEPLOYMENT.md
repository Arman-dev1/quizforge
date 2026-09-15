# QuizForge — Installation & Deployment

Everything needed to take this code from a zip file to a running site.

- [1. Requirements](#1-requirements)
- [2. What to ship in the zip](#2-what-to-ship-in-the-zip)
- [3. Local install (XAMPP / Laragon / Valet)](#3-local-install)
- [4. Live server — shared hosting (Hostinger, cPanel)](#4-live-server--shared-hosting)
- [5. Live server — VPS](#5-live-server--vps)
- [6. After install: the checklist](#6-after-install-the-checklist)
- [7. Payments (Paddle)](#7-payments-paddle)
- [8. Updating an existing install](#8-updating-an-existing-install)
- [9. Troubleshooting](#9-troubleshooting)

---

## 1. Requirements

| Thing | Minimum | Notes |
|---|---|---|
| PHP | **8.2** | 8.3 recommended |
| MySQL | 8.0 | MariaDB 10.6+ also fine |
| Composer | 2.x | Only if installing dependencies on the server |
| Node.js | 20+ | **Local machine only** — never needed on the server |

**Required PHP extensions:** `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`

Check what's enabled with `php -m`. On Hostinger these are set in **hPanel → Advanced → PHP Configuration → PHP extensions**.

---

## 2. What to ship in the zip

**Before zipping**, build the frontend assets on your own machine:

```bash
npm install
npm run build
```

This writes `public/build/`. **The server never runs Node** — it only serves the compiled files, so `public/build/` must be inside the zip.

### Include

```
app/  bootstrap/  config/  database/  public/  resources/  routes/  storage/
artisan  composer.json  composer.lock  package.json  .env.example
vendor/          ← include this if the server has no Composer (see §4.4)
public/build/    ← the compiled assets from `npm run build`
```

### Exclude

| Exclude | Why |
|---|---|
| `node_modules/` | Huge, and never used on the server |
| `.env` | Your secrets. The server gets its own |
| `.git/` | Not needed, and leaks history |
| `storage/logs/*.log` | Old logs from your machine |
| `storage/framework/{cache,sessions,views}/*` | Machine-specific caches |
| `public/hot` | A local Vite marker. **If this file ships, the live site loads no CSS** |

> Keep the empty `storage/` folder structure — Laravel needs those directories to exist.

**Quick clean before zipping:**

```bash
rm -f public/hot
rm -rf storage/framework/cache/data/* storage/framework/sessions/* storage/framework/views/*
rm -f storage/logs/*.log
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

---

## 3. Local install

For XAMPP, Laragon, or anything similar.

```bash
# 1. Put the code where your web server can see it, then:
composer install
npm install && npm run build

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Create an empty MySQL database called `quizforge`, then point .env at it:
#    DB_DATABASE=quizforge
#    DB_USERNAME=root
#    DB_PASSWORD=
php artisan migrate --seed

# 4. Run it
composer dev        # app + queue worker + logs + Vite, all at once
```

The seeder prints your platform admin login. **Copy the password — it is shown once.**

Open `http://localhost:8000`. Register a normal account at `/register`; sign in as platform staff at `/super-admin`.

---

## 4. Live server — shared hosting

Written for **Hostinger hPanel**, but cPanel and Plesk work the same way.

### 4.1 The one thing that matters: the document root

A Laravel app must serve from its `public/` folder and **nothing else**. If visitors can reach the folder above it, your `.env` — database password, API keys, app key — is downloadable over the web.

There are two ways to arrange this. **Use Option A if your plan allows it.**

---

#### Option A — Point the domain at `public/` *(preferred)*

Upload the whole project into a folder next to `public_html`, then repoint the domain.

```
/home/u123456789/
├── domains/yourdomain.com/
│   ├── public_html/          ← document root (leave this alone for now)
│   └── quizforge/            ← upload the whole project HERE
│       ├── app/
│       ├── public/
│       ├── .env
│       └── ...
```

In hPanel: **Websites → yourdomain.com → Dashboard → Advanced → Change website root directory**, and set it to:

```
/domains/yourdomain.com/quizforge/public
```

Done. Nothing else to edit.

> Not all Hostinger plans expose this setting. If you can't find it, use Option B.

---

#### Option B — Split the app *(works everywhere)*

Application outside the web root; only the contents of `public/` inside it.

```
/home/u123456789/
├── quizforge/                ← everything EXCEPT the public folder
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── artisan
│   └── .env
└── domains/yourdomain.com/public_html/   ← CONTENTS of public/ go here
    ├── build/
    ├── css/  fonts/  js/
    ├── .htaccess
    ├── favicon.ico
    ├── index.php             ← must be edited, see below
    └── robots.txt
```

Then edit **`public_html/index.php`**. It ships as:

```php
// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
```

All three `__DIR__.'/../…'` paths have to point at the application folder. The
simplest way is to define the path once at the top and use it throughout:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Absolute path to the application folder, outside the web root.
$app_path = '/home/u123456789/quizforge';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $app_path.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $app_path.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once $app_path.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

> Use the **absolute path**, not `../../`. Counting `../` is where most people
> get a blank page, and the depth of `public_html` differs between hosts. Your
> exact path is shown at the top of hPanel's File Manager — copy it from there.

---

### 4.2 Create the database

hPanel → **Databases → Management → Create new database**.

Note down all three values — Hostinger prefixes them, so they won't match what you typed:

- Database name: `u123456789_quizforge`
- Username: `u123456789_quizforge`
- Password: whatever you set

### 4.3 Configure `.env`

Copy `.env.example` to `.env` in the **application** folder (not `public_html`) and set:

```dotenv
APP_NAME=QuizForge
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_quizforge
DB_USERNAME=u123456789_quizforge
DB_PASSWORD=your-database-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-mailbox-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# The first platform admin. Leave the password blank and one is generated
# and printed when you seed.
SUPER_ADMIN_NAME="Platform Admin"
SUPER_ADMIN_EMAIL=you@yourdomain.com
SUPER_ADMIN_PASSWORD=
```

> `APP_DEBUG=false` is not optional. With it on, any error page shows your
> environment variables — including database and API credentials — to whoever
> triggered it.

### 4.4 Install dependencies

**With SSH** (Hostinger Business plans and above):

```bash
cd ~/quizforge
composer install --optimize-autoloader --no-dev
```

**Without SSH:** you can't run Composer, so upload the `vendor/` folder from your own machine. Generate it locally first with the same flags:

```bash
composer install --optimize-autoloader --no-dev
```

Then include `vendor/` in the zip.

### 4.5 Generate the app key, migrate, seed

With SSH:

```bash
cd ~/quizforge
php artisan key:generate
php artisan migrate --force --seed
```

**Without SSH**, Hostinger's Cron Jobs panel can run one-off commands. Create a cron set to run once, then delete it after:

```
/usr/bin/php /home/u123456789/quizforge/artisan migrate --force --seed
```

Cron output goes to your email, which is where you'll find the generated admin password. Alternatively set `SUPER_ADMIN_PASSWORD` in `.env` **before** seeding so you already know it.

For the app key without SSH, generate it locally (`php artisan key:generate --show`) and paste the `base64:...` value into the server's `.env` as `APP_KEY`.

### 4.6 Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

In File Manager: right-click each folder → Permissions → **775**, apply recursively.

### 4.7 Storage symlink

Uploaded logos and cover images live in `storage/app/public` and are served from `public/storage`.

With SSH:

```bash
php artisan storage:link
```

**Option B users:** the default symlink points to the wrong place. Create it manually instead:

```bash
ln -s /home/u123456789/quizforge/storage/app/public /home/u123456789/domains/yourdomain.com/public_html/storage
```

If symlinks are blocked on your plan, add this to `.env` and images will be served through PHP instead:

```dotenv
FILESYSTEM_DISK=public
```

### 4.8 Cron jobs

Two are needed. hPanel → **Advanced → Cron Jobs**.

**Queue worker** — sends invitation emails, notifications, and pushes responses to integrations. Every minute:

```
* * * * * /usr/bin/php /home/u123456789/quizforge/artisan queue:work --stop-when-empty --tries=3 --max-time=55
```

**Scheduler** — Laravel's task scheduler. Every minute:

```
* * * * * /usr/bin/php /home/u123456789/quizforge/artisan schedule:run >> /dev/null 2>&1
```

> `--stop-when-empty` matters: shared hosts kill long-running processes, and a
> plain `queue:work` would be started again every minute until you have hundreds
> of them. If you skip the queue worker entirely, set `QUEUE_CONNECTION=sync`
> in `.env` — jobs then run inside the web request, which is slower but works.

### 4.9 Cache for production

Run these last, and re-run them after **any** `.env` change:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.10 HTTPS

hPanel → **Security → SSL** → install the free certificate, then turn on **Force HTTPS**.

Make sure `APP_URL` in `.env` starts with `https://`. If it doesn't, generated links (password resets, invitations, public quiz links) point at `http://` and browsers will warn.

---

## 5. Live server — VPS

With root access, the standard Laravel deployment applies. Point the nginx root at `public/`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/quizforge/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Use a real supervisor for the queue instead of cron:

```ini
[program:quizforge-queue]
command=php /var/www/quizforge/artisan queue:work --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/quizforge/storage/logs/worker.log
```

---

## 6. After install: the checklist

Work through these in order.

- [ ] `https://yourdomain.com` loads the marketing page **with styling**
      *(no styling = `public/hot` shipped, or `public/build/` is missing)*
- [ ] `https://yourdomain.com/register` creates an account and lands on the dashboard
- [ ] A workspace was created for that account automatically — you'll see its name top-left
- [ ] `https://yourdomain.com/super-admin` shows the platform login
- [ ] Sign in with the seeded admin credentials
- [ ] **Platform → Payments** — choose a gateway and enter keys (see §7)
- [ ] **Catalog → Plans** — the Free and Pro plans are there
- [ ] Create a quiz, add a question, publish it, open the public link
- [ ] Submit a response and confirm it appears under the quiz's Responses tab
- [ ] Invite a teammate and confirm the email arrives *(tests your SMTP settings)*
- [ ] Try to open `https://yourdomain.com/.env` — **it must 404 or 403.**
      If it downloads a file, your document root is wrong. Fix §4.1 before going live.

### What a fresh install contains

| | |
|---|---|
| Users | **none** — customers register themselves |
| Workspaces | **none** — one is created automatically per account, on first sign-in |
| Quizzes | none |
| Plans | Free and Pro |
| Quiz templates | 6 global starter templates |
| Platform admins | 1, from your `SUPER_ADMIN_*` settings |

No demo or test data is seeded. Every seeder is safe to re-run.

---

## 7. Payments (Paddle)

Credentials are entered **in the app**, not in `.env`.

1. Sign in at `/super-admin` → **Platform → Payments**
2. Choose **Paddle**, leave **Test mode** on while you're setting up
3. Fill in Seller ID, Client-side token, API key, Webhook secret
   *(Paddle → Developer Tools → Authentication)*
4. In Paddle, create a **Product** and a recurring **Price** for your Pro plan
5. Copy the price ID (starts with `pri_`) into **Catalog → Plans → Pro → Paddle price ID**
6. In Paddle → **Notifications → New destination**, set the URL to:

   ```
   https://yourdomain.com/paddle/webhook
   ```

   The Payments screen shows this exact URL — copy it from there.

> **The webhook must be a public URL.** `127.0.0.1` or `localhost` will never
> work: Paddle's servers cannot reach your machine, so payments will succeed
> at Paddle and the app will never learn about them. For local testing run
> `ngrok http 8000` and use the `https://….ngrok-free.app/paddle/webhook` address.

If a webhook is ever missed, reconcile manually:

```bash
php artisan app:sync-subscriptions
```

The **Subscriptions** screen also has a per-customer *Sync from gateway* button.

**Stripe** appears as an option and stores its keys, but checkout is not wired up —
it needs the `laravel/cashier` package, which conflicts with the Paddle one in use.

---

## 8. Updating an existing install

```bash
php artisan down                  # maintenance mode

# upload the new files, then:
composer install --optimize-autoloader --no-dev
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

Re-run `php artisan db:seed --force` only if new templates or plans shipped. It never overwrites an existing platform admin's password.

---

## 9. Troubleshooting

**Blank white page**
`APP_DEBUG=false` is hiding the error. Read `storage/logs/laravel.log`. On Option B, it's usually the `../` path count in `public_html/index.php`.

**500 Internal Server Error**
Almost always permissions. `chmod -R 775 storage bootstrap/cache`. Second most likely: `APP_KEY` is empty.

**The site loads but has no styling**
`public/hot` was included in the zip — delete it. Or `public/build/` is missing: run `npm run build` locally and upload the folder.

**"No application encryption key has been specified"**
`php artisan key:generate`, or paste a locally generated `base64:…` value into `.env`.

**Routes 404 except the homepage**
`.htaccess` didn't upload — it's a hidden file, so turn on "show hidden files" in File Manager. It belongs in the same folder as `index.php`.

**Changes to `.env` do nothing**
Config is cached. `php artisan config:clear`, then `php artisan config:cache`.

**Emails aren't sending**
Check the queue worker cron is running — mail is queued, not sent inline. To test without a worker, set `QUEUE_CONNECTION=sync` temporarily.

**Uploaded images 404**
The storage symlink is missing or points at the wrong path. See §4.7.

**Customer paid but is still on the Free plan**
The webhook isn't reaching you. Check the URL in Paddle is your public domain, then run `php artisan app:sync-subscriptions` to reconcile.

**Locked out of `/super-admin`**
```bash
php artisan app:make-super-admin you@yourdomain.com --name="Your Name" --password="a-long-password"
```
Re-run it on an existing email to reset that account's password.
