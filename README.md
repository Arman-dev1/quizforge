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
- **Billing** — Free/Pro/Scale plans with usage limits, Paddle checkout
- **Admin panel** — Filament panel at `/admin` for users, workspaces, and the
  global template catalog

## Setup

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

Register at `/register`, then grant yourself platform admin:

```bash
php artisan app:make-admin you@example.com
```

### Billing (optional)

Create a [Paddle sandbox](https://sandbox-vendors.paddle.com) account and fill
the `PADDLE_*` variables in `.env` (seller id, API key, client-side token,
webhook secret, and the price ids for the Pro and Scale plans). Until then the
app runs entirely on the Free plan and the billing page shows checkout as not
configured.

## Testing

```bash
php artisan test          # 215+ tests, in-memory sqlite
vendor/bin/pint --dirty   # code style
```

## License

Proprietary — all rights reserved.
