-- Migration: Delivery zone system
-- Run once in phpMyAdmin

CREATE TABLE IF NOT EXISTS delivery_zones (
    zone_id         INT PRIMARY KEY AUTO_INCREMENT,
    zone_name       VARCHAR(100) NOT NULL,
    delivery_fee    DECIMAL(8,2) DEFAULT 0.00,
    is_default_zone TINYINT(1) DEFAULT 0,
    is_active       TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS delivery_pincodes (
    pincode_id INT PRIMARY KEY AUTO_INCREMENT,
    pincode    VARCHAR(10) NOT NULL,
    zone_id    INT NOT NULL,
    area_name  VARCHAR(200) DEFAULT NULL,
    FOREIGN KEY (zone_id) REFERENCES delivery_zones(zone_id) ON DELETE CASCADE,
    UNIQUE INDEX idx_pincode (pincode)
);

-- Zone 1: Free delivery (Kazhakootam → Attingal 20km radius)
INSERT INTO delivery_zones (zone_name, delivery_fee, is_default_zone) VALUES
    ('Zone 1 - Free Delivery', 0.00, 0);

-- Zone 2: Paid delivery (everything else)
INSERT INTO delivery_zones (zone_name, delivery_fee, is_default_zone) VALUES
    ('Zone 2 - Standard Delivery', 49.00, 1);

-- Zone 1 pincodes: Kazhakootam to Attingal corridor (~20km radius)
INSERT INTO delivery_pincodes (pincode, zone_id, area_name) VALUES
    ('695582', 1, 'Kazhakootam'),
    ('695581', 1, 'Technopark / Kariavattom'),
    ('695583', 1, 'Mukkola / Near Kazhakootam'),
    ('695564', 1, 'Sreekaryam'),
    ('695562', 1, 'Chempazhanthy'),
    ('695040', 1, 'Nettayam'),
    ('695316', 1, 'Pallippuram'),
    ('695301', 1, 'Kaniyapuram'),
    ('695607', 1, 'Venjaramoodu'),
    ('695542', 1, 'Mangalapuram'),
    ('695541', 1, 'Njandoorkonam'),
    ('695310', 1, 'Maranalloor'),
    ('695311', 1, 'Pothencode'),
    ('695101', 1, 'Attingal'),
    ('695102', 1, 'Attingal Town'),
    ('695306', 1, 'Vembayam');

-- Add delivery_zone column to orders (run separately if orders table already exists)
ALTER TABLE orders ADD COLUMN delivery_zone VARCHAR(100) DEFAULT NULL AFTER shipping_amount;
