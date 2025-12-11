-- Migration: 2025-12- Add store_address and note columns to stores table
-- Run this from your MySQL client: mysql -u user -p smart_billing < migrations/202512_add_store_address_and_note.sql

ALTER TABLE stores ADD COLUMN IF NOT EXISTS store_address TEXT NULL;
ALTER TABLE stores ADD COLUMN IF NOT EXISTS note TEXT NULL;

-- Fallback note example (only if needed in older schemas):
-- ALTER TABLE stores ADD COLUMN IF NOT EXISTS notice TEXT NULL;

-- Optional: You can populate note from notice if notice exists
-- UPDATE stores SET note = notice WHERE note IS NULL AND notice IS NOT NULL;
