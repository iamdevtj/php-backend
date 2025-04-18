<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/TaxFilingController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$taxFilingController = new TaxFilingController();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/create-tax-filing') {
    // Retrieve the JSON payload sent in the POST request
    $inputData = json_decode(file_get_contents('php://input'), true);
    // Call the createTaxFiling function with the request payload
    $response = $taxFilingController->createTaxFiling($inputData);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-all-tax-filings') {
    // Retrieve query parameters from the GET request
    $queryParams = $_GET;
    // Call the getAllTaxFilings method with the filters passed in the query parameters
    $response = $taxFilingController->getAllTaxFilings($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Assuming you're using a basic routing setup where $uri contains the requested URL path and $_SERVER['REQUEST_METHOD'] contains the request type

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/update-tax-filing') {
    // Retrieve the request payload (the data sent by the admin)
    $inputData = json_decode(file_get_contents('php://input'), true);
    // Check if filing_id is provided in the request body
    if (!isset($inputData['filing_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: filing_id']);
        exit;
    }
    // Get the filing ID from the request payload
    $filingId = $inputData['filing_id'];
    // Call the updateTaxFilingStatus function to update the tax filing
    $response = $taxFilingController->updateTaxFilingStatus($filingId, $inputData);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Assuming you're using a basic routing setup where $uri contains the requested URL path and $_SERVER['REQUEST_METHOD'] contains the request type

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/approve-tax-type') {
    // Retrieve the request payload
    $inputData = json_decode(file_get_contents('php://input'), true);
    // Check if necessary data is provided (filing_id and tax_type_id)
    if (!isset($inputData['filing_id']) || !isset($inputData['tax_type_id']) || !isset($inputData['status'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: filing_id or tax_type_id or status']);
        exit;
    }
    // Get the filing ID and tax type ID from the request payload
    $filingId = $inputData['filing_id'];
    $taxTypeId = $inputData['tax_type_id'];
    $status = $inputData['status'];
    $remarks = isset($inputData['remarks']) ? $inputData['remarks'] : '';
    // Call the approveTaxType function to approve the tax type under the filing
    $response = $taxFilingController->approveTaxType($filingId, $taxTypeId, $status, $remarks);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-tax-filing-statistics') {
    // Retrieve the query parameters
    $queryParams = $_GET;
    // Get statistics based on the filters
    $response = $taxFilingController->getTaxFilingStatistics($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}











