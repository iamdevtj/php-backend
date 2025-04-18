<?php
require_once 'controllers/TaxpayerController.php';

$taxpayerController = new TaxpayerController();

// Route: Check taxpayer verification status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/check-taxpayer-verification') {
    $queryParams = $_GET;
    $response = $taxpayerController->checkVerificationStatus($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/verify-taxpayer') {
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $response = $taxpayerController->verifyTaxpayer($input);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Regenerate verification code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/regenerate-verification-code') {
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $response = $taxpayerController->regenerateVerificationCode($input);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get all taxpayers with filters
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-taxpayers') {
    $queryParams = $_GET;
    $response = $taxpayerController->getAllTaxpayers($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-type-breakdown taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/taxpayer-type-breakdown') {
    $queryParams = $_GET;
    $response = $taxpayerController->getTaxTypeBreakdown($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-dashboard taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/taxpayer-tiles-breakdown') {
    $queryParams = $_GET;
    $response = $taxpayerController->getTaxSummary($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-payment-trends taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/taxpayer-payment-trends') {
    $queryParams = $_GET;
    $response = $taxpayerController->getMonthlyPaymentTrends($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-payment-outstanding taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/taxpayer-payment-outstanding') {
    $queryParams = $_GET;
    // Fetch page and limit from query params (optional)
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    $response = $taxpayerController->getPaymentAndOutstandingTaxes($queryParams, $page, $limit);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total registered taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-taxpayer-statistics') {
    $response = $taxpayerController->getTaxpayerStatistics();

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}


// Route: Update TIN status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/update-tin-status') {
    $input = json_decode(file_get_contents('php://input'), true);
    $response = $taxpayerController->updateTinStatus($input);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/taxpayer-forgot-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taxpayerController->forgotPassword($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/taxpayer-reset-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taxpayerController->resetPassword($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/taxpayer-update-profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    $taxpayerController->updateTaxpayerProfile($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/tin-request-stage') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $current_stage = $data['current_stage'] ?? null;
    
    if ($id && $current_stage) {
        $response = $taxpayerController->updateTinRequestStage($id, $current_stage);
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        header('Content-Type: application/json');
        echo json_encode(["message" => "Missing parameters: id and current_stage are required"]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/tin-request-status') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;

    if ($id && $status) {
        $response = $taxpayerController->updateTinRequestStatus($id, $status);
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        header('Content-Type: application/json');
        echo json_encode(["message" => "Missing parameters: id and status are required"]);
    }
    exit;
}


// Assuming you have a simple routing mechanism
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-tin') {
    $queryParams = $_GET;
    $response = $taxpayerController->fetchAllTIN($queryParams);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-upcoming-taxes') {
    $tax_number = $_GET['tax_number'];
    $response = $taxpayerController->getUpcomingTaxes($tax_number);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Route for fetching all TIN requests with optional filters
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-tin-request') {
    // Get the query parameters for filtering
    $queryParams = $_GET;
    
    // Call the controller method to fetch all TIN requests with the provided filters
    $response = $taxpayerController->fetchAllTINRequest($queryParams);
    
    // Set the content type to JSON and return the response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/tin-request-summary') {
    $response = $taxpayerController->getTinRequestSummary();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}







