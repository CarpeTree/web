-- Customer Interaction Tracking & AI Analytics Schema
-- Add these tables to track all customer interactions and AI processing

-- Customer interaction tracking
CREATE TABLE IF NOT EXISTS customer_interactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    quote_id INT,
    session_id VARCHAR(255),
    interaction_type ENUM('form_view', 'service_select', 'service_deselect', 'file_upload', 'form_submit', 'quote_view', 'quote_accept', 'quote_decline', 'contact_click', 'page_view') NOT NULL,
    interaction_data JSON,
    page_url VARCHAR(500),
    user_agent TEXT,
    ip_address VARCHAR(45),
    referrer VARCHAR(500),
    interaction_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_session_id (session_id),
    INDEX idx_interaction_type (interaction_type),
    INDEX idx_timestamp (interaction_timestamp),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- AI processing detailed logs
CREATE TABLE IF NOT EXISTS ai_processing_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    ai_model VARCHAR(100),
    prompt_tokens INT,
    completion_tokens INT,
    total_tokens INT,
    processing_time_ms INT,
    api_cost_usd DECIMAL(10, 6),
    request_payload JSON,
    response_payload JSON,
    reasoning_trace JSON,
    error_details TEXT,
    processing_status ENUM('started', 'completed', 'failed', 'timeout') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_quote_id (quote_id),
    INDEX idx_model (ai_model),
    INDEX idx_status (processing_status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Service selection analytics
CREATE TABLE IF NOT EXISTS service_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT,
    service_name VARCHAR(100),
    action ENUM('added', 'removed', 'suggested', 'auto_selected') NOT NULL,
    is_optional BOOLEAN DEFAULT FALSE,
    price_when_selected DECIMAL(10, 2),
    selection_order INT,
    time_spent_considering_seconds INT,
    customer_hesitation_score DECIMAL(3, 2), -- 0-1 based on hover time, clicks, etc.
    interaction_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_quote_id (quote_id),
    INDEX idx_service_name (service_name),
    INDEX idx_action (action),
    INDEX idx_optional (is_optional),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Customer journey tracking
CREATE TABLE IF NOT EXISTS customer_journey (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    quote_id INT,
    journey_stage ENUM('discovery', 'form_start', 'form_progress', 'form_complete', 'quote_received', 'quote_reviewed', 'quote_accepted', 'quote_declined', 'service_completed') NOT NULL,
    stage_data JSON,
    time_in_stage_seconds INT,
    exit_reason VARCHAR(255),
    conversion_score DECIMAL(3, 2),
    stage_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_stage (journey_stage),
    INDEX idx_timestamp (stage_timestamp),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- AI model performance tracking
CREATE TABLE IF NOT EXISTS ai_model_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100),
    model_version VARCHAR(50),
    input_type ENUM('text_only', 'image', 'video', 'mixed') NOT NULL,
    accuracy_score DECIMAL(3, 2),
    processing_speed_ms INT,
    cost_per_token DECIMAL(8, 6),
    success_rate DECIMAL(3, 2),
    admin_satisfaction_rating INT, -- 1-5 rating from admin feedback
    customer_acceptance_rate DECIMAL(3, 2),
    evaluation_date DATE,
    sample_size INT,
    notes TEXT,
    INDEX idx_model_name (model_name),
    INDEX idx_input_type (input_type),
    INDEX idx_evaluation_date (evaluation_date)
) ENGINE=InnoDB;

-- Real-time session tracking
CREATE TABLE IF NOT EXISTS live_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE,
    customer_id INT NULL,
    quote_id INT NULL,
    current_page VARCHAR(500),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    session_data JSON,
    device_info JSON,
    geolocation JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- Form abandonment tracking
CREATE TABLE IF NOT EXISTS form_abandonments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255),
    customer_id INT NULL,
    abandonment_stage ENUM('step1_services', 'step2_details', 'step3_files', 'step4_submit') NOT NULL,
    completed_fields JSON,
    time_spent_seconds INT,
    last_interaction VARCHAR(255),
    abandonment_reason VARCHAR(255),
    device_type VARCHAR(50),
    abandoned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_stage (abandonment_stage),
    INDEX idx_abandoned_at (abandoned_at)
) ENGINE=InnoDB;

-- A/B testing framework
CREATE TABLE IF NOT EXISTS ab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_name VARCHAR(100),
    variant_name VARCHAR(100),
    customer_id INT,
    quote_id INT NULL,
    session_id VARCHAR(255),
    conversion_achieved BOOLEAN DEFAULT FALSE,
    test_data JSON,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    converted_at TIMESTAMP NULL,
    INDEX idx_test_name (test_name),
    INDEX idx_variant (variant_name),
    INDEX idx_customer_id (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB; 