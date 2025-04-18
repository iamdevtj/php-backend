<?php
require_once 'config/database.php';
require_once 'helpers/validation_helper.php';  // Use the MDA duplicate check

class MdaController
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new MDA and its contact information.
     */
    public function createMda($data)
    {
        // Validate required fields for MDA
        if (!isset($data['fullname'], $data['mda_code'], $data['email'], $data['phone'], $data['industry'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: fullname, mda_code, email, phone, industry']);
            http_response_code(400); // Bad request
            return;
        }

        // Check if the MDA fullname or MDA code already exists in the 'mda' table
        if (isDuplicateMda($this->conn, $data['fullname'], $data['mda_code'])) {
            echo json_encode(['status' => 'error', 'message' => 'MDA with this name or MDA code already exists']);
            http_response_code(409); // Conflict
            return;
        }

        // Start transaction
        $this->conn->begin_transaction();

        try {
            // Insert MDA into 'mda' table
            $query = "INSERT INTO mda (fullname, mda_code, email, phone, industry, allow_payment, allow_office_creation, status, time_in) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                'ssssssi',
                $data['fullname'],
                $data['mda_code'],
                $data['email'],
                $data['phone'],
                $data['industry'],
                $data['allow_payment'], // Default to allow payment (1 = true)
                $data['status'], // Default status to active (1 = active)
                $data['allow_office_creation'] // Default status to active (1 = active)
            );

            if (!$stmt->execute()) {
                throw new Exception('Error creating MDA: ' . $stmt->error);
            }

            // Get the MDA ID for the newly created MDA
            $mda_id = $stmt->insert_id;

            // Insert MDA contact information if provided
            if (isset($data['contact_info']) && is_array($data['contact_info'])) {
                $this->createMdaContactInfo($mda_id, $data['contact_info']);
            }

            // Commit transaction
            $this->conn->commit();

            // Return success response
            echo json_encode([
                'status' => 'success',
                'message' => 'MDA created successfully',
                'mda_id' => $mda_id
            ]);

        } catch (Exception $e) {
            // Rollback transaction in case of an error
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $stmt->close();
        }
    }

   
    // public function createMultipleMda($data)
    // {
    //     // Validate that $data is an array and contains MDA entries
    //     if (!is_array($data) || empty($data)) {
    //         echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
    //         http_response_code(400); // Bad request
    //         return;
    //     }

    //     // Start transaction
    //     $this->conn->begin_transaction();

    //     try {
    //         foreach ($data as $mda) {
    //             // Validate required fields for each MDA
    //             if (!isset($mda['fullname'], $mda['mda_code'], $mda['email'], $mda['phone'], $mda['industry'])) {
    //                 throw new Exception('Missing required fields for MDA: fullname, mda_code, email, phone, industry');
    //             }

    //             // Check if the MDA fullname or MDA code already exists in the 'mda' table
    //             if (isDuplicateMda($this->conn, $mda['fullname'], $mda['mda_code'])) {
    //                 throw new Exception('MDA with this name or MDA code already exists: ' . $mda['fullname']);
    //             }

    //             // Insert MDA into 'mda' table
    //             $query = "INSERT INTO mda (fullname, mda_code, email, phone, industry, allow_payment, status, time_in) 
    //                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    //             $stmt = $this->conn->prepare($query);

    //             // Assign values to variables
    //             $fullname = $mda['fullname'];
    //             $mda_code = $mda['mda_code'];
    //             $email = $mda['email'];
    //             $phone = $mda['phone'];
    //             $industry = $mda['industry'];
    //             $allow_payment = $mda['allow_payment'] ?? 1; // Default to allow payment (1 = true)
    //             $status = $mda['status'] ?? 1;               // Default status to active (1 = active)

    //             // Bind parameters
    //             $stmt->bind_param(
    //                 'ssssssi',
    //                 $fullname,
    //                 $mda_code,
    //                 $email,
    //                 $phone,
    //                 $industry,
    //                 $allow_payment,
    //                 $status
    //             );

    //             if (!$stmt->execute()) {
    //                 throw new Exception('Error creating MDA: ' . $stmt->error);
    //             }

    //             // Get the MDA ID for the newly created MDA
    //             $mda_id = $stmt->insert_id;

    //             // Insert MDA contact information if provided
    //             if (isset($mda['contact_info']) && is_array($mda['contact_info'])) {
    //                 $this->createMdaContactInfo($mda_id, $mda['contact_info']);
    //             }
    //         }

    //         // Commit transaction
    //         $this->conn->commit();

    //         // Return success response
    //         echo json_encode([
    //             'status' => 'success',
    //             'message' => 'MDAs created successfully'
    //         ]);

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

    public function createMultipleMda($data)
    {
        // Validate that $data is an array and contains MDA entries
        if (!is_array($data) || empty($data)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
            http_response_code(400); // Bad request
            return;
        }

        // Start transaction
        $this->conn->begin_transaction();

        // Arrays to hold successful and unsuccessful MDA registrations
        $successfulMdas = [];
        $unsuccessfulMdas = [];

        try {
            foreach ($data as $mda) {
                // Validate required fields for each MDA
                if (!isset($mda['fullname'], $mda['mda_code'], $mda['email'], $mda['phone'], $mda['industry'])) {
                    $unsuccessfulMdas[] = [
                        'mda' => $mda,
                        'error' => 'Missing required fields: fullname, mda_code, email, phone, industry'
                    ];
                    continue; // Skip to the next MDA
                }

                // Check if the MDA fullname or MDA code already exists in the 'mda' table
                if (isDuplicateMda($this->conn, $mda['fullname'], $mda['mda_code'])) {
                    $unsuccessfulMdas[] = [
                        'mda' => $mda,
                        'error' => 'MDA with this name or MDA code already exists: ' . $mda['fullname']
                    ];
                    continue; // Skip to the next MDA
                }

                // Insert MDA into 'mda' table
                $query = "INSERT INTO mda (fullname, mda_code, email, phone, industry, allow_payment, status, time_in) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $this->conn->prepare($query);

                // Assign values to variables
                $fullname = $mda['fullname'];
                $mda_code = $mda['mda_code'];
                $email = $mda['email'];
                $phone = $mda['phone'];
                $industry = $mda['industry'];
                $allow_payment = $mda['allow_payment'] ?? 1; // Default to allow payment (1 = true)
                $status = $mda['status'] ?? 1;               // Default status to active (1 = active)

                // Bind parameters
                $stmt->bind_param(
                    'ssssssi',
                    $fullname,
                    $mda_code,
                    $email,
                    $phone,
                    $industry,
                    $allow_payment,
                    $status
                );

                if (!$stmt->execute()) {
                    $unsuccessfulMdas[] = [
                        'mda' => $mda,
                        'error' => 'Error creating MDA: ' . $stmt->error
                    ];
                    continue; // Skip to the next MDA
                }

                // Get the MDA ID for the newly created MDA
                $mda_id = $stmt->insert_id;

                // Insert MDA contact information if provided
                if (isset($mda['contact_info']) && is_array($mda['contact_info'])) {
                    $this->createMdaContactInfo($mda_id, $mda['contact_info']);
                }

                // Add to successful registrations
                $successfulMdas[] = $mda;
            }

            // Commit transaction
            $this->conn->commit();

            // Return success response with both successful and unsuccessful MDAs
            echo json_encode([
                'status' => 'success',
                'message' => 'MDAs processed',
                'successful' => $successfulMdas,
                'unsuccessful' => $unsuccessfulMdas
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

    /**
     * Helper function to insert MDA contact information into 'mda_contact_info' table.
     */
    private function createMdaContactInfo($mda_id, $contact_info)
    {
        // Ensure contact information fields are present
        if (!isset($contact_info['state'], $contact_info['geolocation'], $contact_info['lga'], $contact_info['address'])) {
            throw new Exception('Missing required contact info fields: state, geolocation, lga, address');
        }

        $query = "INSERT INTO mda_contact_info (mda_id, state, geolocation, lga, address) 
                  VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'issss',
            $mda_id,
            $contact_info['state'],
            $contact_info['geolocation'],
            $contact_info['lga'],
            $contact_info['address']
        );

        if (!$stmt->execute()) {
            throw new Exception('Error creating MDA contact information: ' . $stmt->error);
        }

        $stmt->close();
    }

    /**
     * Update MDA information and optional contact information.
     */
    public function updateMda($data)
    {
        // Validate required fields
        if (!isset($data['mda_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required field: mda_id']);
            http_response_code(400); // Bad request
            return;
        }

        $mda_id = $data['mda_id'];

        // Check if the MDA exists
        $query = "SELECT id FROM mda WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $mda_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'MDA not found']);
            http_response_code(404); // Not found
            $stmt->close();
            return;
        }

        $stmt->close();

        // Optionally, check for duplicates (if updating fullname or mda_code)
        if (isset($data['fullname']) || isset($data['mda_code'])) {
            $query = "SELECT id FROM mda WHERE (fullname = ? OR mda_code = ?) AND id != ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $fullname = $data['fullname'] ?? '';
            $mda_code = $data['mda_code'] ?? '';
            $stmt->bind_param('ssi', $fullname, $mda_code, $mda_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'MDA with this name or MDA code already exists']);
                http_response_code(409); // Conflict
                $stmt->close();
                return;
            }

            $stmt->close();
        }

        // Update the MDA details
        $query = "UPDATE mda SET fullname = COALESCE(?, fullname), mda_code = COALESCE(?, mda_code), email = COALESCE(?, email), phone = COALESCE(?, phone), industry = COALESCE(?, industry), allow_payment = COALESCE(?, allow_payment), status = COALESCE(?, status) WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'ssssssii',
            $data['fullname'],
            $data['mda_code'],
            $data['email'],
            $data['phone'],
            $data['industry'],
            $data['allow_payment'],
            $data['status'],
            $mda_id
        );

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Error updating MDA: ' . $stmt->error]);
            http_response_code(500); // Internal Server Error
            return;
        }

        $stmt->close();

        // Update MDA contact information if provided
        if (isset($data['contact_info']) && is_array($data['contact_info'])) {
            $this->updateMdaContactInfo($mda_id, $data['contact_info']);
        }

        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'MDA updated successfully'
        ]);
    }

    /**
     * Update MDA contact information.
     */
    private function updateMdaContactInfo($mda_id, $contact_info)
    {
        $query = "UPDATE mda_contact_info SET state = COALESCE(?, state), geolocation = COALESCE(?, geolocation), lga = COALESCE(?, lga), address = COALESCE(?, address) WHERE mda_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'ssssi',
            $contact_info['state'],
            $contact_info['geolocation'],
            $contact_info['lga'],
            $contact_info['address'],
            $mda_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Error updating MDA contact information: ' . $stmt->error);
        }

        $stmt->close();
    }

    /**
     * Fetch all MDAs with pagination.
     */
    public function getAllMdas($page, $limit)
    {
        // Set default page and limit if not provided
        $page = isset($page) ? (int) $page : 1;
        $limit = isset($limit) ? (int) $limit : 10;

        // Calculate the offset
        $offset = ($page - 1) * $limit;

        // Fetch the total number of MDAs
        $count_query = "SELECT COUNT(*) as total FROM mda WHERE account_status = 'activate'";
        $result = $this->conn->query($count_query);
        $total_mdas = $result->fetch_assoc()['total'];

        // Fetch the paginated MDAs
        $query = "SELECT * FROM mda WHERE account_status = 'activate' LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $mdas = $result->fetch_all(MYSQLI_ASSOC);

        // Calculate total pages
        $total_pages = ceil($total_mdas / $limit);

        // Return response
        echo json_encode([
            'status' => 'success',
            'data' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_mdas' => $total_mdas,
                'mdas' => $mdas
            ]
        ]);

        $stmt->close();
    }

    /**
     * Fetch MDA by various filters (id, fullname, mda_code, email, allow_payment, status).
     */
    public function getMdaByFilters($queryParams)
    {
        // Base query to fetch MDA details and count revenue heads
        $query = "
            SELECT
                m.*,
                mdac.state,
                mdac.geolocation,
                mdac.lga,
                mdac.address,
                COUNT(rh.id) AS total_revenue_heads
            FROM
                mda m
            LEFT JOIN mda_contact_info mdac ON m.id = mdac.mda_id AND mdac.account_status = 'activate'
            LEFT JOIN revenue_heads rh ON m.id = rh.mda_id  AND rh.account_status = 'activate'
            WHERE m.account_status = 'activate'
            ";

        $params = [];
        $types = "";

        // Apply filters based on query parameters
        if (!empty($queryParams['id'])) {
            $query .= " AND m.id = ?";
            $params[] = $queryParams['id'];
            $types .= "i";
        }

        if (!empty($queryParams['fullname'])) {
            $query .= " AND m.fullname LIKE ?";
            $params[] = '%' . $queryParams['fullname'] . '%';
            $types .= "s";
        }

        if (!empty($queryParams['mda_code'])) {
            $query .= " AND m.mda_code = ?";
            $params[] = $queryParams['mda_code'];
            $types .= "s";
        }

        if (!empty($queryParams['allow_payment'])) {
            $query .= " AND m.allow_payment = ?";
            $params[] = $queryParams['allow_payment'];
            $types .= "i";
        }

        if (!empty($queryParams['status'])) {
            $query .= " AND m.status = ?";
            $params[] = $queryParams['status'];
            $types .= "i";
        }

        if (!empty($queryParams['email'])) {
            $query .= " AND m.email LIKE ?";
            $params[] = '%' . $queryParams['email'] . '%';
            $types .= "s";
        }

        // Add GROUP BY
        $query .= " GROUP BY m.id, mdac.state, mdac.geolocation, mdac.lga, mdac.address";


        // Add pagination if provided
        $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch MDAs and calculate total remittance for each
        $mdas = [];
        while ($row = $result->fetch_assoc()) {
            $mdaId = $row['id'];

            // Calculate total remittance for the MDA
            $remittanceQuery = "SELECT revenue_head, payment_status FROM invoices WHERE payment_status = 'paid'";
            $remittanceResult = $this->conn->query($remittanceQuery);

            $totalRemittance = 0;

            while ($invoice = $remittanceResult->fetch_assoc()) {
                $revenueHeads = json_decode($invoice['revenue_head'], true);

                foreach ($revenueHeads as $revenueHead) {
                    // Check if the revenue head belongs to this MDA
                    $revenueHeadQuery = "SELECT mda_id FROM revenue_heads WHERE id = ?";
                    $stmtRevenueHead = $this->conn->prepare($revenueHeadQuery);
                    $stmtRevenueHead->bind_param('i', $revenueHead['revenue_head_id']);
                    $stmtRevenueHead->execute();
                    $revenueHeadResult = $stmtRevenueHead->get_result();
                    $revenueHeadData = $revenueHeadResult->fetch_assoc();

                    if ($revenueHeadData['mda_id'] == $mdaId) {
                        $totalRemittance += $revenueHead['amount'];
                    }

                    $stmtRevenueHead->close();
                }
            }

            // Add total remittance to the MDA details
            $row['total_remittance'] = $totalRemittance;

            $mdas[] = $row;
        }

        // Return structured response
        echo json_encode([
            "status" => "success",
            "data" => $mdas
        ]);
    }


    public function deleteMda($mda_id)
    {
        // Ensure the MDA exists
        $check_query = "SELECT id FROM mda WHERE id = ? AND account_status = 'activate'";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bind_param('i', $mda_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            echo json_encode(['status' => 'error', 'message' => 'MDA not found']);
            http_response_code(404); // Not Found
            $stmt->close();
            return;
        }

        // Start transaction to ensure safe deletion
        $this->conn->begin_transaction();

        try {
            // Deactivate associated revenue heads
            $deactivate_revenue_query = "UPDATE revenue_heads SET account_status = 'deactivate' WHERE mda_id = ?";
            $stmt = $this->conn->prepare($deactivate_revenue_query);
            $stmt->bind_param('i', $mda_id);
            if (!$stmt->execute()) {
                throw new Exception('Error deactivating revenue heads: ' . $stmt->error);
            }

            // Deactivate associated MDA contact information
            $deactivate_contact_info_query = "UPDATE mda_contact_info SET account_status = 'deactivate' WHERE mda_id = ?";
            $stmt = $this->conn->prepare($deactivate_contact_info_query);
            $stmt->bind_param('i', $mda_id);
            if (!$stmt->execute()) {
                throw new Exception('Error deactivating MDA contact info: ' . $stmt->error);
            }

            // Finally, deactivate the MDA
            $deactivate_mda_query = "UPDATE mda SET account_status = 'deactivate' WHERE id = ?";
            $stmt = $this->conn->prepare($deactivate_mda_query);
            $stmt->bind_param('i', $mda_id);
            if (!$stmt->execute()) {
                throw new Exception('Error deactivating MDA: ' . $stmt->error);
            }

            // Commit transaction
            $this->conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'MDA deactivated successfully']);
            $stmt->close();
        } catch (Exception $e) {
            // Rollback transaction in case of an error
            $this->conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

        // {
        //     "mda_id": 123
        // }

    }

    public function getMdaUsers($queryParams)
{
    // Base query to fetch MDA users
    $query = "
        SELECT 
            mu.id,
            mu.mda_id,
            mu.name,
            mu.email,
            mu.phone,
            mu.created_at,
            mu.img,
            mu.office_name,
            m.fullname AS mda_name
        FROM mda_users mu
        LEFT JOIN mda m ON mu.mda_id = m.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    // Apply filters
    if (!empty($queryParams['id'])) {
        $query .= " AND mu.id = ?";
        $params[] = $queryParams['id'];
        $types .= "i";
    }

    if (!empty($queryParams['mda_id'])) {
        $query .= " AND mu.mda_id = ?";
        $params[] = $queryParams['mda_id'];
        $types .= "i";
    }

    if (!empty($queryParams['name'])) {
        $query .= " AND mu.name LIKE ?";
        $params[] = '%' . $queryParams['name'] . '%';
        $types .= "s";
    }

    if (!empty($queryParams['email'])) {
        $query .= " AND mu.email LIKE ?";
        $params[] = '%' . $queryParams['email'] . '%';
        $types .= "s";
    }

    if (!empty($queryParams['phone_number'])) {
        $query .= " AND mu.phone LIKE ?";
        $params[] = '%' . $queryParams['phone'] . '%';
        $types .= "s";
    }

    if (!empty($queryParams['office_name'])) {
        $query .= " AND mu.office_name LIKE ?";
        $params[] = '%' . $queryParams['office_name'] . '%';
        $types .= "s";
    }

    // Add pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    // Prepare and execute the query
    $stmt = $this->conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch results
    $mdaUsers = [];
    while ($row = $result->fetch_assoc()) {
        $mdaUsers[] = $row;
    }

    // Get total count for pagination
    $totalQuery = "SELECT COUNT(*) as total FROM mda_users WHERE 1=1";
    if (!empty($queryParams['id'])) {
        $totalQuery .= " AND id = " . (int) $queryParams['id'];
    }
    if (!empty($queryParams['mda_id'])) {
        $totalQuery .= " AND mda_id = " . (int) $queryParams['mda_id'];
    }
    if (!empty($queryParams['name'])) {
        $totalQuery .= " AND name LIKE '%" . $this->conn->real_escape_string($queryParams['name']) . "%'";
    }
    if (!empty($queryParams['email'])) {
        $totalQuery .= " AND email LIKE '%" . $this->conn->real_escape_string($queryParams['email']) . "%'";
    }
    if (!empty($queryParams['phone'])) {
        $totalQuery .= " AND phone LIKE '%" . $this->conn->real_escape_string($queryParams['phone']) . "%'";
    }
    if (!empty($queryParams['office_name'])) {
        $totalQuery .= " AND office_name LIKE '%" . $this->conn->real_escape_string($queryParams['office_name']) . "%'";
    }

    $totalResult = $this->conn->query($totalQuery);
    $total = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($total / $limit);

    // Return structured response
    echo json_encode([
        "status" => "success",
        "data" => $mdaUsers,
        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_pages" => $totalPages,
            "total_records" => $total
        ]
    ]);
}


    public function getInvoicesByMda($queryParams)
    {
        // Ensure MDA ID is provided
        if (empty($queryParams['mda_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
            http_response_code(400);
            return;
        }

        // Fetch all revenue head IDs and names for the specified MDA
        $revenueHeadQuery = "SELECT * FROM revenue_heads WHERE mda_id = ?  AND account_status = 'activate'";
        $stmt = $this->conn->prepare($revenueHeadQuery);
        $stmt->bind_param('i', $queryParams['mda_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $revenueHeadMap = [];
        while ($row = $result->fetch_assoc()) {
            $revenueHeadMap[$row['id']] = $row['item_name'];
        }
        $stmt->close();

        // If no revenue heads found, return an empty result
        if (empty($revenueHeadMap)) { 
            echo json_encode(['status' => 'success', 'data' => [], 'pagination' => ['total_records' => 0]]);
            return;
        }

        // Base invoice query
        $invoiceQuery = "SELECT * FROM invoices WHERE 1=1";
        $params = [];
        $types = "";
        // Add optional filters
        if (!empty($queryParams['status'])) {
            $invoiceQuery .= " AND payment_status = ?";
            $params[] = $queryParams['status'];
            $types .= "s";
        }

        // Filter by revenue_head_id
        if (!empty($queryParams['revenue_head_id'])) {
            $invoiceQuery .= " AND JSON_CONTAINS(revenue_head, ?)";
            $params[] = json_encode(['revenue_head_id' => (int) $queryParams['revenue_head_id']]);
            $types .= "s"; // JSON_CONTAINS requires a string
        }

        // Filter by date range
        if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
            $invoiceQuery .= " AND date_created BETWEEN ? AND ?";
            $params[] = $queryParams['start_date'];
            $params[] = $queryParams['end_date'];
            $types .= "ss";
        }

        // Prepare and execute the query
        $stmt = $this->conn->prepare($invoiceQuery);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();


        $invoices = [];
        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
            $associatedRevenueHeads = [];
            $includeInvoice = false;

            foreach ($revenueHeads as $revenueHead) {
                if (isset($revenueHeadMap[$revenueHead['revenue_head_id']])) {
                    $includeInvoice = true;
                    $associatedRevenueHeads[] = [
                        'revenue_head_id' => $revenueHead['revenue_head_id'],
                        'item_name' => $revenueHeadMap[$revenueHead['revenue_head_id']],
                        'amount' => $revenueHead['amount']
                    ];
                }
            }

            if ($includeInvoice) {
                $row['associated_revenue_heads'] = $associatedRevenueHeads;
                $invoices[] = $row;
            }
        }
        $stmt->close();
        /**
         * Filter data based on revenue_head_id
         *
         * @param array $data
         * @param int $targetId
         * @return array
         */
        if (!empty($queryParams['revenue_head'])) {
            $sent_revenue_head = (int) $queryParams['revenue_head'];
            $filteredInvoices = [];
            foreach ($invoices as $entry) {
                if ($entry['associated_revenue_heads'][0]['revenue_head_id'] == $sent_revenue_head) {
                    $filteredInvoices[] = $entry;
                }  
            }
            $invoices = $filteredInvoices;
        }
        // Pagination
        $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $paginatedInvoices = array_slice($invoices, $offset, $limit);
        $totalRecords = count($invoices);
        $totalPages = ceil($totalRecords / $limit);

        // Return the result
        echo json_encode([
            "status" => "success",
            "data" => $paginatedInvoices,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $totalRecords
            ]
        ]);
    }

 

    public function getInvoicesWithPaymentInfoByMda($queryParams)
    {
        if (empty($queryParams['mda_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
            http_response_code(400);
            return;
        }

        $mda_id = (int) $queryParams['mda_id'];

        // Fetch all revenue heads for the given MDA
        $revenueHeadQuery = "SELECT id, item_name FROM revenue_heads WHERE mda_id = ?  AND account_status = 'activate'";
        $stmt = $this->conn->prepare($revenueHeadQuery);
        $stmt->bind_param('i', $mda_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $revenueHeadMap = [];
        while ($row = $result->fetch_assoc()) {
            $revenueHeadMap[$row['id']] = $row['item_name'];
        }
        $stmt->close();

        // If no revenue heads are found, return empty data
        if (empty($revenueHeadMap)) {
            echo json_encode([
                "status" => "success",
                "data" => [],
                "pagination" => ['total_records' => 0]
            ]);
            return;
        }

        // Fetch all invoices
        $invoiceQuery = "
            SELECT 
                inv.*,
                pc.payment_channel,
                pc.payment_method,
                pc.payment_bank,
                pc.payment_reference_number,
                pc.receipt_number,
                pc.amount_paid AS payment_amount,
                pc.date_payment_created
            FROM invoices inv
            LEFT JOIN payment_collection pc ON inv.invoice_number = pc.invoice_number
            WHERE 1=1
        "; 

        $params = [];
        $types = "";

                // Filter by invoice_number
        if (!empty($queryParams['invoice_number'])) {
            $invoiceQuery .= " AND inv.invoice_number = ?";
            $params[] = $queryParams['invoice_number'];
            $types .= "s";
        } 

        // Filter by revenue_head_id
        if (!empty($queryParams['revenue_head_id'])) {
        $invoiceQuery .= " AND JSON_CONTAINS(inv.revenue_head, ?)";
        $params[] = json_encode(['revenue_head_id' => (int) $queryParams['revenue_head_id']]);
        $types .= "s"; // JSON_CONTAINS requires a string
        }
    

        // Filter by date range
        if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
            $invoiceQuery .= " AND inv.date_created BETWEEN ? AND ?";
            $params[] = $queryParams['start_date'];
            $params[] = $queryParams['end_date'];
            $types .= "ss";
        }

          // Prepare and execute the query
        $stmt = $this->conn->prepare($invoiceQuery);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $invoices = [];
        while ($row = $result->fetch_assoc()) {
            // Decode the revenue_head JSON
            $revenueHeads = json_decode($row['revenue_head'], true);
            $associatedRevenueHeads = [];
            $includeInvoice = false;

            // Filter revenue heads based on MDA
            foreach ($revenueHeads as $revenueHead) {
                if (isset($revenueHeadMap[$revenueHead['revenue_head_id']])) {
                    $includeInvoice = true;
                    $associatedRevenueHeads[] = [
                        'revenue_head_id' => $revenueHead['revenue_head_id'],
                        'item_name' => $revenueHeadMap[$revenueHead['revenue_head_id']],
                        'amount' => $revenueHead['amount']
                    ];
                }
            }

            if ($includeInvoice) {
                $row['associated_revenue_heads'] = $associatedRevenueHeads;

                // Fetch taxpayer info
                $userInfo = $this->getTaxpayerInfo($row['tax_number']);
                $row['user_info'] = $userInfo;

                $invoices[] = $row;
            }
        }
        $stmt->close();

        // Pagination
        $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $pagedInvoices = array_slice($invoices, $offset, $limit);
        $totalRecords = count($invoices);
        $totalPages = ceil($totalRecords / $limit);

        // Return structured response
        echo json_encode([
            "status" => "success",
            "data" => $pagedInvoices,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $totalRecords
            ]
        ]);
    }

    // Helper function to fetch taxpayer info
    private function getTaxpayerInfo($taxNumber)
    {
        // Check taxpayer table
        $query = "SELECT first_name, surname, email, phone FROM taxpayer WHERE tax_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $taxNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $taxpayer = $result->fetch_assoc();
            $stmt->close();
            return $taxpayer;
        }

        // Check enumerator_tax_payers table
        $query = "SELECT first_name, last_name AS surname, email, phone FROM enumerator_tax_payers WHERE tax_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $taxNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $enumeratorTaxpayer = $result->fetch_assoc();
            $stmt->close();
            return $enumeratorTaxpayer;
        }

        $stmt->close();
        return null;
    }

    public function getRevenueHeadSummary()
    {
        // SQL query to fetch counts
        $query = "
            SELECT 
                COUNT(*) AS total_revenue_heads,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_revenue_heads,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_revenue_heads
            FROM revenue_heads WHERE account_status = 'activate'
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();

        // Response structure
        echo json_encode([
            "status" => "success",
            "data" => [
                "total_revenue_heads" => (int) $summary['total_revenue_heads'],
                "active_revenue_heads" => (int) $summary['active_revenue_heads'],
                "inactive_revenue_heads" => (int) $summary['inactive_revenue_heads'],
            ]
        ]);
    }

    public function getRevenueHeadSummaryByMda($mda_id)
    {
        // SQL query to fetch counts for a specific MDA
        $query = "
            SELECT 
                COUNT(*) AS total_revenue_heads,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_revenue_heads,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_revenue_heads
            FROM revenue_heads
            WHERE mda_id = ? AND rh.account_status = 'activate'
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $mda_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();

        // Response structure
        echo json_encode([
            "status" => "success",
            "data" => [
                "mda_id" => $mda_id,
                "total_revenue_heads" => (int) $summary['total_revenue_heads'],
                "active_revenue_heads" => (int) $summary['active_revenue_heads'],
                "inactive_revenue_heads" => (int) $summary['inactive_revenue_heads'],
            ]
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
        $requested_mda_id = $filters['mda_id'];
        // If tax_number is provided, add it as a filter
        if (empty($filters['mda_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
            http_response_code(400); // Bad Request
            return;
        }
        if (isset($filters['mda_id'])) {
                        $filters['mda_id'] = '"mda_id":"'.$filters['mda_id'].'"';
                        $taxNumberCondition = "WHERE i.revenue_head REGEXP ?";
                        $params[] = $filters['mda_id'];
                        $types .= 's';
        }
        
    
        // Fetch Payment History
        $paymentHistoryQuery = "
            SELECT 
                i.invoice_number, 
                i.revenue_head, 
                i.amount_paid, 
                i.date_created,
                i.tax_number, 
                i.payment_status 
            FROM invoices i
            $taxNumberCondition
            AND i.payment_status IN ('paid', 'partially paid')
            ORDER BY i.date_created DESC
            LIMIT ? OFFSET ?
        ";
        // exit();
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
                if($revenueHead['mda_id'] == $requested_mda_id){
                    $totalAmount += $revenueHead['amount'];
                }
            }
    
            // Add the processed data to the payment history
            $paymentHistory[] = [
                'invoice_number' => $row['invoice_number'],
                'tax_number' => $row['tax_number'],
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
                i.tax_number,
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
                if($revenueHead['mda_id'] == $requested_mda_id){
                    $totalAmount += $revenueHead['amount'];
                }
            }
    
            $outstandingAmount = $totalAmount - $row['amount_paid'];
    
            // Add the processed data to the outstanding taxes
            $outstandingTaxes[] = [
                'invoice_number' => $row['invoice_number'],
                'total_amount' => $totalAmount,
                'tax_number' => $row['tax_number'],
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


    // /path/to/your/file.php
    public function getTaxTypeBreakdownByMda($filters) {
        if (empty($filters['mda_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
            http_response_code(400); // Bad Request
            return;
        }

        $mda_id = (int) $filters['mda_id'];

        // Fetch all revenue heads for the given MDA
        $revenueHeadQuery = "SELECT id, item_name FROM revenue_heads WHERE mda_id = ? AND account_status = 'activate'";
        $stmt = $this->conn->prepare($revenueHeadQuery);
        $stmt->bind_param('i', $mda_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $revenueHeadMap = [];
        while ($row = $result->fetch_assoc()) {
            $revenueHeadMap[$row['id']] = $row['item_name'];
        }
        $stmt->close();

        // Return empty data if no revenue heads found
        if (empty($revenueHeadMap)) {
            echo json_encode(['status' => 'success', 'data' => []]);
            return;
        }

        // Fetch breakdown by revenue head
        $query = "
            SELECT 
                inv.revenue_head, 
                SUM(inv.amount_paid) AS total_paid 
            FROM invoices inv
            WHERE inv.payment_status = 'paid'
        ";

        $params = [];
        $types = "";

        // Date range filter
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $query .= " AND inv.date_created BETWEEN ? AND ?";
            $params[] = $filters['date_start'];
            $params[] = $filters['date_end'];
            $types .= "ss";
        }

        $query .= " GROUP BY inv.revenue_head";

        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $breakdown = [];
        $totalPaid = 0;

        while ($row = $result->fetch_assoc()) {
            $revenueHeads = json_decode($row['revenue_head'], true);
            foreach ($revenueHeads as $revenueHead) {
                $revenueHeadId = $revenueHead['revenue_head_id'];
                $amountPaid = $row['total_paid'];

                if (!isset($revenueHeadMap[$revenueHeadId])) {
                    continue;
                }

                if (!isset($breakdown[$revenueHeadId])) {
                    $breakdown[$revenueHeadId] = [
                        'revenue_head_id' => $revenueHeadId,
                        'name' => $revenueHeadMap[$revenueHeadId],
                        'amount_paid' => 0,
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

        // Return structured response
        echo json_encode([
            'status' => 'success',
            'data' => array_values($breakdown)
        ]);
    }

public function getTaxSummaryMda($filters) {
    if (empty($filters['mda_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
        http_response_code(400); // Bad Request
        return;
    }

    // Escape the mda_id value properly to avoid SQL injection issues
    $mdaId = $this->conn->real_escape_string($filters['mda_id']);
    $mdaIdRegexp = '"mda_id":"' . $mdaId . '"'; // You might need to adjust this depending on the data format

    // Use a parameterized query for the regular expression to prevent SQL injection
    $query = "
        SELECT 
            IFNULL(SUM(CASE WHEN i.payment_status = 'unpaid' THEN i.amount_paid ELSE 0 END), 0) AS total_outstanding,
            IFNULL(SUM(CASE WHEN i.payment_status = 'paid' THEN i.amount_paid ELSE 0 END), 0) AS total_revenue,
            COUNT(DISTINCT i.tax_number) AS total_taxpayers,
            COUNT(DISTINCT CASE WHEN tcc.invoice_number IS NOT NULL THEN tcc.invoice_number END) AS total_tcc_applications
        FROM invoices i
        LEFT JOIN tax_clearance_certificates tcc 
            ON i.invoice_number = tcc.invoice_number COLLATE utf8mb4_general_ci
        WHERE i.revenue_head REGEXP ?
    ";

    $stmt = $this->conn->prepare($query);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the query']);
        http_response_code(500); // Internal Server Error
        return;
    }

    // Bind the regular expression pattern
    $stmt->bind_param('s', $mdaIdRegexp); // Binding as a string parameter

    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the query returned any result
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No data found']);
        http_response_code(404); // Not Found
        return;
    }

    $summary = $result->fetch_assoc();
    $stmt->close();

    // Return structured response
    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_outstanding' => $summary['total_outstanding'] ?? 0.00,
            'total_revenue' => $summary['total_revenue'] ?? 0.00,
            'total_tcc_applications' => $summary['total_tcc_applications'] ?? 0,
            'total_taxpayers' => $summary['total_taxpayers'] ?? 0
        ]
    ]);
}


public function getMonthlyPaymentTrendsByMda($filters) {
    // Check if 'mda_id' exists and is not empty
    if (empty($filters['mda_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'MDA ID is required']);
        http_response_code(400); // Bad Request
        return;
    }

    // Prepare REGEXP condition for 'mda_id'
    $mdaIdRegexp = '"mda_id":"' . $this->conn->real_escape_string($filters['mda_id']) . '"';
    $taxNumberCondition = "WHERE i.revenue_head REGEXP ?";
    $params = [$mdaIdRegexp];

    // Queries for payments and invoices
    $paymentsQuery = "
        SELECT 
            MONTH(pc.date_payment_created) AS month,
            COUNT(*) AS total_payments
        FROM payment_collection pc
        LEFT JOIN invoices i ON pc.invoice_number = i.invoice_number
        $taxNumberCondition
        GROUP BY MONTH(pc.date_payment_created)
    ";

    $invoicesQuery = "
        SELECT 
            MONTH(i.date_created) AS month,
            COUNT(*) AS total_invoices
        FROM invoices i
        $taxNumberCondition
        GROUP BY MONTH(i.date_created)
    ";

    try {
        // Prepare and execute payment query
        $stmtPayments = $this->conn->prepare($paymentsQuery);
        $stmtPayments->bind_param('s', $params[0]);
        $stmtPayments->execute();
        $resultPayments = $stmtPayments->get_result();

        $paymentsData = [];
        while ($row = $resultPayments->fetch_assoc()) {
            $paymentsData[(int)$row['month']] = $row['total_payments'];
        }
        $stmtPayments->close();

        // Prepare and execute invoice query
        $stmtInvoices = $this->conn->prepare($invoicesQuery);
        $stmtInvoices->bind_param('s', $params[0]);
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

        // Return structured response
        echo json_encode([
            'status' => 'success',
            'data' => $monthlyTrends
        ]);

    } catch (Exception $e) {
        // Handle query execution errors
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
        http_response_code(500); // Internal Server Error
    }
}

public function forgotMdaPassword($data) {
    // Validate input
    if (empty($data['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Email is required']);
        http_response_code(400); // Bad Request
        return;
    }

    $email = $data['email'];

    // Check if the email exists in the mda_users table
    $queryMda = "SELECT id, name FROM mda_users WHERE email = ? LIMIT 1";
    $stmt = $this->conn->prepare($queryMda);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Email not found']);
        http_response_code(404); // Not Found
        return;
    }

    // Generate a unique reset token
    $resetToken = bin2hex(random_bytes(16));
    $userId = $user['id'];

    // Store the reset token in the password_resets table with an expiration time
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $queryInsert = "INSERT INTO password_resets (reset_token, tax_number, expires_at, table_type) VALUES (?, ?, ?, 'mda_users')";
    $stmt = $this->conn->prepare($queryInsert);
    $stmt->bind_param('sis', $resetToken, $userId, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Send the reset token to the user's email
    global $emailController; // Assuming you have an email controller set up
    $emailController->mdaAdminResetPasswordEmail($email, $user['name'], $resetToken);

    echo json_encode(['status' => 'success', 'message' => 'Password reset link has been sent to your email']);
}

public function resetMdaPassword($data) {
    // Validate input
    if (empty($data['reset_token']) || empty($data['new_password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Reset token and new password are required']);
        http_response_code(400); // Bad Request
        return;
    }

    $resetToken = $data['reset_token'];
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);

    // Check if the reset token exists and is valid for MDA users
    $query = "SELECT user_id FROM password_resets WHERE reset_token = ? AND expires_at > NOW() AND table_type = 'mda_users' LIMIT 1";
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

    $mdaId = $resetData['tax_number']; // Assuming user_id corresponds to the MDA user ID

    // Update the password in the mda_users table
    $queryUpdate = "UPDATE mda_users SET password = ? WHERE id = ?";
    $stmt = $this->conn->prepare($queryUpdate);
    $stmt->bind_param('si', $newPasswordHash, $mdaId);

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

// public function getMDAPerformance() {
//     // SQL query to get all MDAs
//     $mdaQuery = "SELECT id, fullname FROM mda";
//     $stmt = $this->conn->prepare($mdaQuery);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $mdas = $result->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();

//     // Initialize an array to store the MDA performance data
//     $mdaPerformance = [];

//     // Loop through each MDA to get performance data based on invoices
//     foreach ($mdas as $mda) {
//         $mdaId = $mda['id'];
//         $mdaName = $mda['fullname'];

//         // Query to get total revenue or number of paid invoices for each MDA
//         $query = "
//             SELECT 
//                 COUNT(*) AS total_invoices, 
//                 SUM(amount_paid) AS total_revenue
//             FROM invoices 
//             WHERE revenue_head REGEXP ? 
//             AND payment_status = 'paid'
//             ORDER BY date_created DESC
//         ";

//         // Prepare the query with dynamic mda_id using REGEXP
//         $stmt = $this->conn->prepare($query);
//         $regex = '"mda_id":"' . $mdaId . '"';
//         $stmt->bind_param('s', $regex);
//         $stmt->execute();
//         $result = $stmt->get_result();
//         $performanceData = $result->fetch_assoc();
//         $stmt->close();

//         // If performance data is available, add it to the result
//         if ($performanceData) {
//             $mdaPerformance[] = [
//                 'mda_id' => $mdaId,
//                 'mda_name' => $mdaName,
//                 'total_invoices' => $performanceData['total_invoices'],
//                 'total_revenue' => $performanceData['total_revenue']
//             ];
//         }
//     }

//     // Sort the MDAs by total revenue or any other criteria (e.g., total invoices)
//     usort($mdaPerformance, function($a, $b) {
//         return $b['total_revenue'] - $a['total_revenue']; // Sorting by total_revenue (descending)
//     });

//     // Return the sorted list of MDAs by performance
//     return json_encode([
//         'status' => 'success',
//         'data' => $mdaPerformance
//     ]);
// }

public function getMDAPerformance() {
    // SQL query to get all MDAs
    $mdaQuery = "SELECT id, fullname FROM mda";
    $stmt = $this->conn->prepare($mdaQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    $mdas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Initialize an array to store the MDA performance data
    $mdaPerformance = [];
    $totalRevenue = 0;  // Initialize total revenue for all MDAs

    // Loop through each MDA to get performance data based on invoices
    foreach ($mdas as $mda) {
        $mdaId = $mda['id'];
        $mdaName = $mda['fullname'];

        // Query to get total revenue for each MDA
        $query = "
            SELECT 
                COUNT(*) AS total_invoices, 
                SUM(amount_paid) AS total_revenue
            FROM invoices 
            WHERE revenue_head REGEXP ? 
            AND payment_status = 'paid'
            ORDER BY date_created DESC
        ";

        // Prepare the query with dynamic mda_id using REGEXP
        $stmt = $this->conn->prepare($query);
        $regex = '"mda_id":"' . $mdaId . '"';
        $stmt->bind_param('s', $regex);
        $stmt->execute();
        $result = $stmt->get_result();
        $performanceData = $result->fetch_assoc();
        $stmt->close();

        // If performance data is available, add it to the result
        if ($performanceData) {
            // Accumulate total revenue across all MDAs
            $totalRevenue += $performanceData['total_revenue'];

            $mdaPerformance[] = [
                'mda_id' => $mdaId,
                'mda_name' => $mdaName,
                'total_invoices' => $performanceData['total_invoices'],
                'total_revenue' => $performanceData['total_revenue'],
                'percentage_of_total' => 0 // Placeholder for percentage, will be calculated next
            ];
        }
    }

    // Calculate the percentage of total revenue for each MDA
    foreach ($mdaPerformance as &$mda) {
        if ($totalRevenue > 0) {
            $mda['percentage_of_total'] = round(($mda['total_revenue'] / $totalRevenue) * 100, 2);
        } else {
            $mda['percentage_of_total'] = 0;
        }
    }

    // Sort the MDAs by performance (e.g., total revenue) in descending order
    usort($mdaPerformance, function($a, $b) {
        return $b['total_revenue'] - $a['total_revenue']; // Sorting by total_revenue (descending)
    });

    // Return the sorted list of MDAs by performance
    return json_encode([
        'status' => 'success',
        'data' => $mdaPerformance
    ]);
}







}