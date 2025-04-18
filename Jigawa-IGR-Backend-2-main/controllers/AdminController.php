<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication
require_once 'controllers/EmailController.php';


class AdminController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getTotalAmountPaid($filters) {
        // Base query
        $query = "
            SELECT 
                SUM(pc.amount_paid) AS total_amount_paid
            FROM 
                payment_collection pc
            WHERE 1=1
        ";
        $params = [];
        $types = '';
    
        // Add date range filter
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query .= " AND MONTH(pc.date_payment_created) = ? AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['month'];
            $params[] = $filters['year'];
            $types .= 'ii';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
    
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_amount_paid" => $data['total_amount_paid'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }

    public function getTotalAmountPaidYearly($filters) {
        // Base query
        $query = "
            SELECT 
                SUM(pc.amount_paid) AS total_amount_paid
            FROM 
                payment_collection pc
            WHERE 1=1
        ";
        $params = [];
        $types = '';
    
        // Add year filter
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= 'i';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
    
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_amount_paid" => $data['total_amount_paid'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }

    public function getTotalMonthlyInvoices($year = null, $month = null) {
        // Base SQL query to get the total amount paid and count of invoices for each month
        $query = "SELECT 
                    YEAR(date_created) AS year,
                    MONTH(date_created) AS month,
                    SUM(amount_paid) AS total_amount,
                    COUNT(id) AS invoice_count
                  FROM invoices";
        
        // Filter by year and month if provided
        if ($year) {
            $query .= " WHERE YEAR(date_created) = ?";
        }
        if ($month) {
            // Ensure month is between 1 and 12
            $query .= $year ? " AND MONTH(date_created) = ?" : " WHERE MONTH(date_created) = ?";
        }
    
        // Group by year and month
        $query .= " GROUP BY YEAR(date_created), MONTH(date_created)
                    ORDER BY YEAR(date_created) DESC, MONTH(date_created) DESC";
    
        $stmt = $this->conn->prepare($query);
        
        // Bind the parameters if year or month filters are applied
        if ($year && $month) {
            $stmt->bind_param('ii', $year, $month);
        } elseif ($year) {
            $stmt->bind_param('i', $year);
        } elseif ($month) {
            $stmt->bind_param('i', $month);
        }
        
        $stmt->execute();
    
        // Get the results
        $result = $stmt->get_result();
        $monthly_totals = [];
        
        while ($row = $result->fetch_assoc()) {
            // Format the month and year as "YYYY-MM"
            $month_year = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            
            // Store the total amount and invoice count for the given month
            $monthly_totals[] = [
                'month' => $month_year,
                'total_amount' => $row['total_amount'] ?? 0, // Default to 0 if no invoices
                'invoice_count' => $row['invoice_count'] ?? 0 // Default to 0 if no invoices
            ];
        }
    
        // If no results found, return an empty array (no invoices in the given filters)
        if (empty($monthly_totals)) {
            $monthly_totals[] = [
                'month' => 'No data',
                'total_amount' => 0,
                'invoice_count' => 0
            ];
        }
    
        return $monthly_totals;
    }

    public function getAverageDailyRevenue($start_date = null, $end_date = null) {
        // If no start_date or end_date is provided, use the current month
        if (!$start_date || !$end_date) {
            // Get the first day of the current month and the current date
            $start_date = date('Y-m-01');  // First day of the current month
            $end_date = date('Y-m-d');      // Current date
        }
    
        // Ensure start_date is before end_date
        if (strtotime($start_date) > strtotime($end_date)) {
            // Swap the dates if the start_date is later than end_date
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }
    
        // Base SQL query to get total revenue and count of days in the period
        $query = "SELECT 
                    SUM(amount_paid) AS total_amount,
                    DATEDIFF(?, ?) AS total_days
                  FROM invoices";
        
        // Add filters for start_date and end_date if provided
        $query .= " WHERE payment_status = 'paid' AND date_created BETWEEN ? AND ?";
    
        $stmt = $this->conn->prepare($query);
    
        // Bind parameters for start_date and end_date
        $stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
    
        $stmt->execute();
    
        // Get the result
        $result = $stmt->get_result()->fetch_assoc();
        
        // Calculate average daily revenue
        $total_amount = $result['total_amount'] ?? 0;
        $result['total_days'] = abs($result['total_days']);
        $total_days = $result['total_days'] ?? 1;  // Default to 1 to avoid division by zero
    
        $average_daily_revenue = $total_amount / $total_days;
    
        // Prepare the response
        $response = [
            'total_amount' => $total_amount,
            'total_days' => $total_days,
            'average_daily_revenue' => number_format($average_daily_revenue, 2)
        ];
    
        return $response;
    }
    
    
    
    
    
        
    
    
    
    
    public function getExpectedMonthlyRevenue($filters) {
        // Base query
        $query = "
            SELECT 
                inv.revenue_head
            FROM 
                invoices inv
            WHERE 1=1
        ";
        $params = [];
        $types = '';
    
        // Add date range filter (month and year)
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query .= " AND MONTH(inv.date_created) = ? AND YEAR(inv.date_created) = ?";
            $params[] = $filters['month'];
            $params[] = $filters['year'];
            $types .= 'ii';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $totalInvoicedAmount = 0;
    
        // Process each invoice's revenue head
        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
            foreach ($revenueHeads as $revenueHead) {
                $revenueHead['amount'] = (float) $revenueHead['amount'];
                $totalInvoicedAmount += $revenueHead['amount']; // Add the amount from each revenue head
            }
        }
    
        $stmt->close();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "expected_monthly_revenue" => $totalInvoicedAmount
        ]);
    }

    public function getAccruedMonthlyRevenue($filters) {
        // Base query
        $query = "
            SELECT 
                inv.revenue_head
            FROM 
                invoices inv
            WHERE 
                inv.payment_status = 'unpaid'
        ";
        $params = [];
        $types = '';
    
        // Add date range filter (month and year)
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query .= " AND MONTH(inv.date_created) = ? AND YEAR(inv.date_created) = ?";
            $params[] = $filters['month'];
            $params[] = $filters['year'];
            $types .= 'ii';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $totalInvoicedAmount = 0;
    
        // Process each invoice's revenue head
        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
            foreach ($revenueHeads as $revenueHead) {
                $revenueHead['amount'] = (float) $revenueHead['amount'];
                $totalInvoicedAmount += $revenueHead['amount']; // Add the amount from each revenue head
            }
        }
    
        $stmt->close();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "unpaid_monthly_revenue" => $totalInvoicedAmount
        ]);
    }

    public function getTotalSpecialUsers() {
        // Base query
        $query = "SELECT COUNT(*) AS total_special_users FROM special_users_";
    
        // Execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_special_users" => $data['total_special_users'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }

    public function getTotalEmployees() {
        // Base query
        $query = "SELECT COUNT(*) AS total_employees FROM special_user_employees";
    
        // Execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_employees" => $data['total_employees'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }

    public function getTotalAnnualEstimate($filters) {
        // Base query
        $query = "SELECT SUM(annual_gross_income) AS total_annual_estimate FROM employee_salary_and_benefits WHERE 1=1";
    
        $params = [];
        $types = '';
    
        // Add year filter
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(created_date) = ?";
            $params[] = $filters['year'];
            $types .= 'i';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
    
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_annual_estimate" => $data['total_annual_estimate'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }

    public function getTotalAnnualRemittance($filters) {
        // Base query
        $query = "SELECT SUM(monthly_tax_payable) AS total_monthly_tax_payable FROM employee_salary_and_benefits WHERE 1=1";
    
        $params = [];
        $types = '';
    
        // Add year filter based on created_date
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(created_date) = ?";
            $params[] = $filters['year'];
            $types .= 'i';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
    
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Calculate the total annual remittance
        $totalAnnualRemittance = ($data['total_monthly_tax_payable'] ?? 0) * 12;
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_annual_remittance" => $totalAnnualRemittance
        ]);
    
        $stmt->close();
    }

    public function getMonthlyEstimate($filters) {
        // Base query
        $query = "SELECT SUM(annual_gross_income) AS total_monthly_estimate FROM employee_salary_and_benefits WHERE 1=1";
    
        $params = [];
        $types = '';
    
        // Add month and year filter
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query .= " AND MONTH(created_date) = ? AND YEAR(created_date) = ?";
            $params[] = $filters['month'];
            $params[] = $filters['year'];
            $types .= 'ii';
        }
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
    
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        // Return the result
        echo json_encode([
            "status" => "success",
            "total_monthly_estimate" => $data['total_monthly_estimate'] ?? 0 // Default to 0 if no records found
        ]);
    
        $stmt->close();
    }
    
    public function getTaxSummary($filters) {
        $response = [
            'tcc_issued' => 0,
            'tin_issued' => 0,
            'paye_remitted' => 0,
            'unpaid_paye_taxes' => 0,
            'unpaid_amount_paye_taxes' => 0
        ];
    
        // ----------------------
        // 🚀 Fetch TCCs Issued
        // ----------------------
        $tccParams = [];
        $tccTypes = "";
        $tccCondition = "";
    
        if (!empty($filters['year'])) {
            $tccCondition .= " AND YEAR(issued_date) = ?";
            $tccParams[] = $filters['year'];
            $tccTypes .= "i";
        }
        if (!empty($filters['month'])) {
            $tccCondition .= " AND MONTH(issued_date) = ?";
            $tccParams[] = $filters['month'];
            $tccTypes .= "i";
        }
    
        $query = "SELECT COUNT(*) FROM tax_clearance_certificates WHERE issued_date IS NOT NULL $tccCondition";
        $stmt = $this->conn->prepare($query);
        if ($tccTypes) $stmt->bind_param($tccTypes, ...$tccParams);
        $stmt->execute();
        $stmt->bind_result($response['tcc_issued']);
        $stmt->fetch();
        $stmt->close();
    
        // ----------------------
        // 🚀 Fetch TINs Issued
        // ----------------------
        $tinParams = [];
        $tinTypes = "";
        $tinCondition = "";
    
        if (!empty($filters['year'])) {
            $tinCondition .= " AND YEAR(created_at) = ?";
            $tinParams[] = $filters['year'];
            $tinTypes .= "i";
        }
        if (!empty($filters['month'])) {
            $tinCondition .= " AND MONTH(created_at) = ?";
            $tinParams[] = $filters['month'];
            $tinTypes .= "i";
        }
    
        $query = "SELECT COUNT(*) FROM tin_generator WHERE 1=1 $tinCondition";
        $stmt = $this->conn->prepare($query);
        if ($tinTypes) $stmt->bind_param($tinTypes, ...$tinParams);
        $stmt->execute();
        $stmt->bind_result($response['tin_issued']);
        $stmt->fetch();
        $stmt->close();
    
        // ----------------------
        // 🚀 Fetch PAYE Remitted
        // ----------------------
        $payeParams = [];
        $payeTypes = "";
        $payeCondition = "";
    
        if (!empty($filters['year'])) {
            $payeCondition .= " AND YEAR(date_created) = ?";
            $payeParams[] = $filters['year'];
            $payeTypes .= "i";
        }
        if (!empty($filters['month'])) {
            $payeCondition .= " AND MONTH(date_created) = ?";
            $payeParams[] = $filters['month'];
            $payeTypes .= "i";
        }
    
        $query = "SELECT SUM(amount_paid) FROM invoices WHERE invoice_type = 'paye' AND payment_status ='paid' $payeCondition";
        $stmt = $this->conn->prepare($query);
        if ($payeTypes) $stmt->bind_param($payeTypes, ...$payeParams);
        $stmt->execute();
        $stmt->bind_result($response['paye_remitted']);
        $stmt->fetch();
        $stmt->close();
    
        // -------------------------------
        // 🚀 Fetch Unpaid PAYE Taxes (Amount)
        // -------------------------------
        $query = "SELECT SUM(amount_paid) FROM invoices WHERE invoice_type = 'paye' AND payment_status IN ('unpaid', 'partially paid') $payeCondition";
        $stmt = $this->conn->prepare($query);
        if ($payeTypes) $stmt->bind_param($payeTypes, ...$payeParams);
        $stmt->execute();
        $stmt->bind_result($response['unpaid_amount_paye_taxes']);
        $stmt->fetch();
        $stmt->close();
    
        // -------------------------------
        // 🚀 Fetch Unpaid PAYE Taxes (Count)
        // -------------------------------
        $query = "SELECT COUNT(*) FROM invoices WHERE invoice_type = 'paye' AND payment_status IN ('unpaid', 'partially paid') $payeCondition";
        $stmt = $this->conn->prepare($query);
        if ($payeTypes) $stmt->bind_param($payeTypes, ...$payeParams);
        $stmt->execute();
        $stmt->bind_result($response['unpaid_paye_taxes']);
        $stmt->fetch();
        $stmt->close();
    
        // ✅ Return response
        echo json_encode([
            'status' => 'success',
            'data' => $response
        ]);
    }

    public function getRevenueGrowth($filters) {
        $response = [
            'current_revenue' => 0,
            'previous_revenue' => 0,
            'growth_percentage' => 0,
            'trend' => 'neutral'
        ];
    
        $params = [];
        $types = "";
        $conditions = "";
        
        // Apply filters for the current period
        if (!empty($filters['year'])) {
            $conditions .= " AND YEAR(date_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
        if (!empty($filters['month'])) {
            $conditions .= " AND MONTH(date_created) = ?";
            $params[] = $filters['month'];
            $types .= "i";
        }
    
        // Fetch revenue for the current period
        $query = "SELECT SUM(amount_paid) FROM invoices WHERE payment_status = 'paid' $conditions";
        $stmt = $this->conn->prepare($query);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($response['current_revenue']);
        $stmt->fetch();
        $stmt->close();
    
        // Calculate previous period (month or year)
        $prevParams = [];
        $prevTypes = "";
        $prevConditions = "";
    
        if (!empty($filters['year']) && !empty($filters['month'])) {
            // Get last month's data
            $prevConditions .= " AND YEAR(date_created) = ? AND MONTH(date_created) = ?";
            $prevParams[] = ($filters['month'] == 1) ? $filters['year'] - 1 : $filters['year'];
            $prevParams[] = ($filters['month'] == 1) ? 12 : $filters['month'] - 1;
            $prevTypes .= "ii";
        } elseif (!empty($filters['year'])) {
            // Get last year's data
            $prevConditions .= " AND YEAR(date_created) = ?";
            $prevParams[] = $filters['year'] - 1;
            $prevTypes .= "i";
        }
    
        // Fetch revenue for the previous period
        $query = "SELECT SUM(amount_paid) FROM invoices WHERE payment_status = 'paid' $prevConditions";
        $stmt = $this->conn->prepare($query);
        if ($prevTypes) $stmt->bind_param($prevTypes, ...$prevParams);
        $stmt->execute();
        $stmt->bind_result($response['previous_revenue']);
        $stmt->fetch();
        $stmt->close();
    
        // Calculate revenue growth %
        if ($response['previous_revenue'] > 0) {
            $response['growth_percentage'] = round((($response['current_revenue'] - $response['previous_revenue']) / $response['previous_revenue']) * 100, 2);
        }
    
        // Determine trend
        if ($response['growth_percentage'] > 0) {
            $response['trend'] = 'increase';
        } elseif ($response['growth_percentage'] < 0) {
            $response['trend'] = 'decrease';
        }
    
        // ✅ Return JSON response
        echo json_encode([
            'status' => 'success',
            'data' => $response
        ]);
    }

    public function getRevenueGrowth2($filters) {
        $response = [
            'yearly_growth' => [],
            'monthly_growth' => []
        ];
    
        // --------------------------------
        // 🚀 Yearly Revenue Growth
        // --------------------------------
        $query = "
            SELECT YEAR(date_payment_created) AS year, 
                   SUM(amount_paid) AS total_revenue 
            FROM payment_collection 
            GROUP BY YEAR(date_payment_created) 
            ORDER BY YEAR(date_payment_created) ASC
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $previousRevenue = null;
        while ($row = $result->fetch_assoc()) {
            $year = $row['year'];
            $currentRevenue = (float) $row['total_revenue'];
            
            // Calculate revenue growth percentage
            $growth = ($previousRevenue !== null && $previousRevenue > 0) 
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2) 
                : null;
            
            $response['yearly_growth'][] = [
                'year' => $year,
                'total_revenue' => $currentRevenue,
                'growth_percentage' => $growth
            ];
    
            $previousRevenue = $currentRevenue;
        }
        $stmt->close();
    
        // --------------------------------
        // 🚀 Monthly Revenue Growth (Optional: Filter by Year)
        // --------------------------------
        $monthlyParams = [];
        $monthlyTypes = "";
        $monthlyCondition = "";
    
        if (!empty($filters['year'])) {
            $monthlyCondition .= " WHERE YEAR(date_payment_created) = ?";
            $monthlyParams[] = $filters['year'];
            $monthlyTypes .= "i";
        }
    
        $query = "
            SELECT DATE_FORMAT(date_payment_created, '%Y-%m') AS year_months, 
                   SUM(amount_paid) AS total_revenue 
            FROM payment_collection 
            $monthlyCondition 
            GROUP BY DATE_FORMAT(date_payment_created, '%Y-%m') 
            ORDER BY DATE_FORMAT(date_payment_created, '%Y-%m') ASC
        ";
    
        $stmt = $this->conn->prepare($query);
        if ($monthlyTypes) {
            $stmt->bind_param($monthlyTypes, ...$monthlyParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $previousRevenue = null;
        while ($row = $result->fetch_assoc()) {
            $yearMonth = $row['year_months'];
            $currentRevenue = (float) $row['total_revenue'];
    
            // Calculate revenue growth percentage
            $growth = ($previousRevenue !== null && $previousRevenue > 0) 
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2) 
                : null;
    
            $response['monthly_growth'][] = [
                'year_months' => $yearMonth,
                'total_revenue' => $currentRevenue,
                'growth_percentage' => $growth
            ];
    
            $previousRevenue = $currentRevenue;
        }
        $stmt->close();
    
        // ✅ Return response
        echo json_encode([
            'status' => 'success',
            'data' => $response
        ]);
    }
    
    public function updateAdminProfile($data) {
        if (empty($data['admin_id'])) {
            echo json_encode(["status" => "error", "message" => "Admin ID is required"]);
            return;
        }
    
        $adminId = $data['admin_id'];
        $unsetFields = ['admin_id','id', 'password', 'verification_status', 'date_updated', 'date_created'];
        foreach ($unsetFields as $field) {
            unset($data[$field]);
        }
    
        if (empty($data)) {
            echo json_encode(["status" => "error", "message" => "No fields to update"]);
            return;
        }
    
        $fields = [];
        $params = [];
        $types = "";
    
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
            $types .= is_numeric($value) ? "i" : "s";
        }
    
        $params[] = $adminId;
        $types .= "i";
    
        $query = "UPDATE administrative_users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Admin profile updated successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update Admin profile"]);
        }
    
        $stmt->close();
    }

    public function adminForgotPassword($data) {
        // Validate input
        if (empty($data['email'])) {
            echo json_encode(['status' => 'error', 'message' => 'Email is required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        $email = $data['email'];
        $tableType = 'administrative_users';
    
        // Check if email exists in the administrative_users table
        $queryAdmin = "SELECT id, fullname FROM administrative_users WHERE email = ?";
        $stmt = $this->conn->prepare($queryAdmin);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // If email not found, return an error
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email not found']);
            http_response_code(404); // Not Found
            return;
        }
    
        $adminDetails = $result->fetch_assoc();
        $adminId = $adminDetails['id'];
        $fullname = $adminDetails['fullname'];
    
        // Generate a unique reset token
        $resetToken = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
    
        // Insert the reset token into the password_resets table
        $queryInsert = "
            INSERT INTO password_resets (tax_number, reset_token, expires_at, table_type)
            VALUES (?, ?, ?, ?)
        ";
        $stmt = $this->conn->prepare($queryInsert);
        $stmt->bind_param('ssss', $adminId, $resetToken, $expiresAt, $tableType);
    
        if ($stmt->execute()) {
            // Send the reset token via email
            global $emailController;
            $emailController->adminResetPasswordEmail($email, $fullname, $resetToken);
            echo json_encode([
                'status' => 'success',
                'message' => 'Password reset link has been sent',
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to generate reset token']);
            http_response_code(500); // Internal Server Error
        }
        $stmt->close();
    }

    public function resetAdminPassword($data) {
        // Validate input
        if (empty($data['reset_token']) || empty($data['new_password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Reset token and new password are required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        $resetToken = $data['reset_token'];
        $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
    
        // Check if the reset token exists and is valid for administrative users
        $query = "SELECT tax_number FROM password_resets WHERE reset_token = ? AND expires_at > NOW() AND table_type = 'administrative_users' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $resetToken);
        $stmt->execute();
        $result = $stmt->get_result();
        $resetData = $result->fetch_assoc();
        $stmt->close();
    
        if (!$resetData) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired reset token']);
            http_response_code(400); // Bad Request
            return;
        }
    
        $adminId = $resetData['tax_number']; // Assuming tax_number is the admin ID
    
        // Update the password in the administrative_users table
        $queryUpdate = "UPDATE administrative_users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($queryUpdate);
        $stmt->bind_param('si', $newPasswordHash, $adminId);
    
        if ($stmt->execute()) {
            // Delete the reset token after successful reset
            $queryDeleteToken = "DELETE FROM password_resets WHERE reset_token = ?";
            $stmtDelete = $this->conn->prepare($queryDeleteToken);
            $stmtDelete->bind_param('s', $resetToken);
            $stmtDelete->execute();
            $stmtDelete->close();
    
            echo json_encode(['status' => 'success', 'message' => 'Password reset successful']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password']);
            http_response_code(500); // Internal Server Error
        }
        $stmt->close();
    }

    public function regenerateAdminVerificationCode($input) {
        // Validate input
        if (empty($input['fullname']) && empty($input['phone']) && empty($input['email'])) {
            return json_encode(["status" => "error", "message" => "Provide fullname, phone, or email"]);
        }
    
        // Determine the identifier (fullname, phone, or email)
        $identifier = !empty($input['fullname']) ? $input['fullname'] : (!empty($input['phone']) ? $input['phone'] : $input['email']);
    
        // Check if administrative user exists
        $query = "
            SELECT verification_status, id AS admin_id, email, fullname
            FROM administrative_users
            WHERE fullname = ? OR phone = ? OR email = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $identifier, $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return json_encode(["status" => "error", "message" => "Administrative user not found"]);
        }
    
        $adminUser = $result->fetch_assoc();
        // Check if the account is already verified
        if ($adminUser['verification_status'] === 'verified') {
            return json_encode(["status" => "error", "message" => "Account is already verified"]);
        }
    
        // Generate a new verification code
        $newVerificationCode = rand(100000, 999999);
    
        // Update the verification code in the database
        $updateQuery = "UPDATE administrative_users SET verification_code = ? WHERE id = ?";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $newVerificationCode, $adminUser['admin_id']);
        $updateStmt->execute();
    
        if ($updateStmt->affected_rows > 0) {
            global $emailController;
            $emailController->adminVerificationEmail($adminUser['email'], $adminUser['fullname'], $newVerificationCode);
            return json_encode([
                "status" => "success",
                "message" => "New verification code generated"
            ]);
        } else {
            return json_encode(["status" => "error", "message" => "Failed to regenerate verification code"]);
        }
    }
    
    
    
    
    
    
    
    
    
    
}