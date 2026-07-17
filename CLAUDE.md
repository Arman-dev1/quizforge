# QuizForge

Commercial SaaS quiz builder: quizzes, surveys, assessments, lead-gen forms.
Stack: **Laravel 12, Livewire 4 + Volt (single-file components), Tailwind 4, Flux (free), Alpine + SortableJS, MySQL, Pest, Filament 5 (admin), Cashier Paddle (billing)**.

## Commands

```bash
composer dev            # server + queue worker + logs + vite, all at once
php artisan test        # full suite (uses in-memory sqlite)
vendor/bin/pint --dirty # code style
npm run build           # frontend assets
php artisan db:seed --class=TemplateSeeder  # global quiz templates
php artisan app:make-admin <email>          # grant /admin access
```

## Architecture

- **Tenancy**: single DB. Workspace-owned models use the `BelongsToWorkspace` trait
  ([app/Models/Concerns](app/Models/Concerns/BelongsToWorkspace.php)) — global scope on
  `auth()->user()->current_workspace_id` + auto-fill on create. Public surfaces (player,
  export by slug) must query `withoutGlobalScope('workspace')` explicitly.
  `SetCurrentWorkspace` middleware guarantees every authed user has a valid current
  workspace (lazily creates a personal one).
- **Roles**: `WorkspaceRole` enum (owner/admin/editor/viewer) on the `workspace_members`
  pivot. Multiple owners allowed; last-owner protections everywhere. Platform admin is a
  separate `users.is_admin` flag (Filament panel at `/admin`).
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
- **Plans/limits**: `config/plans.php` + `Services/Billing/UsageLimits`. Gates:
  `QuizPolicy::create` (quota), invite seats, player monthly quota (renders as closed).
  Workspace is the Paddle billable.
- **Volt pages** live in `resources/views/livewire/` (PHP class block + template in one
  file). Routes in [routes/web.php](routes/web.php); public player at `q/{slug}`.

## Conventions & gotchas

- Every quiz-child lookup goes through the parent: `$quiz->questions()->findOrFail($id)`.
  Never a bare `Question::find` in components.
- Livewire/Volt tests: authorization failures → `->assertForbidden()`; scoped
  `findOrFail` misses → real `ModelNotFoundException` (try/catch or expectException).
- Never mix inline `@php(...)` and block `@php ... @endphp` in one Blade file — the
  block extractor mispairs them. At most one block form per file.
- Multi-line git commit messages: write to a file, `git commit -F <file>` (PowerShell
  here-strings are unreliable here).
- The zinc-* Tailwind classes are remapped to **stone** values in
  [resources/css/app.css](resources/css/app.css); brand accent is orange
  ("Ember & Stone" palette). Chart/meter marks use `bg-orange-600` in both modes
  (validated for contrast/CVD).
- Snapshot content (with `is_correct`) must never enter Livewire public properties —
  it would leak answers to the browser. Fetch per request in `with()`.
- Drag & drop: `x-sortable` Alpine directive (resources/js/app.js) + `data-sort-*`
  attributes calling a `method(itemId, target, position)` Livewire action.
- Undo/redo: session-backed snapshots, structural mutations only (`pushHistory()` before
  each), text edits excluded on purpose.

## Deferred backlog (in value order)

Page-jump logic rules, personality outcomes, PDF certificates, file-upload/signature in
the public player, response tags & assignment, Paddle checkout verification (needs
sandbox keys in `.env`: PADDLE_*), embed SDK.
