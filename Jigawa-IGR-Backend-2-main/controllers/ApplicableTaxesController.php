<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class ApplicableTaxes {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function createTaxDependency($data) {
        // Validate required fields
        if (empty($data['primary_tax_id']) || empty($data['dependent_tax_id']) || !isset($data['mandatory'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: primary_tax_id, dependent_tax_id, or mandatory']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Validate mandatory field (only allow 'yes' or 'no')
        $mandatory = strtolower(trim($data['mandatory']));
        if ($mandatory !== 'yes' && $mandatory !== 'no') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid value for mandatory field. Allowed values: yes, no']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Check if primary_tax_id exists in revenue_heads
        $primaryTaxExists = $this->checkRevenueHeadExists($data['primary_tax_id']);
        if (!$primaryTaxExists) {
            echo json_encode(['status' => 'error', 'message' => 'Primary tax ID does not exist in revenue_heads']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Check if dependent_tax_id exists in revenue_heads
        $dependentTaxExists = $this->checkRevenueHeadExists($data['dependent_tax_id']);
        if (!$dependentTaxExists) {
            echo json_encode(['status' => 'error', 'message' => 'Dependent tax ID does not exist in revenue_heads']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Check if the dependency already exists
        $queryCheck = "
            SELECT id FROM tax_dependencies 
            WHERE primary_tax_id = ? AND dependent_tax_id = ?
        ";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->bind_param('ii', $data['primary_tax_id'], $data['dependent_tax_id']);
        $stmtCheck->execute();
        $stmtCheck->store_result();
    
        if ($stmtCheck->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tax dependency already exists']);
            http_response_code(400); // Bad Request
            $stmtCheck->close();
            return;
        }
        $stmtCheck->close();
    
        // Insert the new dependency into tax_dependencies
        $queryInsert = "
            INSERT INTO tax_dependencies (
                primary_tax_id, dependent_tax_id, mandatory, created_at, updated_at
            ) VALUES (?, ?, ?, NOW(), NOW())
        ";
    
        $stmtInsert = $this->conn->prepare($queryInsert);
        $stmtInsert->bind_param('iis', $data['primary_tax_id'], $data['dependent_tax_id'], $mandatory);
    
        if ($stmtInsert->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Tax dependency created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create tax dependency']);
            http_response_code(500); // Internal Server Error
        }
    
        $stmtInsert->close();
    }

    private function checkRevenueHeadExists($revenueHeadId) {
        $query = "SELECT id FROM revenue_heads WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $revenueHeadId);
        $stmt->execute();
        $stmt->store_result();
    
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    
        return $exists;
    }

    public function getTaxDependencies($filters, $page, $limit) {
        // Set default page and limit if not provided
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query
        $query = "
            SELECT 
                td.id,
                td.primary_tax_id,
                td.dependent_tax_id,
                td.mandatory,
                td.created_at,
                td.updated_at,
                pt.item_name AS primary_tax_name,
                dt.item_name AS dependent_tax_name
            FROM tax_dependencies td
            LEFT JOIN revenue_heads pt ON td.primary_tax_id = pt.id
            LEFT JOIN revenue_heads dt ON td.dependent_tax_id = dt.id
            WHERE 1=1
        ";
    
        $params = [];
        $types = '';
    
        // Apply filters
        if (isset($filters['primary_tax_id'])) {
            $query .= " AND td.primary_tax_id = ?";
            $params[] = $filters['primary_tax_id'];
            $types .= 'i';
        }
    
        if (isset($filters['dependent_tax_id'])) {
            $query .= " AND td.dependent_tax_id = ?";
            $params[] = $filters['dependent_tax_id'];
            $types .= 'i';
        }
    
        if (isset($filters['mandatory'])) {
            $query .= " AND td.mandatory = ?";
            $params[] = $filters['mandatory'];
            $types .= 's';
        }
    
        // Add pagination and ordering
        $query .= " ORDER BY td.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $taxDependencies = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Fetch total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM tax_dependencies WHERE 1=1";
        if (isset($filters['primary_tax_id'])) {
            $countQuery .= " AND primary_tax_id = " . intval($filters['primary_tax_id']);
        }
        if (isset($filters['dependent_tax_id'])) {
            $countQuery .= " AND dependent_tax_id = " . intval($filters['dependent_tax_id']);
        }
        if (isset($filters['mandatory'])) {
            $countQuery .= " AND mandatory = '" . $this->conn->real_escape_string($filters['mandatory']) . "'";
        }
        $countResult = $this->conn->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $limit);
    
        // Return the response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'dependencies' => $taxDependencies
            ]
        ]);
    }

    public function getApplicableTaxes($taxNumber, $page = 1, $limit = 10) {
        // Calculate offset for pagination
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;

        if (empty($taxNumber)) {
            echo json_encode(['status' => 'error', 'message' => 'Tax number (or payer_id ) is required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Base query to fetch applicable taxes with limit and offset
        $query = "
            SELECT 
                at.id,
                at.tax_number,
                at.revenue_head_id,
                at.created_at,
                at.updated_at,
                rh.item_name AS revenue_head_name,
                rh.item_code AS revenue_head_code,
                rh.amount AS amount,
                m.fullname AS mda_name,
                m.id AS mda_id
            FROM applicable_taxes at
            LEFT JOIN revenue_heads rh ON at.revenue_head_id = rh.id
            LEFT JOIN mda m ON rh.mda_id = m.id
            WHERE at.tax_number = ?
            ORDER BY at.created_at DESC
            LIMIT ? OFFSET ?
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sii', $taxNumber, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch and structure the data
        $applicableTaxes = [];
        while ($row = $result->fetch_assoc()) {
            $applicableTaxes[] = $row;
        }
        $stmt->close();
    
        // Fetch total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM applicable_taxes WHERE tax_number = ?";
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->bind_param('s', $taxNumber);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $limit);
        $stmtCount->close();
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'applicable_taxes' => $applicableTaxes
            ]
        ]);
    }
    
    
    
    public function deleteTaxDependency($dependencyId) {
        // Check if the dependency exists
        $checkQuery = "SELECT id FROM tax_dependencies WHERE id = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param('i', $dependencyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tax dependency not found']);
            http_response_code(404); // Not Found
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Delete the dependency
        $deleteQuery = "DELETE FROM tax_dependencies WHERE id = ?";
        $stmt = $this->conn->prepare($deleteQuery);
        $stmt->bind_param('i', $dependencyId);
        $success = $stmt->execute();
        $stmt->close();
    
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Tax dependency deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete tax dependency']);
            http_response_code(500); // Internal Server Error
        }
    }


    
    
    
    
    


}