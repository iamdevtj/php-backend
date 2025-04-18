<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/OtherKindsTaxController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$otherTaxes = new OtherTaxes();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/calculate-wht') {
    // Retrieve the JSON payload from the body of the request
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate if necessary fields are provided
    if (isset($data['transaction_amount'], $data['transaction_type'], $data['recipient_type'])) {
        // Extract values from the request body
        $transactionAmount = $data['transaction_amount'];
        $transactionType = $data['transaction_type'];
        $recipientType = $data['recipient_type'];

        // Call the WHT calculation function
        $response = $otherTaxes->calculateWHT($transactionAmount, $transactionType, $recipientType);

        // Set the response type as JSON
        header('Content-Type: application/json');
        echo $response;  // Return the calculated WHT as a JSON response
        exit;
    } else {
        // Return error response if required data is missing
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: transaction_amount, transaction_type, recipient_type']);
        http_response_code(400);  // Bad Request
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/calculate-paye') {
    // Retrieve the JSON payload from the body of the request
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate if necessary fields are provided
    if (isset($data['annual_gross_income'])) {
        // Extract values from the request body
        $annual_gross_income = $data['annual_gross_income'];

        // Call the WHT calculation function
        $response = $otherTaxes->calculatePAYE($annual_gross_income);

        // Set the response type as JSON
        header('Content-Type: application/json');
        echo $response;  // Return the calculated WHT as a JSON response
        exit;
    } else {
        // Return error response if required data is missing
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: annual_gross_income']);
        http_response_code(400);  // Bad Request
        exit;
    }
}







