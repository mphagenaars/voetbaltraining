# Voetbaltraining AI Instructions

## Project Overview
A self-hosted, vanilla PHP web application for football coaches to manage training sessions, exercises, and team lineups.
- **Architecture**: Custom MVC-style structure without a framework.
- **Database**: SQLite (`data/database.sqlite`).
- **Frontend**: Plain HTML/CSS (`public/css/style.css`) with some JS (`public/js/`).

## Architecture & Patterns

### 1. Entry Point & Routing
- **Front Controller**: `public/index.php` is the single entry point.
- **Routing**: Implemented via a simple `switch` statement on `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`.
- **Dependency Injection**: Models are instantiated in the controller/router and passed the Database connection manually.
- **Autoloading**: **No Composer**. Classes are loaded via manual `require_once` calls.

### 2. Database Layer
- **Connection**: `src/Database.php` provides a configured `PDO` instance.
- **Schema**: Defined in `scripts/init_db.php`. This file also handles basic migrations (adding columns if missing).
- **Conventions**:
  - Always use prepared statements (`:param`) for user input.
  - SQLite Foreign Keys are explicitly enabled (`PRAGMA foreign_keys = ON`).
  - **Tables**: `users`, `teams`, `team_members`, `exercises`, `trainings`, `training_exercises`, `players`, `lineups`, `lineup_positions`.

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
- **Assets**: CSS/JS are in `public/` and referenced relative to root (e.g., `/css/style.css`).

## Development Workflow

### Setup & Run
1. **Initialize/Update Database**:
   ```bash
   php scripts/init_db.php
   # Check scripts/ folder for other migration scripts (e.g., add_*.php)
   ```
2. **Start Server**:
   ```bash
   php -S localhost:8000 -t public
   ```

### Coding Standards
- **PHP Version**: 8.1+ features (typed properties, constructor promotion).
- **Strict Types**: `declare(strict_types=1);`.
- **Error Handling**: Use `try-catch` blocks for DB operations; `index.php` handles top-level errors.
- **Session**: `session_start()` is handled in `index.php`. Auth checks use `isset($_SESSION['user_id'])`.

## Key Files
- `public/index.php`: Router and controller logic.
- `src/Database.php`: Database connection factory.
- `scripts/init_db.php`: Database schema definition (Single Source of Truth for schema).
- `src/models/`: Business logic classes.
