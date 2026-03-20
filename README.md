# Starboard

> Surf the Web like a pro.

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

- PHP 8.3 or higher
- Composer
- MySQL/MariaDB 5.7+
- Node.js 16+ (for asset compilation)

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

## Configuration

### Environment Variables

Key environment variables to configure in `.env`:

```
APP_NAME=Starboard
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=starboard
DB_USERNAME=root
DB_PASSWORD=
```

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

This project is licensed as proprietary, see the [LICENSE](LICENSE.md) file for details.
