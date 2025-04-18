<?php
require_once 'config/database.php';

class TccController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function registerTCC($data)
    {
        // Validate required fields
        $requiredFields = ['taxpayer_id', 'applicant_tin', 'date_employed', 'occupation', 'category', 'invoice_number','reason', 'declaration_name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
                http_response_code(400); // Bad Request
                return;
            }
        }
    
        // Check for duplicate invoice_number
        $duplicateCheckQuery = "SELECT id FROM tax_clearance_certificates WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($duplicateCheckQuery);
        $stmt->bind_param('s', $data['invoice_number']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invoice number already exists']);
            http_response_code(409); // Conflict
            $stmt->close();
            return;
        }
        $stmt->close();
    
        // Generate unique TCC number
        $tccNumber = $this->generateUniqueTCCNumber();
    
        // Assign issued_date and expiry_date
        $issuedDate = date('Y-m-d'); // Today's date
        $expiryDate = date('Y-m-d', strtotime('+1 year')); // 1 year from today
    
        // Assign tax_period_start and tax_period_end (e.g., last three years)
        // $currentYear = (int)date('Y');
        // $taxPeriodStart = ($currentYear - 3) . '-01-01';
        // $taxPeriodEnd = ($currentYear - 1) . '-12-31';

        $taxPeriodStart = $issuedDate;
        $taxPeriodEnd = $expiryDate;
    
        // Insert into tax_clearance_certificates table
        $query = "
            INSERT INTO tax_clearance_certificates (
                recommendation, declaration_name, reason, taxpayer_id, tcc_number, issued_date, expiry_date, tax_period_start, tax_period_end, 
                total_tax_paid, applicant_tin, date_employed, occupation, category, invoice_number, 
                current_stage, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'first_reviewer_approval', 'pending', NOW())
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'sssisssssdsssss',
            $data['declaration_name'],
            $data['recommendation'],
            $data['reason'],
            $data['taxpayer_id'],
            $tccNumber,
            $issuedDate,
            $expiryDate,
            $taxPeriodStart,
            $taxPeriodEnd,
            $data['total_tax_paid'],
            $data['applicant_tin'],
            $data['date_employed'],
            $data['occupation'],
            $data['category'],
            $data['invoice_number']
        );
    
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to register TCC: ' . $stmt->error]);
            http_response_code(500); // Internal Server Error
            return;
        }
    
        $tccId = $stmt->insert_id; // Get the inserted TCC ID
    
        // Handle secondary_information (optional)
        if (!empty($data['secondary_information'])) {
            foreach ($data['secondary_information'] as $info) {
                $this->insertSecondaryInformation($tccId, $info);
            }
        }
    
        // Handle supporting documents (optional)
        if (!empty($data['supporting_documents'])) {
            foreach ($data['supporting_documents'] as $document) {
                $this->insertSupportingDocument($tccId, $document);
            }
        }
    
        echo json_encode(['status' => 'success', 'message' => 'TCC registered successfully', 'tcc_number' => $tccNumber]);
    }
    

    /**
     * Generate a unique TCC number.
     */
    private function generateUniqueTCCNumber()
    {
        do {
            $tccNumber = 'TCC-' . strtoupper(bin2hex(random_bytes(4))); // Example: TCC-1A2B3C4D

            $query = "SELECT id FROM tax_clearance_certificates WHERE tcc_number = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('s', $tccNumber);
            $stmt->execute();
            $stmt->store_result();

        } while ($stmt->num_rows > 0);

        return $tccNumber;
    }

    /**
     * Insert secondary information.
     */
    private function insertSecondaryInformation($tccId, $info)
    {
        $query = "
            INSERT INTO secondary_information (
                tcc_id, tax_title, amount_owed, exemption_type, husband_name, husband_address, 
                institution_name, first_year_date, first_year_income, first_year_tax_amount, 
                second_year_date, second_year_income, second_year_tax_amount, 
                third_year_date, third_year_income, third_year_tax_amount, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'isdsssssssssssds',
            $tccId,
            $info['tax_title'],
            $info['amount_owed'],
            $info['exemption_type'],
            $info['husband_name'],
            $info['husband_address'],
            $info['institution_name'],
            $info['first_year_date'],
            $info['first_year_income'],
            $info['first_year_tax_amount'],
            $info['second_year_date'],
            $info['second_year_income'],
            $info['second_year_tax_amount'],
            $info['third_year_date'],
            $info['third_year_income'],
            $info['third_year_tax_amount']
        );

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Insert supporting documents.
     */
    private function insertSupportingDocument($tccId, $document)
    {
        $query = "INSERT INTO tcc_supporting_documents (tcc_id, document_name, document_url, uploaded_at) VALUES (?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'iss',
            $tccId,
            $document['document_name'],
            $document['document_url']
        );

        $stmt->execute();
        $stmt->close();
    }

    // public function getTCC($filters, $page = 1, $limit = 10) {
    //     // Default pagination settings
    //     $page = (int)$page;
    //     $limit = (int)$limit;
    //     $offset = ($page - 1) * $limit;
    
    //     // Base query
    //     $query = "
    //         SELECT 
    //             tcc.*, 
    //             COALESCE(tp.first_name, etp.first_name) AS taxpayer_first_name, 
    //             COALESCE(tp.surname, etp.last_name) AS taxpayer_surname,
    //             ar1.fullname AS first_reviewer_name,
    //             ar2.fullname AS reviewer_approval_name,
    //             ar3.fullname AS director_approval_name
    //         FROM tax_clearance_certificates tcc
    //         LEFT JOIN taxpayer tp ON tp.tax_number COLLATE utf8mb4_general_ci = tcc.taxpayer_id
    //         LEFT JOIN enumerator_tax_payers etp ON etp.tax_number COLLATE utf8mb4_general_ci = tcc.taxpayer_id
    //         LEFT JOIN administrative_users ar1 ON ar1.id = tcc.first_reviewer_id
    //         LEFT JOIN administrative_users ar2 ON ar2.id = tcc.reviewer_approval_id
    //         LEFT JOIN administrative_users ar3 ON ar3.id = tcc.director_approval_id
    //         WHERE 1=1
    //     ";
    
    //     $params = [];
    //     $types = '';
    
    //     // Apply filters
    //     if (!empty($filters['taxpayer_id'])) {
    //         $query .= " AND tcc.taxpayer_id = ?";
    //         $params[] = $filters['taxpayer_id'];
    //         $types .= 's';
    //     }

    //     if (!empty($filters['tcc_number'])) {
    //         $query .= " AND tcc.tcc_number = ?";
    //         $params[] = $filters['tcc_number'];
    //         $types .= 's';
    //     }
    
    //     if (!empty($filters['category'])) {
    //         $query .= " AND tcc.category = ?";
    //         $params[] = $filters['category'];
    //         $types .= 's';
    //     }
    
    //     if (!empty($filters['first_reviewer_id'])) {
    //         $query .= " AND tcc.first_reviewer_id = ?";
    //         $params[] = $filters['first_reviewer_id'];
    //         $types .= 'i';
    //     }
    
    //     if (!empty($filters['reviewer_approval_id'])) {
    //         $query .= " AND tcc.reviewer_approval_id = ?";
    //         $params[] = $filters['reviewer_approval_id'];
    //         $types .= 'i';
    //     }
    
    //     if (!empty($filters['director_approval_id'])) {
    //         $query .= " AND tcc.director_approval_id = ?";
    //         $params[] = $filters['director_approval_id'];
    //         $types .= 'i';
    //     }
    
    //     if (!empty($filters['current_stage'])) {
    //         $query .= " AND tcc.current_stage = ?";
    //         $params[] = $filters['current_stage'];
    //         $types .= 's';
    //     }
    
    //     if (!empty($filters['status'])) {
    //         $query .= " AND tcc.status = ?";
    //         $params[] = $filters['status'];
    //         $types .= 's';
    //     }
    
    //     if (!empty($filters['issued_date_start']) && !empty($filters['issued_date_end'])) {
    //         $query .= " AND tcc.issued_date BETWEEN ? AND ?";
    //         $params[] = $filters['issued_date_start'];
    //         $params[] = $filters['issued_date_end'];
    //         $types .= 'ss';
    //     }
    
    //     if (!empty($filters['expiry_date_start']) && !empty($filters['expiry_date_end'])) {
    //         $query .= " AND tcc.expiry_date BETWEEN ? AND ?";
    //         $params[] = $filters['expiry_date_start'];
    //         $params[] = $filters['expiry_date_end'];
    //         $types .= 'ss';
    //     }
    
    //     // Add pagination
    //     $query .= " LIMIT ? OFFSET ?";
    //     $params[] = $limit;
    //     $params[] = $offset;
    //     $types .= 'ii';
    
    //     // Prepare and execute query
    //     $stmt = $this->conn->prepare($query);
    //     if (!empty($params)) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $tccs = $result->fetch_all(MYSQLI_ASSOC);
    //     $stmt->close();
    //     foreach ($tccs as $key => $tccs_value) {
    //         $tccs[$key]['secondary_info'] = $this->getTCCSecondaryInformation($tccs_value['id']);
    //         $tccs[$key]['tcc_supporting_documents'] = $this->getTCCSupportingDocuments($tccs_value['id']);     
    //     }
    //     // Fetch total count for pagination
    //     $countQuery = "
    //         SELECT COUNT(*) as total
    //         FROM tax_clearance_certificates tcc
    //         WHERE 1=1
    //     ";
    //     if (!empty($filters['taxpayer_id'])) {
    //         $countQuery .= " AND tcc.taxpayer_id = ?";
    //     }
    //     $stmtCount = $this->conn->prepare($countQuery);
    //     if (!empty($filters['taxpayer_id'])) {
    //         $stmtCount->bind_param('s', $filters['taxpayer_id']);
    //     }
    //     $stmtCount->execute();
    //     $totalResult = $stmtCount->get_result();
    //     $totalRecords = $totalResult->fetch_assoc()['total'];
    //     $stmtCount->close();
    
    //     $totalPages = ceil($totalRecords / $limit);
    
    //     // Return JSON response
    //     echo json_encode([
    //         'status' => 'success',
    //         'data' => $tccs,
    //         'pagination' => [
    //             'current_page' => $page,
    //             'per_page' => $limit,
    //             'total_pages' => $totalPages,
    //             'total_records' => $totalRecords
    //         ]
    //     ]);
    // }

    public function getTCC($filters, $page = 1, $limit = 10) {
        // Default pagination settings
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
    
        // Base query
        $query = "
            SELECT 
                tcc.*, 
                COALESCE(tp.first_name, etp.first_name) AS taxpayer_first_name, 
                COALESCE(tp.email, etp.email) AS taxpayer_email, 
                COALESCE(tp.category, etp.category) AS taxpayer_category, 
                COALESCE(tp.surname, etp.last_name) AS taxpayer_surname,
                COALESCE(tp.address, etp.address) AS taxpayer_address,
                ar1.fullname AS first_reviewer_name,
                ar2.fullname AS reviewer_approval_name,
                ar3.fullname AS director_approval_name
            FROM tax_clearance_certificates tcc
            LEFT JOIN taxpayer tp ON tp.tax_number COLLATE utf8mb4_general_ci = tcc.taxpayer_id
            LEFT JOIN enumerator_tax_payers etp ON etp.tax_number COLLATE utf8mb4_general_ci = tcc.taxpayer_id
            LEFT JOIN administrative_users ar1 ON ar1.id = tcc.first_reviewer_id
            LEFT JOIN administrative_users ar2 ON ar2.id = tcc.reviewer_approval_id
            LEFT JOIN administrative_users ar3 ON ar3.id = tcc.director_approval_id
            WHERE 1=1
        ";
    
        $params = [];
        $types = '';
    
        // Apply filters
        if (!empty($filters['taxpayer_id'])) {
            $query .= " AND tcc.taxpayer_id = ?";
            $params[] = $filters['taxpayer_id'];
            $types .= 's';
        }
    
        if (!empty($filters['tcc_number'])) {
            $query .= " AND tcc.tcc_number = ?";
            $params[] = $filters['tcc_number'];
            $types .= 's';
        }
    
        if (!empty($filters['category'])) {
            $query .= " AND tcc.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
    
        if (!empty($filters['first_reviewer_id'])) {
            $query .= " AND tcc.first_reviewer_id = ?";
            $params[] = $filters['first_reviewer_id'];
            $types .= 'i';
        }
    
        if (!empty($filters['reviewer_approval_id'])) {
            $query .= " AND tcc.reviewer_approval_id = ?";
            $params[] = $filters['reviewer_approval_id'];
            $types .= 'i';
        }
    
        if (!empty($filters['director_approval_id'])) {
            $query .= " AND tcc.director_approval_id = ?";
            $params[] = $filters['director_approval_id'];
            $types .= 'i';
        }
    
        if (!empty($filters['current_stage'])) {
            $query .= " AND tcc.current_stage = ?";
            $params[] = $filters['current_stage'];
            $types .= 's';
        }
    
        if (!empty($filters['status'])) {
            $query .= " AND tcc.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
    
        if (!empty($filters['issued_date_start']) && !empty($filters['issued_date_end'])) {
            $query .= " AND tcc.issued_date BETWEEN ? AND ?";
            $params[] = $filters['issued_date_start'];
            $params[] = $filters['issued_date_end'];
            $types .= 'ss';
        }
    
        if (!empty($filters['expiry_date_start']) && !empty($filters['expiry_date_end'])) {
            $query .= " AND tcc.expiry_date BETWEEN ? AND ?";
            $params[] = $filters['expiry_date_start'];
            $params[] = $filters['expiry_date_end'];
            $types .= 'ss';
        }
    
        // Add pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $tccs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Fetch additional information for each TCC
        foreach ($tccs as $key => $tccs_value) {
            $tccs[$key]['secondary_info'] = $this->getTCCSecondaryInformation($tccs_value['id']);
            $tccs[$key]['tcc_supporting_documents'] = $this->getTCCSupportingDocuments($tccs_value['id']);     
        }
    
        // Fetch total count for pagination
        $countQuery = "
            SELECT COUNT(*) as total
            FROM tax_clearance_certificates tcc
            WHERE 1=1
        ";
    
        if (!empty($filters['taxpayer_id'])) {
            $countQuery .= " AND tcc.taxpayer_id = ?";
        }
    
        $stmtCount = $this->conn->prepare($countQuery);
        if (!empty($filters['taxpayer_id'])) {
            $stmtCount->bind_param('s', $filters['taxpayer_id']);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result();
        $totalRecords = $totalResult->fetch_assoc()['total'];
        $stmtCount->close();
    
        $totalPages = ceil($totalRecords / $limit);
    
        // Return JSON response
        echo json_encode([
            'status' => 'success',
            'data' => $tccs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords
            ]
        ]);
    }
    

    /**
     * getting Secondary info for TCC
     */
    private function getTCCSecondaryInformation($tccId) {
        // Validate TCC ID
        if (empty($tccId)) {
            // echo json_encode(['status' => 'error', 'message' => 'TCC ID is required']);
            // http_response_code(400); // Bad Request
            // return;
        }
    
        // Query to fetch secondary information for the TCC
        $query = "
            SELECT 
                id,
                tax_title,
                amount_owed,
                exemption_type,
                husband_name,
                husband_address,
                institution_name,
                first_year_date,
                first_year_income,
                first_year_tax_amount,
                second_year_date,
                second_year_income,
                second_year_tax_amount,
                third_year_date,
                third_year_income,
                third_year_tax_amount,
                created_at
            FROM secondary_information
            WHERE tcc_id = ?
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tccId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch and format the secondary information
        $secondaryInfo = [];
        while ($row = $result->fetch_assoc()) {
            $secondaryInfo[] = $row;
        }
    
        // Check if secondary information exists
        if (empty($secondaryInfo)) {
            // echo json_encode(['status' => 'error', 'message' => 'No secondary information found for the given TCC ID']);
            // http_response_code(404); // Not Found
            // return;
        }
    
        // Return the secondary information
        // echo json_encode([
        //     'status' => 'success',
        //     'data' => $secondaryInfo
        // ]);

        return $secondaryInfo;
    }

    public function getTCCStatusCount($filters = []) {
        // Base query to get the total number of TCCs based on their statuses
        $query = "
            SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS total_submitted,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS total_rejected,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) AS total_approved
            FROM tax_clearance_certificates
            WHERE 1=1
        ";
    
        $params = [];
        $types = '';
    
        // Apply category filter if provided
        if (!empty($filters['category'])) {
            $query .= " AND category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch the result
        $statusCounts = $result->fetch_assoc();
    
        // Check if data is returned
        if (!$statusCounts) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch TCC status counts']);
            http_response_code(500); // Internal Server Error
            return;
        }
    
        // Return the status counts
        echo json_encode([
            'status' => 'success',
            'data' => $statusCounts
        ]);
    }
    

    private function getTCCSupportingDocuments($data) {
        // Base query to fetch TCC supporting documents
        $query = "
            SELECT 
                tsd.id,
                tsd.tcc_id,
                tsd.document_name,
                tsd.document_url,
                tsd.uploaded_at
            FROM 
                tcc_supporting_documents tsd
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Apply filters if provided
            $query .= " AND tsd.tcc_id = ?";
            $params[] = $data;
            $types .= "i";
        // Execute the query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
    
        // Check if results exist
        if (empty($documents)) {
            // echo json_encode(['status' => 'error', 'message' => 'No supporting documents found']);
            // http_response_code(404); // Not Found
            // return;
        }
    
        // Return JSON response
        // echo json_encode([
        //     "status" => "success",
        //     "data" => $documents
        // ]);

        return $documents;
    }
    
    public function getTCCStatus($id)
    {
        $query = "
            SELECT 
                status 
            FROM tax_clearance_certificates 
            WHERE id = ?
        ";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare query']);
            http_response_code(500); // Internal Server Error
            return;
        }
    
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch the result
        $statusRow = $result->fetch_assoc();
    
        // Check if data is returned
        if (!$statusRow) {
            echo json_encode(['status' => 'error', 'message' => 'TCC not found']);
            http_response_code(404); // Not Found
            return;
        }
    
        // Return the status
        echo json_encode([
            'status' => 'success',
            'data' => $statusRow
        ]);
        http_response_code(200); // OK
    }

    
    public function updateTCCStatus($data)
    {
        // Validate required fields
        if (!isset($data['id'], $data['status'], $data['remark'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: id or status or remark']);
            http_response_code(400); // Bad Request
            return;
        }

        $id = $data['id'];
        $status = $data['status']; // 'approved' or 'rejected'
        $remark = $data['remark'];

        // Check if the TCC exists
        $query = "SELECT id, current_stage, status FROM tax_clearance_certificates WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'TCC not found']);
            http_response_code(404); // Not Found
            $stmt->close();
            return;
        }

        $tcc = $result->fetch_assoc();

        // Validate the status value
        if (!in_array($status, ['approved', 'rejected'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status value. Must be "approved" or "rejected"']);
            http_response_code(400); // Bad Request
            $stmt->close();
            return;
        }

        // Update the status
        $update_query = "UPDATE tax_clearance_certificates SET status = ?, remarks = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('ssi', $status, $remark, $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'TCC status updated successfully']);
            http_response_code(200); // OK
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update TCC status. Error: ' . $stmt->error]);
            http_response_code(500); // Internal Server Error
        }

        $stmt->close();
    }

    public function updateCurrentStage($tcc_id, $current_stage, $next_stage, $approver_id, $remarks = null) {
        // Validate the current_stage and next_stage
        $valid_stages = [
            'first_review', 'first_reviewer_approval', 
            'second_review', 'second_reviewer_approval', 
            'director_reviewer', 'director_approval'
        ];
    
        if (!in_array($current_stage, $valid_stages) || !in_array($next_stage, $valid_stages)) {
            http_response_code(404); // Not Found
            return ["status"=>"error", "message" => "Invalid current stage or next stage"];
        }
    
        // Validate if the tcc_id exists
        $query = "SELECT id, current_stage, first_reviewer_id, reviewer_approval_id, director_approval_id FROM tax_clearance_certificates WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tcc_id);
        $stmt->execute();
        $stmt->store_result();
    
        if ($stmt->num_rows == 0) {
            http_response_code(404); // Not Found
            return ["status"=>"error", "message" => "TCC not found"];
        }
    
        // Fetch the current stage and the approver IDs of the previous stage
        $stmt->bind_result($id, $current_stage_db, $first_reviewer_id, $reviewer_approval_id, $director_approval_id);
        $stmt->fetch();
    
        // Prepare the update query for the next stage
        $update_query = "UPDATE tax_clearance_certificates 
                         SET current_stage = ?, updated_at = NOW()";
    
        // Dynamically add the required fields (approver ID and remarks) to the query
        $params = [$next_stage]; // Initialize parameters array with next_stage
        $types = 's'; // Initialize the type string for 's' (string) for current_stage
    
        // Update for each stage
        if ($next_stage == 'second_review' || $next_stage == 'second_reviewer_approval') {
            $update_query .= ", first_reviewer_id = ?";
            $params[] = $approver_id;
            $types .= 'i'; // Add 'i' for integer (approver_id)
        } elseif ($next_stage == 'director_reviewer') {
            $update_query .= ", reviewer_approval_id = ?";
            $params[] = $approver_id;
            $types .= 'i'; // Add 'i' for integer (approver_id)
        } elseif ($next_stage == 'director_approval') {
            $update_query .= ", director_approval_id = ?, status = ?";  // Adding status update to 'approved'
            $params[] = $approver_id;
            $params[] = 'approved';  // Set status to 'approved'
            $types .= 'is';  // 'i' for approver_id (integer), 's' for status (string)
        }
    
        // Add remarks if provided
        if ($remarks !== null) {
            $update_query .= ", remarks = ?";
            $params[] = $remarks;
            $types .= 's'; // Add 's' for string (remarks)
        }
    
        // Add the WHERE clause
        $update_query .= " WHERE id = ?";
        $params[] = $tcc_id; // Add tcc_id for WHERE clause
        $types .= 'i'; // Add 'i' for integer (tcc_id)
    
        // Prepare the statement for binding parameters
        $stmt_update = $this->conn->prepare($update_query);
    
        // Bind the parameters dynamically based on the types string
        $stmt_update->bind_param($types, ...$params);
    
        // Execute the update
        if ($stmt_update->execute()) {
            return ["status" => "success", "message" => "TCC moved to next stage successfully"];
        } else {
            http_response_code(404); // Not Found
            return ["status" => "error", "message" => "Error moving TCC to next stage"];
        }
    }
    
    
    
    
    
    
    
    

    


    






    
    
}

    

    
    
    

