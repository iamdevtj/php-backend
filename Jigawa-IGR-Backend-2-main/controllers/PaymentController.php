<?php
require_once 'config/database.php';
require_once 'payment/paystack/PaystackPaymentHandler.php';
require_once 'payment/credo/CredoPaymentHandler.php';
require_once 'payment/paydirect/PayDirectPaymentHandler.php';
require_once 'helpers/format_converter.php';

class PaymentController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

 // Process Paystack Payment
    public function processPaystackPayment($payload) {
        // Store the raw payload for reference
        $payloadId = $this->storeGatewayPayload('paystack', json_encode($payload));
        $responseGate = '';

        try {
            // Process payment using PaystackPaymentHandler
            $handler = new PaystackPaymentHandler($this->conn);
            $paymentData = $handler->extractPaymentData($payload);

            if (!$paymentData) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'Invalid payment data']);
                return;
            }
            
            $user_id = $this->getUserIdFromInvoice($paymentData['invoice_number']);
            if ($user_id === null) {
                $user_id = $this->getUserIdFromDemandNotice($paymentData['invoice_number']);
                if ($user_id === null) {
                    $responseGate = json_encode(['status' => 'error', 'message' => 'Invoice not found or user_id is missing']);
                    return;
                }
            }

            $paymentData['user_id'] = $user_id;

            if ($this->isInvoicePaid($paymentData['invoice_number'])) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'This invoice is already paid']);
                return;
            }

            if ($this->isDemandNoticeInvoicePaid($paymentData['invoice_number'])) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'This invoice is already paid']);
                return;
            }

            if ($this->isPaymentExist($paymentData['invoice_number'])) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'The payment for associated invoice already exists']);
                return;
            }

            if ($this->insertPayment($paymentData)) {
                $this->updateInvoiceStatus($paymentData['invoice_number'], $paymentData['amount_paid']);
                $this->updateDemandNoticeStatus($paymentData['invoice_number'], $paymentData['amount_paid']);
                $responseGate = json_encode(['status' => 'success', 'message' => 'Payment registered successfully']);
            } else {
                $responseGate = json_encode(['status' => 'error', 'message' => 'Failed to register payment']);
            }
        } finally {
            // Update the gateway response regardless of prior conditions
            echo $responseGate;
            $this->updateGatewayResponse($payloadId, $responseGate);
        }
    }


    // Process Credo Payment
    public function processCredoPayment($payload) {
        // Store the raw payload for reference
        $payloadId = $this->storeGatewayPayload('credo', json_encode($payload));
        $responseGate = '';

        // Step 1: Check the event field in the payload
        $event = strtolower($payload['event'] ?? '');

        if ($event !== 'transaction.successful') {
            $responseGate = json_encode(['status' => 'error', 'message' => 'Invalid event type. Payment not processed']);
        } else {
            // Process payment using CredoPaymentHandler
            $handler = new CredoPaymentHandler($this->conn);
            $paymentData = $handler->extractPaymentData($payload);

            if (!$paymentData || !$paymentData['invoice_number']) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'Invalid payment data']);
            } else {
                // Fetch the user_id using the invoice_number from the payment data
                $user_id = $this->getUserIdFromInvoice($paymentData['invoice_number']);
                if ($user_id === null) {
                    $responseGate = json_encode(['status' => 'error', 'message' => 'Invoice not found or user_id is missing']);
                } else {
                    $paymentData['user_id'] = $user_id;

                    if ($this->isInvoicePaid($paymentData['invoice_number'])) {
                        $responseGate = json_encode(['status' => 'error', 'message' => 'This invoice is already paid']);
                    } elseif ($this->insertPayment($paymentData)) {
                        $this->updateInvoiceStatus($paymentData['invoice_number'], $paymentData['amount_paid']);
                        $responseGate = json_encode(['status' => 'success', 'message' => 'Payment registered successfully']);
                    } else {
                        $responseGate = json_encode(['status' => 'error', 'message' => 'Failed to register payment']);
                    }
                }
            }
        }

        // Always update the gateway response
        echo $responseGate;
        $this->updateGatewayResponse($payloadId, $responseGate);
    }


    // Process PayDirect Payment
    public function processPayDirectPayment($xmlPayload) {
        $handler = new PayDirectPaymentHandler($this->conn);
        $payloadId = $this->storeGatewayPayload('paydirect', json_encode($xmlPayload));
        $paymentData = $handler->extractPaymentData($xmlPayload);
        $responseGate = '';

        // If extraction returned an error, send response
        if ($paymentData['status'] === 1) {
            $responseGate = $this->sendPayDirectResponse($paymentData['status'], $paymentData['statusMessage'], $paymentData['payment_reference_number'] ?? null);
        } elseif ($this->isInvoicePaid($paymentData['invoice_number'])) {
            // Check if the invoice is already paid
            $responseGate = $this->sendPayDirectResponse(0, 'Duplicate', $paymentData['payment_reference_number']);
        } elseif ($this->isDuplicateReceipt($paymentData['receipt_number'])) {
            // Check if the receipt number already exists in the payment table
            $responseGate = $this->sendPayDirectResponse(1, 'Receipt already exists', $paymentData['payment_reference_number']);
        } else {
            // Fetch the user_id using the invoice_number from the payment data
            $user_id = $this->getUserIdFromInvoice($paymentData['invoice_number']);
            if ($user_id === null) {
                $responseGate = json_encode(['status' => 'error', 'message' => 'Invoice not found or user_id is missing']);
            } else {
                // Add user_id to the payment data
                $paymentData['user_id'] = $user_id;

                // Insert payment if all checks passed
                if ($this->insertPayment($paymentData)) {
                    $this->updateInvoiceStatus($paymentData['invoice_number'], $paymentData['amount_paid']);
                    $responseGate = $this->sendPayDirectResponse(0, 'Success', $paymentData['payment_reference_number']);
                } else {
                    // Return system error if insertion failed
                    $responseGate = $this->sendPayDirectResponse(1, 'Rejected By System', $paymentData['payment_reference_number']);
                }
            }
        }

        // Always update the gateway response at the end
        echo jsonToXml($responseGate, "PaymentNotificationResponse");
        $this->updateGatewayResponse($payloadId, $responseGate);
    }


    // Check if receipt number exists
    private function isDuplicateReceipt($receipt_number) {
        $query = "SELECT id FROM payment_collection WHERE receipt_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $receipt_number);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    // Send response for PayDirect
    private function sendPayDirectResponse($status, $statusMessage, $payment_reference_number) {
        return json_encode(
            [
                "Payments" => [
                    "Payment" => [
                        "PaymentLogId" => $payment_reference_number,
                        "Status" => $status,
                        "StatusMessage" => $statusMessage
                    ]
                ]
            ]
        );
    }


    // Check if the invoice is already paid
    private function isInvoicePaid($invoice_number) {
        $query = "SELECT payment_status FROM invoices WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $invoice_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['payment_status'] === 'paid';
        }
        return false;
    }

    private function isDemandNoticeInvoicePaid($invoice_number) {
        $query = "SELECT payment_status FROM demand_notices WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $invoice_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['payment_status'] === 'paid';
        }
        return false;
    }

    private function isPaymentExist($invoice_number) {
        $query = "SELECT invoice_number FROM payment_collection WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $invoice_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['invoice_number'] === $invoice_number;
        }
        return false;
    }

    private function insertPayment($paymentData) {
        $query = "INSERT INTO payment_collection (user_id, invoice_number, payment_channel, payment_method, payment_bank, payment_reference_number, receipt_number, amount_paid, date_payment_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'isssssids',
            $paymentData['user_id'],  
            $paymentData['invoice_number'],
            $paymentData['payment_channel'],
            $paymentData['payment_method'],
            $paymentData['payment_bank'],
            $paymentData['payment_reference_number'],
            $paymentData['receipt_number'],
            $paymentData['amount_paid'],
            $paymentData['date_payment_created']
        );
    
        if ($stmt->execute()) {
            $stmt->close();
            
            // Call the registerApplicableTaxes function after payment is inserted
            if (isset($paymentData['invoice_number'], $paymentData['user_id'])) {
                $this->registerApplicableTaxes($paymentData['invoice_number'], $paymentData['user_id']);
            }
    
            return true;
        }
    
        $stmt->close();
        return false;
    }
    
    
    // Register applicable taxes based on the payment
    private function registerApplicableTaxes($invoiceNumber, $taxNumber) {
        // Step 1: Retrieve the revenue_head from the invoices table
        $invoiceQuery = "SELECT revenue_head FROM invoices WHERE invoice_number = ?";
        $stmtInvoice = $this->conn->prepare($invoiceQuery);
        $stmtInvoice->bind_param('s', $invoiceNumber);
        $stmtInvoice->execute();
        $invoiceResult = $stmtInvoice->get_result()->fetch_assoc();
        $stmtInvoice->close();
    
        if ($invoiceResult) {
            $revenueHeads = json_decode($invoiceResult['revenue_head'], true);
    
            foreach ($revenueHeads as $revenueHead) {
                $revenue_head_id = $revenueHead['revenue_head_id'];
    
                // Step 2: Check if the revenue_head_id is a primary_tax_id in the tax_dependencies table
                $dependencyQuery = "SELECT dependent_tax_id FROM tax_dependencies WHERE primary_tax_id = ?";
                $stmtDependency = $this->conn->prepare($dependencyQuery);
                $stmtDependency->bind_param('i', $revenue_head_id);
                $stmtDependency->execute();
                $dependencyResult = $stmtDependency->get_result();
                $dependencies = $dependencyResult->fetch_all(MYSQLI_ASSOC);
                $stmtDependency->close();
    
                if ($dependencies) {
                    // Step 3: Register the dependent taxes in the applicable_taxes table
                    foreach ($dependencies as $dependency) {
                        $dependent_tax_id = $dependency['dependent_tax_id'];
                            $insertApplicableTaxQuery = "INSERT INTO applicable_taxes (tax_number, revenue_head_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                            $stmtInsertTax = $this->conn->prepare($insertApplicableTaxQuery);
                            $stmtInsertTax->bind_param('si', $taxNumber, $dependent_tax_id);
                            $stmtInsertTax->execute();
                            $stmtInsertTax->close();
                        // }
                    }
                }
            }
        }
    }
    

    // Update invoice payment status in the invoices table
    private function updateInvoiceStatus($invoiceNumber, $amountPaid) {
        $query = "UPDATE invoices SET payment_status = 'Paid', amount_paid = ? WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ds', $amountPaid, $invoiceNumber);
        $stmt->execute();
        $stmt->close();
    }
    // Update demand notice payment status in the invoices table
    private function updateDemandNoticeStatus($invoiceNumber, $amountPaid) {
        $query = "UPDATE demand_notices SET payment_status = 'Paid', amount_paid = ? WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ds', $amountPaid, $invoiceNumber);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Store the raw payment JSON in the gateway_payload table.
     */
    private function storeGatewayPayload($gateway, $payload) {
        $query = "INSERT INTO gateway_payload (gateway, payload, date_created) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $gateway, $payload);
        $stmt->execute();
        $insertId_storegateway = $stmt->insert_id;
        $stmt->close();
        return $insertId_storegateway;
    }
        // Get user_id based on invoice_number (or tax_number)
    private function getUserIdFromInvoice($invoice_number) {
        // Query the invoice table to fetch the user_id using the invoice_number
        $query = "SELECT tax_number FROM invoices WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $invoice_number);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch the user_id from the invoice
        if ($row = $result->fetch_assoc()) {
            $row['user_id'] = $row['tax_number'];
            return $row['user_id'];
        }

        // Return null if no matching invoice or user_id found
        return null;
    }

    private function getUserIdFromDemandNotice($invoice_number) {
        // Query the invoice table to fetch the user_id using the invoice_number
        $query = "SELECT tax_number FROM demand_notices WHERE invoice_number = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $invoice_number);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch the user_id from the invoice
        if ($row = $result->fetch_assoc()) {
            $row['user_id'] = $row['tax_number'];
            return $row['user_id'];
        }

        // Return null if no matching invoice or user_id found
        return null;
    }

    public function updateGatewayResponse($id, $result_out) {
        $query = "UPDATE gateway_payload SET result_out = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $result_out, $id);
        $stmt->execute();
        $stmt->close();
    }

    // Retrieve payment collection with optional filters and associated revenue heads
    // public function getPaymentCollection($queryParams) {
    //     // Set default pagination parameters
    //     $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    //     $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    //     $offset = ($page - 1) * $limit;
    
    //     // Base query with corrected JOINs to get payment, invoice, and user details
    //     $query = "SELECT 
    //                 pc.*, 
    //                 inv.revenue_head AS invoice_revenue_heads,
    //                 t.first_name AS taxpayer_first_name, 
    //                 t.surname AS taxpayer_surname, 
    //                 t.email AS taxpayer_email, 
    //                 t.phone AS taxpayer_phone, 
    //                 etp.first_name AS enumerator_first_name, 
    //                 etp.last_name AS enumerator_last_name, 
    //                 etp.email AS enumerator_email, 
    //                 etp.phone AS enumerator_phone
    //               FROM payment_collection pc
    //               LEFT JOIN invoices inv ON pc.invoice_number = inv.invoice_number
    //               LEFT JOIN taxpayer t ON pc.user_id = t.tax_number
    //               LEFT JOIN enumerator_tax_payers etp ON pc.user_id = etp.tax_number
    //               WHERE 1=1";
    
    //     $params = [];
    //     $types = "";
    
    //     // Apply filters if provided in query parameters
    //     if (!empty($queryParams['invoice_number'])) {
    //         $query .= " AND pc.invoice_number = ?";
    //         $params[] = $queryParams['invoice_number'];
    //         $types .= "s";
    //     }

    //     if (!empty($queryParams['tax_number'])) {
    //         $query .= " AND pc.user_id = ?";
    //         $params[] = $queryParams['tax_number'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_reference_number'])) {
    //         $query .= " AND pc.payment_reference_number = ?";
    //         $params[] = $queryParams['payment_reference_number'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['status'])) {
    //         $query .= " AND pc.payment_status = ?";
    //         $params[] = $queryParams['status'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
    //         $query .= " AND pc.date_payment_created BETWEEN ? AND ?";
    //         $params[] = $queryParams['start_date'];
    //         $params[] = $queryParams['end_date'];
    //         $types .= "ss";
    //     }
    
    //     if (!empty($queryParams['payment_channel'])) {
    //         $query .= " AND pc.payment_channel = ?";
    //         $params[] = $queryParams['payment_channel'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_method'])) {
    //         $query .= " AND pc.payment_method = ?";
    //         $params[] = $queryParams['payment_method'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_bank'])) {
    //         $query .= " AND pc.payment_bank = ?";
    //         $params[] = $queryParams['payment_bank'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_gateway'])) {
    //         $query .= " AND pc.payment_gateway = ?";
    //         $params[] = $queryParams['payment_gateway'];
    //         $types .= "s";
    //     }
    
    //     // Add pagination
    //     $query .= " LIMIT ? OFFSET ?";
    //     $params[] = $limit;
    //     $params[] = $offset;
    //     $types .= "ii";
    
    //     // Prepare and execute query
    //     $stmt = $this->conn->prepare($query);
    //     if ($types) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    
    //     // Fetch and format results
    //     $payments = [];
    //     while ($row = $result->fetch_assoc()) {
    //         // Decode revenue_head JSON from invoice table
    //         $revenueHeads = json_decode($row['invoice_revenue_heads'], true);
    //         $row['associated_revenue_heads'] = [];
    
    //         // For each revenue head, fetch details from revenue_heads and mda tables
    //         foreach ($revenueHeads as $revenueHead) {
    //             $queryRevenueHead = "
    //                 SELECT rh.item_name, rh.category, rh.amount, m.fullname 
    //                 FROM revenue_heads rh
    //                 JOIN mda m ON rh.mda_id = m.id
    //                 WHERE rh.id = ?";
    //             $stmtRevenueHead = $this->conn->prepare($queryRevenueHead);
    //             $stmtRevenueHead->bind_param('i', $revenueHead['revenue_head_id']);
    //             $stmtRevenueHead->execute();
    //             $revenueResult = $stmtRevenueHead->get_result();
    
    //             if ($revenueDetails = $revenueResult->fetch_assoc()) {
    //                 $row['associated_revenue_heads'][] = [
    //                     'revenue_head_id' => $revenueHead['revenue_head_id'],
    //                     'item_name' => $revenueDetails['item_name'],
    //                     'category' => $revenueDetails['category'],
    //                     'amount' => $revenueHead['amount'],
    //                     'mda_name' => $revenueDetails['fullname']
    //                 ];
    //             }
    //             $stmtRevenueHead->close();
    //         }
    
    //         // Include user information
    //         $row['user_info'] = [
    //             "first_name" => $row['taxpayer_first_name'] ?? $row['enumerator_first_name'],
    //             "surname" => $row['taxpayer_surname'] ?? $row['enumerator_last_name'],
    //             "email" => $row['taxpayer_email'] ?? $row['enumerator_email'],
    //             "phone" => $row['taxpayer_phone'] ?? $row['enumerator_phone']
    //         ];
    
    //         unset(
    //             $row['taxpayer_first_name'], $row['taxpayer_surname'], $row['taxpayer_email'], $row['taxpayer_phone'],
    //             $row['enumerator_first_name'], $row['enumerator_last_name'], $row['enumerator_email'], $row['enumerator_phone']
    //         );
    
    //         $payments[] = $row;
    //     }
    
    //     // Get total count for pagination
    //     $totalQuery = "SELECT COUNT(*) as total FROM payment_collection WHERE 1=1";
    //     if (!empty($queryParams['invoice_number'])) {
    //         $totalQuery .= " AND invoice_number = '" . $queryParams['invoice_number'] . "'";
    //     }
    //     if (!empty($queryParams['payment_reference_number'])) {
    //         $totalQuery .= " AND payment_reference_number = '" . $queryParams['payment_reference_number'] . "'";
    //     }
    //     if (!empty($queryParams['status'])) {
    //         $totalQuery .= " AND payment_status = '" . $queryParams['status'] . "'";
    //     }
    //     if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
    //         $totalQuery .= " AND date_payment_created BETWEEN '" . $queryParams['start_date'] . "' AND '" . $queryParams['end_date'] . "'";
    //     }
    //     if (!empty($queryParams['payment_channel'])) {
    //         $totalQuery .= " AND payment_channel = '" . $queryParams['payment_channel'] . "'";
    //     }
    //     if (!empty($queryParams['payment_method'])) {
    //         $totalQuery .= " AND payment_method = '" . $queryParams['payment_method'] . "'";
    //     }
    //     if (!empty($queryParams['payment_bank'])) {
    //         $totalQuery .= " AND payment_bank = '" . $queryParams['payment_bank'] . "'";
    //     }
    //     if (!empty($queryParams['payment_gateway'])) {
    //         $totalQuery .= " AND payment_gateway = '" . $queryParams['payment_gateway'] . "'";
    //     }
    
    //     $totalResult = $this->conn->query($totalQuery);
    //     $total = $totalResult->fetch_assoc()['total'];
    //     $totalPages = ceil($total / $limit);
    
    //     // Return structured response
    //     return json_encode([
    //         "status" => "success",
    //         "data" => $payments,
    //         "pagination" => [
    //             "current_page" => $page,
    //             "per_page" => $limit,
    //             "total_pages" => $totalPages,
    //             "total_records" => $total
    //         ]
    //     ]);
    // }

    // public function getPaymentCollection($queryParams) {
    //     // Set default pagination parameters
    //     $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    //     $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
    //     $offset = ($page - 1) * $limit;
    
    //     // Base query with JOINs to get payment, invoice/demand_notice, and user details
    //     $query = "SELECT 
    //                 pc.*, 
    //                 IFNULL(inv.revenue_head, dn.revenue_head) AS invoice_revenue_heads,
    //                 IFNULL(inv.invoice_type, dn.invoice_type) AS invoice_type,
    //                 IFNULL(inv.description, dn.description) AS invoice_description,
    //                 IFNULL(t.first_name, etp.first_name) AS taxpayer_first_name, 
    //                 IFNULL(t.surname, etp.last_name) AS taxpayer_surname, 
    //                 IFNULL(t.email, etp.email) AS taxpayer_email, 
    //                 IFNULL(t.phone, etp.phone) AS taxpayer_phone,
    //                 inv.duration

    //               FROM payment_collection pc
    //               LEFT JOIN invoices inv ON pc.invoice_number = inv.invoice_number
    //               LEFT JOIN demand_notices dn ON pc.invoice_number = dn.invoice_number
    //               LEFT JOIN taxpayer t ON pc.user_id = t.tax_number
    //               LEFT JOIN enumerator_tax_payers etp ON pc.user_id = etp.tax_number
    //               WHERE 1=1";
    
    //     $params = [];
    //     $types = "";
    
    //     // Apply filters if provided in query parameters
    //     if (!empty($queryParams['invoice_number'])) {
    //         $query .= " AND pc.invoice_number = ?";
    //         $params[] = $queryParams['invoice_number'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['tax_number'])) {
    //         $query .= " AND pc.user_id = ?";
    //         $params[] = $queryParams['tax_number'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_reference_number'])) {
    //         $query .= " AND pc.payment_reference_number = ?";
    //         $params[] = $queryParams['payment_reference_number'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['status'])) {
    //         $query .= " AND pc.payment_status = ?";
    //         $params[] = $queryParams['status'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
    //         $query .= " AND pc.date_payment_created BETWEEN ? AND ?";
    //         $params[] = $queryParams['start_date'];
    //         $params[] = $queryParams['end_date'];
    //         $types .= "ss";
    //     }
    
    //     if (!empty($queryParams['payment_channel'])) {
    //         $query .= " AND pc.payment_channel = ?";
    //         $params[] = $queryParams['payment_channel'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_method'])) {
    //         $query .= " AND pc.payment_method = ?";
    //         $params[] = $queryParams['payment_method'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_bank'])) {
    //         $query .= " AND pc.payment_bank = ?";
    //         $params[] = $queryParams['payment_bank'];
    //         $types .= "s";
    //     }
    
    //     if (!empty($queryParams['payment_gateway'])) {
    //         $query .= " AND pc.payment_gateway = ?";
    //         $params[] = $queryParams['payment_gateway'];
    //         $types .= "s";
    //     }
    
    //     // Add pagination
    //     $query .= " ORDER BY pc.date_payment_created DESC LIMIT ? OFFSET ?";
    //     $params[] = $limit;
    //     $params[] = $offset;
    //     $types .= "ii";
    
    //     // Prepare and execute query
    //     $stmt = $this->conn->prepare($query);
    //     if ($types) {
    //         $stmt->bind_param($types, ...$params);
    //     }
    //     $stmt->execute();
    //     $result = $stmt->get_result();
    
    //     // Fetch and format results
    //     $payments = [];
    //     while ($row = $result->fetch_assoc()) {
    //         // Decode revenue_head JSON from invoice/demand_notice table
    //         $revenueHeads = json_decode($row['invoice_revenue_heads'], true);
    //         $row['associated_revenue_heads'] = [];
    //         $row['tax_number'] = $row['user_id'];
    //         // For each revenue head, fetch details from revenue_heads and mda tables
    //         foreach ($revenueHeads as $revenueHead) {
    //             $queryRevenueHead = "
    //                 SELECT rh.item_name, rh.category, rh.amount, m.fullname 
    //                 FROM revenue_heads rh
    //                 JOIN mda m ON rh.mda_id = m.id
    //                 WHERE rh.id = ?";
    //             $stmtRevenueHead = $this->conn->prepare($queryRevenueHead);
    //             $stmtRevenueHead->bind_param('i', $revenueHead['revenue_head_id']);
    //             $stmtRevenueHead->execute();
    //             $revenueResult = $stmtRevenueHead->get_result();
    
    //             if ($revenueDetails = $revenueResult->fetch_assoc()) {
    //                 // Calculate the total amount for demand notices
    //                 $totalAmount = 0;
    //                 if (isset($revenueHead['previous_year_amount']) && isset($revenueHead['current_year_amount'])) {
    //                     $totalAmount = $revenueHead['previous_year_amount'] + $revenueHead['current_year_amount'];
                        
    //                 }
    
    //                 $row['associated_revenue_heads'][] = [
    //                     'amount' => $revenueHead['amount']  ?? $totalAmount,
    //                     'revenue_head_id' => $revenueHead['revenue_head_id'],
    //                     'item_name' => $revenueDetails['item_name'],
    //                     'category' => $revenueDetails['category'],
    //                     'previous_year_date' => $revenueHead['previous_year_date'] ?? null,
    //                     'previous_year_amount' => $revenueHead['previous_year_amount'] ?? 0,
    //                     'current_year_date' => $revenueHead['current_year_date'] ?? null,
    //                     'current_year_amount' => $revenueHead['current_year_amount'] ?? 0,
    //                     'mda_name' => $revenueDetails['fullname']
    //                 ];
    //             }
    //             $stmtRevenueHead->close();
    //         }
    
    //         // Include user information
    //         $row['user_info'] = [
    //             "first_name" => $row['taxpayer_first_name'],
    //             "surname" => $row['taxpayer_surname'],
    //             "email" => $row['taxpayer_email'],
    //             "phone" => $row['taxpayer_phone']
    //         ];
    
    //         unset($row['taxpayer_first_name'], $row['taxpayer_surname'], $row['taxpayer_email'], $row['taxpayer_phone']);
    
    //         $payments[] = $row;
    //     }
    
    //     // Get total count for pagination
    //     $totalQuery = "SELECT COUNT(*) as total FROM payment_collection WHERE 1=1";
    //     if (!empty($queryParams['invoice_number'])) {
    //         $totalQuery .= " AND invoice_number = '" . $queryParams['invoice_number'] . "'";
    //     }
    //     if (!empty($queryParams['payment_reference_number'])) {
    //         $totalQuery .= " AND payment_reference_number = '" . $queryParams['payment_reference_number'] . "'";
    //     }
    //     if (!empty($queryParams['status'])) {
    //         $totalQuery .= " AND payment_status = '" . $queryParams['status'] . "'";
    //     }
    //     if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
    //         $totalQuery .= " AND date_payment_created BETWEEN '" . $queryParams['start_date'] . "' AND '" . $queryParams['end_date'] . "'";
    //     }
    //     if (!empty($queryParams['payment_channel'])) {
    //         $totalQuery .= " AND payment_channel = '" . $queryParams['payment_channel'] . "'";
    //     }
    //     if (!empty($queryParams['payment_method'])) {
    //         $totalQuery .= " AND payment_method = '" . $queryParams['payment_method'] . "'";
    //     }
    //     if (!empty($queryParams['payment_bank'])) {
    //         $totalQuery .= " AND payment_bank = '" . $queryParams['payment_bank'] . "'";
    //     }
    //     if (!empty($queryParams['payment_gateway'])) {
    //         $totalQuery .= " AND payment_gateway = '" . $queryParams['payment_gateway'] . "'";
    //     }
       
    //     $totalResult = $this->conn->query($totalQuery);
    //     $total = $totalResult->fetch_assoc()['total'];
    //     $totalPages = ceil($total / $limit);
    
    //     // Return structured response
    //     return json_encode([
    //         "status" => "success",
    //         "data" => $payments,
    //         "pagination" => [
    //             "current_page" => $page,
    //             "per_page" => $limit,
    //             "total_pages" => $totalPages,
    //             "total_records" => $total
    //         ]
    //     ]);
    // }

    public function getPaymentCollection($queryParams) {
        // Set default pagination parameters
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        // Base query with JOINs to get payment, invoice/demand_notice, and user details
        $query = "SELECT 
                    pc.*, 
                    IFNULL(inv.revenue_head, dn.revenue_head) AS invoice_revenue_heads,
                    IFNULL(inv.invoice_type, dn.invoice_type) AS invoice_type,
                    IFNULL(inv.description, dn.description) AS invoice_description,
                    IFNULL(t.first_name, etp.first_name) AS taxpayer_first_name, 
                    IFNULL(t.surname, etp.last_name) AS taxpayer_surname, 
                    IFNULL(t.email, etp.email) AS taxpayer_email, 
                    IFNULL(t.phone, etp.phone) AS taxpayer_phone,
                    inv.duration,
                    inv.date_created AS invoice_created_date
                  FROM payment_collection pc
                  LEFT JOIN invoices inv ON pc.invoice_number = inv.invoice_number
                  LEFT JOIN demand_notices dn ON pc.invoice_number = dn.invoice_number
                  LEFT JOIN taxpayer t ON pc.user_id = t.tax_number
                  LEFT JOIN enumerator_tax_payers etp ON pc.user_id = etp.tax_number
                  WHERE 1=1";
    
        $params = [];
        $types = "";
    
        // Apply filters if provided in query parameters
        if (!empty($queryParams['invoice_number'])) {
            $query .= " AND pc.invoice_number = ?";
            $params[] = $queryParams['invoice_number'];
            $types .= "s";
        }
    
        if (!empty($queryParams['tax_number'])) {
            $query .= " AND pc.user_id = ?";
            $params[] = $queryParams['tax_number'];
            $types .= "s";
        }
    
        if (!empty($queryParams['payment_reference_number'])) {
            $query .= " AND pc.payment_reference_number = ?";
            $params[] = $queryParams['payment_reference_number'];
            $types .= "s";
        }
    
        if (!empty($queryParams['status'])) {
            $query .= " AND pc.payment_status = ?";
            $params[] = $queryParams['status'];
            $types .= "s";
        }
    
        if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
            $query .= " AND pc.date_payment_created BETWEEN ? AND ?";
            $params[] = $queryParams['start_date'];
            $params[] = $queryParams['end_date'];
            $types .= "ss";
        }
    
        if (!empty($queryParams['payment_channel'])) {
            $query .= " AND pc.payment_channel = ?";
            $params[] = $queryParams['payment_channel'];
            $types .= "s";
        }
    
        if (!empty($queryParams['payment_method'])) {
            $query .= " AND pc.payment_method = ?";
            $params[] = $queryParams['payment_method'];
            $types .= "s";
        }
    
        if (!empty($queryParams['payment_bank'])) {
            $query .= " AND pc.payment_bank = ?";
            $params[] = $queryParams['payment_bank'];
            $types .= "s";
        }
    
        if (!empty($queryParams['payment_gateway'])) {
            $query .= " AND pc.payment_gateway = ?";
            $params[] = $queryParams['payment_gateway'];
            $types .= "s";
        }
    
        // New filter for invoice type
        if (!empty($queryParams['invoice_type'])) {
            $query .= " AND IFNULL(inv.invoice_type, dn.invoice_type) = ?";
            $params[] = $queryParams['invoice_type'];
            $types .= "s";
        }

        if (!empty($queryParams['tax_office'])) {
            $query .= " AND IFNULL(inv.tax_office, dn.tax_office) = ?";
            $params[] = $queryParams['tax_office'];
            $types .= "s";
        }

        if (!empty($queryParams['revenue'])) {
            $query .= " AND IFNULL(inv.invoice_type, dn.invoice_type) = ?";
            $params[] = $queryParams['invoice_type'];
            $types .= "s";
        }

        if (!empty($queryParams['revenue_head'])) {
            $queryParams['revenue_head'] = '"revenue_head_id":"'.$queryParams['revenue_head'].'"';
            $query .= " AND inv.revenue_head REGEXP ?";
            $params[] = $queryParams['revenue_head'];
            $types .= 's';
        }

        if (!empty($queryParams['mda'])) {
            $queryParams['mda'] = '"mda_id":"'.$queryParams['mda'].'"';
            $query .= " AND inv.revenue_head REGEXP ?";
            $params[] = $queryParams['mda'];
            $types .= 's';
        }
    
        // Add pagination
        $query .= " ORDER BY pc.date_payment_created DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    
        // Prepare and execute query
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch and format results
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            // Decode revenue_head JSON from invoice/demand_notice table
            $revenueHeads = json_decode($row['invoice_revenue_heads'], true);
            $row['associated_revenue_heads'] = [];
            $row['tax_number'] = $row['user_id'];
            // For each revenue head, fetch details from revenue_heads and mda tables
            foreach ($revenueHeads as $revenueHead) {
                $queryRevenueHead = "
                    SELECT rh.item_name, rh.category, rh.amount, m.fullname 
                    FROM revenue_heads rh
                    JOIN mda m ON rh.mda_id = m.id
                    WHERE rh.id = ?";
                $stmtRevenueHead = $this->conn->prepare($queryRevenueHead);
                $stmtRevenueHead->bind_param('i', $revenueHead['revenue_head_id']);
                $stmtRevenueHead->execute();
                $revenueResult = $stmtRevenueHead->get_result();
    
                if ($revenueDetails = $revenueResult->fetch_assoc()) {
                    // Calculate the total amount for demand notices
                    $totalAmount = 0;
                    if (isset($revenueHead['previous_year_amount']) && isset($revenueHead['current_year_amount'])) {
                        $totalAmount = $revenueHead['previous_year_amount'] + $revenueHead['current_year_amount'];
                    }
    
                    $row['associated_revenue_heads'][] = [
                        'amount' => $revenueHead['amount']  ?? $totalAmount,
                        'revenue_head_id' => $revenueHead['revenue_head_id'],
                        'item_name' => $revenueDetails['item_name'],
                        'category' => $revenueDetails['category'],
                        'previous_year_date' => $revenueHead['previous_year_date'] ?? null,
                        'previous_year_amount' => $revenueHead['previous_year_amount'] ?? 0,
                        'current_year_date' => $revenueHead['current_year_date'] ?? null,
                        'current_year_amount' => $revenueHead['current_year_amount'] ?? 0,
                        'mda_name' => $revenueDetails['fullname']
                    ];
                }
                $stmtRevenueHead->close();
            }
    
            // Include user information
            $row['user_info'] = [
                "first_name" => $row['taxpayer_first_name'],
                "surname" => $row['taxpayer_surname'],
                "email" => $row['taxpayer_email'],
                "phone" => $row['taxpayer_phone']
            ];
    
            unset($row['taxpayer_first_name'], $row['taxpayer_surname'], $row['taxpayer_email'], $row['taxpayer_phone']);
    
            $payments[] = $row;
        }
    
        // Get total count for pagination
        $totalQuery = "SELECT COUNT(*) as total FROM payment_collection pc
                        LEFT JOIN invoices inv ON pc.invoice_number = inv.invoice_number
                        LEFT JOIN demand_notices dn ON pc.invoice_number = dn.invoice_number
                        WHERE 1=1";
        if (!empty($queryParams['invoice_number'])) {
            $totalQuery .= " AND pc.invoice_number = '" . $queryParams['invoice_number'] . "'";
        }
        if (!empty($queryParams['payment_reference_number'])) {
            $totalQuery .= " AND pc.payment_reference_number = '" . $queryParams['payment_reference_number'] . "'";
        }
        if (!empty($queryParams['status'])) {
            $totalQuery .= " AND pc.payment_status = '" . $queryParams['status'] . "'";
        }
        if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
            $totalQuery .= " AND pc.date_payment_created BETWEEN '" . $queryParams['start_date'] . "' AND '" . $queryParams['end_date'] . "'";
        }
        if (!empty($queryParams['payment_channel'])) {
            $totalQuery .= " AND pc.payment_channel = '" . $queryParams['payment_channel'] . "'";
        }
        if (!empty($queryParams['payment_method'])) {
            $totalQuery .= " AND pc.payment_method = '" . $queryParams['payment_method'] . "'";
        }
        if (!empty($queryParams['payment_bank'])) {
            $totalQuery .= " AND pc.payment_bank = '" . $queryParams['payment_bank'] . "'";
        }
        if (!empty($queryParams['payment_gateway'])) {
            $totalQuery .= " AND pc.payment_gateway = '" . $queryParams['payment_gateway'] . "'";
        }
        // New filter for invoice type in total count query
        if (!empty($queryParams['invoice_type'])) {
            $totalQuery .= " AND IFNULL(inv.invoice_type, dn.invoice_type) = '" . $queryParams['invoice_type'] . "'";
        }
    
        $totalResult = $this->conn->query($totalQuery);
        $total = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($total / $limit);
    
        // Return structured response
        return json_encode([
            "status" => "success",
            "data" => $payments,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_pages" => $totalPages,
                "total_records" => $total
            ]
        ]);
    }

    private function getTaxpayerInfo($identifier) {
        // Base query to fetch taxpayer information
        $query = "
            SELECT 
                t.id,
                t.first_name,
                t.surname,
                t.email,
                t.phone,
                t.address,
                t.state,
                t.lga,
                t.tax_number,
                ti.TIN,
                COALESCE(tb.business_type, etp.business_type) AS business_type
            FROM taxpayer t
            LEFT JOIN taxpayer_security ts ON t.id = ts.taxpayer_id
            LEFT JOIN taxpayer_identification ti ON t.id = ti.taxpayer_id
            LEFT JOIN taxpayer_business tb ON t.id = tb.taxpayer_id
            LEFT JOIN enumerator_tax_payers etp ON etp.tax_number = t.tax_number
            WHERE t.tax_number = ? OR t.email = ?
            LIMIT 1
        ";
    
        // Prepare and execute the query
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $identifier, $identifier); // Bind tax number or email
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Fetch and return the taxpayer info
        $taxpayerInfo = $result->fetch_assoc();
        $stmt->close();
    
        return $taxpayerInfo ? $taxpayerInfo : null;
    }
    
    
    
    
    

    // Process PayDirect Payment (IP validation already done in the route)
    // public function processPaydirectPayment($payload) {
    //     // Similar logic for PayDirect payment processing
    //     // Store raw payload and process payment using PaydirectPaymentHandler (to be implemented)
    //     echo json_encode(['status' => 'success', 'message' => 'PayDirect payment processed']);
    // }

}
