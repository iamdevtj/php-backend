<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/ApplicableTaxesController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$applicableTaxes = new ApplicableTaxes();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/tax-dependencies') {
    // Decode the incoming JSON payload
    $inputData = json_decode(file_get_contents('php://input'), true);

    // Call the function to calculate presumptive tax
    $applicableTaxes->createTaxDependency($inputData);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-tax-dependencies') {
    // Parse query parameters
    $queryParams = $_GET;

    // Call the function
    $applicableTaxes->getTaxDependencies($queryParams, $queryParams['page'] ?? 1, $queryParams['limit'] ?? 10);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-taxpayer-applicable-taxes') {
    // Parse query parameters
    $queryParams = $_GET;
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
    $taxNumber = isset($queryParams['tax_number']) ? $queryParams['tax_number'] : null;
    

    // Call the function
    $applicableTaxes->getApplicableTaxes($taxNumber, $page, $limit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/delete-tax-dependencies') {
    // Parse query parameters
    $inputData = json_decode(file_get_contents('php://input'), true);


    // Call the function
    $applicableTaxes->deleteTaxDependency($inputData['id']);
    exit;
}





