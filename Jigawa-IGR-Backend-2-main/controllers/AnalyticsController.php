<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class AnalyticsController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getTotalRegisteredTaxpayers() {
        // SQL query to get the total number of taxpayers and their verification statuses
        $query = "
            SELECT 
                COUNT(*) AS total_registered,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) AS total_verified,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) AS total_unverified
            FROM taxpayer_security
        ";
        
        // Execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        // Calculate percentages
        $totalRegistered = $data['total_registered'];
        $totalVerified = $data['total_verified'];
        $totalUnverified = $data['total_unverified'];
        
        $percentVerified = $totalRegistered > 0 ? ($totalVerified / $totalRegistered) * 100 : 0;
        $percentUnverified = $totalRegistered > 0 ? ($totalUnverified / $totalRegistered) * 100 : 0;
        
        // Return the result as JSON
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_registered' => $totalRegistered,
                'total_verified' => $totalVerified,
                'percent_verified' => round($percentVerified, 2), // Rounded to 2 decimal places
                'total_unverified' => $totalUnverified,
                'percent_unverified' => round($percentUnverified, 2) // Rounded to 2 decimal places
            ]
        ]);
    }

    public function getTaxpayerSegmentationByCategory() {
        // SQL query to get the count of taxpayers grouped by category
        $query = "
            SELECT 
                category, 
                COUNT(*) AS total_count
            FROM taxpayer
            GROUP BY category
        ";
        
        // Execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $categories = [];
        $totalTaxpayers = 0;
    
        // Fetch all rows and calculate the total taxpayers
        while ($row = $result->fetch_assoc()) {
            $totalTaxpayers += $row['total_count'];
            $categories[] = $row;
        }
        $stmt->close();
    
        // Calculate percentage for each category
        foreach ($categories as &$category) {
            $category['percent'] = $totalTaxpayers > 0 ? round(($category['total_count'] / $totalTaxpayers) * 100, 2) : 0;
        }
    
        // Return the result as JSON
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_taxpayers' => $totalTaxpayers,
                'categories' => $categories
            ]
        ]);
    }

    public function getComplianceRate() {
        // SQL query to fetch compliant, non-compliant, and total taxpayers
        $query = "
            SELECT
                COUNT(DISTINCT t.id) AS total_taxpayers,
                COUNT(DISTINCT CASE WHEN i.payment_status = 'paid' THEN t.id END) AS compliant_taxpayers,
                COUNT(DISTINCT CASE WHEN i.payment_status IN ('unpaid', 'partially paid') THEN t.id END) AS non_compliant_taxpayers
            FROM taxpayer t
            LEFT JOIN invoices i ON t.tax_number = i.tax_number;
        ";
    
        // Execute query
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
    
        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch compliance data']);
            http_response_code(500);
            return;
        }
    
        // Calculate percentages
        $totalTaxpayers = $result['total_taxpayers'];
        $compliantTaxpayers = $result['compliant_taxpayers'];
        $nonCompliantTaxpayers = $result['non_compliant_taxpayers'];
    
        $complianceRate = $totalTaxpayers > 0 ? ($compliantTaxpayers / $totalTaxpayers) * 100 : 0;
        $nonComplianceRate = $totalTaxpayers > 0 ? ($nonCompliantTaxpayers / $totalTaxpayers) * 100 : 0;
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_taxpayers' => $totalTaxpayers,
                'compliant_taxpayers' => $compliantTaxpayers,
                'non_compliant_taxpayers' => $nonCompliantTaxpayers,
                'compliance_rate' => round($complianceRate, 2),
                'non_compliance_rate' => round($nonComplianceRate, 2)
            ]
        ]);
    }

    public function getTopDefaulters($limit = 10) {
        // Base query to fetch invoices with outstanding amounts
        $query = "
            SELECT 
                i.invoice_number, 
                i.revenue_head, 
                i.tax_number, 
                i.amount_paid, 
                tp.first_name AS taxpayer_first_name, 
                tp.surname AS taxpayer_last_name, 
                tp.email AS taxpayer_email, 
                tp.phone AS taxpayer_phone, 
                etp.first_name AS enumerator_first_name, 
                etp.last_name AS enumerator_last_name, 
                etp.email AS enumerator_email, 
                etp.phone AS enumerator_phone 
            FROM invoices i
            LEFT JOIN taxpayer tp ON tp.tax_number = i.tax_number
            LEFT JOIN enumerator_tax_payers etp ON etp.tax_number = i.tax_number
            WHERE i.payment_status = 'unpaid' OR i.payment_status = 'partially paid'
            ORDER BY i.due_date ASC
            LIMIT ?
        ";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $defaulters = [];
    
        // Process each invoice
        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
    
            // Check if JSON decoding was successful
            if (!is_array($revenueHeads)) {
                error_log("Invalid JSON in revenue_head for invoice: " . $row['invoice_number']);
                continue; // Skip this row if JSON is invalid
            }
    
            $totalAmount = 0; // Initialize total amount
            foreach ($revenueHeads as $revenueHead) {
                // Validate the 'amount' field
                if (isset($revenueHead['amount']) && is_numeric($revenueHead['amount'])) {
                    $totalAmount += $revenueHead['amount'];
                } else {
                    error_log("Invalid or missing amount in revenue head: " . json_encode($revenueHead));
                }
            }
    
            $outstandingAmount = $totalAmount - $row['amount_paid'];
    
            // Compile defaulter data
            $defaulters[] = [
                'invoice_number' => $row['invoice_number'],
                'taxpayer_name' => $row['taxpayer_first_name'] ?? $row['enumerator_first_name'],
                'taxpayer_surname' => $row['taxpayer_last_name'] ?? $row['enumerator_last_name'],
                'taxpayer_email' => $row['taxpayer_email'] ?? $row['enumerator_email'],
                'taxpayer_phone' => $row['taxpayer_phone'] ?? $row['enumerator_phone'],
                'total_amount' => $totalAmount,
                'amount_paid' => $row['amount_paid'],
                'outstanding_amount' => $outstandingAmount
            ];
        }
        $stmt->close();
    
        // Return the response
        echo json_encode([
            'status' => 'success',
            'data' => $defaulters
        ]);
    }

    public function getTaxpayerDistributionByLocation($filters) {
        $query = "
            SELECT 
                COALESCE(t.state, etp.state) AS state, 
                COALESCE(t.lga, etp.lga) AS lga, 
                COUNT(*) AS taxpayer_count 
            FROM taxpayer t
            LEFT JOIN enumerator_tax_payers etp ON t.tax_number = etp.tax_number
            WHERE 1=1
        ";
        
        $params = [];
        $types = "";
    
        // Apply filters if provided
        if (!empty($filters['category'])) {
            $query .= " AND (t.category = ? OR etp.category = ?)";
            $params[] = $filters['category'];
            $params[] = $filters['category'];
            $types .= "ss";
        }
    
        if (!empty($filters['state'])) {
            $query .= " AND (t.state = ? OR etp.state = ?)";
            $params[] = $filters['state'];
            $params[] = $filters['state'];
            $types .= "ss";
        }
    
        if (!empty($filters['lga'])) {
            $query .= " AND (t.lga = ? OR etp.lga = ?)";
            $params[] = $filters['lga'];
            $params[] = $filters['lga'];
            $types .= "ss";
        }
    
        $query .= " GROUP BY state, lga ORDER BY taxpayer_count DESC";
    
        // Execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $distribution = [];
        while ($row = $result->fetch_assoc()) {
            $distribution[] = $row;
        }
        $stmt->close();
    
        // Return the response
        echo json_encode([
            'status' => 'success',
            'data' => $distribution
        ]);
    }

    public function getTaxpayerRegistrationTrends($filters) {
        // Base query for taxpayer and enumerator_tax_payers tables
        $query = "
            SELECT 
                DATE_FORMAT(COALESCE(t.created_time, etp.timeIn), '%Y-%m') AS registration_month,
                COUNT(COALESCE(t.id, etp.id)) AS total_registered
            FROM taxpayer t
            LEFT JOIN enumerator_tax_payers etp ON t.tax_number = etp.tax_number
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Apply date range filter
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND COALESCE(t.created_at, etp.created_at) BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= "ss";
        }
    
        // Apply category filter if provided
        if (!empty($filters['category'])) {
            $query .= " AND (t.category = ? OR etp.category = ?)";
            $params[] = $filters['category'];
            $params[] = $filters['category'];
            $types .= "ss";
        }
    
        // Group by registration month and order the data
        $query .= " GROUP BY registration_month ORDER BY registration_month ASC";
    
        // Execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch the results
        $registrationTrends = [];
        while ($row = $result->fetch_assoc()) {
            $registrationTrends[] = $row;
        }
        $stmt->close();
    
        // Return the response
        echo json_encode([
            'status' => 'success',
            'data' => $registrationTrends
        ]);
    }

    public function getRevenueBreakdownByTaxType($filters) {
        $query = "
            SELECT i.revenue_head, i.amount_paid, i.tax_number, i.date_created 
            FROM invoices i
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Apply filters
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND i.date_created BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= "ss";
        }
    
        if (!empty($filters['tax_number'])) {
            $query .= " AND i.tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Process revenue data
        $revenueData = [];
        foreach ($invoices as $invoice) {
            $revenueHeads = json_decode($invoice['revenue_head'], true);
    
            // Validate the decoded JSON
            if (!is_array($revenueHeads)) {
                $revenueHeads = [];
            }
    
            foreach ($revenueHeads as $revenueHead) {
                $revenueHeadId = $revenueHead['revenue_head_id'];
                $amount = isset($revenueHead['amount']) && is_numeric($revenueHead['amount']) ? $revenueHead['amount'] : 0;
    
                // Fetch details using the helper function
                $revenueHeadDetails = $this->getRevenueHeadDetails($revenueHeadId);
    
                if ($revenueHeadDetails) {
                    $key = $revenueHeadDetails['item_name'];
    
                    if (!isset($revenueData[$key])) {
                        $revenueData[$key] = [
                            'tax_type' => $revenueHeadDetails['item_name'],
                            'mda_name' => $revenueHeadDetails['mda_name'],
                            'total_revenue' => 0,
                        ];
                    }
    
                    // Safely aggregate the total revenue
                    $revenueData[$key]['total_revenue'] += $amount;
                }
            }
        }
    
        $revenueBreakdown = array_values($revenueData);
    
        echo json_encode([
            'status' => 'success',
            'data' => $revenueBreakdown
        ]);
    }

    public function getRateUtilizationStatistics($filters) {
        // Base query to fetch revenue data from invoices
        $query = "
            SELECT i.revenue_head, i.amount_paid, i.tax_number, i.date_created 
            FROM invoices i
            WHERE i.payment_status = 'paid'
        ";
    
        $params = [];
        $types = "";
    
        // Apply date filter
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND i.date_created BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= "ss";
        }
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Process revenue data
        $rateUsage = [];
        $totalRevenue = 0;
    
        foreach ($invoices as $invoice) {
            $revenueHeads = json_decode($invoice['revenue_head'], true);
    
            if (!is_array($revenueHeads)) {
                $revenueHeads = [];
            }
    
            foreach ($revenueHeads as $revenueHead) {
                $revenueHeadId = $revenueHead['revenue_head_id'];
                $amount = isset($revenueHead['amount']) && is_numeric($revenueHead['amount']) ? $revenueHead['amount'] : 0;
                $totalRevenue += $amount;
    
                // Fetch revenue head details
                $revenueHeadDetails = $this->getRevenueHeadDetails($revenueHeadId);
                if ($revenueHeadDetails) {
                    $key = $revenueHeadDetails['item_name'];
    
                    if (!isset($rateUsage[$key])) {
                        $rateUsage[$key] = [
                            'tax_type' => $revenueHeadDetails['item_name'],
                            'mda_name' => $revenueHeadDetails['mda_name'],
                            'total_amount' => 0,
                            'percentage' => 0,
                        ];
                    }
    
                    $rateUsage[$key]['total_amount'] += $amount;
                }
            }
        }
    
        // Calculate percentage utilization
        foreach ($rateUsage as &$data) {
            if ($totalRevenue > 0) {
                $data['percentage'] = round(($data['total_amount'] / $totalRevenue) * 100, 2);
            }
        }
    
        $rateStatistics = array_values($rateUsage);
    
        // Return JSON response
        echo json_encode([
            'status' => 'success',
            'data' => $rateStatistics
        ]);
    }
    
    public function getInvoicesGenerated($filters) {
        // Base query to fetch invoice counts by month and year
        $query = "
            SELECT 
                DATE_FORMAT(date_created, '%Y-%m') AS year_months, 
                COUNT(*) AS total_invoices
            FROM invoices
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Filter by year if provided
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(date_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Group by year_month
        $query .= " GROUP BY year_months ORDER BY year_months ASC";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'year_month' => $row['year_months'],
                'total_invoices' => (int)$row['total_invoices']
            ];
        }
    
        $stmt->close();
    
        // Return JSON response
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function getAverageBillingByCategory($filters) {
        // Base query to calculate average invoice amount by category
        $query = "
            SELECT 
                t.category, 
                COUNT(i.invoice_number) AS total_invoices,
                SUM(i.amount_paid) / COUNT(i.invoice_number) AS avg_billing_amount
            FROM invoices i
            LEFT JOIN taxpayer t ON i.tax_number = t.tax_number
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Filter by year (optional)
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(i.date_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Group by taxpayer category
        $query .= " GROUP BY t.category ORDER BY avg_billing_amount DESC";
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'category' => $row['category'],
                'total_invoices' => (int)$row['total_invoices'],
                'avg_billing_amount' => number_format((float)$row['avg_billing_amount'], 2, '.', '')
            ];
        }
    
        $stmt->close();
    
        // Return JSON response
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function getTotalUnpaidInvoicesByMonth($filters) {
        $params = [];
        $types = "";
        $yearCondition = "";
    
        // Apply year filter if provided
        if (!empty($filters['year'])) {
            $yearCondition = "AND YEAR(i.date_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Query to fetch unpaid invoices grouped by year-month
        $query = "
            SELECT i.invoice_number, i.revenue_head, i.amount_paid, i.payment_status, 
                   DATE_FORMAT(i.date_created, '%Y-%m') AS year_months
            FROM invoices i
            WHERE i.payment_status IN ('unpaid', 'partially paid')
            $yearCondition
            ORDER BY i.date_created DESC
        ";
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $monthlyData = [];
    
        while ($row = $result->fetch_assoc()) {
            $yearMonth = $row['year_months'];  // Extract year-month
    
            // Decode revenue_head JSON
            $revenueHeads = json_decode($row['revenue_head'], true);
            $totalInvoiceAmount = 0;
    
            if (is_array($revenueHeads)) {
                foreach ($revenueHeads as $revenueHead) {
                    if (isset($revenueHead['amount'])) {
                        $totalInvoiceAmount += (float)$revenueHead['amount'];
                    }
                }
            }
    
            // Calculate outstanding amount
            $amountPaid = (float)$row['amount_paid'];
            $outstandingAmount = max($totalInvoiceAmount - $amountPaid, 0);
    
            // Aggregate data by year-month
            if (!isset($monthlyData[$yearMonth])) {
                $monthlyData[$yearMonth] = [
                    'total_unpaid_invoices' => 0,
                    'total_outstanding_amount' => 0.00
                ];
            }
    
            $monthlyData[$yearMonth]['total_unpaid_invoices']++;
            $monthlyData[$yearMonth]['total_outstanding_amount'] += $outstandingAmount;
        }
    
        $stmt->close();
    
        // Format response
        echo json_encode([
            'status' => 'success',
            'data' => $monthlyData
        ]);
    }

    public function getTCCCollectionPerformanceByTaxPeriod($filters) {
        $params = [];
        $types = "";
        $yearCondition = "";
    
        // Apply year filter if provided
        if (!empty($filters['year'])) {
            $yearCondition = "AND YEAR(tcc.tax_period_start) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Query to fetch tax clearance certificates with payment data
        $query = "
            SELECT tcc.tax_period_start, tcc.tax_period_end, 
                i.invoice_number, i.revenue_head, i.amount_paid, i.payment_status
            FROM tax_clearance_certificates tcc
            LEFT JOIN invoices i 
                ON tcc.taxpayer_id = i.tax_number COLLATE utf8mb4_general_ci
            WHERE 1=1 $yearCondition
            ORDER BY tcc.tax_period_start DESC
        ";
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $taxPeriodData = [];
    
        while ($row = $result->fetch_assoc()) {
            $periodKey = $row['tax_period_start'] . ' - ' . $row['tax_period_end'];
    
            // Decode revenue_head JSON
            $revenueHeads = json_decode($row['revenue_head'], true);
            $totalAmount = 0;
    
            if (is_array($revenueHeads)) {
                foreach ($revenueHeads as $revenueHead) {
                    if (isset($revenueHead['amount'])) {
                        $totalAmount += (float)$revenueHead['amount'];
                    }
                }
            }
    
            // Calculate outstanding amount
            $amountPaid = (float)$row['amount_paid'];
            $outstandingAmount = max($totalAmount - $amountPaid, 0);
    
            // Aggregate data by tax period
            if (!isset($taxPeriodData[$periodKey])) {
                $taxPeriodData[$periodKey] = [
                    'total_collected' => 0.00,
                    'total_outstanding' => 0.00
                ];
            }
    
            $taxPeriodData[$periodKey]['total_collected'] += $amountPaid;
            $taxPeriodData[$periodKey]['total_outstanding'] += $outstandingAmount;
        }
    
        $stmt->close();
    
        // Format response
        echo json_encode([
            'status' => 'success',
            'data' => $taxPeriodData
        ]);
    }

    public function getCollectionPerformanceByQuarter($filters) {
        $params = [];
        $types = "";
        $yearCondition = "";
    
        // Apply year filter if provided
        if (!empty($filters['year'])) {
            $yearCondition = "AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Query to fetch total collected payments grouped by year & quarter
        $queryCollected = "
            SELECT 
                CONCAT(YEAR(pc.date_payment_created), '-Q', QUARTER(pc.date_payment_created)) AS tax_quarter,
                SUM(pc.amount_paid) AS total_collected
            FROM payment_collection pc
            WHERE 1=1 $yearCondition
            GROUP BY tax_quarter
        ";
    
        $stmt = $this->conn->prepare($queryCollected);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $collectionData = [];
        while ($row = $result->fetch_assoc()) {
            $collectionData[$row['tax_quarter']] = [
                'total_collected' => (float)$row['total_collected'],
                'total_outstanding' => 0.00  // Placeholder for outstanding calculations
            ];
        }
        $stmt->close();
    
        // Query to fetch total outstanding balance from unpaid invoices
        $queryOutstanding = "
            SELECT 
                CONCAT(YEAR(i.date_created), '-Q', QUARTER(i.date_created)) AS tax_quarter,
                SUM(i.amount_paid) AS total_paid
            FROM invoices i
            WHERE i.payment_status IN ('unpaid', 'partially paid') $yearCondition
            GROUP BY tax_quarter
        ";
    
        $stmt = $this->conn->prepare($queryOutstanding);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        while ($row = $result->fetch_assoc()) {
            if (!isset($collectionData[$row['tax_quarter']])) {
                $collectionData[$row['tax_quarter']] = [
                    'total_collected' => 0.00,
                    'total_outstanding' => 0.00
                ];
            }
            $collectionData[$row['tax_quarter']]['total_outstanding'] = (float)$row['total_paid'];
        }
        $stmt->close();
    
        // Return the response
        echo json_encode([
            'status' => 'success',
            'data' => $collectionData
        ]);
    }
    
    public function getTotalPaymentsByYearMonth($filters, $page, $limit) {
        // Set default pagination values
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        $params = [];
        $types = "";
        $conditions = "WHERE 1=1"; // Default condition to avoid syntax errors
    
        // Filter by year
        if (!empty($filters['year'])) {
            $conditions .= " AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Filter by tax_number
        if (!empty($filters['tax_number'])) {
            $conditions .= " AND pc.user_id = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
    
        // Query to get total payments grouped by year-month
        $query = "
            SELECT 
                DATE_FORMAT(pc.date_payment_created, '%Y-%m') AS year_months,
                SUM(pc.amount_paid) AS total_payments
            FROM payment_collection pc
            $conditions
            GROUP BY year_months
            ORDER BY year_months DESC
            LIMIT ? OFFSET ?
        ";
    
        // Append LIMIT and OFFSET
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Prepare query
        $stmt = $this->conn->prepare($query);
    
        // Bind parameters only if there are filters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
    
        $paymentsByMonth = [];
        while ($row = $result->fetch_assoc()) {
            $paymentsByMonth[] = $row;
        }
        $stmt->close();
    
        // Fetch total records count
        $countQuery = "
            SELECT COUNT(DISTINCT DATE_FORMAT(pc.date_payment_created, '%Y-%m')) AS total
            FROM payment_collection pc
            $conditions
        ";

        // Create a new parameter array excluding LIMIT & OFFSET
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes = substr($types, 0, strlen($types) - 2); // Remove last two "ii"

        // Execute count query
        $stmt = $this->conn->prepare($countQuery);

        // Bind parameters only if there are filters
        if (!empty($countParams)) {
            $stmt->bind_param($countTypes, ...$countParams);
        }
    
        $stmt->execute();
        $stmt->bind_result($totalRecords);
        $stmt->fetch();
        $stmt->close();
    
        $totalPages = ceil($totalRecords / $limit);
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'payments' => $paymentsByMonth
            ]
        ]);
    }

    public function getPaymentMethodsUtilized($filters) {
        $query = "
            SELECT payment_method, COUNT(*) AS total_payments
            FROM payment_collection
            WHERE payment_method IS NOT NULL
        ";
        
        $params = [];
        $types = '';
    
        // Apply year filter if provided
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Apply tax_number filter if provided
        if (!empty($filters['tax_number'])) {
            $query .= " AND user_id = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
    
        $query .= " GROUP BY payment_method ORDER BY total_payments DESC";
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $paymentMethods = [];
        $totalPayments = 0;
    
        while ($row = $result->fetch_assoc()) {
            $paymentMethods[] = $row;
            $totalPayments += $row['total_payments'];
        }
        $stmt->close();
    
        // Calculate percentage for each method
        foreach ($paymentMethods as &$method) {
            $method['percentage'] = ($totalPayments > 0) ? round(($method['total_payments'] / $totalPayments) * 100, 2) : 0;
        }
    
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_payments' => $totalPayments,
                'payment_methods' => $paymentMethods
            ]
        ]);
    }

    public function getTopPayers($filters) {
        // Query to fetch top-paying taxpayers from payment_collection
        $query = "
            SELECT pc.user_id AS tax_number, 
                   SUM(pc.amount_paid) AS total_paid
            FROM payment_collection pc
            WHERE 1=1
        ";
    
        $params = [];
        $types = '';
    
        // Apply year filter if provided
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        $query .= " GROUP BY pc.user_id ORDER BY total_paid DESC LIMIT ?";
    
        // Default limit if not provided
        $limit = !empty($filters['limit']) ? (int)$filters['limit'] : 10;
        $params[] = $limit;
        $types .= "i";
    
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $topPayers = [];
        while ($row = $result->fetch_assoc()) {
            $taxNumber = $row['tax_number'];
    
            // Fetch taxpayer details separately
            $taxpayerInfo = $this->getTaxpayerInfo($taxNumber);
    
            // Fetch revenue head details separately
            $revenueHeads = $this->getRevenueHeadsByTaxNumber($taxNumber);
    
            // Apply MDA filter using PHP
            if (!empty($filters['mda_id'])) {
                $mdaFound = false;
                foreach ($revenueHeads as $revenueHead) {
                    if ($revenueHead['mda_id'] == $filters['mda_id']) {
                        $mdaFound = true;
                        break;
                    }
                }
                if (!$mdaFound) {
                    continue; // Skip this record if MDA does not match
                }
            }
    
            // Merge taxpayer details with payment data
            $topPayers[] = array_merge($taxpayerInfo, [
                'tax_number' => $taxNumber,
                'total_paid' => $row['total_paid']
            ]);
        }
        $stmt->close();
    
        echo json_encode([
            'status' => 'success',
            'data' => [
                'top_payers' => $topPayers
            ]
        ]);
    }

    public function getAveragePaymentProcessingTime($filters) {
        // Base query to calculate average payment processing time per month
        $query = "
            SELECT 
                DATE_FORMAT(pc.date_payment_created, '%Y-%m') AS year_months,
                AVG(TIMESTAMPDIFF(MINUTE, i.date_created, pc.date_payment_created)) AS avg_processing_days
            FROM payment_collection pc
            INNER JOIN invoices i ON pc.invoice_number = i.invoice_number
            WHERE i.payment_status = 'paid'
        ";
    
        $params = [];
        $types = '';
    
        // Apply filters
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        if (!empty($filters['mda_id'])) {
            $query .= " AND JSON_UNQUOTE(JSON_EXTRACT(i.revenue_head, '$[0].mda_id')) = ?";
            $params[] = $filters['mda_id'];
            $types .= "i";
        }

        if (isset($filters['mda_id'])) {
            $filters['mda_id'] = '"mda_id":"'.$filters['mda_id'].'"';
            $query .= "AND i.revenue_head REGEXP ?";
            $params[] = $filters['mda_id'];
            $types .= 's';
        }
    
        // Group by Year-Month
        $query .= " GROUP BY year_months ORDER BY year_months ASC";
    
        $stmt = $this->conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
    
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'year_month' => $row['year_months'],
                'average_processing_time_days' => round($row['avg_processing_days'], 2)
            ];
        }
    
        $stmt->close();
    
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function getTotalTCCsIssuedByYearMonth($filters) {
        $params = [];
        $types = "";
    
        // Base query to count TCCs by year-month
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') AS year_months,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS total_pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) AS total_approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS total_rejected
            FROM tax_clearance_certificates
            WHERE 1=1
        ";
    
        // Apply optional year filter
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(created_at) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        $query .= " GROUP BY year_months ORDER BY year_months DESC";
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $tccData = [];
        while ($row = $result->fetch_assoc()) {
            $row['total_issued'] = $row['total_pending'] + $row['total_approved'] + $row['total_rejected'];
            $tccData[] = $row;
        }
        $stmt->close();
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => $tccData
        ]);
    }

    public function getAverageTCCProcessingTimeByYearMonth($filters) {
        $params = [];
        $types = "";
    
        // Base query to calculate the average processing time
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') AS year_months,
                AVG(DATEDIFF(issued_date, created_at)) AS avg_processing_time_days
            FROM tax_clearance_certificates
            WHERE issued_date IS NOT NULL
        ";
    
        // Apply optional year filter
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(created_at) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        $query .= " GROUP BY year_months ORDER BY year_months DESC";
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $tccProcessingTime = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure avg_processing_time_days is a valid number
            $row['avg_processing_time_days'] = $row['avg_processing_time_days'] !== null 
                ? round($row['avg_processing_time_days'], 2) 
                : 0;
            $tccProcessingTime[] = $row;
        }
        $stmt->close();
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => $tccProcessingTime
        ]);
    }

    public function getTCCValidityPercentage() {
        // Query to get total taxpayers who have ever been issued a TCC
        $totalQuery = "
            SELECT COUNT(DISTINCT taxpayer_id) AS total_taxpayers
            FROM tax_clearance_certificates
        ";
        $totalResult = $this->conn->query($totalQuery);
        $totalTaxpayers = $totalResult->fetch_assoc()['total_taxpayers'] ?? 0;
    
        // Query to count taxpayers with valid TCCs
        $validQuery = "
            SELECT COUNT(DISTINCT taxpayer_id) AS valid_tcc
            FROM tax_clearance_certificates
            WHERE expiry_date >= CURDATE()
        ";
        $validResult = $this->conn->query($validQuery);
        $validTCC = $validResult->fetch_assoc()['valid_tcc'] ?? 0;
    
        // Calculate invalid TCC count
        $invalidTCC = $totalTaxpayers - $validTCC;
    
        // Avoid division by zero
        $validPercentage = $totalTaxpayers > 0 ? round(($validTCC / $totalTaxpayers) * 100, 2) : 0;
        $invalidPercentage = $totalTaxpayers > 0 ? round(($invalidTCC / $totalTaxpayers) * 100, 2) : 0;
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_taxpayers' => $totalTaxpayers,
                'valid_tcc_count' => $validTCC,
                'invalid_tcc_count' => $invalidTCC,
                'valid_tcc_percentage' => $validPercentage,
                'invalid_tcc_percentage' => $invalidPercentage
            ]
        ]);
    }
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    

    
    private function getRevenueHeadDetails($revenueHeadId) {
        $query = "
            SELECT rh.item_name, rh.item_code, m.mda_code, m.id AS mda_id, m.fullname AS mda_name 
            FROM revenue_heads rh 
            LEFT JOIN mda m ON rh.mda_id = m.id 
            WHERE rh.id = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $revenueHeadId);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_assoc();
        $stmt->close();
    
        return $details;
    }

    private function getTaxpayerInfo($taxNumber) {
        // Query to fetch taxpayer details
        $query = "
            SELECT first_name, surname, email, phone, category 
            FROM taxpayer WHERE tax_number = ?
            UNION 
            SELECT first_name, last_name AS surname, email, phone, 'enumerator' AS category 
            FROM enumerator_tax_payers WHERE tax_number = ?
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $taxNumber, $taxNumber);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($row = $result->fetch_assoc()) {
            return $row;
        } else {
            return [
                'first_name' => null,
                'surname' => null,
                'email' => null,
                'phone' => null,
                'category' => null
            ];
        }
    }

    private function getRevenueHeadsByTaxNumber($taxNumber) {
        $query = "
            SELECT i.revenue_head 
            FROM invoices i
            WHERE i.tax_number = ?
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $taxNumber);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $revenueHeads = [];
        while ($row = $result->fetch_assoc()) {
            $decodedHeads = json_decode($row['revenue_head'], true);
            if (is_array($decodedHeads)) {
                foreach ($decodedHeads as $revenueHead) {
                    $revenueHeads[] = $revenueHead;
                }
            }
        }
        $stmt->close();
    
        return $revenueHeads;
    }
    
    
    
    
    
    
    
    
    
    
    
}