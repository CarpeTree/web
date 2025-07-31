-- AI Model Cost Tracking Schema
-- Real-time cost monitoring and analytics for all AI models

-- Model pricing configuration table
CREATE TABLE IF NOT EXISTS ai_model_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    input_token_cost DECIMAL(10, 8) NOT NULL, -- Cost per input token in USD
    output_token_cost DECIMAL(10, 8) NOT NULL, -- Cost per output token in USD
    effective_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_active_model (model_name, provider, is_active),
    INDEX idx_model_provider (model_name, provider),
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB;

-- Insert current pricing data
INSERT INTO ai_model_pricing (model_name, provider, input_token_cost, output_token_cost, effective_date) VALUES
-- OpenAI o4-mini (Fast & Efficient)
('gpt-4o-mini', 'openai', 0.000001160, 0.000004620, '2025-01-27'),
('o4-mini-2025-04-16', 'openai', 0.000001160, 0.000004620, '2025-01-27'),

-- OpenAI o3-pro (Premium Reasoning) 
('o3-pro-2025-06-10', 'openai', 0.000020000, 0.000080000, '2025-01-27'),

-- OpenAI o3-mini (Efficient Reasoning)
('o3-mini-2025-01-31', 'openai', 0.000001100, 0.000004400, '2025-01-27'),

-- Gemini 2.5 Pro (Multimodal)
('gemini-2.5-pro', 'google', 0.000003500, 0.000010500, '2025-01-27'),
('gemini-1.5-pro', 'google', 0.000003500, 0.000010500, '2025-01-27');

-- Enhanced AI cost tracking table
CREATE TABLE IF NOT EXISTS ai_cost_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    
    -- Token usage
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    total_tokens INT NOT NULL DEFAULT 0,
    
    -- Cost breakdown
    input_cost DECIMAL(10, 6) NOT NULL DEFAULT 0,
    output_cost DECIMAL(10, 6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(10, 6) NOT NULL DEFAULT 0,
    
    -- Performance metrics
    processing_time_ms INT,
    first_token_latency_ms INT,
    tokens_per_second DECIMAL(8, 2),
    
    -- Context and quality
    context_length INT,
    reasoning_effort ENUM('low', 'medium', 'high') DEFAULT 'medium',
    function_calls_used BOOLEAN DEFAULT FALSE,
    tools_used JSON,
    
    -- Analysis metadata
    media_files_processed INT DEFAULT 0,
    transcriptions_generated INT DEFAULT 0,
    analysis_quality_score DECIMAL(3, 2), -- 0-1 quality rating
    
    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id),
    INDEX idx_model_provider (model_name, provider),
    INDEX idx_cost (total_cost),
    INDEX idx_started_at (started_at),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB;

-- Real-time cost summary table for dashboard
CREATE TABLE IF NOT EXISTS ai_cost_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    summary_date DATE NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    
    -- Daily aggregates
    total_requests INT NOT NULL DEFAULT 0,
    total_tokens INT NOT NULL DEFAULT 0,
    total_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    
    -- Performance averages
    avg_processing_time_ms DECIMAL(8, 2),
    avg_tokens_per_second DECIMAL(8, 2),
    avg_quality_score DECIMAL(3, 2),
    
    -- Usage breakdown
    quotes_processed INT NOT NULL DEFAULT 0,
    media_files_analyzed INT NOT NULL DEFAULT 0,
    transcriptions_generated INT NOT NULL DEFAULT 0,
    
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_daily_model (summary_date, model_name, provider),
    INDEX idx_date (summary_date),
    INDEX idx_cost (total_cost)
) ENGINE=InnoDB;

-- Running cost totals for real-time monitoring
CREATE TABLE IF NOT EXISTS ai_running_totals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Time periods
    today_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    this_week_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    this_month_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    this_year_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    all_time_cost DECIMAL(10, 4) NOT NULL DEFAULT 0,
    
    -- Usage stats
    today_requests INT NOT NULL DEFAULT 0,
    this_week_requests INT NOT NULL DEFAULT 0,
    this_month_requests INT NOT NULL DEFAULT 0,
    this_year_requests INT NOT NULL DEFAULT 0,
    all_time_requests INT NOT NULL DEFAULT 0,
    
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Initialize running totals
INSERT INTO ai_running_totals () VALUES ();

-- Model feature capabilities table
CREATE TABLE IF NOT EXISTS ai_model_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    
    -- Core capabilities
    supports_vision BOOLEAN DEFAULT FALSE,
    supports_function_calling BOOLEAN DEFAULT FALSE,
    supports_structured_output BOOLEAN DEFAULT FALSE,
    supports_reasoning_levels BOOLEAN DEFAULT FALSE,
    supports_streaming BOOLEAN DEFAULT FALSE,
    supports_tool_use BOOLEAN DEFAULT FALSE,
    
    -- Context and limits
    max_context_tokens INT,
    max_output_tokens INT,
    supports_multimodal BOOLEAN DEFAULT FALSE,
    
    -- Reasoning capabilities
    reasoning_model BOOLEAN DEFAULT FALSE,
    thinking_mode BOOLEAN DEFAULT FALSE,
    chain_of_thought BOOLEAN DEFAULT FALSE,
    
    -- Performance characteristics
    typical_latency_ms INT,
    recommended_use_cases JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_model_provider (model_name, provider),
    INDEX idx_capabilities (supports_vision, supports_function_calling, reasoning_model)
) ENGINE=InnoDB;

-- Insert model capabilities
INSERT INTO ai_model_features (
    model_name, provider, supports_vision, supports_function_calling, 
    supports_structured_output, supports_reasoning_levels, supports_streaming,
    supports_tool_use, max_context_tokens, max_output_tokens, supports_multimodal,
    reasoning_model, thinking_mode, chain_of_thought, typical_latency_ms,
    recommended_use_cases
) VALUES 
-- OpenAI o4-mini
('gpt-4o-mini', 'openai', TRUE, TRUE, TRUE, FALSE, TRUE, TRUE, 200000, 100000, TRUE, 
 FALSE, FALSE, TRUE, 2000, 
 '["general", "coding", "analysis", "cost-effective"]'),

-- OpenAI o3-pro  
('o3-pro-2025-06-10', 'openai', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, 200000, 100000, TRUE,
 TRUE, TRUE, TRUE, 15000,
 '["complex reasoning", "mathematics", "coding", "scientific analysis", "premium tasks"]'),

-- OpenAI o3-mini
('o3-mini-2025-01-31', 'openai', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, 200000, 100000, TRUE,
 TRUE, TRUE, TRUE, 7000,
 '["reasoning", "mathematics", "coding", "efficient analysis"]'),

-- Gemini 2.5 Pro
('gemini-2.5-pro', 'google', TRUE, FALSE, FALSE, FALSE, TRUE, TRUE, 1048576, 4000, TRUE,
 FALSE, TRUE, FALSE, 3000,
 '["multimodal", "video analysis", "large context", "vision tasks"]');