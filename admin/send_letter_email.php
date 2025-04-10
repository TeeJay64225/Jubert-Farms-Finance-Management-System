<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}
include '../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Make sure PHPMailer is installed via Composer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];

    $sql = "SELECT e.email, l.letter_type, l.letter_content 
            FROM employees e 
            JOIN letters l ON e.id = l.employee_id 
            WHERE e.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->bind_result($email, $letter_type, $letter_content);
    
    if ($stmt->fetch()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.example.com'; // Replace with your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your@email.com';
            $mail->Password   = 'yourpassword';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('your@email.com', 'HR Department');
            $mail->addAddress($email);
            $mail->Subject = "$letter_type Letter";
            $mail->Body    = $letter_content;

            $mail->send();
            echo 'Letter emailed successfully.';
        } catch (Exception $e) {
            echo "Email failed: {$mail->ErrorInfo}";
        }
    } else {
        echo "No letter found.";
    }
    $stmt->close();
}
?>
