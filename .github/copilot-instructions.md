# Voetbaltraining AI Instructions

## Project Overview
A self-hosted, vanilla PHP web application for football coaches to manage training sessions, exercises, and team lineups.
- **Architecture**: Custom MVC-style structure without a framework.
- **Database**: SQLite (`data/database.sqlite`).
- **Frontend**: Plain HTML/CSS (`public/css/style.css`).

## Architecture & Patterns

### 1. Entry Point & Routing
- **Front Controller**: `public/index.php` is the single entry point.
- **Routing**: Implemented via a simple `switch` statement on `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`.
- **Dependency Injection**: Models are instantiated in the controller/router and passed the Database connection.

### 2. Database Layer
- **Connection**: `src/Database.php` provides a configured `PDO` instance.
- **Schema**: Defined in `scripts/init_db.php`.
- **Conventions**:
  - Always use prepared statements (`:param`) for user input.
  - SQLite Foreign Keys are explicitly enabled (`PRAGMA foreign_keys = ON`).
  - Tables: `users`, `teams`, `team_members`.

### 3. Models (`src/models/`)
- Contain all business logic and database interactions.
- **Pattern**: Constructor injection for `PDO`.
- **Example**:
  ```php
  class Team {
      public function __construct(private PDO $pdo) {}
      public function create(string $name): int { ... }
  }
  ```

### 4. Views (`src/views/`)
- Plain PHP files used as templates.
- **Layouts**: `src/views/layout/header.php` and `footer.php` are manually included.
- **Security**: Output must be escaped using `htmlspecialchars()`.

## Development Workflow

### Setup & Run
1. **Initialize Database**:
   ```bash
   php scripts/init_db.php
   php scripts/create_admin.php # Optional: creates default admin
   ```
2. **Start Server**:
   ```bash
   php -S localhost:8000 -t public
   ```

### Coding Standards
- **PHP Version**: 8.1+ features (typed properties, constructor promotion).
- **Strict Types**: `declare(strict_types=1);` is mandatory at the top of every PHP file.
- **Autoloading**: Currently manual `require_once` is used for class loading (no Composer autoloader).
- **Error Handling**: Use `try-catch` blocks for DB operations; `index.php` handles top-level errors.

## Key Files
- `public/index.php`: Router and controller logic.
- `src/Database.php`: Database connection singleton.
- `scripts/init_db.php`: Database schema definition (Single Source of Truth for schema).
