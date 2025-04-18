<?php
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key; // For decoding the JWT with the key
use \Firebase\JWT\ExpiredException; // To handle expired token errors

$secret_key = 'your_secret_key_plateau_35c731567705ad451533eb8516558ca0dad1e3e56d095c50289aabbff516a7f7_your_secret_key_plateau';  // Use the same secret key used to sign the JWT

function authenticate() {
    global $secret_key;

    try {
        // Retrieve the Authorization header
        $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

        if (!$authorizationHeader) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Token not provided']);
            exit;
        }

        // Remove "Bearer " prefix and extract the token
        $token = str_replace('Bearer ', '', $authorizationHeader);

        // Decode and verify the token
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));

        // Return decoded token if it's valid
        return (array) $decoded;

    } catch (ExpiredException $e) {
        // Handle token expiration
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Token has expired']);
        exit;

    } catch (Exception $e) {
        // Handle other exceptions, such as invalid tokens
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid token']);
        exit;
    }
}
