<?php
require_once 'config/database.php';
require_once 'controllers/EmailController.php';

$emailController = new EmailController();
class TaxpayerController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Method to check verification status of a taxpayer
    public function checkVerificationStatus($queryParams) {
        // Ensure at least one identifier is provided
        if (empty($queryParams['tax_number']) && empty($queryParams['phone']) && empty($queryParams['email'])) {
            return json_encode(["status" => "error", "message" => "Provide tax_number, phone, or email to check verification status"]);
        }

        // Base query
        $query = "
            SELECT t.tax_number, t.first_name, t.surname, ts.verification_status, ts.tin_status
            FROM taxpayer t
            INNER JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
            WHERE 1=1
        ";
        $params = [];
        $types = "";

        // Add conditions based on input
        if (!empty($queryParams['tax_number'])) {
            $query .= " AND t.tax_number = ?";
            $params[] = $queryParams['tax_number'];
            $types .= "s";
        }
        if (!empty($queryParams['phone'])) {
            $query .= " AND t.phone = ?";
            $params[] = $queryParams['phone'];
            $types .= "s";
        }
        if (!empty($queryParams['email'])) {
            $query .= " AND t.email = ?";
            $params[] = $queryParams['email'];
            $types .= "s";
        }

        // Execute query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if taxpayer exists
        if ($result->num_rows === 0) {
            return json_encode(["status" => "error", "message" => "Taxpayer not found"]);
        }

        // Fetch taxpayer data
        $taxpayer = $result->fetch_assoc();

        // Return JSON response
        return json_encode([
            "status" => "success",
            "data" => [
                "tax_number" => $taxpayer['tax_number'],
                "first_name" => $taxpayer['first_name'],
                "surname" => $taxpayer['surname'],
                "verification_status" => $taxpayer['verification_status'], // "verified", "pending", etc.
                "tin_status" => $taxpayer['tin_status'] // "verified", "unverified", etc.
            ]
        ]);
    }

    // Verify taxpayer account using a verification code
    public function verifyTaxpayer($input) {
        // Validate input
        if (empty($input['tax_number']) && empty($input['phone']) && empty($input['email'])) {
            return json_encode(["status" => "error", "message" => "Provide tax_number, phone, or email"]);
        }
        if (empty($input['verification_code'])) {
            return json_encode(["status" => "error", "message" => "Verification code is required"]);
        }

        // Determine the identifier (tax_number, phone, or email)
        $taxIdentifier = !empty($input['tax_number']) ? $input['tax_number'] : (!empty($input['phone']) ? $input['phone'] : $input['email']);
        $verificationCode = $input['verification_code'];

        // Base query to find the taxpayer
        $query = "
            SELECT ts.verification_code, ts.verification_status, t.tax_number, t.phone, t.email
            FROM taxpayer t
            INNER JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
            WHERE (t.tax_number = ? OR t.phone = ? OR t.email = ?)
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $taxIdentifier, $taxIdentifier, $taxIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if taxpayer exists
        if ($result->num_rows === 0) {
            return json_encode(["status" => "error", "message" => "Taxpayer not found"]);
        }

        // Fetch taxpayer data
        $taxpayer = $result->fetch_assoc();

        // Validate verification code
        if ($taxpayer['verification_code'] !== $verificationCode) {
            return json_encode(["status" => "error", "message" => "Invalid verification code"]);
        }

        // Check if already verified
        if ($taxpayer['verification_status'] === 'verified') {
            return json_encode(["status" => "error", "message" => "Account already verified"]);
        }

        // Update verification status
        $updateQuery = "UPDATE taxpayer_security SET verification_status = 'verified' WHERE verification_code = ?";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bind_param("s", $verificationCode);
        $updateStmt->execute();

        if ($updateStmt->affected_rows > 0) {
            return json_encode(["status" => "success", "message" => "Account successfully verified"]);
        } else {
            return json_encode(["status" => "error", "message" => "Failed to verify account"]);
        }
    }

    public function regenerateVerificationCode($input) {
        // Validate input
        if (empty($input['tax_number']) && empty($input['phone']) && empty($input['email'])) {
            return json_encode(["status" => "error", "message" => "Provide tax_number, phone, or email"]);
        }

        // Determine the identifier (tax_number, phone, or email)
        $taxIdentifier = !empty($input['tax_number']) ? $input['tax_number'] : (!empty($input['phone']) ? $input['phone'] : $input['email']);

        // Check if taxpayer exists
        $query = "
            SELECT ts.verification_status, t.id AS taxpayer_id, t.email, t.first_name, t.surname
            FROM taxpayer t
            INNER JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
            WHERE t.tax_number = ? OR t.phone = ? OR t.email = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $taxIdentifier, $taxIdentifier, $taxIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return json_encode(["status" => "error", "message" => "Taxpayer not found"]);
        }

        $taxpayer = $result->fetch_assoc();
        // Check if the account is already verified
        if ($taxpayer['verification_status'] === 'verified') {
            return json_encode(["status" => "error", "message" => "Account is already verified"]);
        }

        // Generate a new verification code
        $newVerificationCode = rand(100000, 999999);

        // Update the verification code in the database
        $updateQuery = "UPDATE taxpayer_security SET verification_code = ? WHERE taxpayer_id = ?";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $newVerificationCode, $taxpayer['taxpayer_id']);
        $updateStmt->execute();


        if ($updateStmt->affected_rows > 0) {
            global $emailController;
            $emailController->userVerificationEmail($taxpayer['email'], $taxpayer['first_name'], $taxpayer['surname'], $newVerificationCode);
            return json_encode([
                "status" => "success",
                "message" => "New verification code generated"
            ]);
        } else {
            return json_encode(["status" => "error", "message" => "Failed to regenerate verification code"]);
        }
    }


    // public function getAllTaxpayers($queryParams)
    // {
    //     $taxpayers = [];

    //     $params = [];
    //     $types = "";

    //     // Define taxpayer query
    //     $taxpayerQuery = "
    //         SELECT t.id, t.created_by, t.tax_number, t.category, t.presumptive, t.first_name, 
    //             t.surname, t.email, t.phone, t.state, t.lga, t.address, t.employment_status, 
    //             t.number_of_staff, t.business_own, t.created_time, t.updated_time, 
    //             ts.tin_status, 'taxpayer' AS source
    //         FROM taxpayer t
    //         INNER JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
    //         WHERE 1=1
    //     ";

    //     // Define enumerator query
    //     $enumeratorQuery = "
    //         SELECT etp.id, NULL AS created_by, etp.tax_number, NULL AS category, NULL AS presumptive, 
    //             etp.first_name, etp.last_name AS surname, etp.email, etp.phone, 
    //             etp.state, etp.lga, etp.address, etp.employment_status, 
    //             etp.staff_quota AS number_of_staff, NULL AS business_own, 
    //             etp.timeIn AS created_time, NULL AS updated_time, 
    //             etp.tin_status, 'enumerator_tax_payers' AS source
    //         FROM enumerator_tax_payers etp
    //         WHERE 1=1
    //     ";

    //     // Add filters to taxpayer query
    //     foreach ($queryParams as $key => $value) {
    //         switch ($key) {
    //             case 'id':
    //                 $taxpayerQuery .= " AND t.id = ?";
    //                 $enumeratorQuery .= " AND etp.id = ?";
    //                 $params[] = $value;
    //                 $types .= "i";
    //                 break;
    //             case 'created_by':
    //                 $taxpayerQuery .= " AND t.created_by = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'tax_number':
    //                 $taxpayerQuery .= " AND t.tax_number LIKE ?";
    //                 $enumeratorQuery .= " AND etp.tax_number LIKE ?";
    //                 $params[] = '%' . $value . '%';
    //                 $types .= "s";
    //                 break;
    //             case 'category':
    //                 $taxpayerQuery .= " AND t.category = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'presumptive':
    //                 $taxpayerQuery .= " AND t.presumptive = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'first_name':
    //                 $taxpayerQuery .= " AND t.first_name LIKE ?";
    //                 $enumeratorQuery .= " AND etp.first_name LIKE ?";
    //                 $params[] = '%' . $value . '%';
    //                 $types .= "s";
    //                 break;
    //             case 'surname':
    //                 $taxpayerQuery .= " AND t.surname LIKE ?";
    //                 $enumeratorQuery .= " AND etp.last_name LIKE ?";
    //                 $params[] = '%' . $value . '%';
    //                 $types .= "s";
    //                 break;
    //             case 'email':
    //                 $taxpayerQuery .= " AND t.email LIKE ?";
    //                 $enumeratorQuery .= " AND etp.email LIKE ?";
    //                 $params[] = '%' . $value . '%';
    //                 $types .= "s";
    //                 break;
    //             case 'phone':
    //                 $taxpayerQuery .= " AND t.phone = ?";
    //                 $enumeratorQuery .= " AND etp.phone = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'state':
    //                 $taxpayerQuery .= " AND t.state = ?";
    //                 $enumeratorQuery .= " AND etp.state = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'lga':
    //                 $taxpayerQuery .= " AND t.lga = ?";
    //                 $enumeratorQuery .= " AND etp.lga = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'address':
    //                 $taxpayerQuery .= " AND t.address LIKE ?";
    //                 $enumeratorQuery .= " AND etp.address LIKE ?";
    //                 $params[] = '%' . $value . '%';
    //                 $types .= "s";
    //                 break;
    //             case 'employment_status':
    //                 $taxpayerQuery .= " AND t.employment_status = ?";
    //                 $enumeratorQuery .= " AND etp.employment_status = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'number_of_staff_min':
    //             case 'number_of_staff_max':
    //                 if (isset($queryParams['number_of_staff_min'], $queryParams['number_of_staff_max'])) {
    //                     $taxpayerQuery .= " AND t.number_of_staff BETWEEN ? AND ?";
    //                     $enumeratorQuery .= " AND etp.staff_quota BETWEEN ? AND ?";
    //                     $params[] = $queryParams['number_of_staff_min'];
    //                     $params[] = $queryParams['number_of_staff_max'];
    //                     $types .= "ii";
    //                 }
    //                 break;
    //             case 'business_own':
    //                 $taxpayerQuery .= " AND t.business_own = ?";
    //                 $params[] = $value;
    //                 $types .= "s";
    //                 break;
    //             case 'created_time_start':
    //             case 'created_time_end':
    //                 if (isset($queryParams['created_time_start'], $queryParams['created_time_end'])) {
    //                     $taxpayerQuery .= " AND t.created_time BETWEEN ? AND ?";
    //                     $enumeratorQuery .= " AND etp.timeIn BETWEEN ? AND ?";
    //                     $params[] = $queryParams['created_time_start'];
    //                     $params[] = $queryParams['created_time_end'];
    //                     $types .= "ss";
    //                 }
    //                 break;
    //             case 'updated_time_start':
    //             case 'updated_time_end':
    //                 if (isset($queryParams['updated_time_start'], $queryParams['updated_time_end'])) {
    //                     $taxpayerQuery .= " AND t.updated_time BETWEEN ? AND ?";
    //                     $params[] = $queryParams['updated_time_start'];
    //                     $params[] = $queryParams['updated_time_end'];
    //                     $types .= "ss";
    //                 }
    //                 break;
    //         }
    //     }

    //     // Execute taxpayer query
    //     $stmt1 = $this->conn->prepare($taxpayerQuery);
    //     if (!empty($types)) {
    //         $stmt1->bind_param($types, ...$params);
    //     }
    //     $stmt1->execute();
    //     $result1 = $stmt1->get_result();
    //     while ($row = $result1->fetch_assoc()) {
    //         $taxpayers[] = $row;
    //     }
    //     $stmt1->close();

    //     // Execute enumerator query
    //     $stmt2 = $this->conn->prepare($enumeratorQuery);
    //     if (!empty($types)) {
    //         $stmt2->bind_param($types, ...$params);
    //     }
    //     $stmt2->execute();
    //     $result2 = $stmt2->get_result();
    //     while ($row = $result2->fetch_assoc()) {
    //         $taxpayers[] = $row;
    //     }
    //     $stmt2->close();

    //     // Pagination
    //     $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    //     $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    //     $offset = ($page - 1) * $limit;

    //     $paginatedTaxpayers = array_slice($taxpayers, $offset, $limit);
    //     $totalRecords = count($taxpayers);
    //     $totalPages = ceil($totalRecords / $limit);

    //     // Return the response
    //     echo json_encode([
    //         "status" => "success",
    //         "data" => $paginatedTaxpayers,
    //         "pagination" => [
    //             "current_page" => $page,
    //             "per_page" => $limit,
    //             "total_pages" => $totalPages,
    //             "total_records" => $totalRecords
    //         ]
    //     ]);
    // }

    public function getAllTaxpayers($queryParams) {
        $taxpayers = [];
        $paramsTaxpayer = [];
        $paramsEnumerator = [];
        $typesTaxpayer = "";
        $typesEnumerator = "";

        // Define taxpayer query
        $taxpayerQuery = "
            SELECT t.id, t.created_by, t.tax_number, t.category, t.presumptive, t.first_name, 
                t.surname, t.email, t.phone, t.state, t.lga, t.address, t.employment_status, 
                t.number_of_staff, t.business_own, t.created_time, t.updated_time, 
                ts.tin_status, 'taxpayer' AS source
            FROM taxpayer t
            INNER JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
            WHERE 1=1
        ";

        // Define enumerator query
        $enumeratorQuery = "
            SELECT etp.id, NULL AS created_by, etp.tax_number, NULL AS category, NULL AS presumptive, 
                etp.first_name, etp.last_name AS surname, etp.email, etp.phone, 
                etp.state, etp.lga, etp.address, etp.employment_status, 
                etp.staff_quota AS number_of_staff, NULL AS business_own, 
                etp.timeIn AS created_time, NULL AS updated_time, 
                etp.tin_status, 'enumerator_tax_payers' AS source
            FROM enumerator_tax_payers etp
            WHERE 1=1
        ";

        // Check for taxpayer type filter
        if (isset($queryParams['type'])) {
            $type = $queryParams['type'];
            if ($type === 'normal') {
                $enumeratorQuery = ""; // Clear enumerator query
            } elseif ($type === 'enumerator') {
                $taxpayerQuery = ""; // Clear taxpayer query
            }
        }

        // Add filters to taxpayer query
        $queryParams = array_filter($queryParams);
        foreach ($queryParams as $key => $value) {
            switch ($key) {
                case 'id':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.id = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "i";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.id = ?";
                        $paramsEnumerator[] = $value;
                        $typesEnumerator .= "i";
                    }
                    break;
                case 'created_by':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.created_by = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    break;
                case 'tax_number':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.tax_number LIKE ?";
                        $paramsTaxpayer[] = '%' . $value . '%';
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.tax_number LIKE ?";
                        $paramsEnumerator[] = '%' . $value . '%';
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'category':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.category = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    break;
                case 'presumptive':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.presumptive = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    break;
                case 'first_name':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.first_name LIKE ?";
                        $paramsTaxpayer[] = '%' . $value . '%';
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.first_name LIKE ?";
                        $paramsEnumerator[] = '%' . $value . '%';
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'surname':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.surname LIKE ?";
                        $paramsTaxpayer[] = '%' . $value . '%';
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.last_name LIKE ?";
                        $paramsEnumerator[] = '%' . $value . '%';
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'email':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.email LIKE ?";
                        $paramsTaxpayer[] = '%' . $value . '%';
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.email LIKE ?";
                        $paramsEnumerator[] = '%' . $value . '%';
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'phone':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.phone = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.phone = ?";
                        $paramsEnumerator[] = $value;
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'state':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.state = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.state = ?";
                        $paramsEnumerator[] = $value;
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'lga':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.lga = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.lga = ?";
                        $paramsEnumerator[] = $value;
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'address':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.address LIKE ?";
                        $paramsTaxpayer[] = '%' . $value . '%';
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.address LIKE ?";
                        $paramsEnumerator[] = '%' . $value . '%';
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'employment_status':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.employment_status = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    if (!empty($enumeratorQuery)) {
                        $enumeratorQuery .= " AND etp.employment_status = ?";
                        $paramsEnumerator[] = $value;
                        $typesEnumerator .= "s";
                    }
                    break;
                case 'number_of_staff_min':
                    if (isset($queryParams['number_of_staff_min'], $queryParams['number_of_staff_max'])) {
                        if (!empty($taxpayerQuery)) {
                            $taxpayerQuery .= " AND t.number_of_staff BETWEEN ? AND ?";
                            $paramsTaxpayer[] = $queryParams['number_of_staff_min'];
                            $paramsTaxpayer[] = $queryParams['number_of_staff_max'];
                            $typesTaxpayer .= "ii";
                        }
                        if (!empty($enumeratorQuery)) {
                            $enumeratorQuery .= " AND etp.staff_quota BETWEEN ? AND ?";
                            $paramsEnumerator[] = $queryParams['number_of_staff_min'];
                            $paramsEnumerator[] = $queryParams['number_of_staff_max'];
                            $typesEnumerator .= "ii";
                        }
                    }
                    break;
                case 'business_own':
                    if (!empty($taxpayerQuery)) {
                        $taxpayerQuery .= " AND t.business_own = ?";
                        $paramsTaxpayer[] = $value;
                        $typesTaxpayer .= "s";
                    }
                    break;
                case 'created_time_start':
                    if (isset($queryParams['created_time_start'], $queryParams['created_time_end'])) {
                        if (!empty($taxpayerQuery)) {
                            $taxpayerQuery .= " AND t.created_time BETWEEN ? AND ?";
                            $paramsTaxpayer[] = $queryParams['created_time_start'];
                            $paramsTaxpayer[] = $queryParams['created_time_end'];
                            $typesTaxpayer .= "ss";
                        }
                        if (!empty($enumeratorQuery)) {
                            $enumeratorQuery .= " AND etp.timeIn BETWEEN ? AND ?";
                            $paramsEnumerator[] = $queryParams['created_time_start'];
                            $paramsEnumerator[] = $queryParams['created_time_end'];
                            $typesEnumerator .= "ss";
                        }
                    }
                    break;
                case 'updated_time_start':
                    if (isset($queryParams['updated_time_start'], $queryParams['updated_time_end'])) {
                        if (!empty($taxpayerQuery)) {
                            $taxpayerQuery .= " AND t.updated_time BETWEEN ? AND ?";
                            $paramsTaxpayer[] = $queryParams['updated_time_start'];
                            $paramsTaxpayer[] = $queryParams['updated_time_end'];
                            $typesTaxpayer .= "ss";
                        }
                    }
                    break;
            }
        }

        // Execute taxpayer query if it's not empty
        if (!empty($taxpayerQuery)) {
            $stmt1 = $this->conn->prepare($taxpayerQuery);
            if (!empty($typesTaxpayer)) {
                $stmt1->bind_param($typesTaxpayer, ...$paramsTaxpayer);
            }
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            while ($row = $result1->fetch_assoc()) {
                $taxpayers[] = $row;
            }
            $stmt1->close();
        }

        // Execute enumerator query if it's not empty
        if (!empty($enumeratorQuery)) {
            $stmt2 = $this->conn->prepare($enumeratorQuery);
            if (!empty($typesEnumerator)) {
                $stmt2->bind_param($typesEnumerator, ...$paramsEnumerator);
            }
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row = $result2->fetch_assoc()) {
                $taxpayers[] = $row;
            }
            $stmt2->close();
        }

        // Handle empty results
        if (empty($taxpayers)) {
            echo json_encode([
                "status" => "success",
                "data" => [],
                "message" => "No records found."
            ]);
            return; // Exit the function early
        }

        // Pagination
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $paginatedTaxpayers = array_slice($taxpayers, $offset, $limit);
        $totalRecords = count($taxpayers);
        $totalPages = ceil($totalRecords / $limit);

        // Return the response
        echo json_encode([
            "status" => "success",
            "data" => $paginatedTaxpayers,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $totalRecords
            ]
        ]);
}

    // Get taxpayer statistics (total, self-registered, admin-registered)[admin]
    public function getTaxpayerStatistics() {
        // Query to count total taxpayers
        $totalQuery = "SELECT COUNT(*) AS total FROM taxpayer";

        // Query to count self-registered taxpayers
        $selfQuery = "SELECT COUNT(*) AS total_self FROM taxpayer WHERE created_by = 'self'";

        // Query to count admin-registered taxpayers
        $adminQuery = "SELECT COUNT(*) AS total_admin FROM taxpayer WHERE created_by = 'admin'";

        // Query to count Inactive taxpayers
        $adminQueryInactiveTaxpayers = "SELECT COUNT(*) AS total_admin_inactive_taxpayer FROM taxpayer_security WHERE verification_status = 'pending'";

        // Query to count Active taxpayers
        $adminQueryActiveTaxpayers = "SELECT COUNT(*) AS total_admin_active_taxpayer FROM taxpayer_security WHERE verification_status = 'verified'";

        try {
            // Execute total taxpayers query
            $totalResult = $this->conn->query($totalQuery);
            $totalCount = $totalResult->fetch_assoc()['total'];

            // Execute self-registered taxpayers query
            $selfResult = $this->conn->query($selfQuery);
            $selfCount = $selfResult->fetch_assoc()['total_self'];

            // Execute admin-registered taxpayers query
            $adminResult = $this->conn->query($adminQuery);
            $adminCount = $adminResult->fetch_assoc()['total_admin'];

            // Execute inactive taxpayers query
            $adminQueryInactiveTaxpayersResult = $this->conn->query($adminQueryInactiveTaxpayers);
            $adminQueryInactiveTaxpayersCount = $adminQueryInactiveTaxpayersResult->fetch_assoc()['total_admin_inactive_taxpayer'];

            // Execute acitve taxpayers query
            $adminQueryActiveTaxpayersResult = $this->conn->query($adminQueryActiveTaxpayers);
            $adminQueryActiveTaxpayersCount = $adminQueryActiveTaxpayersResult->fetch_assoc()['total_admin_active_taxpayer'];

            // Return JSON response
            return json_encode([
                "status" => "success",
                "data" => [
                    "total_registered_taxpayers" => (int)$totalCount,
                    "total_self_registered_taxpayers" => (int)$selfCount,
                    "total_admin_registered_taxpayers" => (int)$adminCount,
                    "total_admin_inactive_taxpayer" => (int)$adminQueryInactiveTaxpayersCount,
                    "total_admin_active_taxpayer" => (int)$adminQueryActiveTaxpayersCount
                ]
            ]);
        } catch (Exception $e) {
            return json_encode([
                "status" => "error",
                "message" => "Failed to fetch statistics",
                "error" => $e->getMessage()
            ]);
        }
    }

    // Fetch all TIN requests with optional filters
    // public function fetchAllTINRequest($queryParams) {
    //     // Base SQL query to fetch all TIN requests with optional joins
    //     $query = "SELECT t.tin, t.taxpayer_id, t.status, t.current_stage, t.taxpayer_email, t.created_at, t.updated_at, 
    //                      tp.first_name, tp.surname, t.taxpayer_email, tp.phone, tp.state, tp.lga, 
    //                      tp.address, t.created_at, t.updated_at
    //               FROM tin_generator t
    //               JOIN taxpayer tp ON t.taxpayer_id = tp.id WHERE 1=1"; // Always true for filtering
        
    //     // Prepare params and types
    //     $params = [];
    //     $types = "";
    
    //     // Apply filters based on query parameters
    //     if (!empty($queryParams['tin'])) {
    //         $query .= " AND t.tin = ?";
    //         $params[] = $queryParams['tin'];
    //         $types .= "s"; // Assuming taxpayer_id is an integer
    //     }

    //     if (!empty($queryParams['status'])) {
    //         $query .= " AND t.status = ?";
    //         $params[] = $queryParams['status'];
    //         $types .= "s"; // Assuming status is an enum(approved, decline)
    //     }

    //     if (!empty($queryParams['current_stage'])) {
    //         $query .= " AND t.current_stage = ?";
    //         $params[] = $queryParams['current_stage'];
    //         $types .= "s"; // Assuming current_stage is an enum('submitted', 'reviewer', 'director_approval')
    //     }
    
    //     if (!empty($queryParams['first_name'])) {
    //         $query .= " AND tp.first_name LIKE ?";
    //         $params[] = '%' . $queryParams['first_name'] . '%';
    //         $types .= "s"; // Assuming first_name is a string
    //     }
    
    //     if (!empty($queryParams['surname'])) {
    //         $query .= " AND tp.surname LIKE ?";
    //         $params[] = '%' . $queryParams['surname'] . '%';
    //         $types .= "s"; // Assuming surname is a string
    //     }
    
    //     if (!empty($queryParams['email'])) {
    //         $query .= " AND t.taxpayer_email LIKE ?";
    //         $params[] = '%' . $queryParams['email'] . '%';
    //         $types .= "s"; // Assuming email is a string
    //     }
    
    //     // Handle pagination if provided
    //     $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
    //     $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
    //     $offset = ($page - 1) * $limit;
    
    //     // Add LIMIT and OFFSET for pagination
    //     $query .= " LIMIT ? OFFSET ?";
    //     $params[] = $limit;
    //     $params[] = $offset;
    //     $types .= "ii"; // Two integer parameters for limit and offset
    
    //     // Prepare the SQL statement
    //     $stmt = $this->conn->prepare($query);
    //     // print_r($params);
    //     // echo "\n";
    //     // echo $types;
    //     // Bind parameters dynamically
    //     if ($params) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    
    //     // Execute the query
    //     $stmt->execute();
        
    //     // Get the result and return
    //     $result = $stmt->get_result();
        
    //     // Check if any rows were returned
    //     if ($result->num_rows > 0) {
    //         $results = $result->fetch_all(MYSQLI_ASSOC);
            
    //         // // Get the total number of records for pagination
    //         // $total_query = "SELECT COUNT(*) AS total_count FROM tin_generator t
    //         //                 JOIN taxpayer tp ON t.taxpayer_id = tp.id WHERE 1=1";
            
    //         // // Reapply the filters for the total count query
    //         // if (!empty($queryParams['taxpayer_id'])) {
    //         //     $total_query .= " AND t.taxpayer_id = ?";
    //         // }
    //         // if (!empty($queryParams['first_name'])) {
    //         //     $total_query .= " AND tp.first_name LIKE ?";
    //         // }
    //         // if (!empty($queryParams['surname'])) {
    //         //     $total_query .= " AND tp.surname LIKE ?";
    //         // }
    //         // if (!empty($queryParams['email'])) {
    //         //     $total_query .= " AND tp.email LIKE ?";
    //         // }
    
    //         // $total_stmt = $this->conn->prepare($total_query);
            
    //         // // Bind parameters dynamically for the total query
    //         // $total_params = $params; // Same parameters for total count query
    //         // $total_types = $types; // Same types for total count query
    //         // $total_stmt->bind_param($total_types, ...$total_params);
    
    //         // $total_stmt->execute();
    //         // $total_result = $total_stmt->get_result();
    //         // $total_count = $total_result->fetch_assoc()['total_count'];
            
    //         // // Calculate the total number of pages
    //         // $total_pages = ceil($total_count / $limit);
            
    //         // Return paginated results with metadata
    //         return [
    //             'current_page' => $page,
    //             'per_page' => $limit,
    //             // 'total_count' => $total_count,
    //             // 'total_pages' => $total_pages,
    //             'data' => $results
    //         ];
    //     } else {
    //         return ["message" => "No TIN requests found"];
    //     }
    // }
    
    public function fetchAllTINRequest($queryParams) {
        // Base SQL query to fetch all TIN requests with optional joins
        $query = "SELECT t.id, t.tin, t.taxpayer_id, t.status, t.current_stage, t.taxpayer_email, t.created_at, t.updated_at, 
                         tp.first_name, tp.surname, t.taxpayer_email, tp.phone, tp.state, tp.lga, 
                         tp.address, t.created_at, t.updated_at
                  FROM tin_generator t
                  JOIN taxpayer tp ON t.taxpayer_id = tp.id WHERE 1=1"; // Always true for filtering
        
        // Prepare params and types
        $params = [];
        $types = "";
    
        // Apply filters based on query parameters
        if (!empty($queryParams['tin'])) {
            $query .= " AND t.tin = ?";
            $params[] = $queryParams['tin'];
            $types .= "s"; // Assuming tin is a string
        }
    
        if (!empty($queryParams['status'])) {
            $query .= " AND t.status = ?";
            $params[] = $queryParams['status'];
            $types .= "s"; // Assuming status is an enum(approved, decline)
        }
    
        if (!empty($queryParams['current_stage'])) {
            $query .= " AND t.current_stage = ?";
            $params[] = $queryParams['current_stage'];
            $types .= "s"; // Assuming current_stage is an enum('submitted', 'reviewer', 'director_approval')
        }
    
        if (!empty($queryParams['first_name'])) {
            $query .= " AND tp.first_name LIKE ?";
            $params[] = '%' . $queryParams['first_name'] . '%';
            $types .= "s"; // Assuming first_name is a string
        }
    
        if (!empty($queryParams['surname'])) {
            $query .= " AND tp.surname LIKE ?";
            $params[] = '%' . $queryParams['surname'] . '%';
            $types .= "s"; // Assuming surname is a string
        }
    
        if (!empty($queryParams['email'])) {
            $query .= " AND t.taxpayer_email LIKE ?";
            $params[] = '%' . $queryParams['email'] . '%';
            $types .= "s"; // Assuming email is a string
        }
    
        // Add ORDER BY clause to fetch the latest data
        $query .= " ORDER BY t.created_at DESC"; // or t.updated_at DESC if you prefer
    
        // Handle pagination if provided
        $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        // Add LIMIT and OFFSET for pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii"; // Two integer parameters for limit and offset
    
        // Prepare the SQL statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters dynamically
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
    
        // Execute the query
        $stmt->execute();
        
        // Get the result and return
        $result = $stmt->get_result();
        
        // Check if any rows were returned
        if ($result->num_rows > 0) {
            $results = $result->fetch_all(MYSQLI_ASSOC);
            
            // Return paginated results with metadata
            return [
                'current_page' => $page,
                'per_page' => $limit,
                'data' => $results
            ];
        } else {
            return ["message" => "No TIN requests found"];
        }
    }

    // Update TIN status of a taxpayer
    public function updateTinStatus($input) {
        // Validate input
        if (empty($input['tax_number']) && empty($input['phone']) && empty($input['email'])) {
            return json_encode(["status" => "error", "message" => "Provide tax_number, phone, or email"]);
        }
        if (empty($input['tin_status']) || !in_array($input['tin_status'], ['issued', 'pending'])) {
            return json_encode(["status" => "error", "message" => "TIN status must be either 'Issued' or 'Pending'"]);
        }

        // Determine the identifier (tax_number, phone, or email)
        $identifier = !empty($input['tax_number']) ? $input['tax_number'] : (!empty($input['phone']) ? $input['phone'] : $input['email']);
        $tinStatus = $input['tin_status'];

        // Query to update TIN status
        $query = "
            UPDATE taxpayer_security ts
            INNER JOIN taxpayer t ON t.id = ts.taxpayer_id
            SET ts.tin_status = ?
            WHERE t.tax_number = ? OR t.phone = ? OR t.email = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssss", $tinStatus, $identifier, $identifier, $identifier);
        $stmt->execute();

        // Check if the update was successful
        if ($stmt->affected_rows > 0) {
            return json_encode(["status" => "success", "message" => "TIN status updated successfully"]);
        } else {
            return json_encode(["status" => "error", "message" => "Failed to update TIN status or no changes were made"]);
        }
    }

    public function forgotPassword($data) {
        // Validate input
        if (empty($data['tax_number']) && empty($data['email'])) {
            echo json_encode(['status' => 'error', 'message' => 'Either tax number or email is required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        $taxNumber = null;
        $email = isset($data['email']) ? $data['email'] : null;
        $tableType = null;
    
        // Check if tax number or email exists in the taxpayer table
        if (!empty($data['tax_number'])) {
            $queryTaxpayer = "SELECT tax_number FROM taxpayer WHERE tax_number = ?";
            $stmt = $this->conn->prepare($queryTaxpayer);
            $stmt->bind_param('s', $data['tax_number']);
        } elseif (!empty($email)) {
            $queryTaxpayer = "SELECT tax_number FROM taxpayer WHERE email = ?";
            $stmt = $this->conn->prepare($queryTaxpayer);
            $stmt->bind_param('s', $email);
        }
    
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $taxNumber = $row['tax_number'];
            $tableType = 'taxpayer';
        } else {
            // Check if tax number or email exists in the enumerator_tax_payers table
            if (!empty($data['tax_number'])) {
                $queryEnumerator = "SELECT tax_number FROM enumerator_tax_payers WHERE tax_number = ?";
                $stmt = $this->conn->prepare($queryEnumerator);
                $stmt->bind_param('s', $data['tax_number']);
            } elseif (!empty($email)) {
                $queryEnumerator = "SELECT tax_number FROM enumerator_tax_payers WHERE email = ?";
                $stmt = $this->conn->prepare($queryEnumerator);
                $stmt->bind_param('s', $email);
            }
    
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $taxNumber = $row['tax_number'];
                $tableType = 'enumerator_tax_payers';
            }
        }
        $stmt->close();
    
        // If tax number not found, return an error
        if (!$taxNumber) {
            echo json_encode(['status' => 'error', 'message' => 'Tax number or email not found']);
            http_response_code(404); // Not Found
            return;
        }
    
        // Generate a unique reset token
        $resetToken = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
    
        // Insert the reset token into the password_resets table
        $queryInsert = "
            INSERT INTO password_resets (tax_number, reset_token, expires_at, table_type)
            VALUES (?, ?, ?, ?)
        ";
        $stmt = $this->conn->prepare($queryInsert);
        $stmt->bind_param('ssss', $taxNumber, $resetToken, $expiresAt, $tableType);
    
        if ($stmt->execute()) {
            // TODO: Send the reset token via email or SMS
            $taxpayerDetails = $this->getTaxpayerDetails($taxNumber);
            $email = $taxpayerDetails['email'];
            $firstName = $taxpayerDetails['first_name'];
            $lastName = $taxpayerDetails['last_name'];
            global $emailController;
            $emailController->userResetPasswordEmail($email, $firstName, $lastName, $resetToken);
            echo json_encode([
                'status' => 'success',
                'message' => 'Password reset link has been sent',
                // 'reset_token' => $resetToken
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to generate reset token']);
            http_response_code(500); // Internal Server Error
        }
        $stmt->close();
    }
    
    private function getTaxpayerDetails($taxNumber) {
        // Define the queries to check all relevant tables
        $queries = [
            "SELECT first_name, surname AS last_name, email FROM taxpayer WHERE tax_number = ? LIMIT 1",
            "SELECT first_name, last_name, email FROM enumerator_tax_payers WHERE tax_number = ? LIMIT 1",
            "SELECT name AS first_name, NULL AS last_name, email FROM special_users_ WHERE payer_id = ? LIMIT 1"
        ];
    
        foreach ($queries as $query) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('s', $taxNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            $details = $result->fetch_assoc();
            $stmt->close();
    
            // Return details if found
            if ($details) {
                return $details;
            }
        }
    
        // Return null if no match is found
        return null;
    }

    private function sendEmail($to, $subject, $message) {
        $headers = "From: no-reply@yourdomain.com\r\n"
                 . "Reply-To: support@yourdomain.com\r\n"
                 . "Content-Type: text/plain; charset=UTF-8";
    
        return mail($to, $subject, $message, $headers);
    }

    public function resetPassword($data) {
        // Validate input
        if (empty($data['reset_token']) || empty($data['new_password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Reset token and new password are required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        $resetToken = $data['reset_token'];
        $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
    
        // Check if the reset token exists and is valid
        $query = "SELECT tax_number, table_type FROM password_resets WHERE reset_token = ? AND expires_at > NOW() LIMIT 1";
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
    
        $taxNumber = $resetData['tax_number'];
        $tableType = $resetData['table_type'];
    
        // Update the password in the appropriate table
        if ($tableType === 'taxpayer') {
            // Update taxpayer_security table
            $queryUpdate = "UPDATE taxpayer_security SET password = ? WHERE taxpayer_id = (SELECT id FROM taxpayer WHERE tax_number = ?)";
        } elseif ($tableType === 'enumerator_tax_payers') {
            // Update enumerator_tax_payers table
            $queryUpdate = "UPDATE enumerator_tax_payers SET password = ? WHERE tax_number = ?";
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid table type']);
            http_response_code(500); // Internal Server Error
            return;
        }
    
        $stmt = $this->conn->prepare($queryUpdate);
        $stmt->bind_param('ss', $newPasswordHash, $taxNumber);
    
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
      
    public function fetchAllTIN($filters = []) {
        // Base query to fetch all records
        $query = "SELECT * FROM tin_generator WHERE 1";
        $params = [];
        $types = '';
    
        // Apply filters only if they are provided
        if (!empty($filters['taxpayer_id'])) {
            $query .= " AND taxpayer_id = ?";
            $params[] = $filters['taxpayer_id'];
            $types .= 's';
        }
        if (!empty($filters['taxpayer_email'])) {
            $query .= " AND taxpayer_email = ?";
            $params[] = $filters['taxpayer_email'];
            $types .= 's';
        }
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND created_at BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        }
    
        // Prepare the statement
        $stmt = $this->conn->prepare($query);
    
        // Bind parameters if filters are present
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
    
        // Execute the query and fetch results
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Check if rows were returned
        $tinRecords = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $tinRecords[] = $row;
            }
        }
    
        // Return all records or an empty array
        return $tinRecords;
    }

    public function updateTaxpayerProfile($data) {
        if (empty($data['tax_number'])) {
            echo json_encode(["status" => "error", "message" => "Tax number is required"]);
            http_response_code(400);
            return;
        }
    
        $taxNumber = $data['tax_number'];
        $unsetFields = ['tax_number', 'created_by', 'category', 'presumptive', 'email', 'phone', 'created_time', 'updated_time'];
        foreach ($unsetFields as $field) {
            unset($data[$field]);
        }
    
        if (empty($data)) {
            echo json_encode(["status" => "error", "message" => "No fields to update"]);
            http_response_code(400);
            return;
        }
    
        $fields = [];
        $params = [];
        $types = "";
    
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
            $types .= "s"; 
        }
    
        $params[] = $taxNumber;
        $types .= "s";
    
        $query = "UPDATE taxpayer SET " . implode(", ", $fields) . " WHERE tax_number = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();
    
        if ($success) {
            echo json_encode(["status" => "success", "message" => "Taxpayer profile updated"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update taxpayer profile"]);
            http_response_code(500);
        }
    }
    

    public function getTaxTypeBreakdown($filters) {
        // Base query
        $query = "
            SELECT 
                revenue_head, 
                SUM(amount_paid) AS total_paid 
            FROM 
                invoices 
            WHERE 
                payment_status = 'paid'
        ";
        
        $params = [];
        $types = '';
    
        // Apply filters
        if (!empty($filters['tax_number'])) {
            $query .= " AND tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= 's';
        }
    
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $query .= " AND date_created BETWEEN ? AND ?";
            $params[] = $filters['date_start'];
            $params[] = $filters['date_end'];
            $types .= 'ss';
        }
    
        $query .= " GROUP BY revenue_head";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch and process data
        $breakdown = [];
        $totalPaid = 0;
    
        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
            foreach ($revenueHeads as $revenueHead) {
                $revenueHeadId = $revenueHead['revenue_head_id'];
                $amountPaid = $row['total_paid'];
    
                if (!isset($breakdown[$revenueHeadId])) {
                    $revenueHeadDetails = $this->getRevenueHeadDetails($revenueHeadId);
                    $breakdown[$revenueHeadId] = [
                        'revenue_head_id' => $revenueHeadId,
                        'name' => $revenueHeadDetails['item_name'] ?? 'Unknown',
                        'item_code' => $revenueHeadDetails['item_code'] ?? 'Unknown',
                        'mda_name' => $revenueHeadDetails['mda_name'] ?? 'Unknown',
                        'mda_id' => $revenueHeadDetails['mda_id'] ?? null,
                        'mda_code' => $revenueHeadDetails['mda_code'] ?? null,
                        'amount_paid' => 0
                    ];
                }
    
                $breakdown[$revenueHeadId]['amount_paid'] += $amountPaid;
                $totalPaid += $amountPaid;
            }
        }
        $stmt->close();
    
        // Calculate percentages
        foreach ($breakdown as &$data) {
            $data['percentage'] = $totalPaid > 0 
                ? round(($data['amount_paid'] / $totalPaid) * 100, 2) 
                : 0;
        }
    
        // Return the breakdown
        echo json_encode([
            'status' => 'success',
            'data' => array_values($breakdown)
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
    
    public function getTaxSummary($filters) {
        $taxNumber = isset($filters['tax_number']) ? $filters['tax_number'] : null;
    
        if (!$taxNumber) {
            echo json_encode(['status' => 'error', 'message' => 'Tax number is required']);
            http_response_code(400); // Bad Request
            return;
        }
    
        // Updated query to handle total taxes generated and owed based on amount_paid
        $query = "
            SELECT 
                COUNT(*) AS total_invoices,
                IFNULL(SUM(CASE WHEN i.payment_status = 'unpaid' THEN i.amount_paid ELSE 0 END), 0) AS total_amount_owed,
                IFNULL(SUM(CASE WHEN i.payment_status = 'paid' THEN i.amount_paid ELSE 0 END), 0) AS total_taxes_paid,
                IFNULL(SUM(i.amount_paid), 0) AS total_taxes_generated
            FROM invoices i
            WHERE i.tax_number = ?
        ";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $taxNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
        $stmt->close();
    
        // Return the result as JSON
        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_invoices' => $summary['total_invoices'] ?? 0,
                'total_taxes_generated' => $summary['total_taxes_generated'] ?? 0.00,
                'total_amount_owed' => $summary['total_amount_owed'] ?? 0.00,
                'total_taxes_paid' => $summary['total_taxes_paid'] ?? 0.00
            ]
        ]);
    }

    public function getMonthlyPaymentTrends($filters) {
        $taxNumberCondition = "";
        $params = [];
        $types = "";
    
        // Add tax_number filter if provided
        if (!empty($filters['tax_number'])) {
            $taxNumberCondition = "WHERE user_id = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
    
        // Query for monthly payments
        $paymentsQuery = "
            SELECT 
                MONTH(pc.date_payment_created) AS month,
                COUNT(*) AS total_payments
            FROM payment_collection pc
            $taxNumberCondition
            GROUP BY MONTH(pc.date_payment_created)
        ";
    
        // Query for monthly invoices
        $invoicesQuery = "
            SELECT 
                MONTH(i.date_created) AS month,
                COUNT(*) AS total_invoices
            FROM invoices i
            " . ($taxNumberCondition ? "WHERE tax_number = ?" : "") . "
            GROUP BY MONTH(i.date_created)
        ";
    
        // Prepare and execute payment query
        $stmtPayments = $this->conn->prepare($paymentsQuery);
        if (!empty($params)) {
            $stmtPayments->bind_param($types, ...$params);
        }
        $stmtPayments->execute();
        $resultPayments = $stmtPayments->get_result();
    
        $paymentsData = [];
        while ($row = $resultPayments->fetch_assoc()) {
            $paymentsData[(int)$row['month']] = $row['total_payments'];
        }
        $stmtPayments->close();
    
        // Prepare and execute invoice query
        $stmtInvoices = $this->conn->prepare($invoicesQuery);
        if (!empty($params)) {
            $stmtInvoices->bind_param($types, ...$params);
        }
        $stmtInvoices->execute();
        $resultInvoices = $stmtInvoices->get_result();
    
        $invoicesData = [];
        while ($row = $resultInvoices->fetch_assoc()) {
            $invoicesData[(int)$row['month']] = $row['total_invoices'];
        }
        $stmtInvoices->close();
    
        // Combine data for all 12 months
        $monthlyTrends = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyTrends[] = [
                'month' => DateTime::createFromFormat('!m', $month)->format('F'),
                'payments' => $paymentsData[$month] ?? 0,
                'invoices' => $invoicesData[$month] ?? 0,
            ];
        }
    
        // Return the result as JSON
        echo json_encode([
            'status' => 'success',
            'data' => $monthlyTrends
        ]);
    }

    public function getPaymentAndOutstandingTaxes($filters, $page, $limit) {
        // Set default page and limit if not provided
        $page = isset($page) ? (int)$page : 1;
        $limit = isset($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;
    
        $params = [];
        $types = "";
        $taxNumberCondition = "";
    
        // If tax_number is provided, add it as a filter
        if (!empty($filters['tax_number'])) {
            $taxNumberCondition = "WHERE i.tax_number = ?";
            $params[] = $filters['tax_number'];
            $types .= "s";
        }
    
        // Fetch Payment History
        $paymentHistoryQuery = "
            SELECT 
                i.invoice_number, 
                i.revenue_head, 
                i.amount_paid, 
                i.date_created, 
                i.payment_status 
            FROM invoices i
            $taxNumberCondition
            AND i.payment_status IN ('paid', 'partially paid')
            ORDER BY i.date_created DESC
            LIMIT ? OFFSET ?
        ";
        $paramsHistory = array_merge($params, [$limit, $offset]);
        $typesHistory = $types . "ii";
    
        $stmt = $this->conn->prepare($paymentHistoryQuery);
        $stmt->bind_param($typesHistory, ...$paramsHistory);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $paymentHistory = [];
        while ($row = $result->fetch_assoc()) {
            // Decode the revenue_head JSON to calculate the total amount
            $revenueHeads = json_decode($row['revenue_head'], true);
            $totalAmount = 0;
    
            foreach ($revenueHeads as $revenueHead) {
                $totalAmount += $revenueHead['amount'];
            }
    
            // Add the processed data to the payment history
            $paymentHistory[] = [
                'invoice_number' => $row['invoice_number'],
                'total_amount' => $totalAmount,
                'amount_paid' => $row['amount_paid'],
                'payment_status' => $row['payment_status'],
                'date_created' => $row['date_created']
            ];
        }
        $stmt->close();
    
        // Fetch Outstanding Taxes
        $outstandingTaxesQuery = "
            SELECT 
                i.invoice_number, 
                i.revenue_head, 
                i.amount_paid, 
                i.due_date, 
                i.payment_status 
            FROM invoices i
            $taxNumberCondition
            AND (i.payment_status = 'unpaid' OR i.payment_status = 'partially paid')
            ORDER BY i.due_date ASC
            LIMIT ? OFFSET ?
        ";
        $paramsOutstanding = array_merge($params, [$limit, $offset]);
        $typesOutstanding = $types . "ii";
    
        $stmt = $this->conn->prepare($outstandingTaxesQuery);
        $stmt->bind_param($typesOutstanding, ...$paramsOutstanding);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $outstandingTaxes = [];
        while ($row = $result->fetch_assoc()) {
            // Decode the revenue_head JSON to calculate the total amount
            $revenueHeads = json_decode($row['revenue_head'], true);
            $totalAmount = 0;
    
            foreach ($revenueHeads as $revenueHead) {
                $totalAmount += $revenueHead['amount'];
            }
    
            $outstandingAmount = $totalAmount - $row['amount_paid'];
    
            // Add the processed data to the outstanding taxes
            $outstandingTaxes[] = [
                'invoice_number' => $row['invoice_number'],
                'total_amount' => $totalAmount,
                'amount_paid' => $row['amount_paid'],
                'outstanding_amount' => $outstandingAmount,
                'due_date' => $row['due_date'],
                'payment_status' => $row['payment_status']
            ];
        }
        $stmt->close();
    
        // Fetch total counts for pagination
        $totalPaymentQuery = "
            SELECT COUNT(*) AS total 
            FROM invoices i 
            $taxNumberCondition 
            AND i.payment_status IN ('paid', 'partially paid')
        ";
        $stmt = $this->conn->prepare($totalPaymentQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($totalPayments);
        $stmt->fetch();
        $stmt->close();
    
        $totalOutstandingQuery = "
            SELECT COUNT(*) AS total 
            FROM invoices i 
            $taxNumberCondition 
            AND (i.payment_status = 'unpaid' OR i.payment_status = 'partially paid')
        ";
        $stmt = $this->conn->prepare($totalOutstandingQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($totalOutstanding);
        $stmt->fetch();
        $stmt->close();
    
        // Calculate total pages for pagination
        $totalPagesPayment = ceil($totalPayments / $limit);
        $totalPagesOutstanding = ceil($totalOutstanding / $limit);
    
        // Return combined response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'payment_history' => [
                    'current_page' => $page,
                    'total_pages' => $totalPagesPayment,
                    'total_records' => $totalPayments,
                    'records' => $paymentHistory
                ],
                'outstanding_taxes' => [
                    'current_page' => $page,
                    'total_pages' => $totalPagesOutstanding,
                    'total_records' => $totalOutstanding,
                    'records' => $outstandingTaxes
                ]
            ]
        ]);
    }

    public function updateTinRequestStage($id, $current_stage) {
        // Validate current_stage (submitted, reviewer, director_approval)
        $valid_stages = ['submitted', 'reviewer', 'director_approval'];
    
        if (!in_array($current_stage, $valid_stages)) {
            return ["message" => "Invalid current_stage"];
        }
    
        // Check if the ID exists in the tin_generator table
        $query = "SELECT id FROM tin_generator WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
    
        if ($stmt->num_rows == 0) {
            return ["message" => "TIN request not found"];
        }
    
        // Update the TIN request's current_stage
        $query = "UPDATE tin_generator 
                  SET current_stage = ?, updated_at = NOW()
                  WHERE id = ?";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $current_stage, $id);
    
        // Execute the query
        if ($stmt->execute()) {
            return ["message" => "TIN request current_stage updated successfully"];
        } else {
            return ["message" => "Error updating TIN request current_stage"];
        }
    }

    public function updateTinRequestStatus($id, $status) {
        // Validate status (approved, declined)
        $valid_statuses = ['approved', 'declined'];
    
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(["message" => "Invalid status"]);
            return;
        }
    
        // Check if the ID exists in the tin_generator table
        $query = "SELECT id FROM tin_generator WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
    
        if ($stmt->num_rows == 0) {
            echo json_encode(["message" => "TIN request not found"]);
            return;
        }
    
        // Update the TIN request's status
        $query = "UPDATE tin_generator 
                  SET status = ?, updated_at = NOW()
                  WHERE id = ?";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $status, $id);
    
        // Execute the query
        if ($stmt->execute()) {
            return ["message" => "TIN request status updated successfully"];
        } else {
            return ["message" => "Error updating TIN request status"];
        }
    }

    public function getTinRequestSummary() {
        // Query to get the total count of TIN requests
        $query_total = "SELECT COUNT(id) as total FROM tin_generator";
        $stmt_total = $this->conn->prepare($query_total);
        $stmt_total->execute();
        $total_result = $stmt_total->get_result()->fetch_assoc();
        $total_count = $total_result['total'];
    
        // Query to get the count of each current_stage
        $query_stage = "SELECT current_stage, COUNT(id) as count 
                        FROM tin_generator 
                        GROUP BY current_stage";
        $stmt_stage = $this->conn->prepare($query_stage);
        $stmt_stage->execute();
        $stage_result = $stmt_stage->get_result();
        $current_stage_count = [];
        while ($row = $stage_result->fetch_assoc()) {
            $current_stage_count[$row['current_stage']] = $row['count'];
        }
    
        // Query to get the count of each status
        $query_status = "SELECT status, COUNT(id) as count 
                         FROM tin_generator 
                         GROUP BY status";
        $stmt_status = $this->conn->prepare($query_status);
        $stmt_status->execute();
        $status_result = $stmt_status->get_result();
        $status_count = [];
        while ($row = $status_result->fetch_assoc()) {
            $status_count[$row['status']] = $row['count'];
        }
    
        // Prepare summary response
        $summary = [
            'total_tin_requests' => $total_count,
            'current_stage_count' => $current_stage_count,
            'status_count' => $status_count
        ];
    
        return $summary;
    }

    // public function getUpcomingTaxes($taxpayer_id) {
    //     // Get the current date
    //     $current_date = date('Y-m-d');
        
    //     // Query to get all active revenue heads
    //     $query_revenue_heads = "SELECT * FROM revenue_heads WHERE `status` = 'active'";
    //     $stmt = $this->conn->prepare($query_revenue_heads);
    //     $stmt->execute();
    //     $revenue_heads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    //     // Query to get the most recent invoices for the given taxpayer
    //     $query_invoices = "SELECT revenue_head, due_date FROM invoices 
    //                        WHERE tax_number = ? AND payment_status = 'paid' 
    //                        ORDER BY due_date DESC";
        
    //     $stmt_invoices = $this->conn->prepare($query_invoices);
    //     $stmt_invoices->bind_param('i', $taxpayer_id);
    //     $stmt_invoices->execute();
    //     $result_invoices = $stmt_invoices->get_result();
    
    //     // Collect the most recent paid revenue heads and their due dates
    //     $paid_revenue_heads = [];
    //     while ($row = $result_invoices->fetch_assoc()) {
    //         $paid_revenue_heads_data = json_decode($row['revenue_head'], true);
    //         $due_date = $row['due_date'];
    
    //         // Loop through the revenue heads in the invoice and only consider the most recent one
    //         foreach ($paid_revenue_heads_data as $item) {
    //             if (!isset($paid_revenue_heads[$item['revenue_head_id']])) {
    //                 $paid_revenue_heads[$item['revenue_head_id']] = $due_date;
    //                 $paid_revenue_heads['mda_id'] = $item['mda_id'];

    //             }
    //         }
    //     }
    
    //     // Prepare the upcoming taxes array
    //     $upcoming_taxes = [];
        
    //     foreach ($revenue_heads as $head) {
    //         // Check if this revenue_head has been paid and is the most recent payment
    //         if (isset($paid_revenue_heads[$head['id']])) {
    //             $last_due_date = $paid_revenue_heads[$head['id']];
                
    //             // Calculate the next due date and payment date based on frequency
    //             $tax_info = $this->calculateNextDueDateAndPaymentDate($head['frequency'], $last_due_date, $current_date);
                
    //             if ($tax_info) {
    //                 // Add the upcoming tax to the list
    //                 $upcoming_taxes[] = [
    //                     'item_name' => $head['item_name'],
    //                     "mda_id" => $paid_revenue_heads['mda_id'],
    //                     'category' => $head['category'],
    //                     'amount' => $head['amount'],
    //                     'frequency' => $head['frequency'],
    //                     'next_due_date' => $tax_info['next_due_date'],
    //                     'next_payment_date' => $tax_info['next_payment_date']
    //                 ];
    //             }
    //         }
    //     }
    
    //     return $upcoming_taxes;
    // }

    public function getUpcomingTaxes($taxpayer_id) {
        // Get the current date
        $current_date = date('Y-m-d');
        
        // Query to get all active revenue heads along with their corresponding MDA information
        $query_revenue_heads = "SELECT rh.*, m.fullname AS mda_name, m.id AS mda_id
                                FROM revenue_heads rh
                                LEFT JOIN mda m ON rh.mda_id = m.id
                                WHERE rh.status = 'active'";
        
        $stmt = $this->conn->prepare($query_revenue_heads);
        $stmt->execute();
        $revenue_heads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
        // Query to get the most recent invoices for the given taxpayer
        $query_invoices = "SELECT revenue_head, due_date FROM invoices 
                           WHERE tax_number = ? AND payment_status = 'paid' 
                           ORDER BY due_date DESC";
        
        $stmt_invoices = $this->conn->prepare($query_invoices);
        $stmt_invoices->bind_param('i', $taxpayer_id);
        $stmt_invoices->execute();
        $result_invoices = $stmt_invoices->get_result();
    
        // Collect the most recent paid revenue heads and their due dates
        $paid_revenue_heads = [];
        while ($row = $result_invoices->fetch_assoc()) {
            $paid_revenue_heads_data = json_decode($row['revenue_head'], true);
            $due_date = $row['due_date'];
    
            // Loop through the revenue heads in the invoice and only consider the most recent one
            foreach ($paid_revenue_heads_data as $item) {
                if (!isset($paid_revenue_heads[$item['revenue_head_id']])) {
                    $paid_revenue_heads[$item['revenue_head_id']] = $due_date;
                }
            }
        }
    
        // Prepare the upcoming taxes array
        $upcoming_taxes = [];
        
        foreach ($revenue_heads as $head) {
            // Check if this revenue_head has been paid and is the most recent payment
            if (isset($paid_revenue_heads[$head['id']])) {
                $last_due_date = $paid_revenue_heads[$head['id']];
                
                // Calculate the next due date and payment date based on frequency
                $tax_info = $this->calculateNextDueDateAndPaymentDate($head['frequency'], $last_due_date, $current_date);
                
                if ($tax_info) {
                    // Add the upcoming tax to the list
                    $upcoming_taxes[] = [
                        'revenue_head_id' => $head['id'], // Include the revenue head ID
                        'item_name' => $head['item_name'],
                        'category' => $head['category'],
                        'amount' => $head['amount'],
                        'frequency' => $head['frequency'],
                        'mda_name' => $head['mda_name'],
                        'mda_id' => $head['mda_id'],
                        'next_due_date' => $tax_info['next_due_date'],
                        'next_payment_date' => $tax_info['next_payment_date']
                    ];
                }
            }
        }
    
        return $upcoming_taxes;
    }
    
    // Method to calculate the next due date and next payment date based on frequency
    private function calculateNextDueDateAndPaymentDate($frequency, $last_due_date, $current_date) {
        $next_due_date = null;
        $next_payment_date = null;
        $last_due_date_obj = new DateTime($last_due_date);
        $current_date_obj = new DateTime($current_date);
        
        // Calculate next due date based on frequency
        if ($frequency == 'monthly') {
            $next_due_date = clone $last_due_date_obj;
            $next_due_date->modify('+1 month');
            
            // Next payment date is the same as the next due date
            $next_payment_date = clone $next_due_date;
            $next_payment_date->modify('-1 months');
            
        } elseif ($frequency == 'quarterly') {
            $next_due_date = clone $last_due_date_obj;
            $next_due_date->modify('+3 months');
            
            // Next payment date is the start of the next quarter
            $next_payment_date = clone $next_due_date;
            $next_payment_date->modify('-3 months');
        } elseif ($frequency == 'yearly') {
            $next_due_date = clone $last_due_date_obj;
            $next_due_date->modify('+1 year');
            
            // Next payment date is the start of the next year
            $next_payment_date = clone $next_due_date;
            $next_payment_date->modify('-1 year');
        }
    
        // Ensure the due date and payment date are in the future
        if ($next_due_date && $next_due_date >= $current_date_obj) {
            return [
                'next_due_date' => $next_due_date->format('Y-m-d'),
                'next_payment_date' => $next_payment_date->format('Y-m-d')
            ];
        }
    
        return null; // Return null if it's in the past
    }
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
}
