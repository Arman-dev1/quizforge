# QuizForge

A modern quiz platform for businesses, educators, marketers, and teams: build
interactive quizzes, surveys, assessments, lead-generation forms, and exams —
publish them at a public link, capture responses page-by-page (partial
responses become leads), score and grade automatically, and analyze everything.

Built with **Laravel 12, Livewire 4 (Volt), Tailwind CSS 4, MySQL, Filament 5,
and Cashier Paddle**.

## Features

- **Workspaces & teams** — multi-tenant workspaces, owner/admin/editor/viewer
  roles, email invitations
- **Visual builder** — 22 question types, multi-page flows, drag & drop,
  autosave, undo/redo, live preview, reusable question library
- **Publishing** — immutable version snapshots; edits never affect live
  respondents until you republish
- **Public player** — fast, mobile-friendly, per-page response capture with
  resume; server-side validation for every type
- **Logic & scoring** — show/hide conditions, points, negative marking,
  pass marks, grade bands, custom result messages and redirects
- **Responses & leads** — filterable response manager, human-readable answer
  views, internal notes, CSV export, automatic lead extraction (including
  from abandoned attempts)
- **Analytics** — views, starts, completions, drop-off funnel, per-question
  answer distributions
- **Templates** — six seeded starter templates plus save-your-own
- **Notifications** — in-app bell + optional email for responses, leads, and
  team activity, with per-user preferences
- **Billing** — Free and Pro plans with usage limits, Paddle checkout, admin-editable from the panel
- **Platform panel** — Filament panel at `/super-admin` (separate `super_admins`
  table and guard) for customers, workspaces, plans, the template catalog and the
  landing page — including "log in as" any customer

## Setup

> Deploying to a live server (Hostinger, cPanel, VPS)? See **[DEPLOYMENT.md](DEPLOYMENT.md)**
> for the full walkthrough, including where files go on shared hosting.

```bash
git clone <repo> quizforge && cd quizforge
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
# point DB_* at a MySQL database, then:
php artisan migrate --seed

composer dev   # serves the app + queue worker + vite
```

`migrate --seed` creates the Free and Pro plans, the starter quiz templates, and
**one platform admin** — it prints the credentials, so copy the password before
the output scrolls away. Set `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in
`.env` first if you'd rather choose them yourself.

No users, workspaces or quizzes are seeded. Register at `/register` for a
customer account; a personal workspace is created for you automatically on first
sign-in. Sign in as platform staff at `/super-admin`.

To add another platform account later (or reset one's password):

```bash
php artisan app:make-super-admin you@example.com
```

Then sign in at `/super-admin`. Because staff use a separate authentication guard,
you can be signed in as a platform admin and as a customer at the same time — and
the Customers screen has a **Log in as** action that opens any customer's account
without ending your platform session.

### Payments (optional)

Gateway credentials are managed in the app, not in `.env`: sign in at
`/super-admin` and go to **Platform → Payments**. Pick Paddle or Stripe, enter the
keys, and leave "Test mode" on until you have live keys. Keys are stored encrypted.

Then create the product/price in your gateway and paste the **price ID** into the
plan under **Catalog → Plans** (`pri_…` for Paddle, `price_…` for Stripe). The Plans
list shows a "No price ID" badge for any paid plan that can't be checked out yet.

Paddle's webhook destination is `https://your-domain.com/paddle/webhook` — it must
be publicly reachable, so use a tunnel (e.g. `ngrok http 8000`) while developing.

> Stripe is currently **credential management only**. Completing a Stripe checkout
> needs `laravel/cashier`, which isn't installed and conflicts with the Paddle
> cashier package already in use.

### Billing via .env (legacy)

The `PADDLE_*` variables in `.env` still work and are used whenever the Payments
screen has no gateway selected — so installs configured before that screen existed
keep running unchanged. Anything set in the panel takes precedence.

Until a gateway is configured either way, every workspace stays on the Free plan
and the billing page shows checkout as unavailable.

## Testing

```bash
php artisan test          # 250+ tests, in-memory sqlite
vendor/bin/pint --dirty   # code style
```

## License

Proprietary — all rights reserved.
