<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class TaxOfficesController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function createTaxOffice($data) {
        // Validate required fields
        if (!isset($data['office_name'], $data['office_code'], $data['location'], $data['contact_phone'], $data['contact_email'], $data['region'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields: office_name, office_code, location, contact_phone, contact_email, region']);
        }
    
        // Check if the office already exists by office_code or office_name
        $query = "SELECT id FROM tax_offices WHERE office_code = ? OR office_name = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $data['office_code'], $data['office_name']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax office with this code or name already exists']);
        }
    
        // Prepare the SQL query to insert a new tax office
        $query = "INSERT INTO tax_offices (office_name, office_code, location, contact_phone, contact_email, region, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    
        // Prepare the statement
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return json_encode(['status' => 'error', 'message' => 'Error preparing the query: ' . $this->conn->error]);
        }
    
        // Bind the parameters
        $status = 'active';  // Default status
        $stmt->bind_param('sssssss', $data['office_name'], $data['office_code'], $data['location'], $data['contact_phone'], $data['contact_email'], $data['region'], $status);
    
        // Execute the statement
        if ($stmt->execute()) {
            // Return the response with the new tax office ID
            return json_encode(['status' => 'success', 'message' => 'Tax office created successfully', 'data' => ['office_id' => $stmt->insert_id]]);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error creating tax office: ' . $stmt->error]);
        }
    }

    public function getAllTaxOffices($filters) {
        // Default values for pagination
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 10; // Set default limit if not provided
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0; // Set default offset if not provided
    
        // Base query for tax offices
        $query = "SELECT id, office_name, office_code, location, contact_phone, contact_email, region, status, created_at, updated_at 
                  FROM tax_offices
                  WHERE 1=1"; // Default condition to apply filters dynamically
    
        $params = [];
        $types = ''; // Initialize the types string for prepared statement
    
        // Apply filters if provided
        if (!empty($filters['office_name'])) {
            $query .= " AND office_name LIKE ?";
            $params[] = "%" . $filters['office_name'] . "%";
            $types .= 's'; // string
        }
    
        if (!empty($filters['office_code'])) {
            $query .= " AND office_code LIKE ?";
            $params[] = "%" . $filters['office_code'] . "%";
            $types .= 's'; // string
        }
    
        if (!empty($filters['location'])) {
            $query .= " AND location LIKE ?";
            $params[] = "%" . $filters['location'] . "%";
            $types .= 's'; // string
        }
    
        if (!empty($filters['region'])) {
            $query .= " AND region LIKE ?";
            $params[] = "%" . $filters['region'] . "%";
            $types .= 's'; // string
        }
    
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's'; // string
        }
        
        if (!empty($filters['id'])) {
            $query .= " AND id = ?";
            $params[] = $filters['id'];
            $types .= 'i'; // string
        }
    
        // Sorting & Pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii'; // integer for limit and offset
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            if (!empty($params)) {
                // Bind the parameters dynamically based on the types string
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $taxOffices = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // Handle error in preparing statement
            return json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
        }
    
        // Fetch total number of records for pagination
        $count_query = "SELECT COUNT(*) as total FROM tax_offices WHERE 1=1";
        $stmt_count = $this->conn->prepare($count_query);
        if ($stmt_count) {
            // No need to bind parameters for the count query
            $stmt_count->execute();
            $count_result = $stmt_count->get_result();
            $totalRecords = $count_result->fetch_assoc()['total'];
            $stmt_count->close();
        } else {
            // Handle error in preparing count statement
            return json_encode(['status' => 'error', 'message' => 'Failed to prepare count statement.']);
        }
    
        // Prevent division by zero error
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 1;
    
        // Return the result in JSON format
        return json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => isset($filters['page']) ? $filters['page'] : 1,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'tax_offices' => $taxOffices
            ]
        ]);
    }

    public function updateTaxOffice($data) {
        // Validate required fields
        if (!isset($data['id'], $data['office_name'], $data['office_code'], $data['location'], $data['contact_phone'], $data['contact_email'], $data['region'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields: id, office_name, office_code, location, contact_phone, contact_email, region']);
        }
    
        // Get the tax office ID from the request data
        $taxOfficeId = $data['id'];
    
        // Check if the tax office exists
        $query = "SELECT id FROM tax_offices WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $taxOfficeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax office not found']);
        }
    
        // Check if the office code or office name is already in use (to avoid duplicates)
        $query = "SELECT id FROM tax_offices WHERE (office_code = ? OR office_name = ?) AND id != ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssi', $data['office_code'], $data['office_name'], $taxOfficeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax office with this code or name already exists']);
        }
    
        // Prepare the SQL query to update the tax office
        $query = "UPDATE tax_offices 
                  SET office_name = ?, office_code = ?, location = ?, contact_phone = ?, contact_email = ?, region = ?, updated_at = NOW() 
                  WHERE id = ?";
    
        // Prepare the statement
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return json_encode(['status' => 'error', 'message' => 'Error preparing the query: ' . $this->conn->error]);
        }
    
        // Bind the parameters
        $stmt->bind_param('ssssssi', $data['office_name'], $data['office_code'], $data['location'], $data['contact_phone'], $data['contact_email'], $data['region'], $taxOfficeId);
    
        // Execute the statement
        if ($stmt->execute()) {
            // Return the success response
            return json_encode(['status' => 'success', 'message' => 'Tax office updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error updating tax office: ' . $stmt->error]);
        }
    }

    public function toggleTaxOfficeStatus($data) {
        // Validate that the required field 'id' is present in the request data
        if (!isset($data['id'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required field: id']);
        }
    
        $taxOfficeId = $data['id'];
    
        // Check if the tax office exists
        $query = "SELECT id, status FROM tax_offices WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $taxOfficeId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax office not found']);
        }
    
        // Get the current status of the tax office
        $taxOffice = $result->fetch_assoc();
    
        // Toggle the status
        $newStatus = ($taxOffice['status'] === 'inactive') ? 'active' : 'inactive'; // If it's inactive, set to active, else set to inactive
    
        // Update the status in the database
        $updateQuery = "UPDATE tax_offices SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmtUpdate = $this->conn->prepare($updateQuery);
        $stmtUpdate->bind_param('si', $newStatus, $taxOfficeId);
    
        if ($stmtUpdate->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Tax office status updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error updating tax office status: ' . $stmtUpdate->error]);
        }
    }

    public function createManagerOffice($data) {
        // Validate required fields for manager office
        if (!isset($data['manager_name'], $data['manager_contact_phone'], $data['manager_contact_email'], $data['position'], $data['tax_office_id'], $data['password'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields: manager_name, manager_contact_phone, manager_contact_email, position, tax_office_id, password']);
        }
    
        // Check if the tax office exists
        $query = "SELECT id FROM tax_offices WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $data['tax_office_id']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax office not found']);
        }
    
        // Check for duplicate email
        $query = "SELECT id FROM manager_offices WHERE manager_contact_email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $data['manager_contact_email']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            return json_encode(['status' => 'error', 'message' => 'Email already in use']);
        }
    
        // Hash the password before storing it (using PASSWORD_BCRYPT)
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
        // Insert manager office data into the manager_offices table
        $query = "INSERT INTO manager_offices (manager_name, manager_contact_phone, manager_contact_email, position, supervisor_id, tax_office_id, password, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
        $stmt = $this->conn->prepare($query);
        
        // Correctly bind the parameters with the appropriate types
        $stmt->bind_param('sssiiss', 
            $data['manager_name'], 
            $data['manager_contact_phone'], 
            $data['manager_contact_email'], 
            $data['position'], 
            $data['supervisor_id'], 
            $data['tax_office_id'], 
            $hashedPassword
        );
    
        if ($stmt->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Manager office created successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error creating manager office: ' . $stmt->error]);
        }
    }

    public function getManagerOfficeDetails($filters) {
        // Base query for fetching manager offices along with supervisor details
        $query = "SELECT mo.id, mo.manager_name, mo.status, mo.manager_contact_phone, mo.manager_contact_email, mo.position, mo.supervisor_id, 
                         mo.tax_office_id, mo.created_at, mo.updated_at, toffice.office_name AS tax_office_name,
                         au.fullname AS supervisor_name, au.email AS supervisor_email, au.phone AS supervisor_phone
                  FROM manager_offices mo
                  LEFT JOIN tax_offices toffice ON toffice.id = mo.tax_office_id
                  LEFT JOIN administrative_users au ON au.id = mo.supervisor_id
                  WHERE 1=1"; // Default condition
        
        $params = [];
        $types = '';
    
        // Apply filters if provided
        if (isset($filters['tax_office_id'])) {
            $query .= " AND mo.tax_office_id = ?";
            $params[] = $filters['tax_office_id'];
            $types .= 'i'; // integer
        }

        if (isset($filters['id'])) {
            $query .= " AND mo.id = ?";
            $params[] = $filters['id'];
            $types .= 'i'; // integer
        }
    
        if (isset($filters['manager_name'])) {
            $query .= " AND mo.manager_name LIKE ?";
            $params[] = "%" . $filters['manager_name'] . "%";
            $types .= 's'; // string
        }
    
        if (isset($filters['position'])) {
            $query .= " AND mo.position LIKE ?";
            $params[] = "%" . $filters['position'] . "%";
            $types .= 's'; // string
        }
    
        if (isset($filters['supervisor_id'])) {
            $query .= " AND mo.supervisor_id = ?";
            $params[] = $filters['supervisor_id'];
            $types .= 'i'; // integer
        }
    
        // Sorting & Pagination
        if (isset($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $filters['limit'];
            $types .= 'i'; // integer
        }
    
        if (isset($filters['offset'])) {
            $query .= " OFFSET ?";
            $params[] = $filters['offset'];
            $types .= 'i'; // integer
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $managerOffices = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Return the JSON response with manager office data
        return json_encode(['status' => 'success', 'data' => $managerOffices]);
    }

    public function editManagerOffice($data) {
        // Validate required fields for editing
        if (!isset($data['id'], $data['manager_name'], $data['manager_contact_phone'], $data['manager_contact_email'], $data['position'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields: id, manager_name, manager_contact_phone, manager_contact_email, position']);
        }
    
        // Check if the manager office exists
        $query = "SELECT id FROM manager_offices WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $data['id']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Manager office not found']);
        }
    
        // Update manager office details
        $updateQuery = "UPDATE manager_offices 
                        SET manager_name = ?, manager_contact_phone = ?, manager_contact_email = ?, position = ?, updated_at = NOW() 
                        WHERE id = ?";
        $stmtUpdate = $this->conn->prepare($updateQuery);
        $stmtUpdate->bind_param('ssssi', $data['manager_name'], $data['manager_contact_phone'], $data['manager_contact_email'], 
                                $data['position'], $data['id']);
        
        if ($stmtUpdate->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Manager office updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error updating manager office: ' . $stmtUpdate->error]);
        }
    }

    public function toggleManagerStatus($data) {
        // Validate the 'id' field
        if (!isset($data['id'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required field: id']);
        }
    
        // Check if the manager office exists
        $query = "SELECT id, status FROM manager_offices WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $data['id']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Manager office not found']);
        }
    
        // Get the current status of the manager
        $manager = $result->fetch_assoc();
        $newStatus = ($manager['status'] === 'inactive') ? 'active' : 'inactive'; // Toggle status
    
        // Update the status in the database
        $updateQuery = "UPDATE manager_offices SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmtUpdate = $this->conn->prepare($updateQuery);
        $stmtUpdate->bind_param('si', $newStatus, $data['id']);
        
        if ($stmtUpdate->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Manager status updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Error updating manager status: ' . $stmtUpdate->error]);
        }
    }
    
    
    
    
    
    
    
    
    
    
    

}