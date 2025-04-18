<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication
class SpecialUserController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Fetch all special users with filters, employee count, total monthly tax payable, annual tax, and total payments
    // public function getAllSpecialUsers($queryParams) {
    //     // Base query with joins for employee count, monthly and annual tax, and total payments
    //     $query = "
    //         SELECT su.id, su.payer_id, su.name, su.industry, su.state, su.lga, su.email, su.phone, su.category, su.official_TIN,
    //                COUNT(e.id) AS employee_count,
    //                IFNULL(SUM(esb.monthly_tax_payable), 0) AS total_monthly_tax,
    //                IFNULL(SUM(esb.monthly_tax_payable) * 12, 0) AS total_annual_tax,
    //                IFNULL(SUM(pc.amount_paid), 0) AS total_payments
    //         FROM special_users_ su
    //         LEFT JOIN special_user_employees e ON su.id = e.associated_special_user_id
    //         LEFT JOIN employee_salary_and_benefits esb ON e.id = esb.employee_id
    //         LEFT JOIN payment_collection pc ON su.payer_id = pc.user_id
    //         WHERE 1=1
    //     ";
        
    //     $params = [];
    //     $types = "";

    //     // Apply filters conditionally
    //     if (!empty($queryParams['payer_id'])) {
    //         $query .= " AND su.payer_id = ?";
    //         $params[] = $queryParams['payer_id'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['name'])) {
    //         $query .= " AND su.name LIKE ?";
    //         $params[] = '%' . $queryParams['name'] . '%';
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['industry'])) {
    //         $query .= " AND su.industry = ?";
    //         $params[] = $queryParams['industry'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['official_TIN'])) {
    //         $query .= " AND su.official_TIN = ?";
    //         $params[] = $queryParams['official_TIN'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['email'])) {
    //         $query .= " AND su.email = ?";
    //         $params[] = $queryParams['email'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['phone'])) {
    //         $query .= " AND su.phone = ?";
    //         $params[] = $queryParams['phone'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['state'])) {
    //         $query .= " AND su.state = ?";
    //         $params[] = $queryParams['state'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['lga'])) {
    //         $query .= " AND su.lga = ?";
    //         $params[] = $queryParams['lga'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['category'])) {
    //         $query .= " AND su.category = ?";
    //         $params[] = $queryParams['category'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['start_timeIn']) && !empty($queryParams['end_timeIn'])) {
    //         $query .= " AND su.timeIn BETWEEN ? AND ?";
    //         $params[] = $queryParams['start_timeIn'];
    //         $params[] = $queryParams['end_timeIn'];
    //         $types .= "ss";
    //     }
    //     if (!empty($queryParams['min_staff_quota']) && !empty($queryParams['max_staff_quota'])) {
    //         $query .= " AND su.staff_quota BETWEEN ? AND ?";
    //         $params[] = $queryParams['min_staff_quota'];
    //         $params[] = $queryParams['max_staff_quota'];
    //         $types .= "ii";
    //     }

    //     // Group by each special user to get individual records with aggregated fields
    //     $query .= " GROUP BY su.id";

    //     // Execute query
    //     $stmt = $this->conn->prepare($query);
    //     if ($types) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    //     $stmt->execute();
    //     $result = $stmt->get_result();

    //     // Fetch results
    //     $specialUsers = [];
    //     while ($row = $result->fetch_assoc()) {
    //         $specialUsers[] = $row;
    //     }

    //     // Return JSON response
    //     return json_encode([
    //         "status" => "success",
    //         "data" => $specialUsers
    //     ]);
    // }

    public function getAllSpecialUsers($queryParams) {
        // Base query to fetch special users
        $query = "
            SELECT su.id, su.payer_id, su.name, su.industry, su.state, su.lga, su.email, su.phone, 
                   su.category, su.official_TIN, su.timeIn
            FROM special_users_ su
            WHERE status = 'active'
        ";
        
        $params = [];
        $types = "";
    
        // Apply filters
        if (!empty($queryParams['payer_id'])) {
            $query .= " AND su.payer_id = ?";
            $params[] = $queryParams['payer_id'];
            $types .= "s";
        }
        if (!empty($queryParams['name'])) {
            $query .= " AND su.name LIKE ?";
            $params[] = '%' . $queryParams['name'] . '%';
            $types .= "s";
        }
        if (!empty($queryParams['industry'])) {
            $query .= " AND su.industry = ?";
            $params[] = $queryParams['industry'];
            $types .= "s";
        }
        if (!empty($queryParams['official_TIN'])) {
            $query .= " AND su.official_TIN = ?";
            $params[] = $queryParams['official_TIN'];
            $types .= "s";
        }
        if (!empty($queryParams['email'])) {
            $query .= " AND su.email = ?";
            $params[] = $queryParams['email'];
            $types .= "s";
        }
        if (!empty($queryParams['phone'])) {
            $query .= " AND su.phone = ?";
            $params[] = $queryParams['phone'];
            $types .= "s";
        }
        if (!empty($queryParams['state'])) {
            $query .= " AND su.state = ?";
            $params[] = $queryParams['state'];
            $types .= "s";
        }
        if (!empty($queryParams['lga'])) {
            $query .= " AND su.lga = ?";
            $params[] = $queryParams['lga'];
            $types .= "s";
        }
        if (!empty($queryParams['category'])) {
            $query .= " AND su.category = ?";
            $params[] = $queryParams['category'];
            $types .= "s";
        }
        if (!empty($queryParams['start_timeIn']) && !empty($queryParams['end_timeIn'])) {
            $query .= " AND su.timeIn BETWEEN ? AND ?";
            $params[] = $queryParams['start_timeIn'];
            $params[] = $queryParams['end_timeIn'];
            $types .= "ss";
        }
        $query .= " ORDER BY su.timeIn DESC;";
        // Execute query to fetch special users
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $specialUsers = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate additional fields for each special user
            $payerId = $row['id'];
            $payerTaxNumber = $row['payer_id'];
    
            // Employee count
            $employeeQuery = "SELECT COUNT(*) as count FROM special_user_employees WHERE associated_special_user_id = ? AND status = 'active'";
            $stmtEmployee = $this->conn->prepare($employeeQuery);
            $stmtEmployee->bind_param("s", $payerId);
            $stmtEmployee->execute();
            $employeeResult = $stmtEmployee->get_result()->fetch_assoc();
            $row['employee_count'] = $employeeResult['count'];
            $stmtEmployee->close();
    
            // Total monthly and annual tax
            $taxQuery = "
                SELECT SUM(esb.monthly_tax_payable) as total_monthly_tax
                FROM special_user_employees e
                JOIN employee_salary_and_benefits esb ON e.id = esb.employee_id
                WHERE e.associated_special_user_id = ? AND e.status = 'active'
            ";
            $stmtTax = $this->conn->prepare($taxQuery);
            $stmtTax->bind_param("s", $payerId);
            $stmtTax->execute();
            $taxResult = $stmtTax->get_result()->fetch_assoc();
            $row['total_monthly_tax'] = $taxResult['total_monthly_tax'] ?? 0;
            $row['total_annual_tax'] = $row['total_monthly_tax'] * 12;
            $stmtTax->close();
    
            // Total payments
            $paymentQuery = "SELECT SUM(amount_paid) as total_payments FROM payment_collection WHERE user_id = ?";
            $stmtPayment = $this->conn->prepare($paymentQuery);
            $stmtPayment->bind_param("s", $payerTaxNumber);
            $stmtPayment->execute();
            $paymentResult = $stmtPayment->get_result()->fetch_assoc();
            $row['total_payments'] = $paymentResult['total_payments'] ?? 0;
            $stmtPayment->close();
    
            $specialUsers[] = $row;
        }
        $stmt->close();
    
        // Return JSON response
        return json_encode([
            "status" => "success",
            "data" => $specialUsers
        ]);
    }

    // public function registerMultipleEmployeesWithSalaries($employeesData) {
    //     // Validate that the input is an array
    //     if (!is_array($employeesData) || empty($employeesData)) {
    //         echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
    //         http_response_code(400); // Bad request
    //         return;
    //     }
    
    //     // Start transaction
    //     $this->conn->begin_transaction();
    
    //     try {
    //         foreach ($employeesData as $data) {
    //             // Validate required fields for employee
    //             if (!isset($data['fullname'], $data['email'], $data['phone'], $data['payer_id'], $data['associated_special_user_id'])) {
    //                 throw new Exception('Missing required fields for employee: fullname, email, phone, payer_id, associated_special_user_id');
    //             }
    
    //             // Validate required fields for salary and benefits
    //             if (!isset($data['basic_salary'], $data['housing'], $data['transport'], $data['utility'], $data['medical'], $data['entertainment'], $data['leaves'])) {
    //                 throw new Exception('Missing required fields for salary and benefits: basic_salary, housing, transport, utility, medical, entertainment, leaves');
    //             }
    
    //             // Check if the email or phone already exists in the 'special_user_employees' table
    //             if (isDuplicateUser ($this->conn, 'special_user_employees', $data['email'], $data['phone'])) {
    //                 throw new Exception('Employee with this email or phone number already exists: ' . $data['email'] . ', ' . $data['phone']);
    //             }
    
    //             // Insert employee into 'special_user_employees' table
    //             $query = "INSERT INTO special_user_employees (fullname, email, phone, payer_id, associated_special_user_id, created_date) 
    //                       VALUES (?, ?, ?, ?, ?, NOW())";
    
    //             $stmt = $this->conn->prepare($query);
    //             $stmt->bind_param(
    //                 'ssssi',
    //                 $data['fullname'],
    //                 $data['email'],
    //                 $data['phone'],
    //                 $data['payer_id'],
    //                 $data['associated_special_user_id']
    //             );
    
    //             if (!$stmt->execute()) {
    //                 throw new Exception('Error registering employee: ' . $stmt->error);
    //             }
    
    //             // Get the employee ID for the newly registered employee
    //             $employee_id = $stmt->insert_id;
    
    //             // Calculate annual gross income
    //             $annual_gross_income = $data['basic_salary'] + $data['housing'] + $data['transport'] + $data['utility'] + $data['medical'] + $data['entertainment'];
    
    //             // Calculate monthly tax payable based on the provided annual gross income
    //             $monthly_tax_payable = $this->calculateMonthlyTaxPayable($annual_gross_income);
    
    //             // Insert salary and benefits into 'employee_salary_and_benefits' table
    //             $query = "INSERT INTO employee_salary_and_benefits (employee_id, basic_salary, date_employed, housing, transport, utility, medical, entertainment, leaves, annual_gross_income, new_gross, monthly_tax_payable, created_date) 
    //                       VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    //             $stmt = $this->conn->prepare($query);
    //             $stmt->bind_param(
    //                 'idddddddddd',
    //                 $employee_id,
    //                 $data['basic_salary'],
    //                 $data['housing'],
    //                 $data['transport'],
    //                 $data['utility'],
    //                 $data['medical'],
    //                 $data['entertainment'],
    //                 $data['leaves'],
    //                 $annual_gross_income,
    //                 $annual_gross_income,  // New gross income (before deductions)
    //                 $monthly_tax_payable
    //             );
    
    //             if (!$stmt->execute()) {
    //                 throw new Exception('Error registering salary and benefits: ' . $stmt->error);
    //             }
    //         }
    
    //         // Commit transaction
    //         $this->conn->commit();
    
    //         // Return success response
    //         echo json_encode(['status' => 'success', 'message' => 'All employees and salaries registered successfully']);
    
    //     } catch (Exception $e) {
    //         // Rollback transaction in case of an error
    //         $this->conn->rollback();
    //         echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    //     } finally {
    //         if (isset($stmt)) {
    //             $stmt->close();
    //         }
    //     }
    // }

    public function registerMultipleEmployeesWithSalaries($employeesData) {
        // Validate that the input is an array
        if (!is_array($employeesData) || empty($employeesData)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
            http_response_code(400); // Bad request
            return;
        }
    
        // Start transaction
        $this->conn->begin_transaction();
    
        $successfulRegistrations = [];
        $unsuccessfulRegistrations = [];
    
        try {
            foreach ($employeesData as $index => $data) {
                // Validate required fields for employee
                if (!isset($data['fullname'], $data['email'], $data['phone'], $data['payer_id'], $data['associated_special_user_id'])) {
                    $unsuccessfulRegistrations[] = [
                        'index' => $index,
                        'error' => 'Missing required fields for employee: fullname, email, phone, payer_id, associated_special_user_id'
                    ];
                    continue; // Skip to the next employee
                }
    
                // Validate required fields for salary and benefits
                if (!isset($data['basic_salary'], $data['housing'], $data['transport'], $data['utility'], $data['medical'], $data['entertainment'], $data['leaves'])) {
                    $unsuccessfulRegistrations[] = [
                        'index' => $index,
                        'error' => 'Missing required fields for salary and benefits: basic_salary, housing, transport, utility, medical, entertainment, leaves'
                    ];
                    continue; // Skip to the next employee
                }
    
                // Check if the email or phone already exists in the 'special_user_employees' table
                if (isDuplicateUser ($this->conn, 'special_user_employees', $data['email'], $data['phone'])) {
                    $unsuccessfulRegistrations[] = [
                        'index' => $index,
                        'error' => 'Employee with this email or phone number already exists: ' . $data['email'] . ', ' . $data['phone']
                    ];
                    continue; // Skip to the next employee
                }
    
                // Insert employee into 'special_user_employees' table
                $query = "INSERT INTO special_user_employees (employee_taxnumber, fullname, email, phone, payer_id, associated_special_user_id, created_date) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param(
                    'ssssi',
                    $data['employee_taxnumber'],
                    $data['fullname'],
                    $data['email'],
                    $data['phone'],
                    $data['payer_id'],
                    $data['associated_special_user_id']
                );
    
                if (!$stmt->execute()) {
                    $unsuccessfulRegistrations[] = [
                        'index' => $index,
                        'error' => 'Error registering employee: ' . $stmt->error
                    ];
                    continue; // Skip to the next employee
                }
    
                // Get the employee ID for the newly registered employee
                $employee_id = $stmt->insert_id;
    
                // Calculate annual gross income
                $annual_gross_income = $data['basic_salary'] + $data['housing'] + $data['transport'] + $data['utility'] + $data['medical'] + $data['entertainment'];
    
                // Calculate monthly tax payable based on the provided annual gross income
                $monthly_tax_payable = $this->calculateMonthlyTaxPayable($annual_gross_income);
    
                // Insert salary and benefits into 'employee_salary_and_benefits' table
                $query = "INSERT INTO employee_salary_and_benefits (employee_id, basic_salary, date_employed, housing, transport, utility, medical, entertainment, leaves, annual_gross_income, new_gross, monthly_tax_payable, created_date) 
                          VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param(
                    'idddddddddd',
                    $employee_id,
                    $data['basic_salary'],
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
    
                if (!$stmt->execute()) {
                    $unsuccessfulRegistrations[] = [
                        'index' => $index,
                        'error' => 'Error registering salary and benefits: ' . $stmt->error
                    ];
                    continue; // Skip to the next employee
                }
    
                // Record successful registration
                $successfulRegistrations[] = [
                    'employee_id' => $employee_id,
                    'fullname' => $data['fullname'],
                    'email' => $data['email'],
                    'phone' => $data['phone']
                ];
            }
    
            // Commit transaction
            $this->conn->commit();
    
            // Return success response with both successful and unsuccessful registrations
            echo json_encode([
                'status' => 'success',
                'message' => 'Processing completed',
                'successful' => $successfulRegistrations,
                'unsuccessful' => $unsuccessfulRegistrations
            ]);
    
        } catch (Exception $e) {
            // Rollback transaction in case of an error
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    // Edit special user
    public function editSpecialUser($data)
    {
        // Validate input
        if (!isset($data['id'], $data['name'], $data['email'], $data['phone'], $data['address'], $data['industry'], $data['official_TIN'], $data['category'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            http_response_code(400); // Bad request
            return;
        }

        $query = "UPDATE special_users_ SET 
                name = ?, email = ?, phone = ?, address = ?, industry = ?, official_TIN = ?, category = ?
                WHERE id = ? AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'sssssssi',
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['industry'],
            $data['official_TIN'],
            $data['category'],
            $data['id']
        );

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Special user updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating special user or no changes made']);
        }
    }

    // Delete (inactivate) special user
    public function deleteSpecialUser($id)
    {
        // Begin transaction
        $this->conn->begin_transaction();

        try {
            // Update status to 'inactive' in special_users_
            $queryUser = "UPDATE special_users_ SET status = 'inactive' WHERE id = ?";
            $stmtUser = $this->conn->prepare($queryUser);
            $stmtUser->bind_param('i', $id);
            $stmtUser->execute();

            if ($stmtUser->affected_rows === 0) {
                throw new Exception('No special user found with the given ID or user already inactive');
            }

            // Update status to 'inactive' in special_user_employees for associated rows
            $queryEmployees = "UPDATE special_user_employees SET status = 'inactive' WHERE associated_special_user_id = ?";
            $stmtEmployees = $this->conn->prepare($queryEmployees);
            $stmtEmployees->bind_param('i', $id);
            $stmtEmployees->execute();

            // Commit transaction
            $this->conn->commit();

            echo json_encode(['status' => 'success', 'message' => 'Special user and associated employees set to inactive']);
        } catch (Exception $e) {
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Error setting special user to inactive: ' . $e->getMessage()]);
        }
    }
    // Fetch employees under a specific special user with optional pagination and filters
    // public function getEmployeesBySpecialUser($queryParams) {
    //     // Check if the associated_special_user_id is provided
    //     if (empty($queryParams['special_user_id'])) {
    //         return json_encode(["status" => "error", "message" => "Special user ID is required"]);
    //     }

    //     $specialUserId = $queryParams['special_user_id'];

    //     // Default pagination
    //     $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    //     $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    //     $offset = ($page - 1) * $limit;

    //     // Base query to get employees for the specified special user
    //     $query = "SELECT e.*, esb.basic_salary, esb.date_employed, esb.housing, esb.transport,
    //                      esb.utility, esb.medical, esb.entertainment, esb.leaves,
    //                      esb.annual_gross_income, esb.new_gross, esb.monthly_tax_payable
    //               FROM special_user_employees e
    //               LEFT JOIN employee_salary_and_benefits esb ON e.id = esb.employee_id
    //               WHERE e.associated_special_user_id = ? AND status = 'active'";
    //     $params = [$specialUserId];
    //     $types = "i";

    //     // Apply filters conditionally
    //     if (!empty($queryParams['id'])) {
    //         $query .= " AND e.id = ?";
    //         $params[] = $queryParams['id'];
    //         $types .= "i";
    //     }
    //     if (!empty($queryParams['fullname'])) {
    //         $query .= " AND e.fullname LIKE ?";
    //         $params[] = '%' . $queryParams['fullname'] . '%';
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['email'])) {
    //         $query .= " AND e.email = ?";
    //         $params[] = $queryParams['email'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['phone'])) {
    //         $query .= " AND e.phone = ?";
    //         $params[] = $queryParams['phone'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['payer_id'])) {
    //         $query .= " AND e.payer_id = ?";
    //         $params[] = $queryParams['payer_id'];
    //         $types .= "s";
    //     }
    //     if (!empty($queryParams['created_date_start']) && !empty($queryParams['created_date_end'])) {
    //         $query .= " AND e.created_date BETWEEN ? AND ?";
    //         $params[] = $queryParams['created_date_start'];
    //         $params[] = $queryParams['created_date_end'];
    //         $types .= "ss";
    //     }
    //     if (!empty($queryParams['basic_salary_min']) && !empty($queryParams['basic_salary_max'])) {
    //         $query .= " AND esb.basic_salary BETWEEN ? AND ?";
    //         $params[] = $queryParams['basic_salary_min'];
    //         $params[] = $queryParams['basic_salary_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['date_employed_start']) && !empty($queryParams['date_employed_end'])) {
    //         $query .= " AND esb.date_employed BETWEEN ? AND ?";
    //         $params[] = $queryParams['date_employed_start'];
    //         $params[] = $queryParams['date_employed_end'];
    //         $types .= "ss";
    //     }
    //     if (!empty($queryParams['housing_min']) && !empty($queryParams['housing_max'])) {
    //         $query .= " AND esb.housing BETWEEN ? AND ?";
    //         $params[] = $queryParams['housing_min'];
    //         $params[] = $queryParams['housing_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['transport_min']) && !empty($queryParams['transport_max'])) {
    //         $query .= " AND esb.transport BETWEEN ? AND ?";
    //         $params[] = $queryParams['transport_min'];
    //         $params[] = $queryParams['transport_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['utility_min']) && !empty($queryParams['utility_max'])) {
    //         $query .= " AND esb.utility BETWEEN ? AND ?";
    //         $params[] = $queryParams['utility_min'];
    //         $params[] = $queryParams['utility_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['medical_min']) && !empty($queryParams['medical_max'])) {
    //         $query .= " AND esb.medical BETWEEN ? AND ?";
    //         $params[] = $queryParams['medical_min'];
    //         $params[] = $queryParams['medical_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['entertainment_min']) && !empty($queryParams['entertainment_max'])) {
    //         $query .= " AND esb.entertainment BETWEEN ? AND ?";
    //         $params[] = $queryParams['entertainment_min'];
    //         $params[] = $queryParams['entertainment_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['leaves_min']) && !empty($queryParams['leaves_max'])) {
    //         $query .= " AND esb.leaves BETWEEN ? AND ?";
    //         $params[] = $queryParams['leaves_min'];
    //         $params[] = $queryParams['leaves_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['annual_gross_income_min']) && !empty($queryParams['annual_gross_income_max'])) {
    //         $query .= " AND esb.annual_gross_income BETWEEN ? AND ?";
    //         $params[] = $queryParams['annual_gross_income_min'];
    //         $params[] = $queryParams['annual_gross_income_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['new_gross_min']) && !empty($queryParams['new_gross_max'])) {
    //         $query .= " AND esb.new_gross BETWEEN ? AND ?";
    //         $params[] = $queryParams['new_gross_min'];
    //         $params[] = $queryParams['new_gross_max'];
    //         $types .= "ii";
    //     }
    //     if (!empty($queryParams['monthly_tax_payable_min']) && !empty($queryParams['monthly_tax_payable_max'])) {
    //         $query .= " AND esb.monthly_tax_payable BETWEEN ? AND ?";
    //         $params[] = $queryParams['monthly_tax_payable_min'];
    //         $params[] = $queryParams['monthly_tax_payable_max'];
    //         $types .= "ii";
    //     }

    //     // Add pagination
    //     $query .= " LIMIT ? OFFSET ?";
    //     $params[] = $limit;
    //     $params[] = $offset;
    //     $types .= "ii";
    //     $query .= " ORDER BY e.created DESC;";

    //     // Execute query
    //     $stmt = $this->conn->prepare($query);
    //     if ($types) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    //     $stmt->execute();
    //     $result = $stmt->get_result();

    //     // Fetch results
    //     $employees = [];
    //     while ($row = $result->fetch_assoc()) {
    //         $employees[] = $row;
    //     }

    //     // Get total count for pagination
    //     $totalQuery = "SELECT COUNT(*) as total FROM special_user_employees WHERE associated_special_user_id = ? AND status = 'active'";
    //     $totalStmt = $this->conn->prepare($totalQuery);
    //     $totalStmt->bind_param("i", $specialUserId);
    //     $totalStmt->execute();
    //     $totalResult = $totalStmt->get_result();
    //     $total = $totalResult->fetch_assoc()['total'];
    //     $totalPages = ceil($total / $limit);

    //     // Return JSON response
    //     return json_encode([
    //         "status" => "success",
    //         "data" => $employees,
    //         "pagination" => [
    //             "current_page" => $page,
    //             "per_page" => $limit,
    //             "total_pages" => $totalPages,
    //             "total_records" => $total
    //         ]
    //     ]);
    // }

    public function getEmployeesBySpecialUser ($queryParams) {
        // Check if the associated_special_user_id is provided
        if (empty($queryParams['special_user_id'])) {
            return json_encode(["status" => "error", "message" => "Special user ID is required"]);
        }
    
        $specialUser_Id = $queryParams['special_user_id'];
    
        // Default pagination
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query to get employees for the specified special user
        $query = "SELECT e.*, esb.basic_salary, esb.date_employed, esb.housing, esb.transport,
                         esb.utility, esb.medical, esb.entertainment, esb.leaves,
                         esb.annual_gross_income, esb.new_gross, esb.monthly_tax_payable
                  FROM special_user_employees e
                  LEFT JOIN employee_salary_and_benefits esb ON e.id = esb.employee_id
                  WHERE e.associated_special_user_id = ? AND status = 'active'";
        $params = [$specialUser_Id];
        $types = "i";
    
        // Apply filters conditionally
        if (!empty($queryParams['id'])) {
            $query .= " AND e.id = ?";
            $params[] = $queryParams['id'];
            $types .= "i";
        }
        if (!empty($queryParams['fullname'])) {
            $query .= " AND e.fullname LIKE ?";
            $params[] = '%' . $queryParams['fullname'] . '%';
            $types .= "s";
        }
        if (!empty($queryParams['email'])) {
            $query .= " AND e.email = ?";
            $params[] = $queryParams['email'];
            $types .= "s";
        }
        if (!empty($queryParams['phone'])) {
            $query .= " AND e.phone = ?";
            $params[] = $queryParams['phone'];
            $types .= "s";
        }
        if (!empty($queryParams['payer_id'])) {
            $query .= " AND e.payer_id = ?";
            $params[] = $queryParams['payer_id'];
            $types .= "s";
        }
        if (!empty($queryParams['created_date_start']) && !empty($queryParams['created_date_end'])) {
            $query .= " AND e.created_date BETWEEN ? AND ?";
            $params[] = $queryParams['created_date_start'];
            $params[] = $queryParams['created_date_end'];
            $types .= "ss";
        }
        if (!empty($queryParams['basic_salary_min']) && !empty($queryParams['basic_salary_max'])) {
            $query .= " AND esb.basic_salary BETWEEN ? AND ?";
            $params[] = $queryParams['basic_salary_min'];
            $params[] = $queryParams['basic_salary_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['date_employed_start']) && !empty($queryParams['date_employed_end'])) {
            $query .= " AND esb.date_employed BETWEEN ? AND ?";
            $params[] = $queryParams['date_employed_start'];
            $params[] = $queryParams['date_employed_end'];
            $types .= "ss";
        }
        if (!empty($queryParams['housing_min']) && !empty($queryParams['housing_max'])) {
            $query .= " AND esb.housing BETWEEN ? AND ?";
            $params[] = $queryParams['housing_min'];
            $params[] = $queryParams['housing_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['transport_min']) && !empty($queryParams['transport_max'])) {
            $query .= " AND esb.transport BETWEEN ? AND ?";
            $params[] = $queryParams['transport_min'];
            $params[] = $queryParams['transport_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['utility_min']) && !empty($queryParams['utility_max'])) {
            $query .= " AND esb.utility BETWEEN ? AND ?";
            $params[] = $queryParams['utility_min'];
            $params[] = $queryParams['utility_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['medical_min']) && !empty($queryParams['medical_max'])) {
            $query .= " AND esb.medical BETWEEN ? AND ?";
            $params[] = $queryParams['medical_min'];
            $params[] = $queryParams['medical_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['entertainment_min']) && !empty($queryParams['entertainment_max'])) {
            $query .= " AND esb.entertainment BETWEEN ? AND ?";
            $params[] = $queryParams['entertainment_min'];
            $params[] = $queryParams['entertainment_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['leaves_min']) && !empty($queryParams['leaves_max'])) {
            $query .= " AND esb.leaves BETWEEN ? AND ?";
            $params[] = $queryParams['leaves_min'];
            $params[] = $queryParams['leaves_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['annual_gross_income_min']) && !empty($queryParams['annual_gross_income_max'])) {
            $query .= " AND esb.annual_gross_income BETWEEN ? AND ?";
            $params[] = $queryParams['annual_gross_income_min'];
            $params[] = $queryParams['annual_gross_income_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['new_gross_min']) && !empty($queryParams['new_gross_max'])) {
            $query .= " AND esb.new_gross BETWEEN ? AND ?";
            $params[] = $queryParams['new_gross_min'];
            $params[] = $queryParams['new_gross_max'];
            $types .= "ii";
        }
        if (!empty($queryParams['monthly_tax_payable_min']) && !empty($queryParams['monthly_tax_payable_max'])) {
            $query .= " AND esb.monthly_tax_payable BETWEEN ? AND ?";
            $params[] = $queryParams['monthly_tax_payable_min'];
            $params[] = $queryParams['monthly_tax_payable_max'];
            $types .= "ii";
        }
    
        // Add ordering and pagination
        $query .= " ORDER BY e.created_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Execute query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch results
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    
        // Get total count for pagination
        $totalQuery = "SELECT COUNT(*) as total FROM special_user_employees WHERE associated_special_user_id = ? AND status = 'active'";
        $totalStmt = $this->conn->prepare($totalQuery);
        $totalStmt->bind_param("i", $specialUser_Id);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $total = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($total / $limit);
    
        // Return JSON response
        return json_encode([
            "status" => "success",
            "data" => $employees,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $total
            ]
        ]);
    }

            /**
         * Edit a special user employee.
         */
               /**
         * Edit a special user employee.
         */
    // public function editSpecialUserEmployee($data) {
    //     // Validate required fields
    //     if (!isset(
    //         $data['id'], $data['fullname'], $data['email'], $data['phone'],
    //         $data['basic_salary'], $data['housing'], $data['transport'], $data['utility'], 
    //         $data['medical'], $data['entertainment'], $data['leaves']
    //     )) {
    //         echo json_encode(['status' => 'error', 'message' => 'Missing required fields: id, fullname, email, phone, basic_salary, housing, transport, utility, medical, entertainment, leaves']);
    //         http_response_code(400); // Bad request
    //         return;
    //     }
    
    //     // Check if the employee exists and is active
    //     $query = "SELECT id FROM special_user_employees WHERE id = ? AND status = 'active'";
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param('i', $data['id']);
    //     $stmt->execute();
    //     $stmt->store_result();
    //     if ($stmt->num_rows === 0) {
    //         echo json_encode(['status' => 'error', 'message' => 'Employee not found or inactive']);
    //         http_response_code(404); // Not found
    //         return;
    //     }
    //     $stmt->close();
    
    //     // Start transaction
    //     $this->conn->begin_transaction();
    
    //     try {
    //         // Update the employee details
    //         $query = "UPDATE special_user_employees 
    //                     SET fullname = ?, email = ?, phone = ? 
    //                     WHERE id = ?";
    //         $stmt = $this->conn->prepare($query);
    //         $stmt->bind_param(
    //             'sssi',
    //             $data['fullname'],
    //             $data['email'],
    //             $data['phone'],
    //             $data['id']
    //         );
    
    //         if (!$stmt->execute()) {
    //             throw new Exception('Error updating employee details: ' . $stmt->error);
    //         }
    //         $stmt->close();
    
    //         // Calculate annual gross income
    //         $annual_gross_income = $data['basic_salary'] + $data['housing'] + $data['transport'] + $data['utility'] + $data['medical'] + $data['entertainment'];
    
    //         // Calculate monthly tax payable based on the provided annual gross income
    //         $monthly_tax_payable = $this->calculateMonthlyTaxPayable($annual_gross_income);
    
    //         // Update salary and benefits
    //         $query = "UPDATE employee_salary_and_benefits 
    //                     SET basic_salary = ?, housing = ?, transport = ?, utility = ?, medical = ?, 
    //                         entertainment = ?, leaves = ?, annual_gross_income = ?, new_gross = ?, 
    //                         monthly_tax_payable = ?
    //                     WHERE employee_id = ?";
    //         $stmt = $this->conn->prepare($query);
    //         $stmt->bind_param(
    //             'ddddddddddi',
    //             $data['basic_salary'],
    //             $data['housing'],
    //             $data['transport'],
    //             $data['utility'],
    //             $data['medical'],
    //             $data['entertainment'],
    //             $data['leaves'],
    //             $annual_gross_income,
    //             $annual_gross_income,  // New gross income (before deductions)
    //             $monthly_tax_payable,
    //             $data['id'] 
    //         );
    
    //         if (!$stmt->execute()) {
    //             throw new Exception('Error updating salary and benefits: ' . $stmt->error);
    //         }
    
    //         // Commit transaction
    //         $this->conn->commit();
    
    //         // Return success response
    //         echo json_encode(['status' => 'success', 'message' => 'Employee and salary details updated successfully']);
    
    //     } catch (Exception $e) {
    //         // Rollback transaction in case of an error
    //         $this->conn->rollback();
    //         echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    //     } finally {
    //         // $stmt->close();
    //     }
    // }

    public function editSpecialUserEmployee($data) {
        // Validate required fields
        if (!isset(
            $data['id'], $data['fullname'], $data['email'], $data['phone'],
            $data['basic_salary'], $data['housing'], $data['transport'], $data['utility'], 
            $data['medical'], $data['entertainment'], $data['leaves'], $data['employee_taxnumber']
        )) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: id, fullname, email, phone, basic_salary, housing, transport, utility, medical, entertainment, leaves, employee_taxnumber']);
            http_response_code(400); // Bad request
            return;
        }
    
        // Check if the employee exists and is active
        $query = "SELECT id FROM special_user_employees WHERE id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $data['id']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found or inactive']);
            http_response_code(404); // Not found
            return;
        }
        $stmt->close();
    
        // Start transaction
        $this->conn->begin_transaction();
    
        try {
            // Update the employee details (including employee_taxnumber)
            $query = "UPDATE special_user_employees 
                        SET fullname = ?, email = ?, phone = ?, employee_taxnumber = ? 
                        WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                'ssssi',
                $data['fullname'],
                $data['email'],
                $data['phone'],
                $data['employee_taxnumber'], // Bind employee_taxnumber
                $data['id']
            );
    
            if (!$stmt->execute()) {
                throw new Exception('Error updating employee details: ' . $stmt->error);
            }
            $stmt->close();
    
            // Calculate annual gross income
            $annual_gross_income = $data['basic_salary'] + $data['housing'] + $data['transport'] + $data['utility'] + $data['medical'] + $data['entertainment'];
    
            // Calculate monthly tax payable based on the provided annual gross income
            $monthly_tax_payable = $this->calculateMonthlyTaxPayable($annual_gross_income);
    
            // Update salary and benefits
            $query = "UPDATE employee_salary_and_benefits 
                        SET basic_salary = ?, housing = ?, transport = ?, utility = ?, medical = ?, 
                            entertainment = ?, leaves = ?, annual_gross_income = ?, new_gross = ?, 
                            monthly_tax_payable = ?
                        WHERE employee_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                'ddddddddddi',
                $data['basic_salary'],
                $data['housing'],
                $data['transport'],
                $data['utility'],
                $data['medical'],
                $data['entertainment'],
                $data['leaves'],
                $annual_gross_income,
                $annual_gross_income,  // New gross income (before deductions)
                $monthly_tax_payable,
                $data['id'] 
            );
    
            if (!$stmt->execute()) {
                throw new Exception('Error updating salary and benefits: ' . $stmt->error);
            }
    
            // Commit transaction
            $this->conn->commit();
    
            // Return success response
            echo json_encode(['status' => 'success', 'message' => 'Employee and salary details updated successfully']);
    
        } catch (Exception $e) {
            // Rollback transaction in case of an error
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } finally {
            // $stmt->close();
        }
    }
    

      /**
     * Calculate the monthly tax payable based on the annual gross income.
     */
    // private function calculateMonthlyTaxPayable($annual_gross_income) {
    //     // Deduction rates
    //     $nhf_rate = 0.025;   // National Housing Fund (2.5%)
    //     $pension_rate = 0.08;  // Pension (8%)
    //     $nhis_rate = 0.05;   // National Health Insurance Scheme (5%)
    //     $life_insurance_rate = 0.00;  // Life Insurance (currently 0%)
    //     $gratuities_rate = 0.00;  // Gratuities (currently 0%)

    //     // Deductions
    //     $nhf_deduction = $annual_gross_income * $nhf_rate;
    //     $pension_deduction = $annual_gross_income * $pension_rate;
    //     $nhis_deduction = $annual_gross_income * $nhis_rate;
    //     $total_deductions = $nhf_deduction + $pension_deduction + $nhis_deduction;

    //     // New gross income
    //     $new_gross_income = $annual_gross_income - $total_deductions;

    //     // Consolidated relief allowance (20% of new gross income)
    //     $consolidated_relief_allowance = $new_gross_income * 0.20;
    //     $additional_relief = ($new_gross_income <= 200000) ? 0 : 0;
    //     $total_allowance = $consolidated_relief_allowance + $additional_relief;

    //     // Chargeable income (annual gross income minus allowances)
    //     $chargeable_income = $annual_gross_income - $total_allowance;

    //     // Progressive tax bands
    //     $first_band = min(300000, $chargeable_income) * 0.07;
    //     $second_band = max(0, min(300000, $chargeable_income - 300000)) * 0.11;
    //     $third_band = max(0, min(500000, $chargeable_income - 600000)) * 0.15;
    //     $fourth_band = max(0, min(500000, $chargeable_income - 1100000)) * 0.19;
    //     $fifth_band = max(0, min(1600000, $chargeable_income - 1600000)) * 0.21;
    //     $sixth_band = max(0, $chargeable_income - 3200000) * 0.24;

    //     // Total annual tax
    //     $annual_tax_due = $first_band + $second_band + $third_band + $fourth_band + $fifth_band + $sixth_band;

    //     // Monthly tax payable
    //     $monthly_tax_payable = $annual_tax_due / 12;

    //     return $monthly_tax_payable;
    // }

    private function calculateMonthlyTaxPayable($annual_gross_income) {
        // Deduction rates
        $nhf_rate = 0.025;   // National Housing Fund (2.5%)
        $pension_rate = 0.08;  // Pension (8%)
        $nhis_rate = 0.05;   // National Health Insurance Scheme (5%)
        $life_insurance_rate = 0.00;  // Life Insurance (currently 0%)
        $gratuities_rate = 0.00;  // Gratuities (currently 0%)
    
        // Step 1: Check if the person is exempt from tax due to earning NGN 30,000/month or less
        if ($annual_gross_income <= 360000) {
            // If monthly income is less than or equal to NGN 30,000 (NGN 360,000/year), exempt from tax
            return [
                'status' => 'success',
                'annual_gross_income' => $annual_gross_income,
                'nhf_deduction' => 0,
                'pension_deduction' => 0,
                'nhis_deduction' => 0,
                'new_gross_income' => $annual_gross_income,
                'consolidated_relief_allowance' => 0,
                'chargeable_income' => 0,
                'annual_tax_due' => 0,
                'monthly_tax_payable' => 0
            ];
        }
    
        // Step 2: Deductions for NHF, Pension, and NHIS
        $nhf_deduction = $annual_gross_income * $nhf_rate;
        $pension_deduction = $annual_gross_income * $pension_rate;
        $nhis_deduction = $annual_gross_income * $nhis_rate;
        $total_deductions = $nhf_deduction + $pension_deduction + $nhis_deduction;
    
        // Step 3: New gross income after deductions
        $new_gross_income = $annual_gross_income - $total_deductions;
    
        // Step 4: Consolidated relief allowance (20% of new gross income)
        $consolidated_relief_allowance = $new_gross_income * 0.20;
        $additional_relief = ($new_gross_income <= 200000) ? 0 : 0;
        $total_allowance = $consolidated_relief_allowance + $additional_relief;
    
        // Step 5: Chargeable income (annual gross income minus allowances)
        $chargeable_income = $annual_gross_income - $total_allowance;
    
        // Step 6: Progressive tax bands
        $first_band = min(300000, $chargeable_income) * 0.07;
        $second_band = max(0, min(300000, $chargeable_income - 300000)) * 0.11;
        $third_band = max(0, min(500000, $chargeable_income - 600000)) * 0.15;
        $fourth_band = max(0, min(500000, $chargeable_income - 1100000)) * 0.19;
        $fifth_band = max(0, min(1600000, $chargeable_income - 1600000)) * 0.21;
        $sixth_band = max(0, $chargeable_income - 3200000) * 0.24;
    
        // Step 7: Total annual tax
        $annual_tax_due = $first_band + $second_band + $third_band + $fourth_band + $fifth_band + $sixth_band;
    
        // Step 8: Monthly tax payable
        $monthly_tax_payable = $annual_tax_due / 12;
        return $monthly_tax_payable;
        // Return the calculated values
        // return [
        //     'status' => 'success',
        //     'annual_gross_income' => $annual_gross_income,
        //     'nhf_deduction' => $nhf_deduction,
        //     'pension_deduction' => $pension_deduction,
        //     'nhis_deduction' => $nhis_deduction,
        //     'new_gross_income' => $new_gross_income,
        //     'consolidated_relief_allowance' => $consolidated_relief_allowance,
        //     'chargeable_income' => $chargeable_income,
        //     'annual_tax_due' => $annual_tax_due,
        //     'monthly_tax_payable' => $monthly_tax_payable
        // ];
    }
    
    
    /**
     * Delete (soft delete) a special user employee.
     */
    public function deleteSpecialUserEmployee($id) {
        // Check if the employee exists and is active
        $query = "SELECT id FROM special_user_employees WHERE id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found or already inactive']);
            http_response_code(404); // Not found
            return;
        }
        $stmt->close();

        // Soft delete the employee by updating the status to 'inactive'
        $query = "UPDATE special_user_employees SET status = 'inactive' WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Employee deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deleting employee: ' . $stmt->error]);
        }

        $stmt->close();
    }

    // public function createMultiplePayeInvoiceStaff($data) {
    //     // Validate required fields
    //     if (empty($data['invoice_number']) || empty($data['staff_ids']) || empty($data['associated_special_user_id'])) {
    //         echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    //         http_response_code(400);
    //         return;
    //     }
    
    //     // Ensure staff_ids is an array
    //     if (!is_array($data['staff_ids'])) {
    //         echo json_encode(["status" => "error", "message" => "staff_ids must be an array"]);
    //         http_response_code(400);
    //         return;
    //     }
    
    //     $invoiceNumber = $data['invoice_number'];
    //     $associatedSpecialUserId = $data['associated_special_user_id'];
    //     $staffIds = $data['staff_ids'];
    
    //     // Check for existing records to prevent duplicates
    //     $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
    //     $query = "
    //         SELECT staff_id FROM paye_invoice_staff 
    //         WHERE invoice_number = ? 
    //         AND associated_special_user_id = ? 
    //         AND staff_id IN ($placeholders)
    //     ";
        
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param("si" . str_repeat("i", count($staffIds)), $invoiceNumber, $associatedSpecialUserId, ...$staffIds);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
        
    //     $existingStaffIds = [];
    //     while ($row = $result->fetch_assoc()) {
    //         $existingStaffIds[] = $row['staff_id'];
    //     }
    //     $stmt->close();
    
    //     // Filter out existing staff IDs
    //     $newStaffIds = array_diff($staffIds, $existingStaffIds);
    
    //     if (empty($newStaffIds)) {
    //         echo json_encode(["status" => "error", "message" => "All staff members are already registered for this invoice"]);
    //         http_response_code(400);
    //         return;
    //     }
    
    //     // Prepare bulk insert query for only new staff
    //     $query = "INSERT INTO paye_invoice_staff (invoice_number, staff_id, associated_special_user_id, created_at) VALUES ";
    //     $params = [];
    //     $types = "";
    
    //     $values = [];
    //     foreach ($newStaffIds as $staffId) {
    //         $values[] = "(?, ?, ?, NOW())";
    //         $params[] = $invoiceNumber;
    //         $params[] = $staffId;
    //         $params[] = $associatedSpecialUserId;
    //         $types .= "sii";
    //     }
    
    //     // Finalize query
    //     $query .= implode(", ", $values);
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param($types, ...$params);
    
    //     if ($stmt->execute()) {
    //         echo json_encode(["status" => "success", "message" => "PAYE invoice staff records created successfully"]);
    //     } else {
    //         echo json_encode(["status" => "error", "message" => "Failed to create records"]);
    //         http_response_code(500);
    //     }
    
    //     $stmt->close();
    // }

    public function createMultiplePayeInvoiceStaff($data) {
        // Validate required fields
        if (empty($data['invoice_number']) || empty($data['staff_data']) || empty($data['associated_special_user_id'])) {
            echo json_encode(["status" => "error", "message" => "Missing required fields"]);
            http_response_code(400);
            return;
        }
    
        // Ensure staff_data is an array of objects
        if (!is_array($data['staff_data'])) {
            echo json_encode(["status" => "error", "message" => "staff_data must be an array"]);
            http_response_code(400);
            return;
        }
    
        $invoiceNumber = $data['invoice_number'];
        $associatedSpecialUserId = $data['associated_special_user_id'];
        $staffData = $data['staff_data']; // Each item should have 'staff_id' and 'monthly_tax_payable'
    
        // Extract staff IDs for checking duplicates
        $staffIds = array_column($staffData, 'staff_id');
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
    
        // Check for existing records to prevent duplicates
        $query = "
            SELECT staff_id FROM paye_invoice_staff 
            WHERE invoice_number = ? 
            AND associated_special_user_id = ? 
            AND staff_id IN ($placeholders)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si" . str_repeat("i", count($staffIds)), $invoiceNumber, $associatedSpecialUserId, ...$staffIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $existingStaffIds = [];
        while ($row = $result->fetch_assoc()) {
            $existingStaffIds[] = $row['staff_id'];
        }
        $stmt->close();
    
        // Filter out existing staff IDs
        $newStaffData = array_filter($staffData, function ($staff) use ($existingStaffIds) {
            return !in_array($staff['staff_id'], $existingStaffIds);
        });
    
        if (empty($newStaffData)) {
            echo json_encode(["status" => "error", "message" => "All staff members are already registered for this invoice"]);
            http_response_code(400);
            return;
        }
    
        // Prepare bulk insert query for only new staff
        $query = "INSERT INTO paye_invoice_staff (invoice_number, staff_id, associated_special_user_id, monthly_tax_payable, created_at) VALUES ";
        $params = [];
        $types = "";
    
        $values = [];
        foreach ($newStaffData as $staff) {
            $values[] = "(?, ?, ?, ?, NOW())";
            $params[] = $invoiceNumber;
            $params[] = $staff['staff_id'];
            $params[] = $associatedSpecialUserId;
            $params[] = $staff['monthly_tax_payable'];
            $types .= "siid";
        }
    
        // Finalize query
        $query .= implode(", ", $values);
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "PAYE invoice staff records created successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to create records"]);
            http_response_code(500);
        }
    
        $stmt->close();
    }
    
    
    public function getPayeInvoiceStaff($filters, $page, $limit) {
        // Set default page and limit if not provided
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        $params = [];
        $types = "";
    
        // Base query
        $query = "
            SELECT 
                pis.id,
                pis.invoice_number,
                pis.staff_id,
                pis.associated_special_user_id,
                pis.monthly_tax_payable,
                pis.created_at,
                e.fullname AS staff_name,
                e.email AS staff_email,
                e.phone AS staff_phone
            FROM paye_invoice_staff pis
            LEFT JOIN special_user_employees e ON pis.staff_id = e.id
            WHERE 1=1
        ";
    
        // Apply filters
        if (!empty($filters['invoice_number'])) {
            $query .= " AND pis.invoice_number = ?";
            $params[] = $filters['invoice_number'];
            $types .= "s";
        }
    
        if (!empty($filters['staff_id'])) {
            $query .= " AND pis.staff_id = ?";
            $params[] = $filters['staff_id'];
            $types .= "i";
        }
    
        if (!empty($filters['associated_special_user_id'])) {
            $query .= " AND pis.associated_special_user_id = ?";
            $params[] = $filters['associated_special_user_id'];
            $types .= "i";
        }
    
        // Apply sorting and pagination
        $query .= " ORDER BY pis.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
    
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM paye_invoice_staff WHERE 1=1";
        if (!empty($filters['invoice_number'])) {
            $countQuery .= " AND invoice_number = '" . $this->conn->real_escape_string($filters['invoice_number']) . "'";
        }
        if (!empty($filters['staff_id'])) {
            $countQuery .= " AND staff_id = " . intval($filters['staff_id']);
        }
        if (!empty($filters['associated_special_user_id'])) {
            $countQuery .= " AND associated_special_user_id = " . intval($filters['associated_special_user_id']);
        }
    
        $countResult = $this->conn->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $limit);
    
        // Return response
        echo json_encode([
            "status" => "success",
            "data" => $records,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $totalRecords
            ]
        ]);
    }

    public function getMonthlyEstimatedPayableByTaxNumber($filters, $page, $limit) {
        // Set default page and limit if not provided
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query
        $query = "
            SELECT 
                DATE_FORMAT(esb.date_employed, '%Y-%m') AS year_months,
                SUM(esb.monthly_tax_payable) AS total_monthly_payable
            FROM employee_salary_and_benefits esb
            JOIN special_user_employees sue ON esb.employee_id = sue.id
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Apply filters
        if (!empty($filters['tax_number'])) {
            $query .= " AND sue.payer_id = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(esb.date_employed) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
        if (!empty($filters['month'])) {
            $query .= " AND MONTH(esb.date_employed) = ?";
            $params[] = $filters['month'];
            $types .= "i";
        }
    
        // Group by year and month
        $query .= " GROUP BY year_months ORDER BY year_months DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch data
        $payeeData = [];
        while ($row = $result->fetch_assoc()) {
            $payeeData[] = $row;
        }
        $stmt->close();
    
        // Get total records for pagination
        $countQuery = "
            SELECT COUNT(DISTINCT DATE_FORMAT(esb.date_employed, '%Y-%m')) AS total 
            FROM employee_salary_and_benefits esb
            JOIN special_user_employees sue ON esb.employee_id = sue.id
            WHERE 1=1
        ";
        if (!empty($filters['tax_number'])) {
            $countQuery .= " AND sue.payer_id = '{$filters['tax_number']}'";
        }
        if (!empty($filters['year'])) {
            $countQuery .= " AND YEAR(esb.date_employed) = {$filters['year']}";
        }
        if (!empty($filters['month'])) {
            $countQuery .= " AND MONTH(esb.date_employed) = {$filters['month']}";
        }
    
        $countResult = $this->conn->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'] ?? 0;
        $totalPages = ceil($totalRecords / $limit);
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => $payeeData,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords
            ]
        ]);
    }

    public function getYearlyEstimatedPayableByTaxNumber($filters, $page, $limit) {
        // Set default page and limit if not provided
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query
        $query = "
            SELECT 
                YEAR(esb.date_employed) AS year,
                SUM(esb.monthly_tax_payable * 12) AS total_yearly_payable
            FROM employee_salary_and_benefits esb
            JOIN special_user_employees sue ON esb.employee_id = sue.id
            WHERE 1=1
        ";
    
        $params = [];
        $types = "";
    
        // Apply filters
        if (!empty($filters['tax_number'])) {
            $query .= " AND sue.payer_id = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(esb.date_employed) = ?";
            $params[] = $filters['year'];
            $types .= "i";
        }
    
        // Group by year
        $query .= " GROUP BY year ORDER BY year DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch data
        $payeeData = [];
        while ($row = $result->fetch_assoc()) {
            $payeeData[] = $row;
        }
        $stmt->close();
    
        // Get total records for pagination
        $countQuery = "
            SELECT COUNT(DISTINCT YEAR(esb.date_employed)) AS total 
            FROM employee_salary_and_benefits esb
            JOIN special_user_employees sue ON esb.employee_id = sue.id
            WHERE 1=1
        ";
        if (!empty($filters['tax_number'])) {
            $countQuery .= " AND sue.payer_id = '{$filters['tax_number']}'";
        }
        if (!empty($filters['year'])) {
            $countQuery .= " AND YEAR(esb.date_employed) = {$filters['year']}";
        }
    
        $countResult = $this->conn->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'] ?? 0;
        $totalPages = ceil($totalRecords / $limit);
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => $payeeData,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords
            ]
        ]);
    }

    public function getTotalPayeInvoicesPaid($filters, $page, $limit) {
        // Default page and limit
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query
        $query = "
            SELECT 
                SUM(pc.amount_paid) AS total_paid 
            FROM payment_collection pc
            JOIN invoices i ON pc.invoice_number = i.invoice_number
            WHERE i.invoice_type = 'paye'
        ";
    
        $params = [];
        $types = '';
    
        // Apply filters
        if (!empty($filters['tax_number'])) {
            $query .= " AND i.tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= 's';
        }
        if (!empty($filters['year'])) {
            $query .= " AND YEAR(pc.date_payment_created) = ?";
            $params[] = $filters['year'];
            $types .= 'i';
        }
        if (!empty($filters['month'])) {
            $query .= " AND MONTH(pc.date_payment_created) = ?";
            $params[] = $filters['month'];
            $types .= 'i';
        }
    
        // Pagination (limit and offset)
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Prepare statement
        $stmt = $this->conn->prepare($query);
    
        // Only bind params if there are parameters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        // Execute and fetch result
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
    
        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_paid' => $data['total_paid'] ?? 0
            ]
        ]);
    }

    public function getPayeeStaffRemittance($filters, $page = 1, $limit = 10) {
        // Default pagination settings
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
    
        // Base query to fetch payee staff remittance information from paye_invoice_staff, special_user_employees, and special_users_
        $query = "
            SELECT 
                pis.id AS invoice_id, 
                pis.invoice_number, 
                pis.staff_id, 
                pis.associated_special_user_id, 
                pis.monthly_tax_payable, 
                pis.session_result, 
                pis.created_at AS invoice_created_at, 
                sue.fullname AS staff_fullname, 
                sue.email AS staff_email, 
                sue.phone AS staff_phone, 
                sue.employee_taxnumber,
                su.name AS employer_name, 
                su.industry AS employer_industry
            FROM paye_invoice_staff pis
            LEFT JOIN special_user_employees sue ON sue.id = pis.staff_id
            LEFT JOIN special_users_ su ON su.id = pis.associated_special_user_id
            WHERE 1=1
        ";
    
        $params = [];
        $types = '';
    
        // Apply filters if provided
        if (!empty($filters['staff_id'])) {
            $query .= " AND pis.staff_id = ?";
            $params[] = $filters['staff_id'];
            $types .= 'i';
        }
    
        if (!empty($filters['associated_special_user_id'])) {
            $query .= " AND pis.associated_special_user_id = ?";
            $params[] = $filters['associated_special_user_id'];
            $types .= 'i';
        }
    
        if (!empty($filters['invoice_number'])) {
            $query .= " AND pis.invoice_number = ?";
            $params[] = $filters['invoice_number'];
            $types .= 's';
        }
    
        if (!empty($filters['employee_taxnumber'])) {
            $query .= " AND sue.employee_taxnumber = ?";
            $params[] = $filters['employee_taxnumber'];
            $types .= 's';
        }
    
        if (!empty($filters['status'])) {
            $query .= " AND su.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
    
        // Add pagination
        $query .= " LIMIT ? OFFSET ?";
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
        $remittances = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        // Now, fetch additional details like special user info and payment details separately
        $validRemittances = [];  // Array to hold valid remittances
    
        foreach ($remittances as &$remittance) {
            // Get payment collection details from payment_collection
            $paymentQuery = "SELECT amount_paid, date_payment_created, payment_reference_number, receipt_number 
                             FROM payment_collection 
                             WHERE invoice_number = ?";
            $stmt = $this->conn->prepare($paymentQuery);
            $stmt->bind_param('s', $remittance['invoice_number']);
            $stmt->execute();
            $paymentResult = $stmt->get_result();
            $paymentData = $paymentResult->fetch_assoc();
            $stmt->close();
    
            // Check if payment data exists before adding to the remittance record
            if ($paymentData) {
                $remittance['amount_paid'] = $paymentData['amount_paid'] ?? 0;
                $remittance['date_payment_created'] = $paymentData['date_payment_created'] ?? '';
                $remittance['payment_reference_number'] = $paymentData['payment_reference_number'] ?? '';
                $remittance['receipt_number'] = $paymentData['receipt_number'] ?? '';
    
                // Add this remittance to the valid remittances array
                $validRemittances[] = $remittance;
            }
        }
    
        // Fetch total count for pagination (same as before)
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM paye_invoice_staff pis
            LEFT JOIN special_user_employees sue ON sue.id = pis.staff_id
            LEFT JOIN special_users_ su ON su.id = pis.associated_special_user_id
            WHERE 1=1
        ";
    
        if (!empty($filters['staff_id'])) {
            $countQuery .= " AND pis.staff_id = ?";
        }
        if (!empty($filters['employee_taxnumber'])) {
            $countQuery .= " AND sue.employee_taxnumber = ?";
        }
    
        $stmtCount = $this->conn->prepare($countQuery);
        if (!empty($filters['staff_id'])) {
            $stmtCount->bind_param('i', $filters['staff_id']);
        }
        if (!empty($filters['employee_taxnumber'])) {
            $stmtCount->bind_param('s', $filters['employee_taxnumber']);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result();
        $totalRecords = $totalResult->fetch_assoc()['total'];
        $stmtCount->close();
    
        $totalPages = ceil($totalRecords / $limit);
    
        // Return JSON response with valid remittances
        return [
            'status' => 'success',
            'data' => $validRemittances, // Only return valid remittances
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords
            ]
        ];
    }

    // public function getEmployeeAnalytics($employeeTaxNumber) { 
    //     // Initialize an array to hold the analytics 
    //     $analytics = [];
    
    //     // 1. Employee Overview
    //     $stmt = $this->conn->prepare("SELECT id, fullname, email, phone, status FROM special_user_employees WHERE employee_taxnumber = ?");
    //     $stmt->bind_param("s", $employeeTaxNumber);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $employeeInfo = $result->fetch_assoc();
    
    //     if ($employeeInfo) {
    //         $analytics['employee_info'] = $employeeInfo;
    //     } else {
    //         return json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    //     }
    //     $stmt->close();
    
    //     // 2. Total Invoices for the Employee
    //     $stmt = $this->conn->prepare("
    //         SELECT COUNT(*) AS total_invoices 
    //         FROM invoices i 
    //         JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number 
    //         WHERE p.staff_id = ?
    //     ");
    //     $stmt->bind_param("i", $employeeInfo['id']); // Use the employee's ID
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $analytics['total_invoices'] = $result->fetch_assoc()['total_invoices'] ?? 0; // Ensure default value of 0 if no invoices
    //     $stmt->close();
    
    //     // 3. Total Amount Invoiced for the Employee
    //     $stmt = $this->conn->prepare("
    //         SELECT SUM(monthly_tax_payable) AS total_amount_invoiced 
    //         FROM invoices i 
    //         JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number 
    //         WHERE p.staff_id = ?
    //     ");
    //     $stmt->bind_param("i", $employeeInfo['id']);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $analytics['total_amount_invoiced'] = $result->fetch_assoc()['total_amount_invoiced'] ?? 0; // Default to 0 if null
    //     $stmt->close();
    
    //     // 4. Total Monthly Tax Payable for the Employee
    //     $stmt = $this->conn->prepare("SELECT SUM(monthly_tax_payable) AS total_monthly_tax_payable FROM paye_invoice_staff WHERE staff_id = ?");
    //     $stmt->bind_param("i", $employeeInfo['id']);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $monthlyTaxPayable = $result->fetch_assoc()['total_monthly_tax_payable'] ?? 0; // Default to 0 if null
    //     $analytics['total_monthly_tax_payable'] = $monthlyTaxPayable;
    //     $analytics['total_annual_tax_payable'] = $monthlyTaxPayable * 12; // Calculate annual tax
    //     $stmt->close();
    
    //     // 5. Payments Made by the Employee
    //     $stmt = $this->conn->prepare("
    //         SELECT SUM(p.monthly_tax_payable) AS total_paid
    //         FROM invoices i
    //         JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number
    //         WHERE p.staff_id = ?
    //     ");
    //     $stmt->bind_param("i", $employeeInfo['id']);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $totalPaid = $result->fetch_assoc()['total_paid'] ?? 0; // Default to 0 if null
    //     $analytics['total_paid'] = $totalPaid;
    //     $analytics['total_annual_payments'] = $totalPaid; // Assuming total paid is already annual
    //     $stmt->close();
    
    //     // 6. Overdue Invoices for the Employee
    //     $stmt = $this->conn->prepare("
    //         SELECT COUNT(*) AS overdue_invoices
    //         FROM invoices i
    //         JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number
    //         WHERE p.staff_id = ? AND i.payment_status = 'unpaid'
    //     ");
    //     $stmt->bind_param("i", $employeeInfo['id']);
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    //     $analytics['overdue_invoices'] = $result->fetch_assoc()['overdue_invoices'] ?? 0; // Default to 0 if null
    //     $stmt->close();
    
    //     // Return JSON response
    //     return json_encode([
    //         "status" => "success",
    //         "data" => $analytics
    //     ]);
    // }

    public function getEmployeeAnalytics($employeeTaxNumber) { 
        // Initialize an array to hold the analytics 
        $analytics = [];
    
        // 1. Employee Overview
        $stmt = $this->conn->prepare("SELECT id, fullname, email, phone, status FROM special_user_employees WHERE employee_taxnumber = ?");
        $stmt->bind_param("s", $employeeTaxNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $employeeInfo = $result->fetch_assoc();
    
        if ($employeeInfo) {
            $analytics['employee_info'] = $employeeInfo;
        } else {
            return json_encode(['status' => 'error', 'message' => 'Employee not found.']);
        }
        $stmt->close();
    
        // 2. Total Invoices for the Employee
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total_invoices 
            FROM invoices i 
            JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number 
            WHERE p.staff_id = ?
        ");
        $stmt->bind_param("i", $employeeInfo['id']); // Use the employee's ID
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics['total_invoices'] = $result->fetch_assoc()['total_invoices'] ?? 0; // Ensure default value of 0 if no invoices
        $stmt->close();
    
        // 3. Total Amount Invoiced for the Employee
        $stmt = $this->conn->prepare("
            SELECT SUM(p.monthly_tax_payable) AS total_amount_invoiced 
            FROM invoices i 
            JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number 
            WHERE p.staff_id = ?
        ");
        $stmt->bind_param("i", $employeeInfo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics['total_amount_invoiced'] = $result->fetch_assoc()['total_amount_invoiced'] ?? 0; // Default to 0 if null
        $stmt->close();
    
        // 4. Total Monthly Tax Payable from `employee_salary_and_benefits` Table
        $stmt = $this->conn->prepare("SELECT monthly_tax_payable FROM employee_salary_and_benefits WHERE employee_id = ?");
        $stmt->bind_param("i", $employeeInfo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyTaxPayable = $result->fetch_assoc()['monthly_tax_payable'] ?? 0; // Default to 0 if null
        $analytics['total_monthly_tax_payable'] = $monthlyTaxPayable;
        $analytics['total_annual_tax_payable'] = $monthlyTaxPayable * 12; // Calculate annual tax
        $stmt->close();
    
        // 5. Payments Made by the Employee
        $stmt = $this->conn->prepare("
            SELECT SUM(p.monthly_tax_payable) AS total_paid
            FROM invoices i
            JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number
            WHERE p.staff_id = ? AND i.payment_status = 'paid'
        ");
        $stmt->bind_param("i", $employeeInfo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalPaid = $result->fetch_assoc()['total_paid'] ?? 0; // Default to 0 if null
        $analytics['total_paid'] = $totalPaid;
        $analytics['total_annual_payments'] = $totalPaid; // Assuming total paid is already annual
        $stmt->close();
    
        // 6. Overdue Invoices for the Employee
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS overdue_invoices
            FROM invoices i
            JOIN paye_invoice_staff p ON i.invoice_number = p.invoice_number
            WHERE p.staff_id = ? AND i.payment_status = 'unpaid'
        ");
        $stmt->bind_param("i", $employeeInfo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics['overdue_invoices'] = $result->fetch_assoc()['overdue_invoices'] ?? 0; // Default to 0 if null
        $stmt->close();
    
        // Return JSON response
        return json_encode([
            "status" => "success",
            "data" => $analytics
        ]);
    }
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
