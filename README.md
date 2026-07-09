# Vortex - Premium PHP REST API (Inventory Management System)

Vortex is a lightweight, high-performance PHP REST API for Inventory Management, supporting multiple database back-ends (MySQL, SQLite, MongoDB) using a dynamically-loaded `.env` configuration file.

---

## Features
- **Database Agnostic Abstraction**: Switch servers easily using the `DB_DRIVER` variable. Compatible with MySQL/MariaDB, SQLite, and MongoDB.
- **Full CRUD Endpoints**:
  - `GET /api/items.php` — Query inventory items with pagination & filters.
  - `GET /api/items.php?id={id}` — Fetch details for a specific item.
  - `POST /api/items.php` — Create a new item (validates SKU uniqueness).
  - `PUT /api/items.php?id={id}` — Update item attributes.
  - `DELETE /api/items.php?id={id}` — Remove item.
- **Dynamic Search & Filtering**: Supports matching parameters (`search`, `category`, `min_price`, `max_price`).
- **Pagination**: Structured database limits and offsets queries, returning counts and total offsets.
- **Secure prepared statements**: SQL parameters are safely bound to prevent SQL Injection (SQLi) attacks.

---

## File Structure
```
api_php/
├── config/
│   ├── db.php          # Database helper class (PDO & MongoDB adapter)
│   └── schema.sql      # Database schema and seed data for MariaDB / MySQL
├── api/
│   └── items.php       # PHP REST API handling GET, POST, PUT, DELETE
├── .env                # Environment configuration variables
└── README.md           # Setup and endpoints documentation
```

---

## Setup & Configuration

### 1. Database environment settings (.env)
Create or modify the `.env` file in the project root:
```ini
# Choose driver: mysql, sqlite, or mongodb
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
You can run this application locally using any PHP runtime. For example, using the built-in development server:
```bash
php -S localhost:8000
```

---

## Endpoints Reference

### 1. GET Inventory List
* **Route**: `GET /api/items.php`
* **Query Parameters**:
  - `limit`: Records count (default: 10).
  - `offset`: Records offset (default: 0).
  - `search`: String query match (Name, SKU, Description).
  - `category`: Exact match filter.
  - `min_price` / `max_price`: Float thresholds.
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Wireless Ergonomic Mouse",
      "sku": "MS-ERG-01",
      "description": "A comfortable vertical wireless mouse...",
      "price": 49.99,
      "quantity": 120,
      "category": "Electronics",
      "created_at": "2026-07-09 20:10:00",
      "updated_at": "2026-07-09 20:10:00"
    }
  ],
  "categories": ["Electronics", "Furniture"],
  "pagination": {
    "total": 45,
    "limit": 10,
    "offset": 0,
    "count": 1
  }
}
```

### 2. GET Item Details
* **Route**: `GET /api/items.php?id={id}` (Supports integer ID for SQL and String/ObjectId for MongoDB)
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Wireless Ergonomic Mouse",
    "sku": "MS-ERG-01",
    "description": "...",
    "price": 49.99,
    "quantity": 120,
    "category": "Electronics",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### 3. POST Create Item
* **Route**: `POST /api/items.php`
* **Body (JSON)**:
```json
{
  "name": "Noise Cancelling Headphones",
  "sku": "HP-ANC-07",
  "description": "Over-ear bluetooth headphones",
  "price": 129.99,
  "quantity": 60,
  "category": "Electronics"
}
```

### 4. PUT Update Item
* **Route**: `PUT /api/items.php?id={id}`
* **Body (JSON)** (Partial updates supported):
```json
{
  "price": 119.99,
  "quantity": 55
}
```

### 5. DELETE Item
* **Route**: `DELETE /api/items.php?id={id}`
