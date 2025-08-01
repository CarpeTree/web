-- Carpe Tree'em Quote-to-Invoice Database Schema
-- MySQL/MariaDB with InnoDB engine

CREATE DATABASE IF NOT EXISTS carpe_tree_quotes;
USE carpe_tree_quotes;

-- Customers table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    referral_source ENUM('google', 'facebook', 'instagram', 'referral', 'neighbor', 'previous_work', 'online_review', 'other'),
    referrer_name VARCHAR(255),
    newsletter_opt_in BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Quotes table
CREATE TABLE quotes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    quote_status ENUM('submitted', 'ai_processing', 'multi_ai_processing', 'draft_ready', 'multi_ai_complete', 'sent_to_client', 'accepted', 'rejected', 'expired') DEFAULT 'submitted',
    quote_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    quote_expires_at TIMESTAMP DEFAULT (DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 6 MONTH)),
    selected_services JSON,
    gps_lat DECIMAL(10, 8),
    gps_lng DECIMAL(11, 8),
    exif_lat DECIMAL(10, 8),
    exif_lng DECIMAL(11, 8),
    total_estimate DECIMAL(10, 2),
    notes TEXT,
    ai_analysis_complete BOOLEAN DEFAULT FALSE,
    ai_response_json JSON,
    ai_o4_mini_analysis JSON,
    ai_o3_analysis JSON,
    ai_gemini_analysis JSON,
    scheduled_at TIMESTAMP NULL,
    google_event_id VARCHAR(255),
    preflight_check_status JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_status (quote_status),
    INDEX idx_created_at (quote_created_at),
    INDEX idx_expires_at (quote_expires_at),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB;

-- Trees table (identified by AI from photos)
CREATE TABLE trees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    tree_species VARCHAR(255),
    tree_height_meters DECIMAL(5, 2),
    tree_height_feet DECIMAL(5, 2),
    tree_dbh_cm DECIMAL(5, 2),
    tree_dbh_inches DECIMAL(5, 2),
    tree_condition ENUM('excellent', 'good', 'fair', 'poor', 'dead'),
    tree_lean_desc TEXT,
    proximity_to_structures BOOLEAN DEFAULT FALSE,
    proximity_to_powerlines BOOLEAN DEFAULT FALSE,
    is_conifer BOOLEAN DEFAULT FALSE,
    within_20m_building BOOLEAN DEFAULT FALSE,
    sprinkler_upsell_applicable BOOLEAN DEFAULT FALSE,
    ai_confidence_score DECIMAL(3, 2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id),
    INDEX idx_species (tree_species)
) ENGINE=InnoDB;

-- Tree work orders (specific services per tree)
CREATE TABLE tree_work_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    quote_id INT NOT NULL,
    service_type ENUM('pruning', 'removal', 'assessment', 'cabling', 'stump_grinding', 'emergency', 'planting', 'wildfire_risk', 'sprinkler_system'),
    service_description TEXT,
    estimated_hours DECIMAL(4, 2),
    hourly_rate DECIMAL(6, 2) DEFAULT 150.00,
    material_cost DECIMAL(8, 2) DEFAULT 0,
    equipment_cost DECIMAL(8, 2) DEFAULT 0,
    cleanup_cost DECIMAL(6, 2) DEFAULT 0,
    total_cost DECIMAL(10, 2),
    cut_count INT DEFAULT 0,
    removal_method ENUM('crane', 'climber', 'bucket_truck', 'felling') NULL,
    disposal_method ENUM('chip', 'firewood', 'haul_away', 'leave_logs') NULL,
    ansi_standards_applied BOOLEAN DEFAULT TRUE,
    refuses_topping BOOLEAN DEFAULT TRUE,
    dual_unit_measurements BOOLEAN DEFAULT TRUE,
    status ENUM('quoted', 'approved', 'in_progress', 'completed', 'cancelled') DEFAULT 'quoted',
    actual_duration_minutes INT NULL,
    completion_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES trees(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_tree_id (tree_id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_service_type (service_type),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Media files (photos, videos, audio)
CREATE TABLE media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_type ENUM('image', 'video', 'audio'),
    file_size INT,
    mime_type VARCHAR(100),
    exif_data JSON,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    media_delete_at TIMESTAMP DEFAULT (DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 12 MONTH)),
    media_deleted BOOLEAN DEFAULT FALSE,
    processed_by_ai BOOLEAN DEFAULT FALSE,
    ai_description TEXT,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id),
    INDEX idx_file_type (file_type),
    INDEX idx_delete_at (media_delete_at),
    INDEX idx_deleted (media_deleted)
) ENGINE=InnoDB;

-- Invoices table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_rate DECIMAL(4, 2) DEFAULT 5.00,
    tax_amount DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    payment_date DATE NULL,
    payment_method VARCHAR(100),
    pdf_file_path VARCHAR(500),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_payment_status (payment_status),
    INDEX idx_invoice_date (invoice_date)
) ENGINE=InnoDB;

-- Admin users table (basic auth)
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- Email log table
CREATE TABLE email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    template_used VARCHAR(100),
    quote_id INT NULL,
    invoice_id INT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed', 'bounced') DEFAULT 'sent',
    error_message TEXT,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    INDEX idx_recipient (recipient_email),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('default_hourly_rate', '150.00', 'number', 'Default hourly rate for tree services'),
('tax_rate', '5.00', 'number', 'Default tax rate percentage'),
('quote_expiry_months', '6', 'number', 'Months until quote expires'),
('media_retention_months', '12', 'number', 'Months to retain uploaded media files'),
('company_name', 'Carpe Tree\'em', 'string', 'Company name for invoices'),
('company_address', 'TODO: Set company address', 'string', 'Company address for invoices'),
('company_phone', '778-655-3741', 'string', 'Company phone number'),
('company_email', 'sapport@carpetree.com', 'string', 'Company email address');

-- Create default admin user (password: 'admin123' - CHANGE THIS!)
INSERT INTO admin_users (username, password_hash, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@carpetree.com');

-- Views for common queries
CREATE VIEW quote_summary AS
SELECT 
    q.id as quote_id,
    q.quote_status,
    q.quote_created_at,
    q.quote_expires_at,
    q.total_estimate,
    q.scheduled_at,
    c.name as customer_name,
    c.email as customer_email,
    c.phone as customer_phone,
    c.address as customer_address,
    COUNT(t.id) as tree_count,
    COUNT(tw.id) as work_order_count,
    COUNT(m.id) as media_count
FROM quotes q
LEFT JOIN customers c ON q.customer_id = c.id
LEFT JOIN trees t ON q.id = t.quote_id
LEFT JOIN tree_work_orders tw ON q.id = tw.quote_id
LEFT JOIN media m ON q.id = m.quote_id
GROUP BY q.id, c.id;

CREATE VIEW active_work_orders AS
SELECT 
    tw.*,
    t.tree_species,
    t.tree_height_meters,
    t.tree_condition,
    q.quote_status,
    q.scheduled_at,
    c.name as customer_name,
    c.email as customer_email,
    c.phone as customer_phone
FROM tree_work_orders tw
JOIN trees t ON tw.tree_id = t.id
JOIN quotes q ON tw.quote_id = q.id
JOIN customers c ON q.customer_id = c.id
WHERE tw.status IN ('quoted', 'approved', 'in_progress')
ORDER BY q.scheduled_at ASC;

CREATE TABLE ai_cost_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    model_name VARCHAR(50),
    provider VARCHAR(50),
    input_tokens INT,
    output_tokens INT,
    total_cost DECIMAL(10, 6),
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
); 