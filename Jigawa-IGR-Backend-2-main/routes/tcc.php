<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/ElectronicTCCController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$tccController = new TccController();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/register-tcc') {
    // Decode the incoming JSON data
    $data = json_decode(file_get_contents("php://input"), true);

    // Validate the data
    if (empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        http_response_code(400); // Bad Request
        exit();
    }

    // Call the registerTCC method
    $tccController->registerTCC($data);
    exit();

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/update-tcc-status') {
    // Decode the incoming JSON data
    $data = json_decode(file_get_contents("php://input"), true);
    // print_r($data);
    // die();
    // Validate the data
    if (empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        http_response_code(400); // Bad Request
        exit();
    }

    // Call the registerTCC method
    $tccController->updateTCCStatus($data);
    exit();

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/update-tcc-stage') {
    // Get the payload from the request body
    $data = json_decode(file_get_contents("php://input"), true);

    // Extract the necessary parameters
    $tcc_id = $data['tcc_id'] ?? null;
    $current_stage = $data['current_stage'] ?? null;
    $next_stage = $data['next_stage'] ?? null;
    $approver_id = $data['approver_id'] ?? null;
    $remarks = $data['remarks'] ?? null;  // Optional remarks

    // Validate the required parameters
    if ($tcc_id && $current_stage && $next_stage && $approver_id) {
        // Call the controller method to update the current stage
        $response = $tccController->updateCurrentStage($tcc_id, $current_stage, $next_stage, $approver_id, $remarks);
        
        // Set the response header and return the response
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // Return an error message if required parameters are missing
        header('Content-Type: application/json');
        echo json_encode(["message" => "Missing required parameters: tcc_id, current_stage, next_stage, and approver_id are required"]);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-tcc') {
    $filters = [
        'tcc_number' => isset($_GET['tcc_number']) ? $_GET['tcc_number'] : null,
        'taxpayer_id' => isset($_GET['taxpayer_id']) ? $_GET['taxpayer_id'] : null,
        'applicant_tin' => isset($_GET['applicant_tin']) ? $_GET['applicant_tin'] : null,
        'status' => isset($_GET['status']) ? $_GET['status'] : null,
        'current_stage' => isset($_GET['current_stage']) ? $_GET['current_stage'] : null,
        'category' => isset($_GET['category']) ? $_GET['category'] : null,
        'first_reviewer_id' => isset($_GET['first_reviewer_id']) ? (int)$_GET['first_reviewer_id'] : null,
        'reviewer_approval_id' => isset($_GET['reviewer_approval_id']) ? (int)$_GET['reviewer_approval_id'] : null,
        'director_approval_id' => isset($_GET['director_approval_id']) ? (int)$_GET['director_approval_id'] : null,
        'issued_date_start' => isset($_GET['issued_date_start']) ? $_GET['issued_date_start'] : null,
        'issued_date_end' => isset($_GET['issued_date_end']) ? $_GET['issued_date_end'] : null,
        'expiry_date_start' => isset($_GET['expiry_date_start']) ? $_GET['expiry_date_start'] : null,
        'expiry_date_end' => isset($_GET['expiry_date_end']) ? $_GET['expiry_date_end'] : null,
    ];

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    // Call the getTCC function in the controller
    $tccController->getTCC(array_filter($filters), $page, $limit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-tcc-status-count') {
    $filters = [
        'category' => $_GET['category'] ?? null,
    ];
    $tccController->getTCCStatusCount(array_filter($filters));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-tcc-status') {
   
    $tccController->getTCCStatus();
    exit;
}



