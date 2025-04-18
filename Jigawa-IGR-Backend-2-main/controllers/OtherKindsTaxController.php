<?php
require_once 'config/database.php';
require_once 'helpers/user_helper.php';  // Include the universal duplicate check helper
require_once 'helpers/auth_helper.php';  // For JWT authentication

class OtherTaxes {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function calculateWHT($transactionAmount, $transactionType, $recipientType) {
        // Define WHT rates based on the transaction type and recipient type (individual or company)
        $whtRates = [
            'rent' => 10, // Rent (Property & Equipment)
            'dividends' => 10, // Dividends, Interests, Royalties
            'consultancy' => 5, // Consultancy, Professional Fees
            'construction' => 5, // Construction (Contracts & Supplies)
            'commissions' => 5, // Commissions
            'directors_fees' => 10, // Directors’ Fees
        ];
    
        // Check if the transaction type exists in the rates array
        if (!array_key_exists($transactionType, $whtRates)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid transaction type']);
        }
    
        // Determine the applicable WHT rate
        $whtRate = $whtRates[$transactionType];
    
        // If the recipient is a company, some rates may differ (e.g., Consultancy, Commissions for companies)
        if ($recipientType === 'company') {
            if ($transactionType === 'consultancy' || $transactionType === 'commissions') {
                $whtRate = 10; // Update to company rates for these transaction types
            }
        }
    
        // Step 1: Calculate WHT Due
        $whtDue = $transactionAmount * ($whtRate / 100);
    
        // Step 2: Calculate net payment (amount after WHT deduction)
        $netPayment = $transactionAmount - $whtDue;
    
        // Return the results
        return json_encode([
            'status' => 'success',
            'data' => [
                'transaction_amount' => $transactionAmount,
                'wht_rate' => $whtRate,
                'wht_due' => $whtDue,
                'net_payment' => $netPayment
            ]
        ]);
    }

    // public function calculatePAYE($annual_gross_income) {
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

    //     // return $monthly_tax_payable;
    //     // Return the results
    //     return json_encode([
    //         'status' => 'success',
    //         'data' => [
    //             'transaction_amount' => $monthly_tax_payable
    //         ]
    //     ]);
    // }

    private function calculatePAYE($annual_gross_income) {
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
    
        // Return the calculated values
        return json_encode([
            'status' => 'success',
            'data' => [
                'transaction_amount' => $monthly_tax_payable
            ]
        ]);
    }
    
    
    


}