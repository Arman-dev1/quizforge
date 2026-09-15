# QuizForge

Commercial SaaS quiz builder: quizzes, surveys, assessments, lead-gen forms.
Stack: **Laravel 12, Livewire 4 + Volt (single-file components), Tailwind 4, Flux (free), Alpine + SortableJS, MySQL, PHPUnit, Filament 5 (admin), Cashier Paddle (billing)**.

## Commands

```bash
composer dev            # server + queue worker + logs + vite, all at once
php artisan test        # full suite (uses in-memory sqlite)
vendor/bin/pint --dirty # code style
npm run build           # frontend assets
php artisan db:seed --class=TemplateSeeder  # global quiz templates
php artisan app:make-super-admin <email>    # create/update a platform (super admin) account
php artisan app:sync-subscriptions          # reconcile subscriptions with Paddle (webhook safety net)
```

## Architecture

- **Tenancy**: single DB. Workspace-owned models use the `BelongsToWorkspace` trait
  ([app/Models/Concerns](app/Models/Concerns/BelongsToWorkspace.php)) — global scope on
  `auth()->user()->current_workspace_id` + auto-fill on create. Public surfaces (player,
  export by slug) must query `withoutGlobalScope('workspace')` explicitly.
  `SetCurrentWorkspace` middleware guarantees every authed user has a valid current
  workspace (lazily creates a personal one).
- **Roles**: `WorkspaceRole` enum (owner/admin/editor/viewer) on the `workspace_members`
  pivot. Multiple owners allowed; last-owner protections everywhere. **Platform staff are a
  separate `super_admins` table on its own `super_admin` guard** (Filament panel at
  `/super-admin`). Both guards can hold a session at once, which is what makes
  "log in as this client" (`Services/Platform/Impersonation`) work.
- **Quiz content**: normalized (`quizzes → quiz_pages → questions → question_options`),
  but **publishing snapshots content into `quiz_versions` (immutable JSON)**. The public
  player renders only snapshots; responses reference their version. Republish = version+1.
- **Responses**: `quiz_responses` + `quiz_answers` (JSON value per question), saved
  **per page** as respondents progress — partials are leads. Session token resumes.
  Scores/grades are stamped onto the response at completion; never recompute.
- **Engines** (pure services, unit-tested): `Services/Logic/LogicEngine` (visibility
  conditions; triggers limited to earlier pages), `Services/Scoring/ScoringEngine` +
  `ResultResolver`, `Services/Player/AnswerValidator` (per-type rules incl. option-id
  membership).
- **Queues**: everything (integration syncs, invitation mail, notifications) runs on
  the single `default` queue — no job sets `onQueue()`. One worker covers the lot.
  A stopped worker breaks integrations *silently* (they still read "Connected"),
  so the Connect tab warns when jobs sit unprocessed for >2 minutes.
- **Paid feature flags** live in `plans.flags`: `integrations`, `custom_code`,
  `remove_branding`. Each is enforced where the feature is *used*, not only where
  its control is drawn, so a downgrade takes effect immediately (e.g. the player
  re-shows "Powered by" regardless of the stored `design.hide_branding`).
- **Plans/limits**: `config/plans.php` + `Services/Billing/UsageLimits`. Gates:
  `QuizPolicy::create` (quota), invite seats, player monthly quota (renders as closed).
  Workspace is the Paddle billable. Managed plans live in the `plans` table
  (Catalog → Plans); config is the fallback when that table is empty.
- **Payments**: gateway + credentials are managed at **/super-admin → Platform →
  Payments** (`payment_settings`, one row, credentials `encrypted:array`).
  `PaymentServiceProvider` pushes them into `config('cashier.*')` /
  `config('services.stripe.*')` at boot, falling back to `.env`. One gateway is
  active at a time; both providers' keys are kept so switching is reversible.
  A plan stores a price id per gateway (`price_id` = Paddle, `stripe_price_id`);
  `Plan::activePriceId()` picks the right one. Paddle webhook: `POST /paddle/webhook`.
  The **workspace** is the billable but has no email, so `Workspace::paddleEmail()`
  /`paddleName()` bill to the owner (`billingContact()`) — without them Cashier
  throws "Unable to create Paddle customer without an email". Subscriptions are
  visible read-only at Customers → Subscriptions.
  **Stripe is credential-management only** — checkout needs `laravel/cashier`
  (Stripe), which is not installed and conflicts with `cashier-paddle`.
- **Volt pages** live in `resources/views/livewire/` (PHP class block + template in one
  file). Routes in [routes/web.php](routes/web.php); public player at `quiz/{slug}`
  (`q/{slug}` permanently redirects). Admin routes bind quizzes by **slug**.

## Conventions & gotchas

- Every quiz-child lookup goes through the parent: `$quiz->questions()->findOrFail($id)`.
  Never a bare `Question::find` in components.
- Livewire/Volt tests: authorization failures → `->assertForbidden()`; scoped
  `findOrFail` misses → real `ModelNotFoundException` (try/catch or expectException).
- Never mix inline `@php(...)` and block `@php ... @endphp` in one Blade file — the
  block extractor mispairs them. At most one block form per file.
- Multi-line git commit messages: write to a file, `git commit -F <file>` (PowerShell
  here-strings are unreliable here).
- The zinc-* Tailwind classes are remapped to cool-gray values in
  [resources/css/app.css](resources/css/app.css); brand accent is **teal** ("Clean
  Slate" palette). Chart/meter marks use `bg-teal-600` in both modes.
- Shared UI primitives live in `resources/views/components/`: `<x-page-header>`,
  `<x-panel>`, `<x-stat>`, `<x-empty-state>`, `<x-status-pill>`, `<x-quiz-nav>`,
  `<x-rich-text>`. Compose these rather than re-rolling `rounded-xl border bg-white`.
  Surfaces: `.qf-surface` (panel), `.qf-well` (inset), `.qf-tab` — see app.css.
- Author-written HTML (result descriptions) goes through `App\Support\HtmlSanitizer`
  on save and renders with `{!! !!}`. Never render author HTML that skipped it.
- Player/preview public properties are `#[Locked]`; scoring reads the *stored*
  answers, never the client-writable `$answers` array.
- Snapshot content (with `is_correct`) must never enter Livewire public properties —
  it would leak answers to the browser. Fetch per request in `with()`.
- **No `@tailwindcss/forms`.** Native controls get zero styling, so `border-zinc-300`
  alone paints a colour on a 0-width border — the field renders invisible. Player
  controls use the `.qf-field` / `.qf-choice` / `.qf-scale-btn` classes in app.css,
  which are driven by the per-quiz `--qf-*` design variables (scoped to `#qf-player`,
  with `data-input-style` on that element carrying the Design tab's input style).
- **Checkbox groups must be seeded to `[]`.** Livewire only gathers checkboxes into
  an array when the bound property already *is* one; left null, each box binds as an
  independent boolean and only one selection survives. `initializeAnswerDefaults()`
  in both the player and the preview does this for multiple choice (and seeds
  ranking with the shown order).
- Drag & drop: `x-sortable` Alpine directive (resources/js/app.js) + `data-sort-*`
  attributes calling a `method(itemId, target, position)` Livewire action.
- Undo/redo: session-backed snapshots, structural mutations only (`pushHistory()` before
  each), text edits excluded on purpose.

## Results (thank-you screens)

`quiz->settings['results']` holds `mode` (`simple` | `score` | `category`) plus an
`outcomes` array. Score mode matches on percentage bands (highest `min` wins);
category mode tallies `question_options.settings['category']` across the chosen
options and matches the winner (`Services/Scoring/CategoryScorer`). Edited on the
per-quiz **Results** tab; resolved by `Services/Scoring/ResultResolver`.

## Deferred backlog (in value order)

Page-jump logic rules, PDF certificates, file-upload/signature in the public player,
response tags & assignment, Stripe checkout (needs `laravel/cashier`), embed SDK,
password reset for platform staff (deliberately omitted — use `app:make-super-admin`).

## Gotcha: the gap between paying and being subscribed

The Paddle overlay confirms the payment in the browser; the subscription only
reaches us later, by webhook. So the billing page must never render plan state
straight after checkout — it would show the *old* plan and read as a failed
payment. Instead: no `successUrl` on the checkout (a redirect lands back here
too early), `Paddle.Initialize`'s `eventCallback` re-broadcasts
`checkout.completed` as a `paddle-checkout-completed` window event, and the
billing page enters a `confirming` state that polls. If the webhook has not
arrived by the 4th poll it reconciles directly from the gateway
(`app:sync-subscriptions --workspace=`), and after 12 it stops with an honest
"still confirming" message rather than spinning forever.

## Gotcha: Paddle.js and wire:navigate

Cashier's `@paddleJS` emits the CDN `<script>` and an inline `Paddle.Initialize()`
together in the page body. Under `wire:navigate` Livewire swaps the body and re-runs
inline scripts, but a `<script src>` inserted that way does not execute
synchronously — so the inline code runs first and throws **"Paddle is not defined"**.
We load it once in the layout `<head>` via `<x-paddle-js>` with `data-navigate-once`,
and `<x-checkout-button>` calls `Paddle.Checkout.open()` directly rather than relying
on Paddle's one-shot `.paddle_button` class scan (which never sees Livewire-rendered
buttons). Do not reintroduce `@paddleJS` or `<x-paddle-button>`.
