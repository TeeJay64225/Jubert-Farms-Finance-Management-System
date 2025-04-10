<?php
// Simple bootstrap.php
// Start session

// bootstrap.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

// Just include router logic
require_once 'router.php'; // This uses switch-case directly. No Router class needed.

define('BASE_URL', '/Jubert_farms_finance_Management_system');

// Initialize router
$router = new Router();

// Define routes based on project structure with role requirements
// Admin routes
$router->addRoute('dashboard',  __DIR__ . '/admin/dashboard.php');
$router->addRoute('payroll',  __DIR__ . 'admin/payroll.php', 'Admin');
$router->addRoute('payroll_dashboard', __DIR__ . 'admin/payroll_dashboard.php', 'Admin');
$router->addRoute('bulk_email', __DIR__ . 'admin/bulk_email.php', 'Admin');
$router->addRoute('send_bulk_email', __DIR__ . 'admin/send_bulk_email.php', 'Admin');
$router->addRoute('employee_management', __DIR__ . 'admin/employee_management.php', 'Admin');
$router->addRoute('generate_letter', __DIR__ . 'admin/generate_letter.php', 'Admin');
$router->addRoute('send_letter_email',  __DIR__ . 'admin/send_letter_email.php', 'Admin');
$router->addRoute('select_section',  __DIR__ . 'admin/select_section.php', 'Admin');

// Manager routes
$router->addRoute('manager_dashboard', __DIR__ . 'manager/manager_dashboard.php', 'Manager');
$router->addRoute('manager_expenses', __DIR__ . 'manager/manager_expenses.php', 'Manager');
$router->addRoute('manager_sales',  __DIR__ . 'manager/manager_sales.php', 'Manager');
$router->addRoute('manager_report',  __DIR__ . 'manager/manager_report.php', 'Manager');
$router->addRoute('manager_expenses_categories',  __DIR__ . 'manager/manager_expenses_categories.php', 'Manager');

// Employee routes
$router->addRoute('employee_dashboard', __DIR__ . 'employee/employee_dashboard.php', 'Employee');
$router->addRoute('employee_expenses', __DIR__ . 'employee/employee_expenses.php', 'Employee');
$router->addRoute('employee_sales', __DIR__ . 'employee/employee_sales.php', 'Employee');

// General routes - 'Any' means any authenticated user can access
$router->addRoute('expenses', __DIR__ . 'expenses.php', 'Any');
$router->addRoute('expenses_categories', __DIR__ . 'expenses_categories.php', 'Any');
$router->addRoute('sales',  __DIR__ . 'sales.php', 'Any');
$router->addRoute('clients_sales',  __DIR__ . 'clients_sales.php', 'Any');
$router->addRoute('clients',  __DIR__ . 'clients.php', 'Any');
$router->addRoute('reports',  __DIR__ . 'reports.php', 'Any');
$router->addRoute('export_report',  __DIR__ . 'export_report.php', 'Any');
$router->addRoute('report',   __DIR__ . 'report.php', 'Any');
$router->addRoute('finance_summary',  __DIR__ . 'finance_summary.php', 'Any');
$router->addRoute('assets',   __DIR__ . 'assets.php', 'Any');
$router->addRoute('asset_categories',  __DIR__ . 'asset_categories.php', 'Any');
$router->addRoute('asset_report',  __DIR__ . 'asset_report.php', 'Any');
$router->addRoute('harvest_crop',  __DIR__ . 'harvest_crop.php', 'Any');
$router->addRoute('harvest_crop_analysis',  __DIR__ . 'harvest_crop_analysis.php', 'Any');
$router->addRoute('invoice_generator',  __DIR__ . 'invoice_generator.php', 'Any');
$router->addRoute('invoice_pdf',  __DIR__ . 'invoice_pdf.php', 'Any');
$router->addRoute('receipt_pdf',  __DIR__ . 'receipt_pdf.php', 'Any');
$router->addRoute('slip',  __DIR__ . 'slip.php', 'Any');
$router->addRoute('user',  __DIR__ . 'user.php', 'Any');

// Public routes - no authentication required
$router->addRoute('login', 'views/login.php');
$router->addRoute('register', 'views/register.php');
$router->addRoute('logout', 'logout.php');

// Dispatch the current request
$url = isset($_GET['url']) ? $_GET['url'] : 'dashboard';
$router->dispatch($url);