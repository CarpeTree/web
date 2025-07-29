-- Enhanced Location Tracking Schema Updates
-- Add new columns to customers table for comprehensive location data

ALTER TABLE customers 
ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL COMMENT 'User IP address',
ADD COLUMN user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent string',
ADD COLUMN geo_latitude DECIMAL(10,8) DEFAULT NULL COMMENT 'GPS latitude from browser geolocation',
ADD COLUMN geo_longitude DECIMAL(11,8) DEFAULT NULL COMMENT 'GPS longitude from browser geolocation', 
ADD COLUMN geo_accuracy DECIMAL(8,2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Customer creation timestamp';

-- Add new table for storing EXIF location data from uploaded images
CREATE TABLE IF NOT EXISTS media_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT NOT NULL,
    quote_id INT NOT NULL,
    exif_latitude DECIMAL(10,8) DEFAULT NULL,
    exif_longitude DECIMAL(11,8) DEFAULT NULL,
    exif_altitude DECIMAL(8,2) DEFAULT NULL,
    exif_timestamp DATETIME DEFAULT NULL,
    camera_make VARCHAR(100) DEFAULT NULL,
    camera_model VARCHAR(100) DEFAULT NULL,
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

-- Add new table for distance calculations from multiple sources
CREATE TABLE IF NOT EXISTS distance_calculations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    source_type ENUM('address', 'geolocation', 'exif', 'ip_lookup') NOT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    distance_km DECIMAL(8,2) DEFAULT NULL,
    calculation_method VARCHAR(50) DEFAULT NULL,
    accuracy_rating ENUM('high', 'medium', 'low') DEFAULT 'medium',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

-- Add indexes for performance
CREATE INDEX idx_customers_location ON customers(geo_latitude, geo_longitude);
CREATE INDEX idx_customers_ip ON customers(ip_address);
CREATE INDEX idx_media_locations_coords ON media_locations(exif_latitude, exif_longitude);
CREATE INDEX idx_distance_calc_quote ON distance_calculations(quote_id, source_type); 