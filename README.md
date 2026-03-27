# Starboard

> Surf the Web like a pro.

[![Tests](https://github.com/zlatanstajic/starboard/actions/workflows/tests.yml/badge.svg)](https://github.com/zlatanstajic/starboard/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![Coverage: 95%+](https://img.shields.io/badge/Coverage-95%25%2B-brightgreen.svg)](https://github.com/zlatanstajic/starboard/actions)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com/)

A modern, centralized platform to track and manage your favorite creators and influencers across multiple social media networks. Monitor profile visits, organize favorites, and maintain a comprehensive directory of creators all in one place.

## Features

- **Multi-Network Support**: Track creators across multiple social media platforms (Instagram, TikTok, Twitter, YouTube, etc.)
- **Profile Management**: Create, edit, and delete creator profiles with ease
- **Visit Tracking**: Automatically track and log visits to creator profiles
- **Favorites System**: Mark profiles as favorites for quick access
- **Privacy Control**: Set profiles as public or private
- **Advanced Filtering**: Filter by network source, visit count, last visit date, status, and favorites
- **Smart Sorting**: Sort by username, visits, last visit date, creation date, and update date
- **Search Functionality**: Quick search for specific creator usernames
- **Responsive Design**: Fully responsive UI that works on desktop, tablet, and mobile
- **Dark Mode**: Native dark mode support for comfortable viewing
- **Real-time Updates**: Visit counts update in real-time without page refresh
- **Pagination**: Browse through profiles with efficient pagination

## Tech Stack

- **Backend**: Laravel 12 with PHP 8.3
- **Frontend**: Blade Templates with Alpine.js
- **Styling**: Tailwind CSS
- **Database**: MySQL/MariaDB
- **Testing**: PHPUnit
- **Code Quality**: Pint (Laravel code style fixer), PHPStan

## Requirements

### Without Docker

- PHP 8.3 or higher
- Composer
- MySQL/MariaDB 5.7+
- Node.js 16+ (for asset compilation)

### With Docker

- Docker 24+
- Docker Compose v2+

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/zlatanstajic/starboard.git
cd starboard
```

### 2. Setup the project

Adjust environment variables in `.env` file and run:

```bash
composer setup
```

This command will:
- Install PHP dependencies
- Install Node.js dependencies
- Generate application key
- Run database migrations
- Seed the database with sample data
- Build frontend assets

## Docker

The project ships with a production-ready Docker setup using a multi-stage `Dockerfile` and a `docker-compose.yml` that orchestrates three services: `app` (PHP-FPM 8.3), `nginx`, and `mysql`.

### Quick Start

```bash
# 1. Copy and configure environment variables
cp .env.example .env

# 2. Build the image and start all services
docker compose up -d --build

# 3. Seed the database (first run only)
docker compose exec app php artisan db:seed
```

The application will be available at `http://localhost:18000`.

### Environment Variables

Override any of these in your `.env` file before running `docker compose up`:

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | *(auto-generated)* | Laravel application key |
| `APP_PORT` | `18000` | Host port mapped to nginx |
| `DB_DATABASE` | `starboard` | MySQL database name |
| `DB_USERNAME` | `starboard` | MySQL user |
| `DB_PASSWORD` | `secret` | MySQL user password |
| `DB_ROOT_PASSWORD` | `rootsecret` | MySQL root password |

**Note**: MySQL is exposed on port `13306` externally and `3306` internally within Docker.

### Common Commands

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# Rebuild after code changes
docker compose up -d --build

# Stream application logs
docker compose logs -f app

# Run Artisan commands
docker compose exec app php artisan <command>

# Open a shell inside the app container
docker compose exec app bash

# Run database migrations
docker compose exec app php artisan migrate

# Destroy containers and volumes (resets the database)
docker compose down -v
```

### Docker File Structure

```
docker/
├── entrypoint.sh       # Container startup script (migrations, cache)
├── nginx/
│   └── default.conf    # Nginx virtual host configuration
└── php/
    └── php.ini         # PHP production settings
Dockerfile              # Multi-stage build (node → composer → app)
docker-compose.yml      # Service definitions
.dockerignore           # Files excluded from the build context
```

## Configuration

### Environment Variables

Key environment variables to configure in `.env`.

## Usage

### Starting the Development Server

```bash
composer run serve
```

The application will be available at `http://localhost:8000`

## Testing

### Run All Tests

```bash
composer run test
```

## Code Quality

### Pre-commit Hook

- A Husky pre-commit hook is present at `.husky/pre-commit` and runs the test suite via `composer run test`. Commits may be blocked if tests fail; run `composer run test` locally to reproduce the check.

## License

This project is licensed under the MIT License, see the [LICENSE](LICENSE.md) file for details.
