<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';
require_once 'controllers/PayeeController.php';

// Initialize the AuthController
$authController = new AuthController();
$specialUserController = new SpecialUserController();

// Route: Get all special users with filters, employee count, total monthly tax, annual tax, and total payments
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-special-users') {
    // Capture query parameters and pass them to the controller
    $queryParams = $_GET;

    // Call the method in the controller with the query parameters
    $response = $specialUserController->getAllSpecialUsers($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
} 

// Route: Edit Special User (PUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST'  && $uri == '/edit-special-user') {
    // $decoded_token = authenticate();  // Authenticate the request
    $input = json_decode(file_get_contents('php://input'), true);
    $specialUserController->editSpecialUser($input);
    exit;
}

// Route: Delete Special User (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST'  && $uri == '/delete-special-user') {
    // $decoded_token = authenticate();  // Authenticate the request
    $input = json_decode(file_get_contents('php://input'), true);
    $specialUserController->deleteSpecialUser($input);
    exit;
}



// Route: Get all employees under a specific special user with optional pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-special-user-employees') {
    // Capture query parameters and pass them to the controller
    $queryParams = $_GET;

    // Call the method in the controller with the query parameters
    $response = $specialUserController->getEmployeesBySpecialUser($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Edit Special User Employee (PUT)
if ($request_method == 'POST' && $uri == '/edit-special-user-employee') {
    $input = json_decode(file_get_contents('php://input'), true);
    $specialUserController->editSpecialUserEmployee($input);
    exit;
}

// Route: Delete Special User Employee (DELETE)
if ($request_method == 'POST' && $uri == '/delete-special-user-employee') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: id']);
        http_response_code(400); // Bad request
        exit;
    }
    $specialUserController->deleteSpecialUserEmployee($input['id']);
    exit;
}

// Create a PAYE Invoice Staff Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/paye-invoice-staff') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    $specialUserController->createMultiplePayeInvoiceStaff($inputData);
    exit;
}

// Get PAYE Invoice Staff Records
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/paye-invoice-staff') {
    $filters = $_GET;
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $specialUserController->getPayeInvoiceStaff($filters, $page, $limit);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/paye-monthly-payable') {
    $filters = $_GET;
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    
    $specialUserController->getMonthlyEstimatedPayableByTaxNumber($filters, $page, $limit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/paye-yearly-payable') {
    $filters = $_GET;
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    
    $specialUserController->getYearlyEstimatedPayableByTaxNumber($filters, $page, $limit);
    exit;
}

// Route to get total amount paid for PAYE invoices with filters
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/paye-invoices-paid') {
    $filters = $_GET;
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;

    $specialUserController->getTotalPayeInvoicesPaid($filters, $page, $limit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/payee-staff-remittance') {
    $queryParams = $_GET; // Get query parameters (filters)
    $page = $queryParams['page'] ?? 1;
    $limit = $queryParams['limit'] ?? 10;
    $response = $specialUserController->getPayeeStaffRemittance($queryParams, $page, $limit);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/payee-staff-analytics') {
    $employeeTaxNumber = $_GET['tax_number']; // Get query parameters (filters)
    $response = $specialUserController->getEmployeeAnalytics($employeeTaxNumber);
    header('Content-Type: application/json');
    echo($response);
    exit;
}

if ($request_method == 'POST' && $uri == '/register-multi-employee-with-salary') {
    // $decoded_token = authenticate();  // Authenticate the request

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $specialUserController->registerMultipleEmployeesWithSalaries($input);
    exit;
}


