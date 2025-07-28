-- Carpe Tree'em Quote-to-Invoice Database Schema
-- For Hostinger MySQL (without CREATE DATABASE)

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
    quote_number VARCHAR(50) UNIQUE,
    status ENUM('pending', 'processing', 'draft_ready', 'sent_to_customer', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
    total_estimate DECIMAL(10,2),
    ai_analysis_json TEXT,
    gps_lat DECIMAL(10, 8),
    gps_lng DECIMAL(11, 8),
    exif_lat DECIMAL(10, 8),
    exif_lng DECIMAL(11, 8),
    location_description TEXT,
    services_requested TEXT,
    urgent_items INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    quote_expires_at TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Trees table
CREATE TABLE trees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    tree_id VARCHAR(50),
    species VARCHAR(255),
    species_confidence DECIMAL(3,2),
    diameter_inches DECIMAL(5,2),
    height_feet DECIMAL(5,2),
    condition_assessment TEXT,
    location_context TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id),
    INDEX idx_quote_id (quote_id)
) ENGINE=InnoDB;

-- Tree work orders table
CREATE TABLE tree_work_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    service_type VARCHAR(100),
    description TEXT,
    price DECIMAL(8,2),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    scheduled_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    google_event_id VARCHAR(255),
    actual_duration_minutes INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES trees(id),
    INDEX idx_tree_id (tree_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
) ENGINE=InnoDB;

-- Media table
CREATE TABLE media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_type ENUM('image', 'video') NOT NULL,
    mime_type VARCHAR(100),
    file_size INT,
    upload_path VARCHAR(500),
    exif_data TEXT,
    ai_analysis TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    media_delete_at TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB;

-- Invoices table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE,
    subtotal DECIMAL(10,2),
    tax_amount DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    pdf_path VARCHAR(500),
    sent_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Admin users table
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- Email log table
CREATE TABLE email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient VARCHAR(255),
    subject VARCHAR(500),
    template VARCHAR(100),
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    quote_id INT,
    invoice_id INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient),
    INDEX idx_status (status),
    INDEX idx_quote_id (quote_id)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB;

-- Insert default admin user (password: carpe_tree_admin_2024)
INSERT INTO admin_users (username, password_hash, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'phil.bajenski@gmail.com');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('quote_expiry_days', '30'),
('tax_rate', '0.12'),
('company_name', 'Carpe Tree''em'),
('company_phone', '778-655-3741'),
('company_email', 'sapport@carpetree.com'); 