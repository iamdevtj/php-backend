<?php
class EmailController
{
    private $apiUrl = "https://api.brevo.com/v3/smtp/email";
    public function invoiceEmail($email, $first_name, $due_date, $invoice_number, $amount, $surname)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $first_name,
                ],
            ],
            "templateId" => 3,
            "params" => [
                "Fname" => $first_name,
                "Lname" => $surname,
                "due" => $due_date,
                "InvoiceN" => $invoice_number,
                "Amount" => $amount,
                "URL" => "https://phpclusters-189302-0.cloudclusters.net/invoiceGeneration/invoice.html?invoice_number=$invoice_number"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function userVerificationEmail($email, $name, $Lname, $verification)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 2,
            "params" => [
                "Fname" => $name,
                "Lname" => $Lname,
                "Verification" => $verification
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function adminVerificationEmail($email, $name, $verification)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 8,
            "params" => [
                "fullname" => $name,
                "verification" => $verification
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function adminReg($email, $name)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 7,
            "params" => [
                "fullname" => $name
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function mdaAdminReg($email, $name, $mda)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 10,
            "params" => [
                "fullname" => $name,
                "mda" => $mda,
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function EnumAdminReg($email, $name)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 13,
            "params" => [
                "fullname" => $name,
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function userResetPasswordEmail($email, $name, $Lname, $resetToken)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 6,
            "params" => [
                "Fname" => $name,
                "Lname" => $Lname,
                "ResetLink" => "https://phpclusters-189302-0.cloudclusters.net/resetpassword.html?resetToken=$resetToken"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function adminResetPasswordEmail($email, $name, $resetToken)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 9,
            "params" => [
                "fullname" => $name,
                "ResetLink" => "https://phpclusters-189302-0.cloudclusters.net/resetpassword.html?user_type=admin&resetToken=$resetToken"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function mdaAdminResetPasswordEmail($email, $name, $resetToken)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 11,
            "params" => [
                "fullname" => $name,
                "ResetLink" => "https://phpclusters-189302-0.cloudclusters.net/resetpassword.html?user_type=mdaAdmin&resetToken=$resetToken"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }
    public function userCreationSuccessEmail($email, $name, $Lname)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $name,
                ],
            ],
            "templateId" => 1,
            "params" => [
                "Fname" => $name,
                "Lname" => $Lname
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function adminCreationEmail($email, $fullname, $dashboard_access, $analytics_access, $mda_access, $reports_access, $tax_payer_access, $users_access, $cms_access, $support, $enumeration, $audit_trail, $payee_access, $tax_manager, $last_id, $verification)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $fullname,
                ],
            ],
            "templateId" => 14,
            "params" => [
                "Fname" => $fullname,
                "email" => $email,
                "dashboard_access" => $dashboard_access,
                "analytics_access" => $analytics_access,
                "ada_access" => $mda_access,
                "reports_access" => $reports_access,
                "tax_payer_access" => $tax_payer_access,
                "users_access" => $users_access,
                "cms_access" => $cms_access,
                "support" => $support,
                "enum_access" => $enumeration,
                "audit_access" => $audit_trail,
                "payee_access" => $payee_access,
                "tax_manager" => $tax_manager,
                "URL" => "https://plateauigr.com/createpassword.html?id=$last_id&verification=$verification"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }

    public function mdaCreation($email, $fullname, $last_id)
    {
        // Create an array with the email data
        $emailData = [
            "to" => [
                [
                    "email" => $email,
                    "name" => $fullname,
                ],
            ],
            "templateId" => 9,
            "params" => [
                "Fname" => $fullname,
                "email" => $email,
                "URL" => "https://plateauigr.com/mdapassword.html?id=$last_id"
            ],
            "headers" => [
                "X-Mailin-custom" => "custom_header_1:custom_value_1|custom_header_2:custom_value_2|custom_header_3:custom_value_3",
                "charset" => "iso-8859-1",
            ],
        ];

        return $this->sendEmail($emailData);
    }


    private function sendEmail($emailData)
    {
        // Convert the email data to a JSON string
        $jsonData = json_encode($emailData);

        // Initialize cURL session
        $ch = curl_init($this->apiUrl);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'api-key: ',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        // Execute cURL request
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // Handle cURL error
            $errorMessage = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'message' => "cURL Error: $errorMessage",
            ];
        }

        curl_close($ch);
    }
}

?>
