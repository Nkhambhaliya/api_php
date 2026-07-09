# Vortex - Premium Inventory Management System API

Vortex is a premium, high-fidelity Inventory Management System built on a lightweight PHP REST API back-end, powered by a dual-driver PDO database layer (MySQL/MariaDB with automatic local SQLite fallback), and a stunning glassmorphic frontend control dashboard.

---

## Features
- **Full CRUD Support**: Create, Read, Update, and Delete products with immediate database synchronization.
- **Dynamic Search & Filtering**:
  - Full-text search (Name, SKU, Description).
  - Exact category matching.
  - Floating-point price range thresholds (`min_price` and `max_price`).
- **Standard SQL Pagination**: Pagination via prepared SQL limit & offset queries, complete with metadata arrays (totals, pages, offsets).
- **Security First**: 100% prepared SQL statements utilizing PDO parameterized attributes to eliminate SQL Injection (SQLi) vulnerabilities.
- **Tap to Detail**: Tapping product names or descriptions dynamically fires an API details request, pulling rich specifications into a dedicated overlay window.
- **Dual-Database Drivers**: Out-of-the-box MySQL/MariaDB schema configurations with automatic SQLite sandbox fallback.

---

## File Structure
```
api_php/
├── config/
│   ├── db.php          # Database helper class (PDO MySQL/SQLite driver)
│   └── schema.sql      # Database schema and seed data for MariaDB / MySQL
├── api/
│   └── items.php       # PHP REST API handling GET, POST, PUT, DELETE
├── style.css           # Premium vanilla CSS styling (dark mode, glassmorphism)
├── app.js              # Frontend logic (AJAX requests, pagination, filter state)
├── index.html          # Web dashboard to interact with the API
└── README.md           # Instructions on how to run, configure, and use the API
```

---

## Setup & Running the Application

Since PHP is not currently added to your system's path, you can run this application by starting a local PHP web server from your PHP workspace terminal (e.g., using XAMPP shell, Laragon terminal, or standard PowerShell if configured):

### 1. Start Built-in PHP Development Server
In your workspace directory, run:
```bash
php -S localhost:8000
```
Then open your browser to: [http://localhost:8000](http://localhost:8000)

### 2. Database Modes
The system handles connections dynamically:
- **MariaDB / MySQL (Production/Local)**:
  - Create a database named `inventory_db`.
  - Import the [schema.sql](file:///c:/Nikhil/NIKK%20797/NIKK%20797%20Edu/hackathon/api_php/config/schema.sql) file to create the tables and seed default products.
  - Modify parameters in `config/db.php` if you run MySQL on a custom host/username/password.
- **SQLite (Fallback Sandbox)**:
  - If a MySQL server connection cannot be established (or if config is set to sqlite), the API automatically initializes a file-based SQLite database named `inventory.db` in your workspace and seeds it with default inventory data. No manual server configurations are required!

---

## API Endpoints Reference

### 1. Get Inventory Items (List with Filters & Pagination)
* **Route**: `GET /api/items.php`
* **Query Parameters**:
  - `limit`: Number of records to return (default: 10, range: 1-100).
  - `offset`: Number of records to skip (default: 0).
  - `search`: String match in Name, SKU, Description.
  - `category`: Exact string match on product category.
  - `min_price`: Minimum price threshold.
  - `max_price`: Maximum price threshold.
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Wireless Ergonomic Mouse",
      "sku": "MS-ERG-01",
      "description": "...",
      "price": 49.99,
      "quantity": 120,
      "category": "Electronics",
      "created_at": "2026-07-09 20:10:00",
      "updated_at": "2026-07-09 20:10:00"
    }
  ],
  "categories": ["Electronics", "Furniture", "Kitchenware"],
  "pagination": {
    "total": 45,
    "limit": 10,
    "offset": 0,
    "count": 1
  }
}
```

### 2. Get Item Details
* **Route**: `GET /api/items.php?id={id}`
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Wireless Ergonomic Mouse",
    "sku": "MS-ERG-01",
    "description": "A comfortable vertical wireless mouse...",
    "price": 49.99,
    "quantity": 120,
    "category": "Electronics",
    "created_at": "...",
    "updated_at": "..."
  }
}
```
* **Error Response (404 Not Found)**:
```json
{
  "status": "error",
  "message": "Item not found"
}
```

### 3. Create Product
* **Route**: `POST /api/items.php`
* **Body (JSON)**:
```json
{
  "name": "Noise Cancelling Headphones",
  "sku": "HP-ANC-07",
  "description": "Over-ear active noise cancelling bluetooth headphones",
  "price": 129.99,
  "quantity": 60,
  "category": "Electronics"
}
```
* **Success Response (210 Created)**:
```json
{
  "status": "success",
  "message": "Item created successfully.",
  "data": { ... }
}
```

### 4. Update Product Details
* **Route**: `PUT /api/items.php?id={id}`
* **Body (JSON)** (Partial body updates supported):
```json
{
  "price": 119.99,
  "quantity": 55
}
```
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Item updated successfully.",
  "data": { ... }
}
```

### 5. Delete Product
* **Route**: `DELETE /api/items.php?id={id}`
* **Success Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Item deleted successfully."
}
```
