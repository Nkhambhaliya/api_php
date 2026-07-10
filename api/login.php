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
    $password = $input['password'];

    // Check user exists
    $user = $db->getUserByUsername($username);
    if (!$user) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
        exit;
    }

    // Verify Password Hashing
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
        exit;
    }

    // Generate access token (identified unique hex string)
    $token = bin2hex(random_bytes(32));
    
    // Set expiration to 8 hours from now
    $expiresAt = date('Y-m-d H:i:s', time() + 28800);

    // Save token to database
    $db->updateUserToken($user['id'], $token, $expiresAt);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Logged in successfully.",
        "data" => [
            "username" => $user['username'],
            "access_token" => $token,
            "token_type" => "Bearer",
            "expires_at" => $expiresAt
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
