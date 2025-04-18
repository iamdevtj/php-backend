<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class DirectAssessmentController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function registerEmployeeDirectAssessment($data) {
        // Validate required fields for direct assessment details
        if (!isset($data['tax_number'], $data['basic_salary'], $data['housing'], $data['transport'], 
                  $data['utility'], $data['medical'], $data['entertainment'], $data['leaves'], $data['date_employed'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: tax_number, basic_salary, housing, transport, utility, medical, entertainment, leaves, date_employed']);
            http_response_code(400); // Bad request
            return;
        }
    
        // Validate if the tax_number exists in the taxpayer table
        $query = "SELECT id FROM taxpayer WHERE tax_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $data['tax_number']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tax number not found']);
            http_response_code(404); // Not found
            return;
        }
        $stmt->close();
    
        // Calculate annual gross income
        $annual_gross_income = $data['basic_salary'] + $data['housing'] + $data['transport'] + $data['utility'] + $data['medical'] + $data['entertainment'];
    
        // Calculate monthly tax payable based on the provided annual gross income
        $monthly_tax_payable = $this->calculateMonthlyTaxPayable($annual_gross_income);
    
        // Insert direct assessment data into 'direct_assessment' table
        $query = "INSERT INTO direct_assessment (tax_number, basic_salary, date_employed, housing, transport, utility, medical, entertainment, leaves, annual_gross_income, new_gross, monthly_tax_payable, created_date) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'sdsddddddddd',
            $data['tax_number'],
            $data['basic_salary'],
            $data['date_employed'],
            $data['housing'],
            $data['transport'],
            $data['utility'],
            $data['medical'],
            $data['entertainment'],
            $data['leaves'],
            $annual_gross_income,
            $annual_gross_income,  // New gross income (before deductions)
            $monthly_tax_payable
        );
    
        // Execute the query and check if the operation was successful
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Error registering direct assessment: ' . $stmt->error]);
            http_response_code(500); // Internal server error
            return;
        }
    
        // Return success response
        echo json_encode(['status' => 'success', 'message' => 'Employee direct assessment registered successfully']);
        $stmt->close();
    }

    private function calculateMonthlyTaxPayable($annual_gross_income) {
        // Step 1: Calculate annual gross income
        $annual_gross_income = $monthly_income * 12;

        // Step 2: Calculate consolidated relief allowance
        $consolidated_relief_allowance = 200000 + (0.2 * $annual_gross_income);

        // Step 3: Calculate taxable income
        $taxable_income = $annual_gross_income - $consolidated_relief_allowance;

        // Step 4: Check for tax exemption
        if ($monthly_income <= 30000) {
            // If monthly income is less than or equal to NGN 30,000, no tax
            return 0;
        }

        // Step 5: Calculate tax liability based on the progressive tax rates
        $tax_liability = 0;

        // Tax brackets
        $tax_brackets = [
            [300000, 0.07],  // 7% for the first 300,000
            [300000, 0.11],  // 11% for the next 300,000
            [500000, 0.15],  // 15% for the next 500,000
            [500000, 0.19],  // 19% for the next 500,000
            [1600000, 0.21], // 21% for the next 1,600,000
            [PHP_INT_MAX, 0.24] // 24% for anything above 3,200,000
        ];

        $remaining_income = $taxable_income;

        foreach ($tax_brackets as $bracket) {
            $bracket_limit = $bracket[0];
            $bracket_rate = $bracket[1];

            if ($remaining_income <= 0) break;

            $taxable_amount = min($remaining_income, $bracket_limit);
            $tax_liability += $taxable_amount * $bracket_rate;
            $remaining_income -= $taxable_amount;
        }
        $tax_liability = $tax_liability / 12;
        return $tax_liability;
    }

    public function getAllDirectAssessments($filters) {
        // Base query to get direct assessments
        $query = "SELECT * FROM direct_assessment WHERE 1=1"; // Start with a basic query
        $params = [];
        $types = ''; // Initialize the types string for prepared statement

        // Apply filters if provided
        if (isset($filters['tax_number'])) {
            $query .= " AND tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= 's'; // string
        }

        if (isset($filters['date_employed_start']) && isset($filters['date_employed_end'])) {
            $query .= " AND date_employed BETWEEN ? AND ?";
            $params[] = $filters['date_employed_start'];
            $params[] = $filters['date_employed_end'];
            $types .= 'ss'; // two strings (start and end date)
        }

        if (isset($filters['basic_salary_min']) && isset($filters['basic_salary_max'])) {
            $query .= " AND basic_salary BETWEEN ? AND ?";
            $params[] = $filters['basic_salary_min'];
            $params[] = $filters['basic_salary_max'];
            $types .= 'dd'; // two decimals for salary range
        }

        if (isset($filters['monthly_tax_min']) && isset($filters['monthly_tax_max'])) {
            $query .= " AND monthly_tax_payable BETWEEN ? AND ?";
            $params[] = $filters['monthly_tax_min'];
            $params[] = $filters['monthly_tax_max'];
            $types .= 'dd'; // two decimals for tax range
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
        $directAssessments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch total count for pagination
        $countQuery = "SELECT COUNT(*) AS total FROM direct_assessment WHERE 1=1";
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $stmtCount->close();

        $totalPages = ceil($totalRecords / (isset($filters['limit']) ? $filters['limit'] : 10)); // Default to 10 if no limit is specified

        // Return JSON response
        return json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => isset($filters['offset']) ? ($filters['offset'] / (isset($filters['limit']) ? $filters['limit'] : 10)) + 1 : 1,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'direct_assessments' => $directAssessments
            ]
        ]);
    }

    public function createDirectAssessmentInvoice($data) {
        // Validate required fields
        if (!isset($data['tax_number'], $data['invoice_number'], $data['direct_assessment_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: tax_number, invoice_number, or direct_assessment_id']);
            http_response_code(400); // Bad request
            return;
        }
    
        // Check if the direct assessment exists
        $query = "SELECT id FROM direct_assessment WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $data['direct_assessment_id']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Direct assessment not found']);
            http_response_code(404); // Not found
            return;
        }
        $stmt->close();
    
        // Check if the invoice number already exists in the direct_assessment_invoices table
        $query = "SELECT id FROM direct_assessment_invoices WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $data['invoice_number']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invoice number already exists in direct_assessment_invoices']);
            http_response_code(409); // Conflict
            return;
        }
        $stmt->close();
    
        // // Check if the invoice number already exists in the invoices table
        // $query = "SELECT id FROM invoices WHERE invoice_number = ?";
        // $stmt = $this->conn->prepare($query);
        // $stmt->bind_param('s', $data['invoice_number']);
        // $stmt->execute();
        // $result = $stmt->get_result();
    
        // if ($result->num_rows > 0) {
        //     echo json_encode(['status' => 'error', 'message' => 'Invoice number already exists in invoices']);
        //     http_response_code(409); // Conflict
        //     return;
        // }
        // $stmt->close();
    
        // Insert the direct assessment invoice into the 'direct_assessment_invoices' table
        $query = "INSERT INTO direct_assessment_invoices (tax_number, invoice_number, direct_assessment_id) 
                  VALUES (?, ?, ?)";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssi', $data['tax_number'], $data['invoice_number'], $data['direct_assessment_id']);
    
        // Execute the query and check if the operation was successful
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Error generating direct assessment invoice: ' . $stmt->error]);
            http_response_code(500); // Internal server error
            return;
        }
    
        // Return success response
        echo json_encode(['status' => 'success', 'message' => 'Direct assessment invoice generated successfully']);
        $stmt->close();
    }

    public function getAllDirectAssessmentInvoices($filters) {
        // Validate and set default limit and offset
        $limit = isset($filters['limit']) && is_int($filters['limit']) && $filters['limit'] >= 0 ? (int)$filters['limit'] : 10;  // Default limit to 10
        $offset = isset($filters['offset']) && is_int($filters['offset']) && $filters['offset'] >= 0 ? (int)$filters['offset'] : 0;  // Default offset to 0
    
        // Base query for direct assessment invoices with joins to taxpayer and invoices table
        $query = "
            SELECT dai.id, dai.tax_number, dai.invoice_number, dai.generated_date, i.payment_status, dai.direct_assessment_id, 
                   tp.first_name AS taxpayer_first_name, tp.surname AS taxpayer_last_name, tp.email AS taxpayer_email, tp.phone AS taxpayer_phone,
                   i.invoice_type AS invoice_type, i.amount_paid AS amount_paid, i.due_date AS invoice_due_date, i.payment_status AS invoice_payment_status
            FROM direct_assessment_invoices dai
            LEFT JOIN taxpayer tp ON dai.tax_number = tp.tax_number
            LEFT JOIN invoices i ON dai.invoice_number = i.invoice_number
            WHERE 1=1"; // Default condition for applying filters dynamically
    
        $params = [];
        $types = ''; // Initialize the types string for prepared statement
    
        // Apply filters if provided
        if (!empty($filters['tax_number'])) {
            $query .= " AND dai.tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= 's'; // string
        }
    
        if (!empty($filters['invoice_number'])) {
            $query .= " AND dai.invoice_number = ?";
            $params[] = $filters['invoice_number'];
            $types .= 's'; // string
        }
    
        if (!empty($filters['status'])) {
            $query .= " AND i.payment_status = ?";
            $params[] = $filters['status'];
            $types .= 's'; // string
        }
    
        if (!empty($filters['generated_date_start']) && !empty($filters['generated_date_end'])) {
            $query .= " AND dai.generated_date BETWEEN ? AND ?";
            $params[] = $filters['generated_date_start'];
            $params[] = $filters['generated_date_end'];
            $types .= 'ss'; // two strings (start and end date)
        }
    
        // Sorting & Pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii'; // integer for limit and offset
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
        }
    
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $directAssessmentInvoices = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Base count query for total records
        $count_query = "
            SELECT COUNT(*) as total 
            FROM direct_assessment_invoices dai
            LEFT JOIN taxpayer tp ON dai.tax_number = tp.tax_number
            LEFT JOIN invoices i ON dai.invoice_number = i.invoice_number
            WHERE 1=1"; // Default condition for applying filters dynamically
    
        // Apply filters for the count query
        $count_params = [];
        $count_types = ''; // Initialize the types string for the count query
    
        if (!empty($filters['tax_number'])) {
            $count_query .= " AND dai.tax_number = ?";
            $count_params[] = $filters['tax_number'];
            $count_types .= 's'; // string
        }
    
        if (!empty($filters['invoice_number'])) {
            $count_query .= " AND dai.invoice_number = ?";
            $count_params[] = $filters['invoice_number'];
            $count_types .= 's'; // string
        }
    
        if (!empty($filters['status'])) {
            $count_query .= " AND i.payment_status = ?";
            $count_params[] = $filters['status'];
            $count_types .= 's'; // string
        }
    
        if (!empty($filters['generated_date_start']) && !empty($filters['generated_date_end'])) {
            $count_query .= " AND dai.generated_date BETWEEN ? AND ?";
            $count_params[] = $filters['generated_date_start'];
            $count_params[] = $filters['generated_date_end'];
            $count_types .= 'ss'; // two strings (start and end date)
        }
    
        // Prepare the count query
        $stmt_count = $this->conn->prepare($count_query);
        if (!$stmt_count) {
            return json_encode(['status' => 'error', 'message' => 'Failed to prepare count statement.']);
        }
    
        // Bind parameters for count query if any
        if (!empty($count_params)) {
            $stmt_count->bind_param($count_types, ...$count_params);
        }
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $totalRecords = $count_result->fetch_assoc()['total'];
        $stmt_count->close();
    
        // Pagination calculations
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 1;
    
        // Return the result in JSON format
        return json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => isset($filters['page']) ? (int)$filters['page'] : 1,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'invoices' => $directAssessmentInvoices
            ]
        ]);
    }

    
    
    
    
    
    
    
}