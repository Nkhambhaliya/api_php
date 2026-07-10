<?php
class Database {
    private static $instance = null;
    private $driver;
    private $connection; // PDO or MongoDB Driver Manager

    private function __construct() {
        $this->loadEnv();
        $this->driver = getenv('DB_DRIVER') ?: 'sqlite';
        $this->connect();
    }

    private function loadEnv() {
        $path = __DIR__ . '/../.env';
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Ignore comments
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Strip wrapping quotes
                if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match('/^\'(.*)\'$/', $value, $matches)) {
                    $value = $matches[1];
                }
                
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    private function connect() {
        switch ($this->driver) {
            case 'mysql':
                $host = getenv('DB_HOST') ?: 'localhost';
                $port = getenv('DB_PORT') ?: '3306';
                $name = getenv('DB_NAME') ?: 'inventory_db';
                $user = getenv('DB_USER') ?: 'root';
                $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
                try {
                    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                    $this->connection = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } catch (PDOException $e) {
                    header('Content-Type: application/json; charset=UTF-8');
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "MySQL Connection Failed: " . $e->getMessage()]);
                    exit;
                }
                break;

            case 'sqlite':
                $dbFile = getenv('DB_FILE') ?: 'inventory.db';
                $dbPath = __DIR__ . '/../' . $dbFile;
                // Auto create parent directory if needed
                $dir = dirname($dbPath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                try {
                    $dsn = "sqlite:$dbPath";
                    $this->connection = new PDO($dsn, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    $this->initializeSQLiteSchema();
                } catch (PDOException $e) {
                    header('Content-Type: application/json; charset=UTF-8');
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "SQLite Connection Failed: " . $e->getMessage()]);
                    exit;
                }
                break;

            case 'mongodb':
                $uri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
                try {
                    if (class_exists('MongoDB\Driver\Manager')) {
                        $this->connection = new MongoDB\Driver\Manager($uri);
                    } else {
                        throw new Exception("MongoDB PECL driver extension is not installed in this PHP runtime.");
                    }
                } catch (Exception $e) {
                    header('Content-Type: application/json; charset=UTF-8');
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "MongoDB Connection Failed: " . $e->getMessage()]);
                    exit;
                }
                break;

            default:
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Unsupported DB_DRIVER: " . $this->driver]);
                exit;
        }
    }

    private function initializeSQLiteSchema() {
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
        $this->connection->exec($sql);

        $userSql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            token TEXT,
            token_expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $this->connection->exec($userSql);


        // Auto-seed table if SQLite DB is completely fresh
        $stmt = $this->connection->query("SELECT COUNT(*) as count FROM items");
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
            $insertStmt = $this->connection->prepare($insertSql);
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
        return $this->connection;
    }

    public function getDriver() {
        return $this->driver;
    }

    /**
     * Fetch multiple items with pagination and query filtering
     */
    public function queryItems($filters, $limit, $offset) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $conditions = [];
            $params = [];

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $conditions[] = "(name LIKE :search OR sku LIKE :search2 OR description LIKE :search3)";
                $params[':search'] = $search;
                $params[':search2'] = $search;
                $params[':search3'] = $search;
            }

            if (!empty($filters['category'])) {
                $conditions[] = "category = :category";
                $params[':category'] = $filters['category'];
            }

            if (isset($filters['min_price']) && $filters['min_price'] !== '') {
                $conditions[] = "price >= :min_price";
                $params[':min_price'] = (float)$filters['min_price'];
            }

            if (isset($filters['max_price']) && $filters['max_price'] !== '') {
                $conditions[] = "price <= :max_price";
                $params[':max_price'] = (float)$filters['max_price'];
            }

            $whereClause = "";
            if (count($conditions) > 0) {
                $whereClause = "WHERE " . implode(" AND ", $conditions);
            }

            // 1. Get total count
            $countSql = "SELECT COUNT(*) as total FROM items $whereClause";
            $countStmt = $this->connection->prepare($countSql);
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetch()['total'];

            // 2. Fetch records
            $sql = "SELECT * FROM items $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();

            // 3. Unique categories list
            $catStmt = $this->connection->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
            $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

            return [
                'items' => $items,
                'total' => $total,
                'categories' => $categories
            ];
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";

            $mongoFilter = [];
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $mongoFilter['$or'] = [
                    ['name' => ['$regex' => $search, '$options' => 'i']],
                    ['sku' => ['$regex' => $search, '$options' => 'i']],
                    ['description' => ['$regex' => $search, '$options' => 'i']]
                ];
            }

            if (!empty($filters['category'])) {
                $mongoFilter['category'] = $filters['category'];
            }

            if ((isset($filters['min_price']) && $filters['min_price'] !== '') || (isset($filters['max_price']) && $filters['max_price'] !== '')) {
                $priceCond = [];
                if (isset($filters['min_price']) && $filters['min_price'] !== '') {
                    $priceCond['$gte'] = (float)$filters['min_price'];
                }
                if (isset($filters['max_price']) && $filters['max_price'] !== '') {
                    $priceCond['$lte'] = (float)$filters['max_price'];
                }
                $mongoFilter['price'] = $priceCond;
            }

            // Query DB
            $options = [
                'limit' => $limit,
                'skip' => $offset,
                'sort' => ['_id' => -1]
            ];
            $query = new MongoDB\Driver\Query($mongoFilter, $options);
            $cursor = $this->connection->executeQuery($namespace, $query);
            $items = [];
            foreach ($cursor as $doc) {
                $item = (array)$doc;
                if (isset($item['_id']) && is_object($item['_id'])) {
                    $item['id'] = (string)$item['_id'];
                    unset($item['_id']);
                }
                $items[] = $item;
            }

            // Total Count command
            $countCmd = new MongoDB\Driver\Command([
                'count' => 'items',
                'query' => $mongoFilter
            ]);
            $countCursor = $this->connection->executeCommand($dbName, $countCmd);
            $countArr = $countCursor->toArray();
            $total = isset($countArr[0]) ? (int)$countArr[0]->n : 0;

            // Categories distinct command
            $distinctCmd = new MongoDB\Driver\Command([
                'distinct' => 'items',
                'key' => 'category',
                'query' => ['category' => ['$ne' => null]]
            ]);
            $distCursor = $this->connection->executeCommand($dbName, $distinctCmd);
            $distArr = $distCursor->toArray();
            $categories = [];
            if (isset($distArr[0]) && isset($distArr[0]->values)) {
                $categories = (array)$distArr[0]->values;
                sort($categories);
            }

            return [
                'items' => $items,
                'total' => $total,
                'categories' => $categories
            ];
        }
    }

    /**
     * Retrieve single record by ID (Integer ID for SQL, hex ObjectId/String for MongoDB)
     */
    public function getItemById($id) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";
            try {
                $mongoFilter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            } catch (Exception $e) {
                $mongoFilter = ['_id' => $id];
            }
            $query = new MongoDB\Driver\Query($mongoFilter, ['limit' => 1]);
            $cursor = $this->connection->executeQuery($namespace, $query);
            $rows = $cursor->toArray();
            if (count($rows) > 0) {
                $item = (array)$rows[0];
                if (isset($item['_id'])) {
                    $item['id'] = (string)$item['_id'];
                    unset($item['_id']);
                }
                return $item;
            }
            return null;
        }
    }

    /**
     * Verify SKU uniqueness
     */
    public function checkSkuExists($sku, $excludeId = null) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            if ($excludeId !== null) {
                $stmt = $this->connection->prepare("SELECT id FROM items WHERE sku = ? AND id != ?");
                $stmt->execute([$sku, $excludeId]);
            } else {
                $stmt = $this->connection->prepare("SELECT id FROM items WHERE sku = ?");
                $stmt->execute([$sku]);
            }
            return (bool)$stmt->fetch();
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";
            
            $mongoFilter = ['sku' => $sku];
            if ($excludeId !== null) {
                try {
                    $mongoFilter['_id'] = ['$ne' => new MongoDB\BSON\ObjectId($excludeId)];
                } catch (Exception $e) {
                    $mongoFilter['_id'] = ['$ne' => $excludeId];
                }
            }
            
            $query = new MongoDB\Driver\Query($mongoFilter, ['limit' => 1]);
            $cursor = $this->connection->executeQuery($namespace, $query);
            return count($cursor->toArray()) > 0;
        }
    }

    /**
     * Insert new record
     */
    public function createItem($data) {
        $now = date('Y-m-d H:i:s');
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $sql = "INSERT INTO items (name, sku, description, price, quantity, category, created_at, updated_at) 
                    VALUES (:name, :sku, :description, :price, :quantity, :category, :created_at, :updated_at)";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                ':name' => $data['name'],
                ':sku' => $data['sku'],
                ':description' => $data['description'],
                ':price' => $data['price'],
                ':quantity' => $data['quantity'],
                ':category' => $data['category'],
                ':created_at' => $now,
                ':updated_at' => $now
            ]);
            $newId = $this->connection->lastInsertId();
            return $this->getItemById($newId);
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";
            
            $bulk = new MongoDB\Driver\BulkWrite;
            $document = [
                'name' => $data['name'],
                'sku' => $data['sku'],
                'description' => $data['description'],
                'price' => (float)$data['price'],
                'quantity' => (int)$data['quantity'],
                'category' => $data['category'],
                'created_at' => $now,
                'updated_at' => $now
            ];
            $_id = $bulk->insert($document);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return $this->getItemById((string)$_id);
        }
    }

    /**
     * Update existing record
     */
    public function updateItem($id, $data) {
        $now = date('Y-m-d H:i:s');
        $existing = $this->getItemById($id);
        if (!$existing) return null;

        $name = isset($data['name']) ? $data['name'] : $existing['name'];
        $sku = isset($data['sku']) ? $data['sku'] : $existing['sku'];
        $description = isset($data['description']) ? $data['description'] : $existing['description'];
        $price = isset($data['price']) ? $data['price'] : $existing['price'];
        $quantity = isset($data['quantity']) ? $data['quantity'] : $existing['quantity'];
        $category = isset($data['category']) ? $data['category'] : $existing['category'];

        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $sql = "UPDATE items SET 
                    name = :name, sku = :sku, description = :description, 
                    price = :price, quantity = :quantity, category = :category, updated_at = :updated_at 
                    WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':sku' => $sku,
                ':description' => $description,
                ':price' => $price,
                ':quantity' => $quantity,
                ':category' => $category,
                ':updated_at' => $now,
                ':id' => $id
            ]);
            return $this->getItemById($id);
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";
            
            try {
                $mongoFilter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            } catch (Exception $e) {
                $mongoFilter = ['_id' => $id];
            }

            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update($mongoFilter, ['$set' => [
                'name' => $name,
                'sku' => $sku,
                'description' => $description,
                'price' => (float)$price,
                'quantity' => (int)$quantity,
                'category' => $category,
                'updated_at' => $now
            ]]);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return $this->getItemById($id);
        }
    }

    /**
     * Delete record
     */
    public function deleteItem($id) {
        $existing = $this->getItemById($id);
        if (!$existing) return false;

        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.items";
            
            try {
                $mongoFilter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            } catch (Exception $e) {
                $mongoFilter = ['_id' => $id];
            }

            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->delete($mongoFilter);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return true;
        }
    }

    /**
     * Create user (hashing is already handled in register.php)
     */
    public function createUser($username, $hashedPassword) {
        $now = date('Y-m-d H:i:s');
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $sql = "INSERT INTO users (username, password, created_at) VALUES (:username, :password, :created_at)";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':created_at' => $now
            ]);
            return true;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.users";
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert([
                'username' => $username,
                'password' => $hashedPassword,
                'token' => null,
                'token_expires_at' => null,
                'created_at' => $now
            ]);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return true;
        }
    }

    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch() ?: null;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.users";
            $query = new MongoDB\Driver\Query(['username' => $username], ['limit' => 1]);
            $cursor = $this->connection->executeQuery($namespace, $query);
            $rows = $cursor->toArray();
            if (count($rows) > 0) {
                $user = (array)$rows[0];
                if (isset($user['_id'])) {
                    $user['id'] = (string)$user['_id'];
                    unset($user['_id']);
                }
                return $user;
            }
            return null;
        }
    }

    /**
     * Update active login token for a user
     */
    public function updateUserToken($userId, $token, $expiresAt) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("UPDATE users SET token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expiresAt, $userId]);
            return true;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.users";
            try {
                $mongoFilter = ['_id' => new MongoDB\BSON\ObjectId($userId)];
            } catch (Exception $e) {
                $mongoFilter = ['_id' => $userId];
            }
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update($mongoFilter, ['$set' => ['token' => $token, 'token_expires_at' => $expiresAt]]);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return true;
        }
    }

    /**
     * Retrieve user details based on active token and verify expiration
     */
    public function getUserByToken($token) {
        $now = date('Y-m-d H:i:s');
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("SELECT * FROM users WHERE token = ? AND token_expires_at > ?");
            $stmt->execute([$token, $now]);
            return $stmt->fetch() ?: null;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.users";
            $query = new MongoDB\Driver\Query([
                'token' => $token,
                'token_expires_at' => ['$gt' => $now]
            ], ['limit' => 1]);
            $cursor = $this->connection->executeQuery($namespace, $query);
            $rows = $cursor->toArray();
            if (count($rows) > 0) {
                $user = (array)$rows[0];
                if (isset($user['_id'])) {
                    $user['id'] = (string)$user['_id'];
                    unset($user['_id']);
                }
                return $user;
            }
            return null;
        }
    }

    /**
     * Invalidate user session token on logout
     */
    public function clearUserToken($token) {
        if ($this->driver === 'mysql' || $this->driver === 'sqlite') {
            $stmt = $this->connection->prepare("UPDATE users SET token = NULL, token_expires_at = NULL WHERE token = ?");
            $stmt->execute([$token]);
            return true;
        }

        if ($this->driver === 'mongodb') {
            $dbName = getenv('MONGODB_DB') ?: 'inventory_db';
            $namespace = "$dbName.users";
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(['token' => $token], ['$set' => ['token' => null, 'token_expires_at' => null]]);
            $this->connection->executeBulkWrite($namespace, $bulk);
            return true;
        }
    }
}

