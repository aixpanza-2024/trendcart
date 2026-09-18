-- Fix: delivery_zones / delivery_pincodes were created with the server's
-- default collation (utf8mb4_general_ci) instead of this schema's standard
-- utf8mb4_unicode_ci, because the original add_delivery_zones.sql migration
-- didn't specify one explicitly. This causes "Illegal mix of collations"
-- whenever a query joins these tables against shops.shop_pincode (or any
-- other unicode_ci column) — breaking the zone-based delivery fee lookup.
-- Run once in phpMyAdmin, on both local and production.

ALTER TABLE delivery_zones    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE delivery_pincodes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
