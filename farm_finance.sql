CREATE DATABASE IF NOT EXISTS farm_finance;
USE farm_finance;

-- Users Table (For Admin Login)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'Employee') NOT NULL DEFAULT 'Employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);







-- this is the payroll side
-- Employees Table
CREATE TABLE IF NOT EXISTS employees (
id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL,
dob DATE NOT NULL,
position ENUM('C.E.O', 'Manager', 'Marketing Director', 'Supervisor', 'Laborer') NOT NULL, salary DECIMAL(10,2) NOT NULL,
employment_type ENUM('Fulltime', 'By-Day') NOT NULL,
phone VARCHAR(15) NOT NULL,
email VARCHAR(100) NOT NULL UNIQUE,
address TEXT NOT NULL,
emergency_contact VARCHAR(15) NOT NULL,
photo VARCHAR(255) DEFAULT NULL,
status ENUM('Active', 'Terminated', 'Suspended') DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Payroll Table
CREATE TABLE IF NOT EXISTS payroll (
id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL,
payment_date DATE NOT NULL,
FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
-- Letters Table (Stores generated letters)
 CREATE TABLE IF NOT EXISTS letters (
id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
letter_type ENUM('Appointment', 'Dismissal', 'Suspension') NOT NULL,
letter_content TEXT NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Audit Logs Table (Tracks user actions) 
CREATE TABLE IF NOT EXISTS audit_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
action TEXT NOT NULL,
timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



-- First, create the labor_categories table with unsigned INT
CREATE TABLE IF NOT EXISTS labor_categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    fee_per_head DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Then create the labor table with matching foreign key type
CREATE TABLE IF NOT EXISTS labor (
    labor_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    labor_date DATE NOT NULL,
    worker_name VARCHAR(100),
    hours_worked DECIMAL(5,2) NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    category_id INT UNSIGNED,
    task_description TEXT,
    payment_status ENUM('Paid', 'Not Paid') NOT NULL DEFAULT 'Not Paid',
    payment_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES labor_categories(category_id) ON DELETE SET NULL
);

-- Also update the labor_records table to match
CREATE TABLE IF NOT EXISTS labor_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    labor_date DATE NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    worker_count INT NOT NULL,
    fee_per_head DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES labor_categories(category_id) ON DELETE CASCADE
);



-- Clients table (already created)
CREATE TABLE IF NOT EXISTS clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales table (already created, but modified)
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(10) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    payment_status ENUM('Paid', 'Not Paid') NOT NULL DEFAULT 'Not Paid',
    client_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE SET NULL
);

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Unpaid',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);

-- Invoice items table
CREATE TABLE IF NOT EXISTS invoice_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
);

-- Receipts table
CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(20) NOT NULL UNIQUE,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'Credit Card', 'Bank Transfer', 'Check', 'Other') NOT NULL,
    payment_reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
);

CREATE INDEX idx_invoices_client_id ON invoices(client_id);
CREATE INDEX idx_invoice_items_invoice_id ON invoice_items(invoice_id);
CREATE INDEX idx_receipts_invoice_id ON receipts(invoice_id);

-- Expense Categories Table
CREATE TABLE IF NOT EXISTS expense_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expenses Table (Modified to include category)
CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    expense_reason VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_status ENUM('Paid', 'Not Paid') NOT NULL DEFAULT 'Paid',
    category_id INT,
    vendor_name VARCHAR(255),  
    notes TEXT,
    receipt_file VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(category_id) ON DELETE SET NULL
);

-- Insert default expense categories
INSERT INTO expense_categories (category_name, description) VALUES
('Labor', 'Costs related to workforce and manual labor'),
('Food/Water', 'Expenses for animal feed, water, and related consumables'),
('Transport', 'Transportation costs including fuel, vehicle maintenance, and delivery fees'),
('Fertilizer', 'Costs for fertilizers, soil enhancement, and plant nutrients'),
('Miscellaneous', 'Other expenses that do not fit into the above categories');


-- Assets Categories Table
CREATE TABLE IF NOT EXISTS asset_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assets Condition Enum
CREATE TABLE IF NOT EXISTS asset_conditions (
    condition_id INT AUTO_INCREMENT PRIMARY KEY,
    condition_name VARCHAR(50) NOT NULL UNIQUE
);

-- Inventory/Assets Table
CREATE TABLE IF NOT EXISTS assets (
    asset_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_name VARCHAR(255) NOT NULL,
    category_id INT,
    quantity INT NOT NULL DEFAULT 1,
    condition_id INT,
    acquisition_date DATE,
    acquisition_cost DECIMAL(10,2),
    current_value DECIMAL(10,2),
    location VARCHAR(100),
    serial_number VARCHAR(100),
    remarks TEXT,
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES asset_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (condition_id) REFERENCES asset_conditions(condition_id) ON DELETE SET NULL
);

-- Asset Transactions Table (for tracking additions, removals, transfers)
CREATE TABLE IF NOT EXISTS asset_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    transaction_type ENUM('Addition', 'Removal', 'Transfer', 'Maintenance', 'Value Adjustment') NOT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantity INT NOT NULL DEFAULT 1,
    from_location VARCHAR(100),
    to_location VARCHAR(100),
    cost DECIMAL(10,2),
    reason TEXT,
    performed_by VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE
);

-- Insert default asset categories
INSERT INTO asset_categories (category_name, description) VALUES
('Equipment & Machinery', 'Farm equipment, tools, and machinery used for operations'),
('Infrastructure', 'Buildings, storage facilities, and permanent structures'),
('Vehicles', 'Transportation vehicles including tractors and trucks'),
('Livestock', 'Farm animals and related assets'),
('Land', 'Farm land and property'),
('Tools', 'Hand tools and small equipment'),
('Office Equipment', 'Computers, furniture, and other office-related items');

-- Insert condition options
INSERT INTO asset_conditions (condition_name) VALUES
('Excellent'),
('Good'),
('Fair'),
('Poor'),
('Needs Repair'),
('Not Functional');

-- Insert sample assets from the provided data
INSERT INTO assets (asset_name, category_id, quantity, condition_id, acquisition_date, remarks) VALUES
('Laptop', (SELECT category_id FROM asset_categories WHERE category_name = 'Equipment & Machinery'), 1, 
 (SELECT condition_id FROM asset_conditions WHERE condition_name = 'Good'), '2024-01-15', 'Used for farm records'),
('Drone', (SELECT category_id FROM asset_categories WHERE category_name = 'Equipment & Machinery'), 1, 
 (SELECT condition_id FROM asset_conditions WHERE condition_name = 'Excellent'), '2024-03-10', 'Used for farm monitoring'),
('Water Tank', (SELECT category_id FROM asset_categories WHERE category_name = 'Infrastructure'), 2, 
 (SELECT condition_id FROM asset_conditions WHERE condition_name = 'Good'), '2023-12-05', '5000L capacity each'),
('Sprayers', (SELECT category_id FROM asset_categories WHERE category_name = 'Equipment & Machinery'), 3, 
 (SELECT condition_id FROM asset_conditions WHERE condition_name = 'Fair'), '2023-11-20', 'For pesticide application'),
('Storage Unit', (SELECT category_id FROM asset_categories WHERE category_name = 'Infrastructure'), 1, 
 (SELECT condition_id FROM asset_conditions WHERE condition_name = 'Good'), '2022-06-30', 'Stores harvested peppers');



-- Crop Categories Table
CREATE TABLE IF NOT EXISTS crop_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crops Table
CREATE TABLE IF NOT EXISTS crops (
    crop_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_name VARCHAR(100) NOT NULL,
    category_id INT,
    image_path VARCHAR(255),
    description TEXT,
    soil_requirements TEXT,
    watering_needs TEXT,
    sunlight_requirements VARCHAR(100),
    days_to_maturity VARCHAR(50),
    spacing_requirements VARCHAR(100),
    common_issues TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES crop_categories(category_id) ON DELETE SET NULL
);

CREATE TABLE crop_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    crop_id INT NOT NULL,
    description TEXT,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE
);

-- Seasons Table
CREATE TABLE IF NOT EXISTS seasons (
    season_id INT AUTO_INCREMENT PRIMARY KEY,
    season_name VARCHAR(20) NOT NULL UNIQUE
);

-- Growth Stages Table
CREATE TABLE IF NOT EXISTS growth_stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(50) NOT NULL UNIQUE,
    color_code VARCHAR(20) NOT NULL,
    description TEXT
);

-- Crop Calendar Table (for the main calendar display)
CREATE TABLE IF NOT EXISTS crop_calendar (
    calendar_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_id INT NOT NULL,
    stage_id INT NOT NULL,
    start_month INT NOT NULL CHECK (start_month BETWEEN 1 AND 12),
    end_month INT NOT NULL CHECK (end_month BETWEEN 1 AND 12),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES growth_stages(stage_id) ON DELETE CASCADE
);

-- Crop Season Relationships (for filtering by season)
CREATE TABLE IF NOT EXISTS crop_seasons (
    crop_season_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_id INT NOT NULL,
    season_id INT NOT NULL,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(season_id) ON DELETE CASCADE,
    UNIQUE (crop_id, season_id)
);

-- Common Issues Table (specific issues that affect crops)
CREATE TABLE IF NOT EXISTS common_issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_name VARCHAR(100) NOT NULL,
    issue_type ENUM('Pest', 'Disease', 'Environmental', 'Other') NOT NULL,
    description TEXT,
    symptoms TEXT,
    solutions TEXT,
    prevention TEXT
);

-- Crop Issues Relationship Table
CREATE TABLE IF NOT EXISTS crop_issues (
    crop_issue_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_id INT NOT NULL,
    issue_id INT NOT NULL,
    severity ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    notes TEXT,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE,
    FOREIGN KEY (issue_id) REFERENCES common_issues(issue_id) ON DELETE CASCADE,
    UNIQUE (crop_id, issue_id)
);

-- User Crop Notes Table (for user-specific notes on crops)
CREATE TABLE IF NOT EXISTS user_crop_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crop_id INT NOT NULL,
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE
);

-- Insert default crop categories
INSERT INTO crop_categories (category_name, description) VALUES
('Vegetables', 'Edible plant parts grown for consumption'),
('Fruits', 'Edible fruits from trees, vines, or bushes'),
('Grains', 'Cereal crops and edible seeds'),
('Herbs', 'Aromatic plants used for flavoring or medicine'),
('Leafy Greens', 'Edible leaves of plants'),
('Root Vegetables', 'Vegetables grown underground');

-- Insert seasons
INSERT INTO seasons (season_name) VALUES
('Spring'),
('Summer'),
('Fall'),
('Winter');

-- Insert growth stages
INSERT INTO growth_stages (stage_name, color_code, description) VALUES
('Planting', '#bbdefb', 'Initial planting period when seeds or seedlings are placed in soil'),
('Growing', '#c8e6c9', 'Main growth period when plants develop'),
('Harvesting', '#ffcc80', 'Period when crops are ready to be harvested');

-- Insert sample crops
INSERT INTO crops (crop_name, category_id, soil_requirements, watering_needs, sunlight_requirements, days_to_maturity, spacing_requirements, common_issues) VALUES
('Tomatoes', (SELECT category_id FROM crop_categories WHERE category_name = 'Vegetables'), 
 'Well-drained, rich in organic matter', 'Regular, consistent moisture', 'Full sun (6-8 hours daily)', 
 '70-90 days', '18-24 inches apart', 'Susceptible to blight, hornworms, blossom end rot'),

('Lettuce', (SELECT category_id FROM crop_categories WHERE category_name = 'Leafy Greens'), 
 'Loose, cool, well-drained soil', 'Consistent moisture, avoid drought', 'Partial shade to full sun', 
 '45-60 days', '8-12 inches apart', 'Aphids, slugs, bolting in hot weather'),

('Carrots', (SELECT category_id FROM crop_categories WHERE category_name = 'Root Vegetables'), 
 'Loose, sandy, well-drained soil', 'Regular moisture, especially when young', 'Full sun to partial shade', 
 '60-80 days', '2-3 inches apart', 'Carrot rust flies, forking due to rocky soil'),

('Corn', (SELECT category_id FROM crop_categories WHERE category_name = 'Grains'), 
 'Rich, well-drained soil', 'Regular water, especially during tasseling', 'Full sun', 
 '60-100 days', '8-12 inches apart in rows 30-36 inches apart', 'Corn earworms, raccoon damage'),

('Spinach', (SELECT category_id FROM crop_categories WHERE category_name = 'Leafy Greens'), 
 'Rich, moist soil high in organic matter', 'Regular moisture', 'Partial shade to full sun', 
 '40-50 days', '3-5 inches apart', 'Leaf miners, bolting in hot weather'),

('Bell Peppers', (SELECT category_id FROM crop_categories WHERE category_name = 'Vegetables'), 
 'Well-drained, rich soil', 'Consistent moisture', 'Full sun', 
 '60-90 days', '18-24 inches apart', 'Aphids, blossom end rot'),

('Potatoes', (SELECT category_id FROM crop_categories WHERE category_name = 'Root Vegetables'), 
 'Loose, slightly acidic soil', 'Consistent moisture', 'Full sun', 
 '90-120 days', '12-15 inches apart', 'Colorado potato beetle, blight');

-- Insert sample crop calendar entries for the sample crops
-- Tomatoes
INSERT INTO crop_calendar (crop_id, stage_id, start_month, end_month) VALUES
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Planting'), 3, 4),
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Growing'), 5, 6),
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Harvesting'), 7, 9);

-- Lettuce
INSERT INTO crop_calendar (crop_id, stage_id, start_month, end_month) VALUES
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Planting'), 3, 3),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Growing'), 4, 4),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Harvesting'), 5, 6),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Planting'), 8, 8),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Growing'), 9, 9),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT stage_id FROM growth_stages WHERE stage_name = 'Harvesting'), 10, 10);

-- Associate crops with seasons
INSERT INTO crop_seasons (crop_id, season_id) VALUES
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Summer')),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Spring')),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Fall')),
((SELECT crop_id FROM crops WHERE crop_name = 'Carrots'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Spring')),
((SELECT crop_id FROM crops WHERE crop_name = 'Carrots'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Fall')),
((SELECT crop_id FROM crops WHERE crop_name = 'Corn'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Summer')),
((SELECT crop_id FROM crops WHERE crop_name = 'Spinach'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Spring')),
((SELECT crop_id FROM crops WHERE crop_name = 'Spinach'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Fall')),
((SELECT crop_id FROM crops WHERE crop_name = 'Bell Peppers'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Summer')),
((SELECT crop_id FROM crops WHERE crop_name = 'Potatoes'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Spring')),
((SELECT crop_id FROM crops WHERE crop_name = 'Potatoes'), 
 (SELECT season_id FROM seasons WHERE season_name = 'Summer'));

-- Insert sample common issues
INSERT INTO common_issues (issue_name, issue_type, description, symptoms, solutions, prevention) VALUES
('Aphids', 'Pest', 'Small sap-sucking insects that can quickly colonize plants', 
 'Curled leaves, sticky residue, yellowing leaves', 
 'Insecticidal soap, neem oil, introducing natural predators like ladybugs', 
 'Regular inspection, companion planting, healthy soil'),

('Blight', 'Disease', 'Fungal disease affecting many crops especially in wet conditions', 
 'Brown spots on leaves that spread quickly, wilting', 
 'Remove affected parts, fungicide application, improve air circulation', 
 'Crop rotation, resistant varieties, avoid overhead watering'),

('Blossom End Rot', 'Environmental', 'Calcium deficiency often triggered by irregular watering', 
 'Dark, sunken spots at the blossom end of fruits', 
 'Consistent watering, calcium supplements', 
 'Soil testing, regular watering schedule, mulching');

-- Link issues to crops
INSERT INTO crop_issues (crop_id, issue_id, severity) VALUES
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT issue_id FROM common_issues WHERE issue_name = 'Blight'), 'High'),
((SELECT crop_id FROM crops WHERE crop_name = 'Tomatoes'), 
 (SELECT issue_id FROM common_issues WHERE issue_name = 'Blossom End Rot'), 'Medium'),
((SELECT crop_id FROM crops WHERE crop_name = 'Bell Peppers'), 
 (SELECT issue_id FROM common_issues WHERE issue_name = 'Aphids'), 'Medium'),
((SELECT crop_id FROM crops WHERE crop_name = 'Lettuce'), 
 (SELECT issue_id FROM common_issues WHERE issue_name = 'Aphids'), 'High');

 -- Crop Cycles Table (for tracking multiple plantings of the same crop)
CREATE TABLE IF NOT EXISTS crop_cycles (
    cycle_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_id INT NOT NULL,
    field_or_location VARCHAR(100),
    start_date DATE NOT NULL,
    nursing_duration INT NOT NULL COMMENT 'Duration in days',
    growth_duration INT NOT NULL COMMENT 'Duration in days',
    expected_first_harvest DATE NOT NULL,
    harvest_frequency INT COMMENT 'Days between harvests',
    expected_end_date DATE,
    status ENUM('Planned', 'In Progress', 'Completed', 'Failed') DEFAULT 'Planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (crop_id) REFERENCES crops(crop_id) ON DELETE CASCADE
);

-- Task Types Table
CREATE TABLE IF NOT EXISTS task_types (
    task_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    color_code VARCHAR(20) NOT NULL,
    icon VARCHAR(50),
    description TEXT
);

-- Farm Tasks Table
CREATE TABLE IF NOT EXISTS farm_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    task_type_id INT NOT NULL,
    task_name VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    completion_status BOOLEAN DEFAULT FALSE,
    completed_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES crop_cycles(cycle_id) ON DELETE CASCADE,
    FOREIGN KEY (task_type_id) REFERENCES task_types(task_type_id) ON DELETE CASCADE
);

-- Harvest Records Table
CREATE TABLE IF NOT EXISTS harvest_records (
    harvest_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    quantity DECIMAL(10,2),
    unit VARCHAR(20),
    quality_rating INT CHECK (quality_rating BETWEEN 1 AND 5),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES crop_cycles(cycle_id) ON DELETE CASCADE
);

-- Insert default task types
INSERT INTO task_types (type_name, color_code, icon, description) VALUES
('Fertilizing', '#8bc34a', 'fertilizer', 'Application of fertilizers'),
('Spraying', '#03a9f4', 'spray', 'Application of pesticides or other treatments'),
('Watering', '#00bcd4', 'water', 'Irrigation and watering tasks'),
('Planting', '#9c27b0', 'seed', 'Planting seeds or transplanting'),
('Harvesting', '#ff9800', 'harvest', 'Collecting mature crops'),
('Weeding', '#795548', 'weed', 'Removing unwanted plants'),
('Pruning', '#607d8b', 'scissors', 'Trimming plants for optimal growth');

-- Create the suppliers table to store supplier company information
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create the product categories table for organizing chemical supplies
CREATE TABLE IF NOT EXISTS product_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the products table to store chemical supply details
CREATE TABLE IF NOT EXISTS chemical_products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    supplier_id INT NOT NULL,
    category_id INT,
    description TEXT,
    unit_of_measure VARCHAR(50) NOT NULL,
    price_per_unit DECIMAL(10,2),
    application_rate VARCHAR(100),
    safety_info TEXT,
    composition TEXT,
    registration_number VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES product_categories(category_id) ON DELETE SET NULL
);

-- Create inventory table to track stock levels of chemical products
CREATE TABLE IF NOT EXISTS chemical_inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(100),
    expiration_date DATE,
    purchase_date DATE NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES chemical_products(product_id) ON DELETE CASCADE
);

-- Create table to track chemical usage on crops
CREATE TABLE IF NOT EXISTS chemical_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    cycle_id INT,
    application_date DATE NOT NULL,
    quantity_used DECIMAL(10,2) NOT NULL,
    area_treated VARCHAR(100),
    applied_by VARCHAR(100),
    weather_conditions TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES chemical_products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES crop_cycles(cycle_id) ON DELETE SET NULL
);

-- Insert default product categories
INSERT INTO product_categories (category_name, description) VALUES
('Fertilizers', 'Products that provide nutrients to plants'),
('Pesticides', 'Products that control pests including insects'),
('Herbicides', 'Products that control weeds and unwanted plants'),
('Fungicides', 'Products that control fungal diseases'),
('Growth Regulators', 'Products that regulate plant growth processes'),
('Soil Amendments', 'Products that improve soil quality');


-- Users Table
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
('manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager'),
('employee1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee');

-- Employees Table
INSERT INTO employees (first_name, last_name, dob, position, salary, employment_type, phone, email, address, emergency_contact, status) VALUES
('John', 'Smith', '1985-05-15', 'Manager', 50000.00, 'Fulltime', '123-456-7890', 'john.smith@example.com', '123 Farm Road, Farmville', '987-654-3210', 'Active'),
('Jane', 'Doe', '1990-03-20', 'Supervisor', 40000.00, 'Fulltime', '234-567-8901', 'jane.doe@example.com', '456 Crop Lane, Farmville', '876-543-2109', 'Active'),
('Bob', 'Johnson', '1988-07-12', 'Laborer', 30000.00, 'Fulltime', '345-678-9012', 'bob.johnson@example.com', '789 Harvest St, Farmville', '765-432-1098', 'Active'),
('Alice', 'Williams', '1992-11-30', 'Marketing Director', 45000.00, 'Fulltime', '456-789-0123', 'alice.williams@example.com', '101 Market Rd, Farmville', '654-321-0987', 'Active'),
('David', 'Brown', '1993-01-25', 'Laborer', 100.00, 'By-Day', '567-890-1234', 'david.brown@example.com', '202 Field Ave, Farmville', '543-210-9876', 'Active');

-- Payroll Table
INSERT INTO payroll (employee_id, amount, payment_date) VALUES
(1, 4166.67, '2024-03-31'),
(2, 3333.33, '2024-03-31'),
(3, 2500.00, '2024-03-31'),
(4, 3750.00, '2024-03-31'),
(5, 500.00, '2024-03-31'),
(1, 4166.67, '2024-04-30'),
(2, 3333.33, '2024-04-30'),
(3, 2500.00, '2024-04-30'),
(4, 3750.00, '2024-04-30');

-- Letters Table
INSERT INTO letters (employee_id, letter_type, letter_content) VALUES
(1, 'Appointment', 'Dear John Smith, We are pleased to confirm your appointment as Manager...'),
(5, 'Suspension', 'Dear David Brown, This letter serves as formal notification of your temporary suspension...');



-- Labor Categories Table
INSERT INTO labor_categories (category_name, fee_per_head, description) VALUES
('Harvesting', 150.00, 'Workers for harvesting crops'),
('Planting', 120.00, 'Workers for planting seeds and seedlings'),
('Weeding', 100.00, 'Workers for removing weeds'),
('General Labor', 90.00, 'General farm labor and maintenance');

-- Labor Table
INSERT INTO labor (labor_date, worker_name, hours_worked, hourly_rate, total_amount, category_id, task_description, payment_status) VALUES
('2024-04-01', 'Carlos Mendez', 8.5, 12.50, 106.25, 1, 'Tomato harvesting', 'Paid'),
('2024-04-01', 'Maria Garcia', 7.0, 12.50, 87.50, 1, 'Tomato harvesting', 'Paid'),
('2024-04-02', 'James Wilson', 6.5, 10.00, 65.00, 3, 'Weeding vegetable garden', 'Paid'),
('2024-04-03', 'Sarah Johnson', 8.0, 11.00, 88.00, 2, 'Planting corn seedlings', 'Not Paid'),
('2024-04-04', 'Luis Rodriguez', 9.0, 12.50, 112.50, 4, 'Fence repair', 'Not Paid');

-- Labor Records Table
INSERT INTO labor_records (labor_date, category_id, worker_count, fee_per_head, total_cost, notes) VALUES
('2024-04-01', 1, 12, 150.00, 1800.00, 'Tomato harvest team'),
('2024-04-02', 3, 8, 100.00, 800.00, 'Weeding team for north field'),
('2024-04-03', 2, 10, 120.00, 1200.00, 'Corn planting team');

-- Clients Table
INSERT INTO clients (full_name, email, phone_number, address, notes) VALUES
('Farmville Market', 'orders@farmvillemarket.com', '123-555-7890', '100 Market St, Farmville', 'Weekly orders for fresh produce'),
('Green Grocers', 'purchasing@greengrocers.com', '234-555-6789', '200 Main St, Greentown', 'Organic produce buyer'),
('Farm-to-Table Restaurant', 'chef@farmtotable.com', '345-555-5678', '300 Cuisine Blvd, Tasteville', 'Premium client, requires highest quality'),
('Local School District', 'nutrition@localschools.edu', '456-555-4567', '400 Education Dr, Learnville', 'School lunch program'),
('Community Supported Agriculture', 'members@csafarm.org', '567-555-3456', '500 Community Way, Shareville', 'Weekly subscription boxes');

-- Sales Table
INSERT INTO sales (invoice_no, product_name, quantity, unit_price, amount, sale_date, payment_status, client_id, notes) VALUES
('INV-001', 'Tomatoes', 50, 2.50, 125.00, '2024-04-01', 'Paid', 1, 'Regular weekly order'),
('INV-002', 'Lettuce', 30, 1.75, 52.50, '2024-04-01', 'Paid', 1, NULL),
('INV-003', 'Organic Bell Peppers', 25, 3.00, 75.00, '2024-04-02', 'Paid', 2, 'Special order'),
('INV-004', 'Carrots', 100, 1.25, 125.00, '2024-04-03', 'Paid', 3, 'Premium quality'),
('INV-005', 'Potatoes', 200, 0.75, 150.00, '2024-04-04', 'Not Paid', 4, 'Monthly school order');

-- Invoices Table
INSERT INTO invoices (invoice_no, client_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, payment_status, notes) VALUES
('INV2024-001', 1, '2024-04-01', '2024-04-15', 177.50, 5.00, 8.88, 0.00, 186.38, 'Paid', 'Weekly order'),
('INV2024-002', 2, '2024-04-02', '2024-04-16', 75.00, 5.00, 3.75, 0.00, 78.75, 'Paid', 'Organic produce'),
('INV2024-003', 3, '2024-04-03', '2024-04-17', 125.00, 5.00, 6.25, 12.50, 118.75, 'Paid', '10% loyalty discount'),
('INV2024-004', 4, '2024-04-04', '2024-04-18', 150.00, 0.00, 0.00, 0.00, 150.00, 'Unpaid', 'Tax exempt educational institution'),
('INV2024-005', 5, '2024-04-05', '2024-04-19', 250.00, 5.00, 12.50, 25.00, 237.50, 'Partial', 'Weekly subscription boxes');

-- Invoice Items Table
INSERT INTO invoice_items (invoice_id, product_name, description, quantity, unit_price, amount) VALUES
(1, 'Tomatoes', 'Fresh vine tomatoes', 50, 2.50, 125.00),
(1, 'Lettuce', 'Organic green leaf lettuce', 30, 1.75, 52.50),
(2, 'Organic Bell Peppers', 'Mixed colors', 25, 3.00, 75.00),
(3, 'Carrots', 'Premium organic carrots', 100, 1.25, 125.00),
(4, 'Potatoes', 'Russet potatoes', 200, 0.75, 150.00),
(5, 'Mixed Seasonal Vegetables', 'Weekly CSA box', 25, 10.00, 250.00);

-- Receipts Table
INSERT INTO receipts (receipt_no, invoice_id, payment_date, payment_amount, payment_method, payment_reference, notes) VALUES
('REC2024-001', 1, '2024-04-10', 186.38, 'Bank Transfer', 'TRF123456', 'Payment received in full'),
('REC2024-002', 2, '2024-04-12', 78.75, 'Credit Card', 'CC789012', NULL),
('REC2024-003', 3, '2024-04-15', 118.75, 'Check', 'CHK345678', 'Check cleared'),
('REC2024-005', 5, '2024-04-17', 100.00, 'Cash', 'CSH901234', 'Partial payment received');

-- Expenses Table (using default categories already inserted)
INSERT INTO expenses (expense_reason, amount, expense_date, payment_status, category_id, vendor_name, notes) VALUES
('Monthly labor wages', 3500.00, '2024-03-31', 'Paid', 1, 'Internal', 'Regular monthly labor payment'),
('Chicken feed purchase', 850.00, '2024-04-05', 'Paid', 2, 'Farm Supply Co.', '10 bags of premium feed'),
('Diesel for tractors', 320.00, '2024-04-07', 'Paid', 3, 'Local Gas Station', '40 gallons'),
('NPK fertilizer', 1200.00, '2024-04-10', 'Paid', 4, 'Agri-Chem Supplies', '20 bags of general purpose fertilizer'),
('Equipment repair', 450.00, '2024-04-15', 'Not Paid', 5, 'Farm Equipment Repairs', 'Tractor maintenance');

-- Assets (added sample data for asset tables already in DB)
-- Adding more sample transactions
INSERT INTO asset_transactions (asset_id, transaction_type, transaction_date, quantity, from_location, to_location, cost, reason, performed_by) VALUES
(1, 'Maintenance', '2024-04-10', 1, NULL, NULL, 150.00, 'Software update and cleaning', 'John Smith'),
(2, 'Transfer', '2024-04-12', 1, 'Main Office', 'Field Operations', NULL, 'Needed for aerial survey', 'Jane Doe'),
(3, 'Value Adjustment', '2024-04-15', 1, NULL, NULL, -100.00, 'Minor damage from storm', 'John Smith');

-- Suppliers Table
INSERT INTO suppliers (company_name, contact_person, phone, email, address, notes) VALUES
('Farm Supply Co.', 'Michael Johnson', '123-456-7890', 'mjohnson@farmsupply.com', '123 Supply Road, Farmville', 'Main supplier for animal feed'),
('Agri-Chem Supplies', 'Sarah Williams', '234-567-8901', 'swilliams@agrichem.com', '234 Chemical Ave, Cropville', 'Agricultural chemicals and fertilizers'),
('Farm Equipment Inc.', 'James Davis', '345-678-9012', 'jdavis@farmequip.com', '345 Machine St, Toolville', 'Equipment and parts supplier'),
('Green Grow Organics', 'Lisa Martinez', '456-789-0123', 'lmartinez@greengrow.com', '456 Organic Blvd, Ecotown', 'Organic fertilizers and pest control'),
('Irrigation Systems', 'Robert Wilson', '567-890-1234', 'rwilson@irrigationsys.com', '567 Water Way, Flowville', 'Irrigation equipment and supplies');

-- Chemical Products Table (using existing product categories)
INSERT INTO chemical_products (product_name, supplier_id, category_id, description, unit_of_measure, price_per_unit, application_rate, safety_info, composition) VALUES
('Premium NPK 15-15-15', 2, 1, 'Balanced fertilizer for general use', 'kg', 25.00, '50kg per acre', 'Keep away from children and water sources', 'N:15%, P:15%, K:15%'),
('Organic Plant Food', 4, 1, 'Certified organic plant food', 'kg', 35.00, '25kg per acre', 'Safe for organic farming, keep sealed', 'Composted materials, bone meal, blood meal'),
('Weed-B-Gone', 2, 3, 'General purpose herbicide', 'liter', 45.00, '2 liters per acre diluted', 'Wear gloves and mask during application', 'Glyphosate 41%'),
('Insect Shield', 2, 2, 'Broad-spectrum insecticide', 'liter', 60.00, '1 liter per acre diluted', 'Toxic to bees, avoid application during flowering', 'Pyrethroids 10%, Inert ingredients 90%'),
('Fungus Fighter', 4, 4, 'Organic fungicide for vegetables', 'liter', 40.00, '3 liters per acre diluted', 'Apply in early morning or late evening', 'Neem oil, garlic extract');

-- Chemical Inventory Table
INSERT INTO chemical_inventory (product_id, quantity, batch_number, expiration_date, purchase_date, purchase_price, location) VALUES
(1, 500.00, 'NPK-202404-01', '2026-04-01', '2024-04-01', 1250.00, 'Main Warehouse'),
(2, 250.00, 'ORG-202404-01', '2025-10-15', '2024-04-02', 875.00, 'Organic Storage Room'),
(3, 100.00, 'WBG-202404-01', '2026-04-05', '2024-04-03', 450.00, 'Chemical Storage'),
(4, 50.00, 'INS-202404-01', '2025-12-31', '2024-04-04', 300.00, 'Chemical Storage'),
(5, 75.00, 'FUN-202404-01', '2025-09-30', '2024-04-05', 300.00, 'Organic Storage Room');

-- Crop Cycles Table (using existing crops data)
INSERT INTO crop_cycles (crop_id, field_or_location, start_date, nursing_duration, growth_duration, expected_first_harvest, harvest_frequency, expected_end_date, status) VALUES
(1, 'Field A - North', '2024-03-15', 21, 60, '2024-06-15', 7, '2024-09-15', 'In Progress'),
(2, 'Field B - East', '2024-03-20', 7, 40, '2024-05-06', 3, '2024-06-06', 'In Progress'),
(3, 'Field C - West', '2024-04-01', 14, 65, '2024-06-19', NULL, '2024-06-19', 'In Progress'),
(6, 'Greenhouse 1', '2024-03-01', 28, 70, '2024-06-08', 10, '2024-09-08', 'In Progress'),
(7, 'Field D - South', '2024-04-10', 0, 90, '2024-07-09', NULL, '2024-07-09', 'Planned');

-- Farm Tasks Table (using existing task types)
INSERT INTO farm_tasks (cycle_id, task_type_id, task_name, scheduled_date, completion_status, completed_date, notes) VALUES
(1, 4, 'Plant tomato seedlings', '2024-03-15', TRUE, '2024-03-15', 'Completed on schedule'),
(1, 1, 'Apply starter fertilizer', '2024-03-22', TRUE, '2024-03-23', 'Delayed one day due to rain'),
(1, 3, 'Set up irrigation system', '2024-03-25', TRUE, '2024-03-25', NULL),
(2, 4, 'Direct seed lettuce', '2024-03-20', TRUE, '2024-03-20', 'Used new seed variety'),
(2, 6, 'First weeding', '2024-04-10', FALSE, NULL, 'Scheduled for next week'),
(3, 4, 'Plant carrot seeds', '2024-04-01', TRUE, '2024-04-01', 'Soil was perfect condition'),
(3, 3, 'First watering', '2024-04-01', TRUE, '2024-04-01', 'Applied after planting');

-- Harvest Records Table
INSERT INTO harvest_records (cycle_id, harvest_date, quantity, unit, quality_rating, notes) VALUES
(2, '2024-05-10', 50.5, 'kg', 5, 'First lettuce harvest, excellent quality'),
(2, '2024-05-13', 45.0, 'kg', 4, 'Second lettuce harvest');

-- Chemical Applications Table
INSERT INTO chemical_applications (product_id, cycle_id, application_date, quantity_used, area_treated, applied_by, weather_conditions, notes) VALUES
(1, 1, '2024-03-23', 25.0, 'Field A - North', 'Bob Johnson', 'Clear, light breeze, 72°F', 'Starter fertilizer applied after planting'),
(5, 1, '2024-04-10', 3.0, 'Field A - North', 'Jane Doe', 'Cloudy, no wind, 65°F', 'Preventative fungicide application'),
(2, 2, '2024-03-25', 12.5, 'Field B - East', 'Bob Johnson', 'Sunny, 70°F', 'Organic fertilizer application for lettuce'),
(3, 3, '2024-04-15', 2.0, 'Field C - West', 'Jane Doe', 'Clear, 68°F', 'Spot treatment for weed outbreak');

-- User Crop Notes Table
INSERT INTO user_crop_notes (user_id, crop_id, note_text) VALUES
(1, 1, 'This variety of tomatoes seems to be performing better than last year. Consider increasing planting area next season.'),
(2, 2, 'Lettuce is showing signs of heat stress. Consider planting in partial shade next time or using shade cloth.'),
(1, 6, 'Bell peppers need more calcium. Add eggshells to the soil next planting.');

-- Crop Events Table
INSERT INTO crop_events (event_date, event_name, crop_id, description) VALUES
('2024-05-01', 'First Flowering', 1, 'Tomato plants showing first flowers'),
('2024-04-15', 'Germination Complete', 2, 'Lettuce seeds have all germinated'),
('2024-04-20', 'Thinning', 3, 'Thinned carrot seedlings to proper spacing'),
('2024-05-10', 'Fruit Set', 6, 'Bell peppers starting to set fruit');

-- Create indexes for better performance
CREATE INDEX idx_chemical_products_supplier ON chemical_products(supplier_id);
CREATE INDEX idx_chemical_products_category ON chemical_products(category_id);
CREATE INDEX idx_chemical_inventory_product ON chemical_inventory(product_id);
CREATE INDEX idx_chemical_applications_product ON chemical_applications(product_id);
CREATE INDEX idx_chemical_applications_cycle ON chemical_applications(cycle_id);