<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/EnumeratorController.php';



// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$enumeratorController = new EnumeratorController();

// Route: Get enumerator admins with optional filters, pagination, and tax payer count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-enumerator-admins') {
    $queryParams = $_GET; // Capture query parameters from the URL

    // Fetch enumerator admins with optional filters and pagination
    $response = $enumeratorController->getEnumeratorAdmins($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get all tax payers under a specific enumerator admin with optional filters and pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-enumerator-tax-payers') {
    $queryParams = $_GET; // Capture query parameters from the URL

    // Fetch tax payers under the specified enumerator admin
    $response = $enumeratorController->getTaxPayersByEnumerator($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}


// Route: Get total registered Enum taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-enumtaxpayer-statistics') {
    // Validate and get enumerator_id
    if (!isset($_GET['enumerator_id'])) {
        header('Content-Type: application/json');
        echo json_encode([
            "status" => "error",
            "message" => "Enumerator ID is required"
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $enumerator_id = intval($_GET['enumerator_id']);
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;

    // Fetch statistics
    $response = $enumeratorController->getEnumTaxpayerStatistics($enumerator_id, $month, $year);

    // Set response header and output JSON
    header('Content-Type: application/json');
    echo $response;
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/enum-taxpayer-update-profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    $enumeratorController->updateEnumeratorTaxpayerProfile($data);
    exit;
}





