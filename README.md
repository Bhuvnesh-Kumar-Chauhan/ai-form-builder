# AI Form Builder

A modern, AI-powered form builder built with **Laravel 12 + Livewire**. Describe a form in plain language and let AI generate a complete, validated schema — or import a `.docx`/`.xlsx` file, or build it field-by-field in a visual drag-free builder. Then publish it, collect submissions, and measure performance with built-in analytics.

- **Repository:** https://github.com/Bhuvnesh-Kumar-Chauhan/ai-form-builder
- **Live Demo:** https://edunet.shivammehendiart.com/login

## Demo Credentials

These accounts are created by the database seeder (`php artisan migrate --seed`). The password for every account is `password`.

| Role         | Email                     | Permissions                                                     |
|--------------|---------------------------|-----------------------------------------------------------------|
| Super Admin  | `superadmin@example.com`  | All permissions — can manage any form, users, and settings      |
| Admin        | `admin@example.com`       | Full form & submission management (no user/role management)     |
| Editor       | `editor@example.com`      | Create, edit, and view forms; view and export submissions       |
| Viewer       | `viewer@example.com`      | View forms and submissions only                                 |

> **Note:** These are seeded demo credentials. Change them before going to production.

## Features

- **AI form generation** — prompt → complete schema in create and edit modes, with JSON-mode output, retries, and schema repair
- **AI form audit** — scored report (0–100) with one-click fixes for validation and UX issues
- **Document import** — parse `.docx` and `.xlsx` files into editable fields (template + plain header-row layouts)
- **Visual form builder** — 20+ field types, multi-step wizards, themes, reorder/duplicate, and raw JSON schema editor
- **Form versioning & rollback** — snapshot history, side-by-side diff preview, reversible one-click rollback
- **Completion & drop-off analytics** — views, starts, per-step funnel, completion time, and 14-day activity
- **Spam protection** — honeypot, time-trap, and IP velocity checks with a spam filter in the submissions list
- **Submission management** — search, filter, bulk delete, CSV export, and email notifications
- **Role-based access control** — Spatie Laravel Permission (super-admin / admin / editor / viewer)

## Technology Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Blade + Livewire 4 + Bootstrap 5
- **Database:** MySQL (SQLite/PostgreSQL also supported)
- **AI:** OpenAI-compatible `/chat/completions` API (or the bundled mock server)
- **Docs:** PhpSpreadsheet + PhpWord
- **Queue:** Laravel Queue (+ optional Horizon)

## Installation

```bash
git clone https://github.com/Bhuvnesh-Kumar-Chauhan/ai-form-builder.git
cd ai-form-builder
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`, then migrate and seed:

```bash
php artisan migrate --seed
php artisan storage:link
```

Start the app:

```bash
php artisan serve
npm run dev
```

## AI Setup

Point `.env` at a real provider:

```env
AI_SERVICE_URL=https://api.openai.com/v1
AI_API_KEY=sk-your-key
AI_MODEL=gpt-4o-mini
AI_JSON_MODE=true
```

Or use the bundled mock LLM server for zero-cost exploration:

```bash
node scripts/mock-llm-server.mjs
```

```env
AI_SERVICE_URL=http://localhost:8001
AI_JSON_MODE=false
```

## Testing

```bash
composer test
```

## Documentation

Design decisions for the analytics, versioning, and spam-protection features are documented in [`DECISIONS.md`](DECISIONS.md).

## License

MIT.
