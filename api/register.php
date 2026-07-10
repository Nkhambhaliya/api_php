<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username and Password are required."]);
        exit;
    }

    $username = trim($input['username']);
    $password = $input['password']; // Do not trim password to preserve custom whitespaces

    // Validate username format (simple alphanumeric check)
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Username must be alphanumeric (plus underscores) and between 3 and 30 characters."
        ]);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
        exit;
    }

    // Check if user already exists
    $existing = $db->getUserByUsername($username);
    if ($existing) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Username '$username' is already taken."]);
        exit;
    }

    // Hashed Password (encrypted form)
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Save
    $db->createUser($username, $hashedPassword);

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "User registered successfully."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
