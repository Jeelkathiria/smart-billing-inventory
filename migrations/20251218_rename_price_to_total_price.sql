-- Migration: rename 'price' to 'total_price' in sale_items and migrate existing values
-- Adds 'total_price' if missing, populates it using existing price and gst, then drops 'price'

START TRANSACTION;

ALTER TABLE sale_items
  ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Also add category column if it does not exist
ALTER TABLE sale_items
  ADD COLUMN IF NOT EXISTS category VARCHAR(255) DEFAULT NULL;

-- Populate total_price using existing price (unit price excl GST) if present
-- total_price := quantity * (price + price * gst_percent / 100)
UPDATE sale_items
SET total_price = ROUND(quantity * (price + (price * gst_percent / 100)), 2)
WHERE (total_price IS NULL OR total_price = 0) AND EXISTS (
  SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'price'
);

-- Populate category from products/categories when possible
UPDATE sale_items si
LEFT JOIN products p ON si.product_id = p.product_id
LEFT JOIN categories c ON p.category_id = c.category_id
SET si.category = c.category_name
WHERE si.category IS NULL OR si.category = '';

ALTER TABLE sale_items
  DROP COLUMN IF EXISTS price;

COMMIT;
