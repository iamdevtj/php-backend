<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class TaxFilingController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function createTaxFiling($data) {
        // Validate required fields
        if (!isset($data['taxpayer_id'], $data['filing_date'], $data['tax_types'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields: taxpayer_id, filing_date, or tax_types']);
        }
    
        // Define valid tax types
        $validTaxTypes = ['paye', 'direct_assessment', 'withholding_tax'];
    
        // Validate each tax type in the provided data
        foreach ($data['tax_types'] as $taxType) {
            if (!in_array($taxType['tax_type'], $validTaxTypes)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid tax type: ' . $taxType['tax_type']]);
            }
        }
    
        // Generate a 10-digit tax filing number (e.g., 1234567890)
        $taxFilingNumber = $this->generateTaxFilingNumber();
    
        // Insert tax filing into `tax_filing` table
        $query = "INSERT INTO tax_filing (taxpayer_id, filing_date, status, filing_number) VALUES (?, ?, 'pending', ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iss', $data['taxpayer_id'], $data['filing_date'], $taxFilingNumber);
        $stmt->execute();
        $filingId = $stmt->insert_id;  // Get the inserted filing ID
        $stmt->close();
    
        // Insert tax types and supporting documents
        foreach ($data['tax_types'] as $taxType) {
            // Insert tax type
            $query = "INSERT INTO tax_filing_types (filing_id, tax_type, amount_paid, status, annual_income, profession, tax_assessment_type, taxpayer_category) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('isdsdsss', $filingId, $taxType['tax_type'], $taxType['amount_paid'], $taxType['status'], $taxType['annual_income'], 
                              $taxType['profession'], $taxType['tax_assessment_type'], $taxType['taxpayer_category']);
            $stmt->execute();
            $filingTypeId = $stmt->insert_id;
            $stmt->close();
    
            // Insert supporting documents
            if (isset($taxType['documents'])) {
                foreach ($taxType['documents'] as $document) {
                    $query = "INSERT INTO tax_filing_supporting_documents (filing_type_id, document_type, file_path, uploaded_at) 
                              VALUES (?, ?, ?, ?)";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param('isss', $filingTypeId, $document['document_type'], $document['file_path'], $document['uploaded_at']);
                    $stmt->execute();
                }
            }
        }
    
        // Return success response with the filing number
        return json_encode(['status' => 'success', 'message' => 'Tax filing created successfully', 'data' => ['filing_id' => $filingId, 'filing_number' => $taxFilingNumber]]);
    }
    
    public function generateTaxFilingNumber() {
        // Generate a 10-digit random number
        do {
            // Generate a random number between 0 and 9999999999, then pad it to 10 digits
            $taxFilingNumber = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
    
            // Check if the generated number already exists in the tax_filing table
            $query = "SELECT id FROM tax_filing WHERE filing_number = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('s', $taxFilingNumber);
            $stmt->execute();
            $stmt->store_result();
            
        } while ($stmt->num_rows > 0); // Regenerate the number if it already exists
    
        $stmt->close();
    
        // Return the unique 10-digit tax filing number
        return $taxFilingNumber;
    }
    
    

    public function getAllTaxFilings($filters) {
        // Base query for tax filings (now including filing_number)
        $query = "SELECT tf.id, tf.filing_date, tf.status, tf.admin_remarks, tf.created_at, tf.updated_at, tf.filing_number
                  FROM tax_filing tf
                  WHERE 1=1"; // Default where condition to apply filters dynamically
    
        $params = [];
        $types = ''; // Initialize the types string for prepared statement
    
        // Apply filters if provided
        if (isset($filters['taxpayer_id'])) {
            $query .= " AND tf.taxpayer_id = ?";
            $params[] = $filters['taxpayer_id'];
            $types .= 'i'; // integer
        }

        if (isset($filters['tax_filing_id'])) {
            $query .= " AND tf.id = ?";
            $params[] = $filters['tax_filing_id'];
            $types .= 'i'; // integer
        }
    
        if (isset($filters['status'])) {
            $query .= " AND tf.status = ?";
            $params[] = $filters['status'];
            $types .= 's'; // string
        }
    
        if (isset($filters['filing_date_start']) && isset($filters['filing_date_end'])) {
            $query .= " AND tf.filing_date BETWEEN ? AND ?";
            $params[] = $filters['filing_date_start'];
            $params[] = $filters['filing_date_end'];
            $types .= 'ss'; // two strings (start and end date)
        }
    
        if (isset($filters['tax_type'])) {
            $query .= " AND tf.tax_type = ?";
            $params[] = $filters['tax_type'];
            $types .= 's'; // string
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
        $taxFilings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Fetch tax filing types for each filing (with supporting documents)
        foreach ($taxFilings as &$filing) {
            // Get tax types for each filing
            $query = "SELECT tft.id, tft.tax_type, tft.amount_paid, tft.status, tft.annual_income, tft.profession, 
                             tft.tax_assessment_type, tft.taxpayer_category 
                      FROM tax_filing_types tft
                      WHERE tft.filing_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $filing['id']);
            $stmt->execute();
            $typeResult = $stmt->get_result();
            $taxTypes = $typeResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
    
            // Get supporting documents for each tax type
            foreach ($taxTypes as &$taxType) {
                $query = "SELECT sd.document_type, sd.file_path, sd.uploaded_at 
                          FROM tax_filing_supporting_documents sd
                          WHERE sd.filing_type_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('i', $taxType['id']);
                $stmt->execute();
                $docResult = $stmt->get_result();
                $documents = $docResult->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
    
                $taxType['documents'] = $documents;
            }
    
            $filing['tax_types'] = $taxTypes;
    
            // Fetch tax filing history
            $historyQuery = "SELECT tfh.status, tfh.admin_remarks, tfh.created_at
                             FROM tax_filing_history tfh
                             WHERE tfh.filing_id = ?";
            $stmt = $this->conn->prepare($historyQuery);
            $stmt->bind_param('i', $filing['id']);
            $stmt->execute();
            $historyResult = $stmt->get_result();
            $history = $historyResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
    
            $filing['history'] = $history;  // Add history to the tax filing
        }
    
        // Return JSON response with tax filings data including filing_number
        return json_encode(['status' => 'success', 'data' => $taxFilings]);
    }
    

    // public function approveTaxType($filingId, $taxTypeId) {
    //     // Validate if the filing and tax type exist
    //     $query = "SELECT id, status FROM tax_filing_types WHERE filing_id = ? AND id = ?";
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param('ii', $filingId, $taxTypeId);
    //     $stmt->execute();
    //     $result = $stmt->get_result();

    //     if ($result->num_rows === 0) {
    //         return json_encode(['status' => 'error', 'message' => 'Tax type not found in the specified filing']);
    //     }

    //     // Get the tax type details
    //     $taxType = $result->fetch_assoc();
    //     $stmt->close();

    //     // If the tax type is already approved, return an error
    //     if ($taxType['status'] === 'approved') {
    //         return json_encode(['status' => 'error', 'message' => 'Tax type is already approved']);
    //     }

    //     // Update the tax type status to "approved"
    //     $query = "UPDATE tax_filing_types SET status = 'approved' WHERE id = ?";
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param('i', $taxTypeId);
    //     $stmt->execute();
    //     $stmt->close();

    //     // Optionally, check if all tax types in the filing are approved
    //     $checkQuery = "SELECT COUNT(*) AS total, SUM(status = 'approved') AS approved_count 
    //                    FROM tax_filing_types WHERE filing_id = ?";
    //     $stmtCheck = $this->conn->prepare($checkQuery);
    //     $stmtCheck->bind_param('i', $filingId);
    //     $stmtCheck->execute();
    //     $checkResult = $stmtCheck->get_result();
    //     $checkData = $checkResult->fetch_assoc();
    //     $stmtCheck->close();

    //     if ($checkData['total'] === $checkData['approved_count']) {
    //         // If all tax types are approved, update the filing status to approved
    //         $updateFilingQuery = "UPDATE tax_filing SET status = 'approved' WHERE id = ?";
    //         $stmtUpdateFiling = $this->conn->prepare($updateFilingQuery);
    //         $stmtUpdateFiling->bind_param('i', $filingId);
    //         $stmtUpdateFiling->execute();
    //         $stmtUpdateFiling->close();
    //     }

    //     // Return success message
    //     return json_encode(['status' => 'success', 'message' => 'Tax type approved successfully']);
    // }

    public function approveTaxType($filingId, $taxTypeId, $newStatus, $adminRemarks = null) {
        // Validate if the filing and tax type exist
        $query = "SELECT id, status FROM tax_filing_types WHERE filing_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $filingId, $taxTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax type not found in the specified filing']);
        }
    
        // Get the tax type details
        $taxType = $result->fetch_assoc();
        $stmt->close();
    
        // Validate the status transition
        if ($newStatus === 'approved' && $taxType['status'] === 'approved') {
            return json_encode(['status' => 'error', 'message' => 'Tax type is already approved']);
        }
        
        if ($newStatus === 'flagged' && $taxType['status'] === 'flagged') {
            return json_encode(['status' => 'error', 'message' => 'Tax type is already flagged']);
        }
    
        if ($newStatus === 'approved' && $taxType['status'] !== 'pending') {
            return json_encode(['status' => 'error', 'message' => 'Tax type must be in "pending" status before being approved']);
        }
    
        // Update the tax type status
        $query = "UPDATE tax_filing_types SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $newStatus, $taxTypeId);
        $stmt->execute();
        $stmt->close();
    
        // Capture status change in the tax_filing_history table
        $historyQuery = "INSERT INTO tax_filing_history (filing_id, status, admin_remarks, created_at) 
                         VALUES (?, ?, ?, NOW())";
        $stmtHistory = $this->conn->prepare($historyQuery);
        $stmtHistory->bind_param('iss', $filingId, $newStatus, $adminRemarks);
        $stmtHistory->execute();
        $stmtHistory->close();
    
        // Optionally, check if all tax types in the filing are approved
        $checkQuery = "SELECT COUNT(*) AS total, SUM(status = 'approved') AS approved_count 
                       FROM tax_filing_types WHERE filing_id = ?";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bind_param('i', $filingId);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        $checkData = $checkResult->fetch_assoc();
        $stmtCheck->close();
    
        // If all tax types are approved, update the filing status to approved
        if ($checkData['total'] === $checkData['approved_count']) {
            $updateFilingQuery = "UPDATE tax_filing SET status = 'approved' WHERE id = ?";
            $stmtUpdateFiling = $this->conn->prepare($updateFilingQuery);
            $stmtUpdateFiling->bind_param('i', $filingId);
            $stmtUpdateFiling->execute();
            $stmtUpdateFiling->close();
        }
    
        // Return success message
        return json_encode(['status' => 'success', 'message' => 'Tax type updated successfully']);
    }
    
    

    public function updateTaxFilingStatus($filingId, $data) {
        // Validate required fields
        if (!isset($data['status'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required field: status']);
        }

        // Define valid statuses
        $validStatuses = ['approved', 'flagged'];

        // Validate the status
        if (!in_array($data['status'], $validStatuses)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid status value. Must be "approved" or "flagged"']);
        }

        // Fetch the tax filing to ensure it exists
        $query = "SELECT id, status FROM tax_filing WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $filingId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return json_encode(['status' => 'error', 'message' => 'Tax filing not found']);
        }

        // Get current filing details
        $filing = $result->fetch_assoc();
        $stmt->close();

        // Check if the status is already the one the admin is trying to set
        if ($filing['status'] === $data['status']) {
            return json_encode(['status' => 'error', 'message' => 'The tax filing is already in the specified status']);
        }

        // If the new status is "approved", check that all tax types in the filing have been approved
        if ($data['status'] === 'approved') {
            // Fetch the status of all tax types associated with this filing
            $query = "SELECT status FROM tax_filing_types WHERE filing_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $filingId);
            $stmt->execute();
            $taxTypeResult = $stmt->get_result();
            $allApproved = true;

            while ($taxType = $taxTypeResult->fetch_assoc()) {
                if ($taxType['status'] !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }
            $stmt->close();

            if (!$allApproved) {
                return json_encode(['status' => 'error', 'message' => 'Not all tax types have been approved']);
            }
        }

        // Update the tax filing status and add remarks (if provided)
        $remarks = isset($data['admin_remarks']) ? $data['admin_remarks'] : null;
        $query = "UPDATE tax_filing SET status = ?, admin_remarks = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssi', $data['status'], $remarks, $filingId);
        $stmt->execute();
        $stmt->close();

        // Log the status change in tax_filing_history
        $historyQuery = "INSERT INTO tax_filing_history (filing_id, status, admin_remarks) VALUES (?, ?, ?)";
        $historyStmt = $this->conn->prepare($historyQuery);
        $historyStmt->bind_param('iss', $filingId, $data['status'], $remarks);
        $historyStmt->execute();
        $historyStmt->close();

        return json_encode(['status' => 'success', 'message' => 'Tax filing status updated successfully']);
    }

    public function getTaxFilingStatistics($filters) {
        // Initialize statistics array
        $statistics = [];

        // 1. Tax Filing Status Overview (approved, flagged, pending)
        $query = "SELECT status, COUNT(*) AS total_filings FROM tax_filing WHERE 1=1";
        $params = [];
        $types = '';
        
        // Apply filters if provided
        if (isset($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        // Apply date filter if provided
        if (isset($filters['filing_date_start']) && isset($filters['filing_date_end'])) {
            $query .= " AND filing_date BETWEEN ? AND ?";
            $params[] = $filters['filing_date_start'];
            $params[] = $filters['filing_date_end'];
            $types .= 'ss';
        }

        // Add GROUP BY status to avoid SQL error
        $query .= " GROUP BY status";

        // Execute the query for status overview
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $statusOverview = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $statistics['status_overview'] = $statusOverview;

        // 2. Tax Type Breakdown
        // Add GROUP BY tax_type to avoid SQL error
        $query = "SELECT tax_type, COUNT(*) AS total_tax_types FROM tax_filing_types WHERE 1=1";
        $params = [];
        $types = '';

        // Apply filters for tax type breakdown
        if (isset($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (isset($filters['filing_date_start']) && isset($filters['filing_date_end'])) {
            $query .= " AND filing_date BETWEEN ? AND ?";
            $params[] = $filters['filing_date_start'];
            $params[] = $filters['filing_date_end'];
            $types .= 'ss';
        }

        // Add GROUP BY tax_type to avoid SQL error
        $query .= " GROUP BY tax_type";

        // Execute the query for tax type breakdown
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $taxTypeBreakdown = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $statistics['tax_type_breakdown'] = $taxTypeBreakdown;

        // 3. Compliance Rate (Percentage of filings fully approved)
        $query = "SELECT filing_id, COUNT(*) AS total_tax_types, SUM(status = 'approved') AS approved_count 
                  FROM tax_filing_types GROUP BY filing_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $complianceRate = 0;
        $totalFilings = 0;
        $fullyApprovedFilings = 0;

        // Calculate compliance rate (percentage of filings fully approved)
        while ($row = $result->fetch_assoc()) {
            $totalFilings++;
            if ($row['total_tax_types'] === $row['approved_count']) {
                $fullyApprovedFilings++;
            }
        }
        $stmt->close();

        if ($totalFilings > 0) {
            $complianceRate = ($fullyApprovedFilings / $totalFilings) * 100;
        }

        $statistics['compliance_rate'] = $complianceRate;

        // Return the statistics as JSON
        return json_encode(['status' => 'success', 'data' => $statistics]);
    }
    
    

   

}