# Mudeer Bedaie

Laravel Livewire Starter Kit application built with Laravel 12, Livewire Volt, and Flux UI components.

## Features

- Laravel 12 with PHP 8.2+
- Livewire Volt (single-file components)
- Flux UI components
- Tailwind CSS v4
- SQLite database
- Pest PHP testing framework
- GitHub Actions CI/CD

## Development Setup

### Requirements
- PHP 8.2+
- Composer
- Node.js & npm

### Installation

```bash
# Clone the repository
git clone https://github.com/aminpamelo/mudeerbedaie.git
cd mudeerbedaie

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Start development server
composer run dev
```

## Testing

```bash
# Run tests
composer run test

# Code formatting
./vendor/bin/pint
```

## Access Roles

For testing purposes:

- **Admin**: admin@example.com / password
- **Teacher**: teacher@example.com / password  
- **User**: user@example.com / password

## License

Open source Laravel application.