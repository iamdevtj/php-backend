<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';
require_once 'controllers/AdminController.php';

// Initialize the AuthController
$authController = new AuthController();
$adminController = new AdminController();

if ($request_method == 'GET' && $uri == '/get-total-amount-paid') {
    $filters = [
        'month' => isset($_GET['month']) ? (int)$_GET['month'] : null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getTotalAmountPaid(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-total-monthly-invoice') {
    // Get query parameters (year and month)
    $queryParams = $_GET;
    $year = $queryParams['year'] ?? null;
    $month = $queryParams['month'] ?? null;

    // Get the monthly total from the controller with optional filters
    $response = $adminController->getTotalMonthlyInvoices($year, $month);

    // Set header and return response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($request_method == 'GET' && $uri == '/get-average-daily-revenue') {
   // Get query parameters (start_date and end_date)
   $queryParams = $_GET;
   $start_date = $queryParams['start_date'] ?? null;
   $end_date = $queryParams['end_date'] ?? null;

   // Get the average daily revenue from the controller with optional date filters
   $response = $adminController->getAverageDailyRevenue($start_date, $end_date);

   // Set header and return response as JSON
   header('Content-Type: application/json');
   echo json_encode($response);
   exit;
}

if ($request_method == 'GET' && $uri == '/get-total-amount-paid-yearly') {
    $filters = [
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getTotalAmountPaidYearly(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-expected-monthly-revenue') {
    $filters = [
        'month' => isset($_GET['month']) ? (int)$_GET['month'] : null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getExpectedMonthlyRevenue(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-accrued-monthly-revenue') {
    $filters = [
        'month' => isset($_GET['month']) ? (int)$_GET['month'] : null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getAccruedMonthlyRevenue(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-total-special-users') {
    $adminController->getTotalSpecialUsers();
    exit;
}

if ($request_method == 'GET' && $uri == '/get-total-employees') {
    $adminController->getTotalEmployees();
    exit;
}

if ($request_method == 'GET' && $uri == '/get-total-annual-estimate') {
    $filters = [
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getTotalAnnualEstimate(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-total-annual-remittance') {
    $filters = [
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getTotalAnnualRemittance(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/get-monthly-estimate') {
    $filters = [
        'month' => isset($_GET['month']) ? (int)$_GET['month'] : null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
    ];

    $adminController->getMonthlyEstimate(array_filter($filters)); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/admin-tax-summary') {
    $filters = $_GET;
    $adminController->getTaxSummary($filters); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/admin-revenue-growth') {
    $filters = $_GET;
    $adminController->getRevenueGrowth($filters); // Filter out null values
    exit;
}

if ($request_method == 'GET' && $uri == '/admin-revenue-growth-2') {
    $filters = $_GET;
    $adminController->getRevenueGrowth2($filters); // Filter out null values
    exit;
}

if ($request_method === 'POST' && $uri === '/admin-update-profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    $adminController->updateAdminProfile($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/admin-forgot-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $adminController->adminForgotPassword($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/admin-reset-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $adminController->resetAdminPassword($data);
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/check-admin-verification') {
//     $queryParams = $_GET;
//     $response = $adminController->checkVerificationStatus($queryParams);

//     // Set response header and output the response in JSON format
//     header('Content-Type: application/json');
//     echo $response;
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/regenerate-verification-admin') {
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $response = $adminController->regenerateAdminVerificationCode($input);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}










