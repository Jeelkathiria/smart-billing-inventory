-- Migration: Add deleted_stores audit table for account deletions
CREATE TABLE IF NOT EXISTS deleted_stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  deleted_by INT,
  admin_name VARCHAR(100),
  store_name VARCHAR(100),
  store_email VARCHAR(100),
  contact_number VARCHAR(20),
  reason VARCHAR(255),
  deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- End of migration
