<?php
// Include the required files
require_once 'controllers/AuthController.php';
require_once 'controllers/UserManagementController.php';
require_once 'helpers/auth_helper.php';

// Initialize controllers
$authController = new AuthController();
$usermanagementController = new UserManagementController();

if ($_SERVER['REQUEST_METHOD'] == 'PUT' && $uri == '/update-admin-user') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); 

    // if (!$decoded_token) {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid token']);
    //     http_response_code(401); // Unauthorized
    //     exit;
    // }

    // // Verify the user's role
    // if (!isset($decoded_token['role']) || $decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can update admin users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON format']);
        http_response_code(400); // Bad request
        exit;
    }

    // Validate the presence of 'admin_id' in the input data
    if (empty($input['admin_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: admin_id']);
        http_response_code(400); // Bad request
        exit;
    }

    // Extract 'admin_id' from input data and remove it from the payload
    $admin_id = $input['admin_id'];
    unset($input['admin_id']);

    // Ensure $usermanagementController is properly initialized
    if (!isset($usermanagementController) || !method_exists($usermanagementController, 'updateAdminUser')) {
        echo json_encode(['status' => 'error', 'message' => 'Server error: User management controller not available']);
        http_response_code(500); // Internal Server Error
        exit;
    }

    // Call the updateAdminUser method in the UserManagementController
    $usermanagementController->updateAdminUser($admin_id, $input);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE' && $uri == '/deactivate-admin') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); // Decodes and verifies the JWT token

    // // Verify the user's role
    // if (!isset($decoded_token['role']) || $decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can deactivate admin users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON format']);
        http_response_code(400); // Bad request
        exit;
    }

    // Validate the presence of 'admin_id' in the input data
    if (empty($input['admin_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: admin_id']);
        http_response_code(400); // Bad request
        exit;
    }

    // Extract 'admin_id' from input data
    $admin_id = $input['admin_id'];

    // Ensure $usermanagementController is properly initialized
    if (!isset($usermanagementController) || !method_exists($usermanagementController, 'deactivateAdmin')) {
        echo json_encode(['status' => 'error', 'message' => 'Server error: User management controller not available']);
        http_response_code(500); // Internal Server Error
        exit;
    }

    // Call the deactivateAdmin method in the UserManagementController
    $usermanagementController->deactivateAdmin($admin_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT' && $uri == '/update-admin-permissions') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); // Decodes and verifies the JWT token

    // // Verify the user's role
    // if (!isset($decoded_token['role']) || $decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can update permissions']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Get the JSON data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['admin_id']) || empty($input['permissions']) || !is_array($input['permissions'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields: admin_id and permissions are required']);
        http_response_code(400); // Bad request
        exit;
    }

    // Extract data
    $admin_id = $input['admin_id'];
    $permissions = $input['permissions'];

    // Call the function to update permissions
    $usermanagementController->updateAdminPermissions($admin_id, $permissions);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-admin-users') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); // Decodes and verifies the JWT token

    // // Verify the user's role (Only super_admin can fetch all admins)
    // if (!isset($decoded_token['role']) || $decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can fetch admin users']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Get filters from query parameters
    $role = $_GET['role'] ?? null;
    $account_status = $_GET['account_status'] ?? null;
    $search = $_GET['search'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    // Fetch admin users with filters
    $usermanagementController->getAllAdminUsers($role, $account_status, $search, $limit, $page);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-admin-permissions') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); // Decodes and verifies the JWT token

    // Validate query parameter
    if (empty($_GET['admin_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: admin_id']);
        http_response_code(400); // Bad request
        exit;
    }

    $admin_id = (int) $_GET['admin_id'];

    // // Only allow super_admin or the admin themselves to access this data
    // if ($decoded_token['role'] !== 'super_admin' && $decoded_token['admin_id'] != $admin_id) {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: You do not have permission to view this data']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Fetch admin permissions
    $usermanagementController->getAdminPermissions($admin_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $uri == '/get-permissions') {
    // Authenticate the request using the JWT token
    // $decoded_token = authenticate(); // Decodes and verifies the JWT token

    // Ensure only authorized users can access
    // if (!isset($decoded_token['role']) || $decoded_token['role'] !== 'super_admin') {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only super admins can view permissions']);
    //     http_response_code(403); // Forbidden
    //     exit;
    // }

    // Fetch permissions grouped by category
    $usermanagementController->getGroupedPermissions();
    exit;
}

