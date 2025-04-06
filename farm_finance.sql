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

-- Create indexes for better performance
CREATE INDEX idx_client_id ON invoices(client_id);
CREATE INDEX idx_invoice_id ON invoice_items(invoice_id);
CREATE INDEX idx_receipt_invoice ON receipts(invoice_id);

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