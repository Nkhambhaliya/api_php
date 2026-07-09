<?php
// CORS & JSON Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$database = Database::getInstance();
$db = $database->getConnection();
$driverName = $database->getDriverName();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getItemDetails($db, (int)$_GET['id']);
        } else {
            getItemsList($db);
        }
        break;

    case 'POST':
        createItem($db, $driverName);
        break;

    case 'PUT':
        updateItem($db, $driverName);
        break;

    case 'DELETE':
        deleteItem($db);
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
        break;
}

/**
 * GET Single Item details
 */
function getItemDetails($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => $item
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Item not found"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * GET Paginated and Filtered List of Items
 */
function getItemsList($db) {
    try {
        // Pagination params
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

        // Filtering conditions
        $conditions = [];
        $params = [];

        // Search in Name, SKU, or Description
        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $conditions[] = "(name LIKE :search OR sku LIKE :search2 OR description LIKE :search3)";
            $params[':search'] = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
        }

        // Category filter
        if (!empty($_GET['category'])) {
            $conditions[] = "category = :category";
            $params[':category'] = $_GET['category'];
        }

        // Price range filters
        if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
            $conditions[] = "price >= :min_price";
            $params[':min_price'] = (float)$_GET['min_price'];
        }
        if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
            $conditions[] = "price <= :max_price";
            $params[':max_price'] = (float)$_GET['max_price'];
        }

        // Build WHERE clause
        $whereClause = "";
        if (count($conditions) > 0) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }

        // 1. Get total matching count
        $countSql = "SELECT COUNT(*) as total FROM items $whereClause";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch()['total'];

        // 2. Get items with pagination
        // Using explicit binding for LIMIT and OFFSET to ensure SQLite and MySQL compatibility
        $sql = "SELECT * FROM items $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        // 3. Extract unique categories for filter options
        $catStmt = $db->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $items,
            "categories" => $categories,
            "pagination" => [
                "total" => $total,
                "limit" => $limit,
                "offset" => $offset,
                "count" => count($items)
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * POST Create New Item
 */
function createItem($db, $driverName) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        if (empty($input['name']) || empty($input['sku']) || !isset($input['price']) || !isset($input['quantity'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name, SKU, Price, and Quantity are required."]);
            return;
        }

        $name = trim($input['name']);
        $sku = strtoupper(trim($input['sku']));
        $price = (float)$input['price'];
        $quantity = (int)$input['quantity'];
        $category = isset($input['category']) ? trim($input['category']) : null;
        $description = isset($input['description']) ? trim($input['description']) : null;

        if ($price < 0 || $quantity < 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Price and Quantity must be positive values."]);
            return;
        }

        // Check if SKU already exists
        $checkStmt = $db->prepare("SELECT id FROM items WHERE sku = ?");
        $checkStmt->execute([$sku]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "SKU '$sku' already exists. SKU must be unique."]);
            return;
        }

        // Insert
        $sql = "INSERT INTO items (name, sku, description, price, quantity, category, created_at, updated_at) 
                VALUES (:name, :sku, :description, :price, :quantity, :category, :created_at, :updated_at)";
        
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':sku' => $sku,
            ':description' => $description,
            ':price' => $price,
            ':quantity' => $quantity,
            ':category' => $category,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $newId = $db->lastInsertId();

        // Retrieve created item
        $getStmt = $db->prepare("SELECT * FROM items WHERE id = ?");
        $getStmt->execute([$newId]);
        $newItem = $getStmt->fetch();

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Item created successfully.",
            "data" => $newItem
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * PUT Update Existing Item
 */
function updateItem($db, $driverName) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing Item ID."]);
            return;
        }

        // Verify item exists
        $checkStmt = $db->prepare("SELECT * FROM items WHERE id = ?");
        $checkStmt->execute([$id]);
        $existingItem = $checkStmt->fetch();

        if (!$existingItem) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Item not found."]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Extract fields, falling back to existing data if not provided
        $name = isset($input['name']) ? trim($input['name']) : $existingItem['name'];
        $sku = isset($input['sku']) ? strtoupper(trim($input['sku'])) : $existingItem['sku'];
        $description = isset($input['description']) ? trim($input['description']) : $existingItem['description'];
        $price = isset($input['price']) ? (float)$input['price'] : (float)$existingItem['price'];
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : (int)$existingItem['quantity'];
        $category = isset($input['category']) ? trim($input['category']) : $existingItem['category'];

        // Basic checks
        if (empty($name) || empty($sku)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name and SKU cannot be empty."]);
            return;
        }

        if ($price < 0 || $quantity < 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Price and Quantity must be positive values."]);
            return;
        }

        // Check if SKU is changed and already exists on another item
        if ($sku !== $existingItem['sku']) {
            $skuCheck = $db->prepare("SELECT id FROM items WHERE sku = ? AND id != ?");
            $skuCheck->execute([$sku, $id]);
            if ($skuCheck->fetch()) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "SKU '$sku' is already taken by another item."]);
                return;
            }
        }

        // Update statement
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE items SET 
                name = :name, 
                sku = :sku, 
                description = :description, 
                price = :price, 
                quantity = :quantity, 
                category = :category, 
                updated_at = :updated_at 
                WHERE id = :id";
        
        $stmt = $db->prepare($sql);
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

        // Fetch updated item
        $getStmt = $db->prepare("SELECT * FROM items WHERE id = ?");
        $getStmt->execute([$id]);
        $updatedItem = $getStmt->fetch();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Item updated successfully.",
            "data" => $updatedItem
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * DELETE Item
 */
function deleteItem($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing Item ID."]);
            return;
        }

        // Verify item exists
        $checkStmt = $db->prepare("SELECT id FROM items WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Item not found."]);
            return;
        }

        // Delete
        $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Item deleted successfully."
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
