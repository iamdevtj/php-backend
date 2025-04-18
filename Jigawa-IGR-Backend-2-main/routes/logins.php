<?php
require_once 'controllers/TaxpayerController.php';
require_once 'controllers/AuthController.php';
$authController = new AuthController();
$taxpayerController = new TaxpayerController();

// Route: Login (POST)
if ($request_method == 'POST' && $uri == '/taxpayer-login') {
    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate if email and password are provided
    if (!isset($input['email']) || !isset($input['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Call the login method in AuthController
    $authController->taxpayerLogin($input['email'], $input['password']);
    exit;
}

if ($request_method == 'POST' && $uri == '/tax-officer-login') {
    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate if email and password are provided
    if (!isset($input['email']) || !isset($input['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Call the login method in AuthController
    $authController->managerLogin($input['email'], $input['password']);
    exit;
}

if ($request_method == 'POST' && $uri == '/admin-login') {
    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate if email and password are provided
    if (!isset($input['email']) || !isset($input['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Call the login method in AuthController
    $authController->adminLogin($input['email'], $input['password']);
    exit;
}

if ($request_method == 'POST' && $uri == '/mda-login') {
    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate if email and password are provided
    if (!isset($input['email']) || !isset($input['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Call the login method in AuthController
    $authController->mdaLogin($input['email'], $input['password']);
    exit;
}

if ($request_method == 'POST' && $uri == '/enumerator-login') {
    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate if email and password are provided
    if (!isset($input['email']) || !isset($input['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Call the login method in AuthController
    $authController->enumeratorLogin($input['email'], $input['password']);
    exit;
}
