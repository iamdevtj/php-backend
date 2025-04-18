<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/TaxOfficesController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$taxOfficeController = new TaxOfficesController();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/tax-offices') {
    $data = json_decode(file_get_contents('php://input'), true); // Get the data from the request
    $response = $taxOfficeController->createTaxOffice($data);
    header('Content-Type: application/json');
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/tax-offices') {
    // Get the filters from the request (e.g., ?office_name=Lagos&status=active)
    $queryParams = $_GET;
    $response = $taxOfficeController->getAllTaxOffices($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/update-tax-office') {
    // Get the data from the POST request body
    $data = json_decode(file_get_contents('php://input'), true);
    $response = $taxOfficeController->updateTaxOffice($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/toggle-tax-office-status') {
    // Get the data from the POST request body
    $data = json_decode(file_get_contents('php://input'), true);
    $response = $taxOfficeController->toggleTaxOfficeStatus($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Route for creating manager office
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/create-manager-office') {
    // Get the data from the POST request body
    $data = json_decode(file_get_contents('php://input'), true);
    $response = $taxOfficeController->createManagerOffice($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Route for fetching manager offices with filters
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/manager-offices') {
    // Get query parameters
    $queryParams = $_GET;
    $response = $taxOfficeController->getManagerOfficeDetails($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Route for editing manager office details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/edit-manager-office') {
    // Get the data from the POST request body
    $data = json_decode(file_get_contents('php://input'), true);
    $response = $taxOfficeController->editManagerOffice($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

// Route for toggling manager status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/toggle-manager-status') {
    // Get the data from the POST request body
    $data = json_decode(file_get_contents('php://input'), true);
    $response = $taxOfficeController->toggleManagerStatus($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}















