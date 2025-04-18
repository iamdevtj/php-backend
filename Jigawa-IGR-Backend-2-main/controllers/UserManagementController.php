<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication
require_once 'controllers/EmailController.php';

$emailController = new EmailController();


class UserManagementController
{
    private $conn;

    // Constructor to initialize database connection
    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }



    public function updateAdminUser($admin_id, $data)
    {
        // Validate required fields
        $required_fields = ['fullname', 'email', 'phone', 'role'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
                http_response_code(400); // Bad request
                return;
            }
        }

        // Prepare SQL query
        $query = "UPDATE administrative_users 
                  SET fullname = ?, email = ?, phone = ?, role = ?, date_updated = NOW()
                  WHERE id = ?";

        // Prepare and bind statement
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $this->conn->error]);
            http_response_code(500); // Internal Server Error
            return;
        }

        // Bind parameters
        $params = [
            $data['fullname'],
            $data['email'],
            $data['phone'],
            $data['role'],
            $admin_id
        ];

        $stmt->bind_param('sssss', ...$params);

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating user: ' . $stmt->error]);
        }

        $stmt->close();
    }
   /**
     * Update permissions for the admin user.
     */
    public function updateAdminPermissions($admin_id, $permissions)
    {
        // Begin transaction for data consistency
        $this->conn->begin_transaction();

        try {
            // Delete existing permissions
            $delete_query = "DELETE FROM admin_permissions WHERE admin_id = ?";
            $delete_stmt = $this->conn->prepare($delete_query);

            if (!$delete_stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            $delete_stmt->bind_param('i', $admin_id);
            if (!$delete_stmt->execute()) {
                throw new Exception('Error deleting old permissions: ' . $delete_stmt->error);
            }
            $delete_stmt->close();

            // Insert new permissions
            $insert_query = "INSERT INTO admin_permissions (admin_id, permission_id, date_created) VALUES (?, ?, NOW())";
            $insert_stmt = $this->conn->prepare($insert_query);

            if (!$insert_stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            foreach ($permissions as $permission_id) {
                // Ensure permission_id is an integer to prevent SQL errors
                if (!is_numeric($permission_id)) {
                    throw new Exception('Invalid permission ID: ' . $permission_id);
                }

                $insert_stmt->bind_param('ii', $admin_id, $permission_id);
                if (!$insert_stmt->execute()) {
                    throw new Exception('Error inserting permission: ' . $insert_stmt->error);
                }
            }

            $insert_stmt->close();

            // Commit transaction
            $this->conn->commit();

            echo json_encode(['status' => 'success', 'message' => 'Permissions updated successfully']);
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function deactivateAdmin($admin_id)
    {
        // Prepare the SQL query to update account_status instead of deleting
        $query = "UPDATE administrative_users SET account_status = 'deactivate', date_updated = NOW() WHERE id = ?";

        // Prepare statement
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $this->conn->error]);
            http_response_code(500); // Internal Server Error
            return;
        }

        // Bind parameters
        $stmt->bind_param('i', $admin_id);

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Admin user deactivated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deactivating user: ' . $stmt->error]);
        }

        $stmt->close();
    }

    public function getAllAdminUsers($role, $account_status, $search, $limit, $page) {
        $query = "SELECT id AS admin_id, fullname, email, phone, role, account_status, date_created FROM administrative_users WHERE 1=1";
    
        // Add filters dynamically
        $params = [];
        $types = "";
    
        if ($role) {
            $query .= " AND role = ?";
            $params[] = $role;
            $types .= "s";
        }
        if ($account_status) {
            $query .= " AND account_status = ?";
            $params[] = $account_status;
            $types .= "s";
        }
        if ($search) {
            $query .= " AND (fullname LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
    
        // Pagination
        $offset = ($page - 1) * $limit;
        $query .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    
        // Get total records count for pagination
        $count_query = "SELECT COUNT(*) as total FROM administrative_users WHERE 1=1";
        if ($role) $count_query .= " AND role = '$role'";
        if ($account_status) $count_query .= " AND account_status = '$account_status'";
        if ($search) $count_query .= " AND (fullname LIKE '%$search%' OR email LIKE '%$search%')";
    
        $count_result = $this->conn->query($count_query);
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);
    
        // Return response
        echo json_encode([
            "status" => "success",
            "data" => $admins,
            "pagination" => [
                "current_page" => $page,
                "total_pages" => $total_pages,
                "total_records" => $total_records
            ]
        ]);
    }
    
    public function getAdminPermissions($admin_id) {
        $query = "SELECT id, admin_id, permission_id, date_created FROM admin_permissions WHERE admin_id = ?";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    
        // Check if permissions exist
        if (empty($permissions)) {
            echo json_encode([
                "status" => "error",
                "message" => "No permissions found for the given admin_id."
            ]);
            http_response_code(404); // Not found
            return;
        }
    
        // Return response
        echo json_encode([
            "status" => "success",
            "data" => $permissions
        ]);
    }
    

    public function getGroupedPermissions() {
        $query = "SELECT id, permission_name, category FROM permissions ORDER BY category";
    
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Group permissions by category
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $category = $row['category'];
    
            if (!isset($permissions[$category])) {
                $permissions[$category] = [];
            }
    
            $permissions[$category][] = [
                'id' => $row['id'],
                'permission_name' => $row['permission_name']
            ];
        }
    
        // Check if permissions exist
        if (empty($permissions)) {
            echo json_encode([
                "status" => "error",
                "message" => "No permissions found."
            ]);
            http_response_code(404); // Not found
            return;
        }
    
        // Return grouped permissions
        echo json_encode([
            "status" => "success",
            "data" => $permissions
        ]);
    }
    
    
}
