-- Migration: Add deleted_by to deleted_stores (safe add)
ALTER TABLE deleted_stores
  ADD COLUMN IF NOT EXISTS deleted_by INT;

-- End of migration
