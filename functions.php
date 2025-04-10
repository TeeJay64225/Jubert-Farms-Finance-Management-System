<?php
// Include this in includes/functions.php or create a new file

/**
 * Generate letter content based on type and employee data
 */
function generateLetterContent($letter_type, $employee, $additional_details) {
    $today = date("F d, Y");
    $company_name = "Your Company Name";
    $company_address = "123 Business Street, City, Country";
    
    $full_name = $employee['first_name'] . ' ' . $employee['last_name'];
    $position = $employee['position'];
    $address = $employee['address'];
    
    $letter_content = "";
    
    // Common header
    $letter_header = "
    <div style='text-align: right;'>$today</div>
    <div style='margin-top: 20px;'>
        <strong>$full_name</strong><br>
        $address
    </div>
    <div style='margin-top: 20px;'>Dear $full_name,</div>
    ";
    
    // Common footer
    $letter_footer = "
    <div style='margin-top: 40px;'>
        Sincerely,<br><br><br>
        ____________________<br>
        HR Department<br>
        $company_name<br>
        $company_address
    </div>
    ";
    
    // Create content based on letter type
    switch ($letter_type) {
        case 'Appointment':
            $letter_content = $letter_header . "
            <div style='margin-top: 20px;'>
                <strong>Subject: Appointment as $position</strong>
            </div>
            
            <div style='margin-top: 20px;'>
                <p>We are pleased to inform you that you have been appointed to the position of <strong>$position</strong> at $company_name.</p>
                
                <p>Your appointment is effective from " . date('F d, Y') . ". Your compensation and benefits have been discussed and agreed upon during your interview process.</p>
                
                <p>$additional_details</p>
                
                <p>We are excited to have you join our team and look forward to your valuable contributions to our organization.</p>
                
                <p>Please sign and return a copy of this letter to acknowledge your acceptance of this appointment.</p>
            </div>
            " . $letter_footer;
            break;
            
        case 'Dismissal':
            $letter_content = $letter_header . "
            <div style='margin-top: 20px;'>
                <strong>Subject: Termination of Employment</strong>
            </div>
            
            <div style='margin-top: 20px;'>
                <p>This letter is to inform you that your employment with $company_name will be terminated effective " . date('F d, Y', strtotime('+14 days')) . ".</p>
                
                <p>$additional_details</p>
                
                <p>You are required to return all company property including, but not limited to, keys, ID cards, equipment, and documents before your last day of employment.</p>
                
                <p>Your final paycheck will include payment for any accrued but unused vacation days and will be issued according to company policy.</p>
                
                <p>If you have any questions regarding this decision or the transition process, please contact the HR department.</p>
            </div>
            " . $letter_footer;
            break;
            
        case 'Suspension':
            $letter_content = $letter_header . "
            <div style='margin-top: 20px;'>
                <strong>Subject: Temporary Suspension of Employment</strong>
            </div>
            
            <div style='margin-top: 20px;'>
                <p>This letter serves as notification that you are being placed on temporary suspension from your duties as $position, effective immediately.</p>
                
                <p>$additional_details</p>
                
                <p>During this suspension period, you are not permitted to enter company premises or access company systems without prior authorization from the HR department.</p>
                
                <p>This suspension will remain in effect until " . date('F d, Y', strtotime('+30 days')) . ", at which time your employment status will be reviewed.</p>
                
                <p>If you have any questions regarding this suspension, please contact the HR department.</p>
            </div>
            " . $letter_footer;
            break;
    }
    
    return $letter_content;
}

/**
 * Send letter via email to employee
 */
function sendLetterEmail($to_email, $letter_type, $letter_content, $employee) {
    $subject = '';
    $company_name = "Your Company Name";
    
    switch ($letter_type) {
        case 'Appointment':
            $subject = "Appointment Letter - $company_name";
            break;
        case 'Dismissal':
            $subject = "Important: Employment Update - $company_name";
            break;
        case 'Suspension':
            $subject = "Important: Employment Status Update - $company_name";
            break;
    }
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: HR Department <hr@yourcompany.com>" . "\r\n";
    
    // Send email
    $mail_sent = mail($to_email, $subject, $letter_content, $headers);
    
    return $mail_sent;
}

/**
 * Log user action to audit_logs table
 */
function logAction($user_id, $action) {
    global $conn;
    
    $sql = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

?>