# Vortex - Premium PHP REST API (Inventory Management System with Auth)

Vortex is a lightweight, high-performance PHP REST API for Inventory Management, supporting multiple database back-ends (MySQL, SQLite, MongoDB) using a dynamically-loaded `.env` configuration file, secured with BCrypt password hashing and custom session-token authorization.

---

## Features
- **Database Agnostic Abstraction**: Switch servers easily using the `DB_DRIVER` variable. Compatible with MySQL/MariaDB, SQLite, and MongoDB.
- **Secure Authentication**:
  - `POST /api/register.php` — Secure user registration using standard BCrypt password hashing.
  - `POST /api/login.php` — Issue a unique cryptographically secure bearer token upon login.
  - `POST /api/logout.php` — Clear and invalidate session tokens.
- **Authorized Inventory Endpoints**:
  - All routes to `api/items.php` require the header: `Authorization: Bearer <access_token>`.
  - `GET /api/items.php` — Query inventory items with pagination & filters.
  - `GET /api/items.php?id={id}` — Fetch details for a specific item.
  - `POST /api/items.php` — Create a new item.
  - `PUT /api/items.php?id={id}` — Update item attributes.
  - `DELETE /api/items.php?id={id}` — Remove item.
- **Secure prepared statements**: SQL parameters are safely bound to prevent SQL Injection (SQLi) attacks.

---

## File Structure
```
api_php/
├── config/
│   ├── db.php          # Database helper class (PDO & MongoDB adapter)
│   ├── auth.php        # Authentication middleware guard
│   └── schema.sql      # Database schema and seed data for MariaDB / MySQL
├── api/
│   ├── items.php       # Secured PHP REST API handling inventory CRUD
│   ├── register.php    # User registration endpoint
│   ├── login.php       # User login session generator
│   └── logout.php      # Session token invalidator
├── .env                # Environment configuration variables
└── README.md           # Setup and endpoints documentation
```

---

## Setup & Configuration

### 1. Database environment settings (.env)
Configure your `.env` file in the project root:
```ini
DB_DRIVER=sqlite

# MySQL / MariaDB Config
DB_HOST=localhost
DB_PORT=3306
DB_NAME=inventory_db
DB_USER=root
DB_PASS=password

# SQLite Config
DB_FILE=inventory.db

# MongoDB Config
MONGODB_URI=mongodb://localhost:27017
MONGODB_DB=inventory_db
```

### 2. Start Web Server
Run using XAMPP (place in `C:\xampp\htdocs\api_php`), Laragon, or via the built-in development server:
```bash
php -S localhost:8000
```

---

## Endpoints Reference

Refer to the complete handbook [api_test_urls.txt](file:///c:/xampp/htdocs/api_php/api_test_urls.txt) in the project root for URLs, JSON body shapes, and headers required to verify register, login, logout, and authenticated inventory operations.
