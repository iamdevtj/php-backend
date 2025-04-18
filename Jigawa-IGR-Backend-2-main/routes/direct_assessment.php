<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/DirectAssessmentController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$directAssessmentController = new DirectAssessmentController();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/register-direct-assessment') {
    // Get the data from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    // Call the function to register the direct assessment
    $response = $directAssessmentController->registerEmployeeDirectAssessment($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-direct-assessments') {
    // Get query parameters from the URL
    $queryParams = $_GET;
    // Call the function to fetch direct assessments with filters
    $response = $directAssessmentController->getAllDirectAssessments($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/create-direct-assessment-invoice') {
    // Get the data from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    // Call the function to create the invoice
    $response = $directAssessmentController->createDirectAssessmentInvoice($data);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-direct-assessment-invoices') {
    // Get query parameters from the URL
    $queryParams = $_GET;
    // Call the function to get the direct assessment invoices with filters
    $response = $directAssessmentController->getAllDirectAssessmentInvoices($queryParams);
    // Set the response content type to JSON
    header('Content-Type: application/json');
    // Output the response
    echo $response;
    exit;
}









