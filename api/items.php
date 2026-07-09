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

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id']) && $_GET['id'] !== '') {
            getItemDetails($db, $_GET['id']);
        } else {
            getItemsList($db);
        }
        break;

    case 'POST':
        createItem($db);
        break;

    case 'PUT':
        updateItem($db);
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
        $item = $db->getItemById($id);

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
    } catch (Exception $e) {
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

        // Build filters array
        $filters = [
            'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
            'category' => isset($_GET['category']) ? trim($_GET['category']) : '',
            'min_price' => isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : '',
            'max_price' => isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : ''
        ];

        // Fetch query details
        $result = $db->queryItems($filters, $limit, $offset);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $result['items'],
            "categories" => $result['categories'],
            "pagination" => [
                "total" => $result['total'],
                "limit" => $limit,
                "offset" => $offset,
                "count" => count($result['items'])
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * POST Create New Item
 */
function createItem($db) {
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
        if ($db->checkSkuExists($sku)) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "SKU '$sku' already exists. SKU must be unique."]);
            return;
        }

        // Save
        $data = [
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
            'price' => $price,
            'quantity' => $quantity,
            'category' => $category
        ];
        $newItem = $db->createItem($data);

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Item created successfully.",
            "data" => $newItem
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * PUT Update Existing Item
 */
function updateItem($db) {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : '';

        if ($id === '') {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing Item ID."]);
            return;
        }

        // Verify item exists
        $existingItem = $db->getItemById($id);
        if (!$existingItem) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Item not found."]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Extract fields
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

        // Check SKU unique if modified
        if ($sku !== $existingItem['sku']) {
            if ($db->checkSkuExists($sku, $id)) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "SKU '$sku' is already taken by another item."]);
                return;
            }
        }

        // Update
        $data = [
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
            'price' => $price,
            'quantity' => $quantity,
            'category' => $category
        ];
        $updatedItem = $db->updateItem($id, $data);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Item updated successfully.",
            "data" => $updatedItem
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

/**
 * DELETE Item
 */
function deleteItem($db) {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : '';

        if ($id === '') {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing Item ID."]);
            return;
        }

        // Verify item exists
        $existingItem = $db->getItemById($id);
        if (!$existingItem) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Item not found."]);
            return;
        }

        // Delete
        $db->deleteItem($id);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Item deleted successfully."
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
