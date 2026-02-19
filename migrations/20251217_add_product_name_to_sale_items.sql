-- Add product_name to sale_items so historical product names are preserved
ALTER TABLE sale_items ADD COLUMN product_name VARCHAR(255) NULL AFTER product_id;

-- Populate existing rows with current product name where available
UPDATE sale_items si
JOIN products p ON si.product_id = p.product_id
SET si.product_name = p.product_name
WHERE si.product_id IS NOT NULL AND (si.product_name IS NULL OR si.product_name = '');

-- Allow product_id to become NULL so we can set it to NULL when product is deleted
ALTER TABLE sale_items MODIFY product_id INT NULL;

-- If there's an existing foreign key referencing products(product_id), drop it and recreate with ON DELETE SET NULL
SELECT CONSTRAINT_NAME INTO @fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'sale_items'
  AND COLUMN_NAME = 'product_id'
  AND REFERENCED_TABLE_NAME = 'products'
LIMIT 1;

SET @s = IFNULL(CONCAT('ALTER TABLE sale_items DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE sale_items
  ADD CONSTRAINT fk_sale_items_product_id FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL; 

-- Note: run this migration with care in production and take a DB backup first.
