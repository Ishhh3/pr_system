-- Updated Database Schema with New Features
-- Version 2.0 - Enhanced Purchase Request System

-- CREATE DATABASE IF NOT EXISTS item_request_system;
-- USE item_request_system;

-- Table for user roles (UPDATED)
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for offices (previously departments)
CREATE TABLE offices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    office_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for users (office heads and secretaries) - UPDATED
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    office_id INT,
    role_id INT DEFAULT 2, -- Default to 'User' role (now includes both Head and Secretary)
    user_type ENUM('head', 'secretary') DEFAULT 'head', -- NEW: Distinguishes between head and secretary
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
);

-- NEW: Table for item categories
CREATE TABLE item_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for items (managed by IT department) - UPDATED
CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT, -- NEW: Link to category
    unit_types JSON, -- Store available unit types as JSON array
    price DECIMAL(10, 2) DEFAULT 0.00, -- NEW: Price per unit
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL
);

-- Table for bulk requests (one request can have multiple items)
CREATE TABLE requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    office_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
);

-- Table for request items (multiple items per request) - UPDATED
CREATE TABLE request_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    item_id INT NULL, -- NULL for custom "Others" items
    custom_item_name VARCHAR(200) NULL, -- NEW: For "Others" category
    unit_type VARCHAR(50) NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    price_per_unit DECIMAL(10, 2) DEFAULT 0.00, -- NEW: Price snapshot at request time
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- NEW: Table for system settings (signature fields, etc.)
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_requests_user ON requests(user_id);
CREATE INDEX idx_requests_office ON requests(office_id);
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_date ON requests(date_requested);
CREATE INDEX idx_request_items_request ON request_items(request_id);
CREATE INDEX idx_request_items_item ON request_items(item_id);
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_name ON items(item_name);

-- Insert default roles
INSERT INTO roles (role_name) VALUES 
('Admin'),
('User'); -- This now covers both Head and Secretary

-- Insert sample offices
INSERT INTO offices (office_name) VALUES 
('Human Resources Office'),
('Finance Office'),
('Operations Office'),
('Marketing Office'),
('Procurement Office'),
('Legal Office');

-- Insert default item categories
INSERT INTO item_categories (category_name, description) VALUES 
('Paper Products', 'All types of paper and paper-based products'),
('Office Supplies', 'General office supplies and stationery'),
('Technology', 'Computers, peripherals, and electronic devices'),
('Furniture', 'Office furniture and fixtures'),
('Cleaning Supplies', 'Cleaning and sanitation products'),
('Beverages', 'Coffee, tea, and refreshments'),
('Others', 'Miscellaneous items'); -- Special category for custom items

-- Insert sample items with categories and prices
INSERT INTO items (item_name, description, category_id, unit_types, price) VALUES 
-- Paper Products
('Bond Paper A4', 'Standard white bond paper', 1, '["reams", "boxes"]', 250.00),
('Bond Paper Legal', 'Legal size bond paper', 1, '["reams", "boxes"]', 280.00),
('Colored Paper', 'Assorted colored paper', 1, '["reams", "packs"]', 350.00),
('Folder', 'Document folders', 1, '["pcs", "dozens", "boxes"]', 5.00),
('Envelope (Letter)', 'Standard letter envelopes', 1, '["pcs", "boxes"]', 2.00),
('Envelope (Legal)', 'Legal size envelopes', 1, '["pcs", "boxes"]', 3.00),

-- Office Supplies
('Ballpen (Blue)', 'Blue ballpoint pen', 2, '["pcs", "dozens", "boxes"]', 8.00),
('Ballpen (Black)', 'Black ballpoint pen', 2, '["pcs", "dozens", "boxes"]', 8.00),
('Pencil', 'Writing pencils with eraser', 2, '["pcs", "dozens", "boxes"]', 5.00),
('Permanent Marker', 'Black permanent marker', 2, '["pcs", "sets"]', 25.00),
('Highlighter', 'Fluorescent highlighter set', 2, '["pcs", "sets"]', 15.00),
('Stapler', 'Standard office stapler', 2, '["pcs", "units"]', 85.00),
('Staples', 'Stapler refills', 2, '["boxes", "packs"]', 25.00),
('Hole Puncher', '2-hole paper puncher', 2, '["pcs", "units"]', 120.00),
('Scissors', 'Office scissors', 2, '["pcs", "units"]', 45.00),
('Paper Clips', 'Metal paper clips', 2, '["boxes", "packs"]', 35.00),
('Binder Clips', 'Assorted binder clips', 2, '["boxes", "packs"]', 40.00),
('Rubber Bands', 'Elastic rubber bands', 2, '["packs", "boxes"]', 30.00),
('Tape Dispenser', 'Desktop tape dispenser', 2, '["pcs", "units"]', 65.00),
('Correction Fluid', 'White correction fluid', 2, '["pcs", "bottles"]', 20.00),
('Glue Stick', 'Adhesive glue stick', 2, '["pcs", "packs"]', 18.00),
('Notebook', 'Spiral notebook', 2, '["pcs", "dozens"]', 35.00),
('Sticky Notes', 'Post-it sticky notes', 2, '["packs", "boxes"]', 45.00),
('Calculator', 'Desktop calculator', 2, '["pcs", "units"]', 250.00),

-- Technology
('Laptop', 'Company laptops for employees', 3, '["pcs", "units"]', 35000.00),
('Desktop Computer', 'Desktop computer with monitor', 3, '["pcs", "units", "sets"]', 28000.00),
('Monitor', '24-inch LCD/LED monitor', 3, '["pcs", "units"]', 8500.00),
('Keyboard', 'USB computer keyboard', 3, '["pcs", "units"]', 450.00),
('Mouse', 'Optical computer mouse', 3, '["pcs", "units"]', 250.00),
('Printer', 'Office laser printer', 3, '["pcs", "units"]', 12000.00),
('Scanner', 'Document scanner', 3, '["pcs", "units"]', 8500.00),
('Webcam', 'HD video conference camera', 3, '["pcs", "units"]', 2500.00),
('Headset', 'Audio headset with microphone', 3, '["pcs", "units"]', 850.00),
('USB Flash Drive', '32GB storage device', 3, '["pcs", "units"]', 350.00),
('External Hard Drive', '1TB backup storage', 3, '["pcs", "units"]', 2800.00),
('HDMI Cable', '2-meter HDMI cable', 3, '["pcs", "meters"]', 250.00),
('Network Cable', 'Cat6 ethernet cable', 3, '["pcs", "meters", "rolls"]', 15.00),
('Power Strip', '6-outlet extension cord', 3, '["pcs", "units"]', 450.00),
('Projector', 'HD presentation projector', 3, '["pcs", "units"]', 18000.00),

-- Furniture
('Office Desk', 'Standard office desk', 4, '["pcs", "units"]', 8500.00),
('Desk Chair', 'Ergonomic office chair', 4, '["pcs", "sets"]', 4500.00),
('File Cabinet', '4-drawer filing cabinet', 4, '["pcs", "units"]', 6500.00),
('Storage Box', 'Document storage box', 4, '["pcs", "units"]', 250.00),
('Whiteboard', '4x6 feet wall-mounted whiteboard', 4, '["pcs", "units"]', 3500.00),

-- Cleaning Supplies
('Tissue Paper', 'Facial tissue box', 5, '["boxes", "packs"]', 45.00),
('Toilet Paper', 'Bathroom tissue roll', 5, '["rolls", "packs"]', 35.00),
('Hand Sanitizer', '500ml alcohol-based sanitizer', 5, '["bottles", "liters"]', 85.00),
('Disinfectant Spray', 'Surface disinfectant spray', 5, '["bottles", "liters"]', 120.00),
('Air Freshener', 'Room deodorizer spray', 5, '["pcs", "bottles"]', 95.00),
('Trash Bags', 'Heavy duty garbage bags', 5, '["rolls", "packs"]', 180.00),
('Hand Soap', 'Liquid hand soap 500ml', 5, '["bottles", "liters"]', 65.00),

-- Beverages
('Coffee (3-in-1)', 'Instant coffee sachets', 6, '["packs", "boxes"]', 8.00),
('Coffee (Ground)', 'Ground coffee beans 250g', 6, '["packs", "kg"]', 350.00),
('Sugar', 'White refined sugar', 6, '["packs", "kg"]', 55.00),
('Creamer', 'Non-dairy coffee creamer', 6, '["packs", "boxes"]', 12.00),
('Disposable Cups', 'Paper cups 50pcs', 6, '["packs", "boxes"]', 85.00);

-- Insert default signature settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('signature_1_label', 'Requested by'),
('signature_1_name', ''),
('signature_2_label', 'Approved by'),
('signature_2_name', ''),
('signature_3_label', 'Verified by'),
('signature_3_name', ''),
('signature_4_label', 'Received by'),
('signature_4_name', '');

-- Create admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, office_id, role_id, user_type) VALUES 
('admin', 'admin@company.com', '$2y$12$8eNX9484xdvpju8gnA4q3u87uDkx4j3Zm.RfqOQZF0CdARyLa2pY6', 'IT Department Head', NULL, 1, 'head');

-- Create sample office heads and secretaries (password: user123 for all)
INSERT INTO users (username, email, password, full_name, office_id, role_id, user_type) VALUES 
('hr.head', 'hr.head@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'HR Office Head', 1, 2, 'head'),
('hr.secretary', 'hr.secretary@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'HR Secretary', 1, 2, 'secretary'),
('finance.head', 'finance.head@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'Finance Office Head', 2, 2, 'head'),
('finance.secretary', 'finance.secretary@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'Finance Secretary', 2, 2, 'secretary'),
('ops.head', 'ops.head@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'Operations Office Head', 3, 2, 'head'),
('ops.secretary', 'ops.secretary@company.com', '$2y$12$ogXwvo8Rd8GgCB0RNCYHOeAsAHCX2H63dbmFTbpvoMD1Ld0uSJge.', 'Operations Secretary', 3, 2, 'secretary');

-- Sample bulk requests with prices
INSERT INTO requests (user_id, office_id) VALUES (2, 1); -- HR Head request

INSERT INTO request_items (request_id, item_id, unit_type, quantity, price_per_unit) VALUES
(1, 1, 'reams', 10, 250.00),
(1, 7, 'dozens', 2, 8.00),
(1, 8, 'dozens', 2, 8.00),
(1, 24, 'pcs', 5, 35.00);

-- Sample request with custom "Others" item
INSERT INTO requests (user_id, office_id) VALUES (3, 1); -- HR Secretary request

INSERT INTO request_items (request_id, item_id, unit_type, quantity, price_per_unit) VALUES
(2, 2, 'reams', 5, 280.00),
(2, NULL, 'pcs', 3, 150.00); -- Custom item

-- Update the custom item with its name
UPDATE request_items SET custom_item_name = 'Custom Award Plaque' WHERE request_id = 2 AND item_id IS NULL;