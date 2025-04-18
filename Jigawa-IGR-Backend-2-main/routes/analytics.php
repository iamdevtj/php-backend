<?php
// Include the AuthController
require_once 'controllers/AuthController.php';
require_once 'controllers/AnalyticsController.php';
// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';

// Initialize the AuthController
$authController = new AuthController();
$analyticsController = new AnalyticsController();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/tax-dependencies') {
    // Decode the incoming JSON payload
    $inputData = json_decode(file_get_contents('php://input'), true);

    // Call the function to calculate presumptive tax
    $applicableTaxes->createTaxDependency($inputData);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-taxpayers-total') {
    // Parse query parameters
    $queryParams = $_GET;

    // Call the function
    $analyticsController->getTotalRegisteredTaxpayers();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-taxpayers-segmentation') {
    // Parse query parameters
    $queryParams = $_GET;

    // Call the function
    $analyticsController->getTaxpayerSegmentationByCategory();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-compliance-rate') {
    // Parse query parameters
    $queryParams = $_GET;

    // Call the function
    $analyticsController->getComplianceRate();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-top-defaulters') {
    // Parse query parameters
    $queryParams = $_GET;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    // Call the function
    $analyticsController->getTopDefaulters($limit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-taxpayer-distribution') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTaxpayerDistributionByLocation($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-taxpayer-registration-trends') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTaxpayerRegistrationTrends($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-taxpayer-revenue-breakdown') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getRevenueBreakdownByTaxType($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-rate-utilization-statistics') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getRateUtilizationStatistics($queryParams);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-invoices-generated') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getInvoicesGenerated($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-average-billing') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getAverageBillingByCategory($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-unpaid-invoices') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTotalUnpaidInvoicesByMonth($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-tcc-collection-performance') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTCCCollectionPerformanceByTaxPeriod($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-collection-performance') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getCollectionPerformanceByQuarter($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-payments-by-year-month') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTotalPaymentsByYearMonth($queryParams, $_GET['page'] ?? 1, $_GET['limit'] ?? 10);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-payment-methods-utilized') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getPaymentMethodsUtilized($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-top-payers') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTopPayers($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-average-processing-time') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getAveragePaymentProcessingTime($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-total-issued-by-month') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTotalTCCsIssuedByYearMonth($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-tcc-average-processing-time') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getAverageTCCProcessingTimeByYearMonth($queryParams);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/analytics-tcc-validity-percentage') {
    // Parse query parameters
    $queryParams = $_GET;
    // Call the function
    $analyticsController->getTCCValidityPercentage($queryParams);
    exit;
}





