
CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    recipient_email VARCHAR(255) NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_quote_id (quote_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_sent_at (sent_at),
    
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);
