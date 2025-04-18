<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use \Firebase\JWT\JWT;

class AuthController {
    private $secret_key = 'your_secret_key_plateau_35c731567705ad451533eb8516558ca0dad1e3e56d095c50289aabbff516a7f7_your_secret_key_plateau';  // Use a strong secret key!
    private $conn;  // Database connection

    // Constructor: initialize database connection
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // public function login($email, $password) {
    //     // Check each user type, one by one
    //     $user = $this->checkAdministrativeUsers($email, $password);
    //     if (!$user) {
    //         $user = $this->checkMdaUsers($email, $password);
    //     }
    //     if (!$user) {
    //         $user = $this->checkEnumeratorUsers($email, $password);
    //     }
    //     if (!$user) {
    //         $user = $this->checkTaxPayerUsers($email, $password);
    //     }
    //     if (!$user) {
    //         $user = $this->checkSpecialUsers($email, $password);
    //     }

    //     // If no user is found, return an error
    //     if (!$user) {
    //         echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    //         return;
    //     }

    //     // Credentials are valid, generate JWT
    //     $issued_at = time();
    //     $expiration_time = $issued_at + 3600;  // jwt valid for 1 hour
    //     $payload = array(
    //         'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
    //         'iat' => $issued_at,               // Issued at
    //         'exp' => $expiration_time,         // Expiration time
    //         'user_id' => $user['id'], 
    //         // 'user_id_number' => $user['tax_number'],          // Store user ID in token
    //         'email' => $user['email'],         // Email in the payload
    //         'user_type' => $user['user_type'], // Store user type (admin, mda, etc.)
    //     );

    //     // Include extra fields based on user type
    //     if ($user['user_type'] == 'admin') {
    //         $payload['role'] = $user['role'];
    //         $payload['fullname'] = $user['fullname'];
    //     } elseif ($user['user_type'] == 'mda') {
    //         $payload['mda_id'] = $user['mda_id'];
    //         $payload['fullname'] = $user['name'];
    //     } elseif ($user['user_type'] == 'enumerator') {
    //         $payload['agent_id'] = $user['agent_id'];
    //         $payload['fullname'] = $user['fullname'];
    //     } elseif ($user['user_type'] == 'tax_payer') {
    //         $payload['tax_number'] = $user['tax_number'];
    //         $payload['TIN'] = $user['TIN'];
    //         $payload['fullname'] = $user['first_name'].' '.$user['surname'];
    //         $payload['first_name'] = $user['first_name'];
    //         $payload['surname'] = $user['surname'];
    //         $payload['category'] = $user['category'];
    //     } elseif ($user['user_type'] == 'special_user') {
    //         $payload['official_TIN'] = $user['official_TIN'];
    //         $payload['payer_id'] = $user['payer_id'];
    //         $payload['fullname'] = $user['name'];
    //     }

    //     // Encode the JWT
    //     $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

    //     // Return the token to the client
    //     echo json_encode([
    //         'status' => 'success',
    //         'message' => 'Login successful',
    //         'token' => $jwt  // Return the token
    //     ]);
    // }

    public function mdaLogin($email, $password) {
        // Check each user type, one by one
        $user = $this->checkMdaUsers($email, $password);

        // If no user is found, return an error
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        // Credentials are valid, generate JWT
        $issued_at = time();
        $expiration_time = $issued_at + 3600;  // jwt valid for 1 hour
        $payload = array(
            'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
            'iat' => $issued_at,               // Issued at
            'exp' => $expiration_time,         // Expiration time
            'user_id' => $user['id'], 
            // 'user_id_number' => $user['tax_number'],          // Store user ID in token
            'email' => $user['email'],         // Email in the payload
            'user_type' => $user['user_type'], // Store user type (admin, mda, etc.)
        );

        // Include extra fields based on user type
        if ($user['user_type'] == 'mda') {
            $payload['mda_id'] = $user['mda_id'];
            $payload['fullname'] = $user['name'];
        }

        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

        // Return the token to the client
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $jwt  // Return the token
        ]);
    }

    public function adminLogin($email, $password) {
        // Check each user type, one by one
        $user = $this->checkAdministrativeUsers($email, $password);
        
        // If no user is found, return an error
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        // Credentials are valid, generate JWT
        $issued_at = time();
        $expiration_time = $issued_at + 3600;  // jwt valid for 1 hour
        $payload = array(
            'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
            'iat' => $issued_at,               // Issued at
            'exp' => $expiration_time,         // Expiration time
            'user_id' => $user['id'], 
            // 'user_id_number' => $user['tax_number'],          // Store user ID in token
            'email' => $user['email'],         // Email in the payload
            'user_type' => $user['user_type'], // Store user type (admin, mda, etc.)
        );

        // Include extra fields based on user type
        if ($user['user_type'] == 'admin') {
            $payload['role'] = $user['role'];
            $payload['fullname'] = $user['fullname'];
        }

        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

        // Return the token to the client
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $jwt  // Return the token
        ]);
    }

    public function taxpayerLogin($email, $password) {
        // Check each user type, one by one
        $user = $this->checkTaxPayerUsers($email, $password);
        // if (!$user) {
        //     $user = $this->checkEnumeratorUsers($email, $password);
        // }

        
        // print_r($user);
        // If no user is found, return an error
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        // Credentials are valid, generate JWT
        $issued_at = time();
        $expiration_time = $issued_at + 3600;  // jwt valid for 1 hour
        $payload = array(
            'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
            'iat' => $issued_at,               // Issued at
            'exp' => $expiration_time,         // Expiration time
            'user_id' => $user['id'], 
            'user_tax_number' => $user['tax_number'],          // Store user ID in token
            'email' => $user['email'], 
            'phone' => $user['phone'],
            'business_type' => $user['business_type'],       // Email in the payload
            'user_type' => $user['user_type'], // Store user type (admin, mda, etc.)
        );

        // Include extra fields based on user type
        if ($user['user_type'] == 'enumerator') {
            $payload['agent_id'] = $user['agent_id'];
            $payload['fullname'] = $user['fullname'];
            $payload['phone'] = $user['phone'];
            $payload['business_type'] = $user['business_type'];
        } elseif ($user['user_type'] == 'tax_payer') {
            $payload['tax_number'] = $user['tax_number'];
            $payload['TIN'] = $user['TIN'];
            $payload['phone'] = $user['phone'];
            $payload['business_type'] = $user['business_type'];
            $payload['fullname'] = $user['first_name'].' '.$user['surname'];
            $payload['first_name'] = $user['first_name'];
            $payload['surname'] = $user['surname'];
            $payload['category'] = $user['category'];
        }
        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

        // Return the token to the client
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $jwt  // Return the token
        ]);
    }

    public function managerLogin($email, $password) {
        // Check if manager credentials are valid
        $manager = $this->checkManagerUsers($email, $password);
    
        // If no user is found, return an error
        if (!$manager) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }
    
        // Credentials are valid, generate JWT
        $issued_at = time();
        $expiration_time = $issued_at + 3600;  // jwt valid for 1 hour
        $payload = array(
            'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
            'iat' => $issued_at,               // Issued at
            'exp' => $expiration_time,         // Expiration time
            'user_id' => $manager['id'], 
            'email' => $manager['manager_contact_email'],  // Email in the payload
            'phone' => $manager['manager_contact_phone'],
            'position' => $manager['position'],
            'tax_office_id' => $manager['tax_office_id'],
            'user_type' => 'manager' // Store user type as manager
        );
    
        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');
    
        // Return the token to the client
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $jwt  // Return the token
        ]);
    }
    
    private function checkManagerUsers($email, $password) {
        // Fetch manager data from the manager_offices table
        $managerQuery = "SELECT id, manager_contact_email, manager_contact_phone, position, tax_office_id FROM manager_offices WHERE manager_contact_email = ? LIMIT 1";
        $stmt = $this->conn->prepare($managerQuery);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $managerResult = $stmt->get_result();
        $manager = $managerResult->fetch_assoc();
        $stmt->close();
    
        if (!$manager) {
            // If no manager found, return false
            return false;
        }
    
        // Fetch password from manager_offices table
        $securityQuery = "SELECT password FROM manager_offices WHERE id = ?";
        $stmt = $this->conn->prepare($securityQuery);
        $stmt->bind_param('i', $manager['id']);
        $stmt->execute();
        $securityResult = $stmt->get_result();
        $security = $securityResult->fetch_assoc();
        $stmt->close();
    
        if (!$security) {
            // If no security details found, return false
            return false;
        }
    
        // Verify password
        if (!password_verify($password, $security['password'])) {
            return false; // Incorrect password
        }
    
        // Construct the user object
        $user = [
            'id' => $manager['id'],
            'manager_contact_email' => $manager['manager_contact_email'],
            'manager_contact_phone' => $manager['manager_contact_phone'],
            'position' => $manager['position'],
            'tax_office_id' => $manager['tax_office_id'],
            'user_type' => 'manager' // Add user type for context
        ];
    
        return $user; // Return the constructed user object
    }
    
    

    public function enumeratorLogin($email, $password) {
        // Check for enumerator credentials
        $user = $this->checkEnumeratorUsers($email, $password);
    
        // If user not found, return an error
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }
    
        // Credentials are valid, generate JWT
        $issued_at = time();
        $expiration_time = $issued_at + 3600;  // JWT valid for 1 hour
        $payload = array(
            'iss' => 'https://phpclusters-188739-0.cloudclusters.net',  // Issuer
            'iat' => $issued_at,                // Issued at
            'exp' => $expiration_time,          // Expiration time
            'user_id' => $user['id'], 
            'agent_id' => $user['agent_id'],    // Store enumerator's agent ID
            'fullname' => $user['fullname'],    // Store full name
            'email' => $user['email'],          // Email in the payload
            'phone' => $user['phone'],          // Phone number
            'state' => $user['state'],          // State
            'lga' => $user['lga'],  
            'status' => $user['status'],
            'img' => $user['img'], 
            'address' => $user['address']          // LGA  // Store user type
        );
    
        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');
    
        // Return the token to the client
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $jwt  // Return the token
        ]);
    }
    // Check for admin users
    private function checkAdministrativeUsers($email, $password) {
        $query = 'SELECT id, email, fullname, password, role FROM administrative_users WHERE email = ? LIMIT 1';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $user['user_type'] = 'admin';  // Identify the user type
                return $user;
            }
        }
        return false;
    }

    // Check for MDA users
    private function checkMdaUsers($email, $password) {
        $query = 'SELECT id, mda_id, email, password, name FROM mda_users WHERE email = ? LIMIT 1';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $user['user_type'] = 'mda';  // Identify the user type
                return $user;
            }
        }
        return false;
    }

    // Check for enumerator users
   private function checkEnumeratorUsers($email, $password) {
    $query = 'SELECT id, agent_id, fullname, email, password, phone, state, lga, img, status, address FROM enumerator_users WHERE email = ? LIMIT 1';
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $user['user_type'] = 'enumerator';  // Identify the user type
            return $user;
        }
    }
    return false;
}

    // Check for tax payer users
    private function checkTaxPayerUsers($email, $password) {
        // Fetch user data from the taxpayer table
        $taxpayerQuery = "SELECT id, email, phone, first_name, surname, tax_number, category 
                          FROM taxpayer WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($taxpayerQuery);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $taxpayerResult = $stmt->get_result();
        $taxpayer = $taxpayerResult->fetch_assoc();
        $stmt->close();
        // var_dump($taxpayer);
        // exit();
        if (!$taxpayer) {
            // If no taxpayer found, return false
            return false;
        }
    
        // Fetch password and security details from taxpayer_security
        $securityQuery = "SELECT password FROM taxpayer_security WHERE taxpayer_id = ?";
        $stmt = $this->conn->prepare($securityQuery);
        $stmt->bind_param('i', $taxpayer['id']);
        $stmt->execute();
        $securityResult = $stmt->get_result();
        $security = $securityResult->fetch_assoc();
        $stmt->close();
        
        if (!$security) {
            // If no security details found, return false
            return false;
        }
       
        // Verify password
        if (!password_verify($password, $security['password'])) {
            return false; // Incorrect password
        }

        
    
        // Fetch additional identification details
        $identificationQuery = "SELECT TIN FROM taxpayer_identification WHERE taxpayer_id = ?";
        $stmt = $this->conn->prepare($identificationQuery);
        $stmt->bind_param('i', $taxpayer['id']);
        $stmt->execute();
        $identificationResult = $stmt->get_result();
        $identification = $identificationResult->fetch_assoc();
        $stmt->close();

        // Fetch additional taxpayer_business details
        $businessQuery = "SELECT business_type FROM taxpayer_business WHERE taxpayer_id = ?";
        $stmt2 = $this->conn->prepare($businessQuery);
        $stmt2->bind_param('i', $taxpayer['id']);
        $stmt2->execute();
        $businessResult = $stmt2->get_result();
        $business = $businessResult->fetch_assoc();
        $stmt2->close();
        if (!$business) {
            // If no taxpayer_business details found, return false
            $business['business_type'] = null;
            // return false;
        }

        // var_dump($security);
        // exit();
        // Construct the user object
        $user = [
            'id' => $taxpayer['id'],
            'email' => $taxpayer['email'],
            'first_name' => $taxpayer['first_name'],
            'surname' => $taxpayer['surname'],
            'tax_number' => $taxpayer['tax_number'],
            'category' => $taxpayer['category'],
            'phone' => $taxpayer['phone'],
            'business_type' => $business['business_type'],
            'TIN' => $identification['TIN'] ?? null, // Include TIN if available
            'user_type' => 'tax_payer' // Add user type for context
        ];
    
        return $user; // Return the constructed user object
    }
    

    // Check for special users
    // private function checkSpecialUsers($email, $password) {
    //     $query = 'SELECT id, email, password, name, official_TIN, payer_id FROM special_users_ WHERE email = ? LIMIT 1';
    //     $stmt = $this->conn->prepare($query);
    //     $stmt->bind_param('s', $email);
    //     $stmt->execute();
    //     $result = $stmt->get_result();

    //     if ($result->num_rows > 0) {
    //         $user = $result->fetch_assoc();
    //         if ($password== $user['password']) {
    //             $user['user_type'] = 'special_user';  // Identify the user type
    //             return $user;
    //         }
    //     }
    //     return false;
    // }
}