<?php
/**
 * Authentication Guard Middleware
 */

/**
 * Fetch Bearer Token from HTTP Headers
 */
function getBearerToken() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } else if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Standardize header keys casing
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Require valid authenticated user session. Terminates request on failure.
 */
function requireAuth($db) {
    $token = getBearerToken();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized. Missing authorization bearer token."
        ]);
        exit;
    }
    
    $user = $db->getUserByToken($token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized. Invalid or expired authorization token."
        ]);
        exit;
    }
    
    // Add current active token to user data object for convenience
    $user['active_token'] = $token;
    return $user;
}
