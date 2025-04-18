<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/MdaController.php';
require_once 'controllers/RevenueHeadController.php';

// Include the auth_helper where authenticate() is defined
require_once 'helpers/auth_helper.php';



$mdaController = new MdaController();
$revenueHeadController = new RevenueHeadController();

// Route: Create MDA (POST)
if ($request_method == 'POST' && $uri == '/create-mda') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $mdaController->createMda($input);
    exit;
}

if ($request_method == 'POST' && $uri == '/create-multiple-mda') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $mdaController->createMultipleMda($input);
    exit;
}


// Route: Create Revenue Head for a specific MDA (POST)
if ($request_method == 'POST' && $uri == '/create-revenue-head') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $revenueHeadController->createRevenueHead($input);
    exit;
}

// Route: Create Multiple Revenue Heads (POST)
if ($request_method == 'POST' && $uri == '/create-multiple-revenue-heads') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $revenueHeadController->createMultipleRevenueHeads($input);
    exit;
}


// Route: Update MDA information (POST)
if ($request_method == 'POST' && $uri == '/update-mda') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $mdaController->updateMda($input);
    exit;
}
// You can add more MDA-related routes here (e.g., update MDA, delete MDA)

// Route: Update Revenue Head information (POST)
if ($request_method == 'POST' && $uri == '/update-revenue-head') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['revenue_head_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: revenue_head_id']);
        http_response_code(400); // Bad Request
        exit;
    }

    $revenueHeadController->updateRevenueHead($input);
    exit;
}

// Route: Fetch All MDAs with pagination (GET)
if ($request_method == 'GET' && $uri == '/get-mdas') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $mdaController->getAllMdas($page, $limit);
    exit;
}

// Route: Fetch MDA by filters (GET)
if ($request_method == 'GET' && $uri == '/get-mda') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $filters = [
        'id' => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'fullname' => isset($_GET['fullname']) ? $_GET['fullname'] : null,
        'mda_code' => isset($_GET['mda_code']) ? $_GET['mda_code'] : null,
        'email' => isset($_GET['email']) ? $_GET['email'] : null,
        'allow_payment' => isset($_GET['allow_payment']) ? (int)$_GET['allow_payment'] : null,
        'status' => isset($_GET['status']) ? (int)$_GET['status'] : null,
    ];

    $mdaController->getMdaByFilters(array_filter($filters)); // Filter out null values
    exit;
}

// Route: Fetch Revenue Head by filters (GET)
if ($request_method == 'GET' && $uri == '/get-revenue-head') {
    // $decoded_token = authenticate();  // Authenticate the request
    // Call the register method in RegistrationController

    // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $filters = [
        'id' => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'item_code' => isset($_GET['item_code']) ? $_GET['item_code'] : null,
        'item_name' => isset($_GET['item_name']) ? $_GET['item_name'] : null,
        'category' => isset($_GET['category']) ? $_GET['category'] : null,
        'status' => isset($_GET['status']) ? (int)$_GET['status'] : null,
        'mda_id' => isset($_GET['mda_id']) ? (int)$_GET['mda_id'] : null,
    ];

    $revenueHeadController->getRevenueHeadByFilters(array_filter($filters)); // Filter out null values
    exit;
}


// Route: Approve Revenue Head Status  (POST)
if ($request_method == 'PUT' && $uri == '/approve-revenue-head') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);
    $revenueHeadController->approveRevenueHead($input);
    exit;
}

// Route: Get total tax-payment-outstanding taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/mda-payment-outstanding') {
    $queryParams = $_GET;
    // Fetch page and limit from query params (optional)
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    $response = $mdaController->getPaymentAndOutstandingTaxes($queryParams, $page, $limit);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}
// Route: Delete Revenue Head (PUT)
if ($request_method == 'PUT' && $uri == '/delete-revenue-head') {
    // $decoded_token = authenticate();  // Authenticate the request
    // // Call the register method in RegistrationController

    // // Optionally check if the authenticated user has the role to create an admin
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can register new users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: id']);
        http_response_code(400); // Bad Request
        exit;
    }

    $revenueHeadController->deleteRevenueHead($input);
    exit;
}



// Route: Delete MDA
if ($request_method == 'PUT' && $uri == '/delete-mda') {
    // You may want to authenticate the request, here’s an example
    // $decoded_token = authenticate();

    // Optionally check if the authenticated user has the role to delete
    // if ($decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can delete MDAs']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Get the input data from the request body (the ID of the MDA to delete)
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if mda_id is provided in the input
    if (isset($input['mda_id'])) {
        $mda_id = $input['mda_id']; // Extract mda_id
        $mdaController->deleteMda($mda_id); // Call the delete method
    } else {
        echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
        http_response_code(400); // Bad Request
    }
    exit;
}

// Route: Get Users under MDAs and Specific MDAs
if ($request_method == 'GET' && $uri == '/get-mda-users') {
    $filters = [
        'id' => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'mda_id' => isset($_GET['mda_id']) ? (int)$_GET['mda_id'] : null,
        'name' => isset($_GET['name']) ? $_GET['name'] : null,
        'email' => isset($_GET['email']) ? $_GET['email'] : null,
        'phone_number' => isset($_GET['phone']) ? $_GET['phone'] : null,
        'office_name' => isset($_GET['office_name']) ? $_GET['office_name'] : null
    ];

    $mdaController->getMdaUsers(array_filter($filters)); // Filter out null values
    exit;
}


// Route: Get invoices under MDAs and Specific MDAs
if ($request_method == 'GET' && $uri == '/get-mda-invoices') {
    $filters = [
        'mda_id' => isset($_GET['mda_id']) ? (int)$_GET['mda_id'] : null,
        'revenue_head' => isset($_GET['revenue_head']) ? (int)$_GET['revenue_head'] : null,
        'invoice_number' => isset($_GET['invoice_number']) ? $_GET['invoice_number'] : null,
        'status' => isset($_GET['status']) ? $_GET['status'] : null,
        'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : null,
        'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : null,
        'revenue_head_id' => isset($_GET['revenue_head_id']) ? $_GET['revenue_head_id'] : null,
        'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 10
    ];

    $mdaController->getInvoicesByMda(array_filter($filters));
    exit;
}

// Route: Get invoices under MDAs and Specific MDAs
if ($request_method == 'GET' && $uri == '/get-mda-payments') {
    $filters = [
        'mda_id' => isset($_GET['mda_id']) ? (int)$_GET['mda_id'] : null,
        'invoice_number' => isset($_GET['invoice_number']) ? $_GET['invoice_number'] : null,
        'status' => isset($_GET['status']) ? $_GET['status'] : null,
        'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : null,
        'revenue_head_id' => isset($_GET['revenue_head_id']) ? $_GET['revenue_head_id'] : null,
        'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : null,
        'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 10
    ];

    $mdaController->getInvoicesWithPaymentInfoByMda(array_filter($filters));
    exit;
}

// Route: Get revenue-heads-summary under Admin
if ($request_method == 'GET' && $uri == '/revenue-heads-summary') {
    $mdaController->getRevenueHeadSummary();
    exit;
}

// Route: Get revenue-heads-summary under Specific MDA
if ($request_method == 'GET' && $uri == '/revenue-heads-summary-by-mda') {
    $mda_id = isset($_GET['mda_id']) ? (int)$_GET['mda_id'] : null;
    if (!$mda_id) {
        echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
        http_response_code(400);
        exit;
    }
    $mdaController->getRevenueHeadSummaryByMda($mda_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/mda-type-breakdown') {
    $queryParams = $_GET;
    $response = $mdaController->getTaxTypeBreakdownByMda($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-dashboard taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/mda-tiles-breakdown') {
    $queryParams = $_GET;
    $response = $mdaController->getTaxSummaryMda($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Route: Get total tax-payment-trends taxpayers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/mda-payment-trends') {
    $queryParams = $_GET;
    $response = $mdaController->getMonthlyPaymentTrendsByMda($queryParams);

    // Set response header and output the response in JSON format
    header('Content-Type: application/json');
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $uri === '/get-mda-performance') {
    $response = $mdaController->getMDAPerformance();
    header('Content-Type: application/json');
    echo $response;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/mda-forgot-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mdaController->forgotMdaPassword($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/mda-reset-password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mdaController->resetAdminPassword($data);
    exit;
}


// If no matching route is found
http_response_code(404);
echo json_encode(['status' => 'error:'.$uri, 'message' => 'Endpoint not found']);
exit;