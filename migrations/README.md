Migration scripts for the project

- SQL migration files are stored under `migrations/`.
- A CLI helper to add columns is available at `scripts/add_store_columns.php`.

How to run migrations:

- SQL (recommended for production):
  - Run the SQL file using your MySQL client:
    mysql -u your_user -p smart_billing < migrations/202512_add_store_address_and_note.sql

- PHP helper (for local usage/testing):
  - Run the script from the repo root:
    php scripts/add_store_columns.php --force

Note: Always backup your DB before running migrations in production.
