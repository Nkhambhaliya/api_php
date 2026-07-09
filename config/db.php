<?php
class Database {
    private static $instance = null;
    private $pdo;
    private $driver;

    private function __construct() {
        // Default Database Configuration
        $dbHost = 'localhost';
        $dbName = 'inventory_db';
        $dbUser = 'root';
        $dbPass = '';
        $dbDriver = 'mysql'; // 'mysql' or 'sqlite'

        if ($dbDriver === 'mysql') {
            try {
                $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
                $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $this->driver = 'mysql';
            } catch (PDOException $e) {
                // If MySQL connection fails, auto-fallback to SQLite for easy local execution
                $this->connectSQLite();
            }
        } else {
            $this->connectSQLite();
        }
    }

    private function connectSQLite() {
        try {
            $dbPath = __DIR__ . '/../inventory.db';
            $dsn = "sqlite:$dbPath";
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->driver = 'sqlite';
            $this->initializeSQLiteSchema();
        } catch (PDOException $e) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Database Connection Failed: " . $e->getMessage()
            ]);
            exit;
        }
    }

    private function initializeSQLiteSchema() {
        // Create table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            sku TEXT UNIQUE NOT NULL,
            description TEXT,
            price REAL NOT NULL DEFAULT 0.0,
            quantity INTEGER NOT NULL DEFAULT 0,
            category TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $this->pdo->exec($sql);

        // Seed default items if table is empty
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM items");
        $row = $stmt->fetch();
        if ($row['count'] == 0) {
            $dummyItems = [
                ['Wireless Ergonomic Mouse', 'MS-ERG-01', 'A comfortable vertical wireless mouse designed for long hours of office work.', 49.99, 120, 'Electronics'],
                ['Mechanical Keyboard', 'KB-MECH-02', 'RGB Backlit mechanical keyboard with brown tactile switches.', 89.95, 75, 'Electronics'],
                ['Office Ergonomic Chair', 'CH-ERG-03', 'High-back mesh office chair with lumbar support and adjustable armrests.', 189.00, 30, 'Furniture'],
                ['Standing Desk Converter', 'DK-STND-04', 'Dual monitor height adjustable sit-to-stand converter.', 149.50, 45, 'Furniture'],
                ['Stainless Steel Water Bottle', 'BT-SST-05', 'Double-wall vacuum insulated water bottle (32oz), keeps drinks cold for 24h.', 24.99, 250, 'Kitchenware'],
                ['USB-C Hub Multiport Adapter', 'HB-USBC-06', '7-in-1 USB-C hub with 4K HDMI, 3 USB 3.0 ports, SD/TF card reader, and 100W PD.', 34.99, 150, 'Electronics'],
                ['Noise Cancelling Headphones', 'HP-ANC-07', 'Over-ear active noise cancelling bluetooth headphones with 40h playtime.', 129.99, 60, 'Electronics'],
                ['Leather Portfolio Notebook', 'NB-LTH-08', 'Premium refillable A5 leather journal for writing and drawing.', 19.99, 300, 'Office Supplies'],
                ['Smart LED Desk Lamp', 'LP-LED-09', 'Dimmable eye-caring desk light with USB charging port and 5 color modes.', 39.99, 90, 'Furniture'],
                ['Bluetooth Trackball Mouse', 'MS-TRK-10', 'Ergonomic trackball mouse with adjustable DPI and rechargeable battery.', 59.99, 50, 'Electronics'],
            ];

            $insertSql = "INSERT INTO items (name, sku, description, price, quantity, category) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->pdo->prepare($insertSql);
            foreach ($dummyItems as $item) {
                $insertStmt->execute($item);
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function getDriverName() {
        return $this->driver;
    }
}
