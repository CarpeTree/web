-- Add GPS and location tracking columns to customers table
-- Run this on live server to enable GPS functionality

ALTER TABLE customers 
ADD COLUMN geo_latitude DECIMAL(10, 8) DEFAULT NULL,
ADD COLUMN geo_longitude DECIMAL(11, 8) DEFAULT NULL,
ADD COLUMN geo_accuracy FLOAT DEFAULT NULL,
ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL,
ADD COLUMN user_agent TEXT DEFAULT NULL;

-- Add indexes for performance
CREATE INDEX idx_customers_geo ON customers (geo_latitude, geo_longitude);
CREATE INDEX idx_customers_ip ON customers (ip_address);