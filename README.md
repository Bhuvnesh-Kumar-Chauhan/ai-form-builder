# AI Form Builder

AI Form Builder is a Laravel + Livewire application that helps teams create, manage, and analyze dynamic forms — powered by AI. Instead of designing every field and validation rule by hand, you can describe what you need and let the AI generate a complete, production-ready form in seconds. You can then fine-tune it in the visual builder, or import an existing Word/Excel document and have it converted into editable fields automatically.

## About The Project

Creating forms manually is slow and repetitive, especially when applications require many fields, validations, and different structures. AI Form Builder tackles this from three angles:

1. **Generate** — ask the AI for a form in plain language ("internship application", "translate my form to Hindi") and get a complete, validated schema.
2. **Import** — drop in a `.docx` or `.xlsx` file and have its structure parsed into builder-ready fields.
3. **Audit** — let the AI review your form for validation quality, weak labels, and UX issues, then apply the suggested fixes in one click.

Beyond the AI workflows, the app ships a full visual form builder, a public-facing form renderer (multi-step capable), submission management, email notifications, role-based permissions, and a JSON schema editor.

## Features

### Core
* **Visual form builder** — add, edit, duplicate, remove, and reorder 20+ field types (text, email, phone, date, rating, file upload, select, radio, checkbox, layout blocks, and more)
* **Public form rendering** — responsive fill page with per-field validation, multi-step wizard support, and themes
* **Submission management** — view/search submissions, delete single or bulk, export to CSV
* **Email notifications** — optional notification to the form owner (or a custom address) on every new submission
* **Schema editor** — power users can view/edit the raw JSON schema
* **Form lifecycle** — drafts, publish/unpublish, expiry dates, duplication, soft-delete

### AI (Parts A & B)
* **AI form generation** — plain-language prompt → complete form schema, in *create* and *edit* (modify existing form) modes
* **Robust LLM integration** — JSON-mode output, automatic retries with the parse error fed back to the model, type/validation coercion, and repair of hallucinated field types
* **AI form audit** — a scored report (0–100) with severity-tagged issues and a one-click "apply suggested fixes"

### Document Import (Part C)
* **DOCX parsing** — extracts the title, headings/sections, and question fields; detects emails/phones/dates; turns bulleted lists and tables into radio/checkbox/select options (including native content-control checkboxes)
* **XLSX parsing** — supports both a *template* layout (`type`, `label`, `required`, `options`, `placeholder`, `help_text`, `section` columns) and a plain *header-row/data* layout with type inference from the actual values
* **Hybrid AI refinement** — an optional second pass where the LLM only improves types/required flags/validation for ambiguous fields; the structure always comes from the document
* **Queued parsing** — large files are parsed in the background with live status polling; small files parse inline

### Access Control
* Role-based permissions via Spatie Laravel Permission
* Roles: **super-admin** (everything), **admin**, **editor**, **viewer**
* Super admins can manage any form regardless of ownership

## Technology Stack

* **Backend:** Laravel 12 (PHP 8.2+)
* **Frontend:** Blade + Livewire 4 + Bootstrap 5 (no build step required for views)
* **Database:** SQLite (default) / MySQL / PostgreSQL
* **Queue:** Laravel Queue (database driver by default) + optional Laravel Horizon
* **AI Integration:** OpenAI-compatible `/chat/completions` API (works with official OpenAI or a local mock server)
* **Document parsing:** PhpSpreadsheet + PhpWord
* **Email:** Laravel Mail (log driver in local dev)

## Installation

### Prerequisites
* PHP 8.2+ with the `zip` extension (required for DOCX import)
* Composer
* Node.js / npm (for asset tooling)

### Steps

Clone the repository:

```bash
git clone <repository-url>
cd ai-form-builder
```

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create your environment file and generate an app key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`. The default is SQLite:

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password
```

For SQLite, create the database file (if it doesn't already exist):

```bash
touch database/database.sqlite
```

Run migrations and seed the database (users, roles, permissions, and 5 sample forms):

```bash
php artisan migrate --seed
```

Link storage for file uploads:

```bash
php artisan storage:link
```

Start the development server and asset watcher:

```bash
php artisan serve
npm run dev
```

## Dummy Credentials

The seeder creates the following accounts. The password for every account is `password`.

| Role         | Email                     | Permissions                                      |
|--------------|---------------------------|--------------------------------------------------|
| Super Admin  | `superadmin@example.com`  | All permissions (can manage any form)            |
| Admin        | `admin@example.com`       | Forms, submissions, and settings (no user/role mgmt) |
| Editor       | `editor@example.com`      | Create, edit, and view forms + view/export submissions |
| Viewer       | `viewer@example.com`      | View forms and submissions only                  |

## AI Setup

By default the app points at a **mock LLM server** that ships with the repo, so you can explore every AI feature with zero API cost.

### Using the bundled mock server

```bash
node scripts/mock-llm-server.mjs
```

Keep these `.env` values:

```env
AI_SERVICE_URL=http://localhost:8001
AI_JSON_MODE=false
```

The mock server responds to special keywords in your prompt to exercise edge cases (`garbage`, `bogus types`, `hindi`, `emergency`, `phone required`, `refine`, `audit`).

### Using a real provider (OpenAI or compatible)

```env
AI_SERVICE_URL=https://api.openai.com/v1
AI_API_KEY=sk-your-key
AI_MODEL=gpt-4o-mini
AI_JSON_MODE=true
```

Other tunable options live in `config/ai.php` (temperature, max tokens, retry attempts, import size/queue thresholds).

## Queue Configuration

AI generation, AI audit, document parsing (for large files), and email notifications use queued jobs. The `.env.example` already defaults to the database driver:

```env
QUEUE_CONNECTION=database
```

Run the queue worker (e.g. in a second terminal):

```bash
php artisan queue:work
```

Or run the full dev stack with a single command (server + queue + logs + Vite):

```bash
composer run dev
```

> **Laravel Horizon:** Horizon requires Linux-specific PHP extensions (`pcntl` and `posix`). On Windows (e.g. XAMPP), run Horizon inside WSL2 or a Linux environment.

## Testing

The suite covers the AI generation pipeline, schema repair, document import parsers, and the import job:

```bash
composer test
```

`composer test` clears the configuration cache before running `php artisan test`. If you run `php artisan test` directly after `php artisan config:cache` (or `optimize`), the stale cached config breaks the test environment — clear it first with `php artisan config:clear`.

Sample import files used by the tests live in `tests/fixtures/` and can be regenerated with:

```bash
php tests/fixtures/make-fixtures.php
```

## Project Structure

```
app/
├── Http/Controllers/    # FormController (submit, duplicate, publish, export, delete)
├── Livewire/            # Livewire components
│   └── Forms/           #   FormBuilder, FormList, FormView, FormSubmissions,
│                        #   FormImporter, FormAudit, AiFormGenerator
├── Jobs/                # GenerateFormSchemaJob, AuditFormSchemaJob, ParseImportJob
├── Mail/                # NewSubmissionNotification
├── Models/              # Form, FormField, FieldOption, FormSubmission, ...
├── Services/            # FormSchemaService, FormImportService, LlmClient
└── Traits/              # HasPermissions
resources/
└── views/
    ├── layouts/         # app, public, sidebar, navigation
    └── livewire/forms/  # builder, public view, submissions, import, audit, AI
routes/
└── web.php
scripts/
├── mock-llm-server.mjs  # local OpenAI-compatible server
└── smoke-import.php     # CLI smoke test for the import parsers
tests/
├── Unit/                # FormImportServiceTest, FormSchemaServiceTest
└── Feature/             # GenerateFormSchemaJobTest, ParseImportJobTest, auth/profile
```

## Usage

1. Log in with any of the **dummy credentials** above.
2. Go to **Forms → New Form**:
   * **Type a prompt** and hit *Generate* to have the AI build the form, or
   * Go to **Forms → Import** and upload a `.docx`/`.xlsx` file, or
   * Build it field-by-field in the visual builder.
3. Refine fields, validation, options, and settings as needed.
4. On a form's edit page you can also run an **AI audit** to score it and apply suggested fixes.
5. **Publish** the form and share the public link (`/f/{slug}`). Owners and admins can manage submissions (`/forms/{slug}/submissions`), export CSV, and toggle email notifications in settings.

## Future Improvements

* Form versioning and rollback
* Conditional logic and branching
* Template library
* Completion and drop-off analytics
* AI multi-language forms
* Embeddable widget / QR sharing
* Webhooks and a public submissions API
* CI/CD and Docker packaging

## Contributing

Contributions are welcome. Feel free to submit improvements, bug fixes, or feature requests.

## License

This project is open-source and available under the MIT License.
