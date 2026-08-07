# AI Form Builder

AI Form Builder is a Laravel-based application that helps users generate dynamic forms using artificial intelligence. The project simplifies form creation by allowing users to create structured forms automatically instead of manually designing every field and validation rule.

## About The Project

Creating forms manually can be time-consuming, especially when applications require multiple fields, validations, and different form structures. AI Form Builder uses AI-powered generation to create forms based on user requirements.

The application provides a flexible system for generating, managing, and displaying dynamic forms.

## Features

* AI-powered form generation
* Dynamic form creation
* Custom form fields
* Form validation support
* User-friendly form management
* Database-driven form structures
* Laravel-based backend architecture
* Queue support for background processing
* Scalable application structure

## Technology Stack

* **Backend:** Laravel 12
* **Language:** PHP 8+
* **Database:** MySQL / PostgreSQL
* **Frontend:** Blade / Livewire
* **Queue System:** Laravel Queue
* **AI Integration:** AI API based form generation

## Installation

### Clone the Repository

```bash
git clone <repository-url>

cd ai-form-builder
```

### Install Dependencies

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

### Environment Setup

Create your environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Configure your database details inside `.env`:

```env
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Run Database Migration

```bash
php artisan migrate
```

### Start Development Server

Run Laravel server:

```bash
php artisan serve
```

Run frontend assets:

```bash
npm run dev
```

## Queue Configuration

AI form generation may use background jobs for processing.

Set your queue driver:

```env
QUEUE_CONNECTION=database
```

Create queue tables:

```bash
php artisan queue:table

php artisan migrate
```

Start queue worker:

```bash
php artisan queue:work
```

## Laravel Horizon

Laravel Horizon can be used for monitoring queues in production environments.

Note: Horizon requires Linux-specific PHP extensions (`pcntl` and `posix`). If you are developing on Windows with XAMPP, run Horizon inside WSL2 or a Linux server environment.

## Project Structure

```
app/
├── Http/
├── Models/
├── Jobs/
└── Livewire/

resources/
├── views/
└── js/

database/
├── migrations/
└── seeders/
```

## Usage

1. Register or log in to the application.
2. Enter your form requirements.
3. Let AI generate the form structure.
4. Customize generated fields if needed.
5. Save and use the generated form.

## Future Improvements

* More AI model integrations
* Form templates library
* Advanced field customization
* Drag-and-drop form builder
* Export forms to different formats
* Advanced analytics

## Contributing

Contributions are welcome. Feel free to submit improvements, bug fixes, or feature requests.

## License

This project is open-source and available under the MIT License.
