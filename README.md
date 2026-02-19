# Smart Billing & Inventory System

A comprehensive web-based billing and inventory management system designed for small to medium-sized businesses. Built with PHP, MySQL, and Bootstrap for a responsive user interface.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)

## Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Support](#support)

## Features

### Core Features

- **Multi-Store Support** - Manage multiple stores from a single application
- **User Management** - Role-based access control (Admin, Manager, Cashier)
- **Product Inventory** - Complete product catalog with barcode support
- **Billing & Sales** - Create invoices and process sales transactions
- **Payment Processing** - Accept multiple payment methods
- **Customer Management** - Maintain customer database and purchase history
- **Reports & Analytics** - Comprehensive sales reports and business insights
- **PDF Invoices** - Generate professional invoice PDFs

### Advanced Features

- **Category Management** - Organize products by categories
- **Stock Tracking** - Real-time inventory management
- **Sales Export** - Export sales data to CSV for analysis
- **OTP Verification** - Secure authentication with email OTP
- **Admin Dashboard** - Executive overview with key metrics
- **Multi-user Sessions** - Concurrent user support with per-store isolation
- **Audit Logs** - Track user actions and changes

## System Requirements

### Minimum Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- 50MB disk space
- 256MB RAM

### Recommended Requirements
- PHP 8.0 or higher
- MySQL 8.0 or higher
- 100MB disk space
- 512MB RAM
- SSD storage

### Required PHP Extensions
- MySQLi (MySQL Improved)
- OpenSSL (for SMTP)
- JSON
- SPL

## Installation

### Step 1: Clone Repository

```bash
git clone https://github.com/JeelKathiria/smart-billing-inventory.git
cd smart-billing-inventory
```

### Step 2: Install Dependencies

```bash
# Install Composer dependencies
composer install
```

### Step 3: Configure Database

1. Create a MySQL database:
   ```bash
   mysql -u root -p
   ```

   ```sql
   CREATE DATABASE smart_billing;
   CREATE USER 'sbi_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON smart_billing.* TO 'sbi_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

2. Update `config/db.php`:
   ```php
   $db_host = 'localhost';
   $db_user = 'sbi_user';
   $db_pass = 'secure_password';
   $db_name = 'smart_billing';
   ```

### Step 4: Set Up Database Schema

Import the database schema:
```bash
mysql -u sbi_user -p smart_billing < database/schema.sql
```

Run migrations:
```bash
# Apply migrations in order
mysql -u sbi_user -p smart_billing < migrations/202512_add_store_address_and_note.sql
mysql -u sbi_user -p smart_billing < migrations/20251213_add_deleted_stores_table.sql
mysql -u sbi_user -p smart_billing < migrations/20251214_add_deleted_by_to_deleted_stores.sql
mysql -u sbi_user -p smart_billing < migrations/20251217_add_product_name_to_sale_items.sql
mysql -u sbi_user -p smart_billing < migrations/20251218_rename_price_to_total_price.sql
```

### Step 5: Configure Email (Optional but Recommended)

Update `config/mail.php` for OTP and notification emails:

```php
$mail_config = [
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_port'    => 587,
    'smtp_auth'    => true,
    'smtp_secure'  => 'tls',
    'from_email'   => 'your-email@gmail.com',
    'from_name'    => 'BillMitra',
    'username'     => 'your-email@gmail.com',
    'password'     => 'your-app-password',
];
```

### Step 6: Set File Permissions

```bash
chmod -R 755 ./
chmod -R 775 logs/ uploads/ cache/ sessions/
```

### Step 7: Start Development Server

```bash
# Using PHP built-in server
php -S localhost:8000

# Or configure your web server (Apache/Nginx)
```

Access the application at `http://localhost:8000`

## Configuration

### Database Configuration (`config/db.php`)

```php
$db_host = 'localhost';      // Database host
$db_user = 'root';           // Database username
$db_pass = 'password';       // Database password
$db_name = 'smart_billing';  // Database name
```

### Email Configuration (`config/mail.php`)

```php
$mail_config = [
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_port'    => 587,
    'smtp_auth'    => true,
    'smtp_secure'  => 'tls',
    'from_email'   => 'noreply@billmitra.com',
    'from_name'    => 'BillMitra',
    'username'     => 'your-email@gmail.com',
    'password'     => 'your-app-password',
];
```

### For Gmail SMTP:
1. Enable 2-Factor Authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use the generated 16-character password

## Quick Start

### First Time Setup

1. **Create Admin User**
   - Register as admin via login page
   - Set up your store information

2. **Configure Store Settings**
   - Add store details (name, address, email)
   - Set billing fields (GST number, etc.)

3. **Add Products**
   - Create product categories
   - Add products with barcodes and prices

4. **Create Users**
   - Add cashiers and managers
   - Assign roles and permissions

5. **Process First Sale**
   - Scan product barcode or search
   - Add to billing cart
   - Process payment and generate invoice

### User Roles

| Role | Permissions |
|------|------------|
| **Admin** | Full access, user management, store settings |
| **Manager** | View reports, manage products, user management |
| **Cashier** | Process sales, view own transactions |

## Project Structure

```
smart-billing-inventory/
├── auth/                      # Authentication & login
│   ├── index.php             # Login page
│   ├── forgot_password.php    # Password reset
│   ├── verify_otp.php        # OTP verification
│   ├── logout.php            # Logout handler
│   ├── auth_check.php        # Session validation
│   └── check_store_code.php   # Store validation
│
├── modules/                    # Main application modules
│   ├── dashboard.php          # Admin dashboard
│   ├── categories.php         # Category management
│   ├── billing/               # Billing operations
│   │   ├── billing.php
│   │   ├── checkout.php
│   │   └── generate_invoice.php
│   ├── customers/             # Customer management
│   ├── products/              # Product inventory
│   ├── sales/                 # Sales tracking
│   ├── reports/               # Business reports
│   ├── settings/              # User settings
│   └── users/                 # User management
│
├── config/                     # Configuration files
│   ├── db.php                 # Database config (git ignored)
│   └── mail.php               # Email config (git ignored)
│
├── includes/                   # Shared utilities
│   └── fpdf/                  # PDF generation library
│
├── components/                 # Reusable UI components
│   ├── navbar.php
│   └── sidebar.php
│
├── assets/                     # Static files
│   ├── css/                   # Stylesheets
│   └── js/                    # JavaScript files
│
├── migrations/                 # Database schema changes
│
├── vendor/                     # Composer dependencies (git ignored)
│
├── logs/                       # Application logs
├── uploads/                    # User uploads
└── index.php                   # Application entry point
```

## Usage

### Login
```
URL: http://yourdomain.com/auth/
Default: Create first admin user during initial setup
```

### Dashboard
- View sales metrics
- See top products
- Check sales trends
- Monitor inventory levels

### Product Management
- Add/Edit/Delete products
- Manage categories
- Set pricing and GST
- Generate barcodes

### Billing & Sales
1. Click "Billing" module
2. Search/Scan product
3. Add to cart
4. Enter customer details
5. Select payment method
6. Generate and print invoice

### Reports
- Daily sales report
- Monthly sales trends
- Top products analysis
- Category-wise breakdown
- Export to CSV

## API Endpoints

### Authentication
- `POST /auth/index.php` - Login
- `GET /auth/logout.php` - Logout
- `POST /auth/forgot_password.php` - Reset password
- `POST /auth/verify_otp.php` - Verify OTP

### Products
- `GET /modules/products/products.php` - List products
- `POST /modules/products/check_barcode.php` - Verify barcode

### Sales
- `POST /modules/sales/sales.php` - Create sale
- `GET /modules/sales/fetch_products.php` - Get products
- `GET /modules/sales/view_invoice.php` - View invoice PDF

### Reports
- `GET /modules/reports/report_data.php` - Get report data
- `GET /modules/reports/get_sales_by_date.php` - Sales by date
- `GET /modules/reports/get_top_products.php` - Top products

### Settings
- `POST /modules/settings/update_profile.php` - Update profile
- `POST /modules/settings/update_password.php` - Change password
- `POST /modules/settings/update_store.php` - Update store info

## Database Schema

### Key Tables

**users** - User accounts with roles
```sql
user_id, username, email, password, role, store_id, created_at
```

**stores** - Store information
```sql
store_id, store_name, store_code, store_address, store_email, created_at
```

**products** - Product catalog
```sql
product_id, product_name, category_id, barcode, cost_price, selling_price, gst_percent, stock_quantity, store_id
```

**sales** - Sales transactions
```sql
sale_id, invoice_id, store_id, customer_name, total_amount, payment_method, created_by, sale_date
```

**sale_items** - Individual items in a sale
```sql
sale_item_id, sale_id, product_id, quantity, total_price, gst_percent, profit
```

**categories** - Product categories
```sql
category_id, category_name, store_id
```

**customers** - Customer information
```sql
customer_id, customer_name, customer_email, customer_phone, store_id
```

## Security

### Important Security Practices

1. **Always use HTTPS** in production
2. **Keep credentials in config files** - Never commit to Git
3. **Use strong passwords** - Minimum 8 characters, mix of cases and numbers
4. **Enable 2FA** - Use OTP for sensitive operations
5. **Regular backups** - Automated daily database backups
6. **Update regularly** - Keep PHP and dependencies up to date
7. **File permissions** - Restrict web access to necessary files
8. **SQL Injection prevention** - All queries use prepared statements
9. **XSS prevention** - Input validation and output encoding
10. **CSRF tokens** - Anti-forgery tokens on state-changing operations

### Files to Protect

The following files contain sensitive information and are excluded from Git:

- `config/db.php` - Database credentials
- `config/mail.php` - Email credentials
- `vendor/` - Third-party dependencies
- `logs/` - Application logs
- `uploads/` - User uploads

See `.gitignore` for complete list.

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed
```
Error: Connection failed: ...
```

**Solution:**
- Check database host, username, password in `config/db.php`
- Ensure MySQL is running
- Verify user has database privileges

#### 2. PHPMailer Not Found
```
Error: Class "PHPMailer\PHPMailer\PHPMailer" not found
```

**Solution:**
```bash
composer update
```

#### 3. Font Files Missing for PDF
```
Warning: fopen(...DejaVuSans.ttf): Failed to open stream
```

**Solution:**
- Font files are included in `includes/fpdf/font/unifont/`
- Verify files exist and permissions are correct
- Check file paths in `includes/fpdf/font/unifont/*.mtx.php`

#### 4. Permission Denied
```
Error: Permission denied for [directory]
```

**Solution:**
```bash
chmod -R 775 logs/ uploads/ cache/ sessions/
chown -R www-data:www-data ./
```

#### 5. Blank Page Display
**Solution:**
- Check PHP error logs
- Enable error display (development only):
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Verify all `require_once` paths are correct

### Debug Mode

Enable detailed error logging:

1. Edit `config/db.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. Check logs in `logs/` directory

3. Disable in production!

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Standards
- Follow PSR-12 PHP standards
- Use meaningful variable names
- Add comments for complex logic
- Test thoroughly before submitting

## Support

### Documentation
- [Deployment Guide](DEPLOYMENT_GUIDE.md) - Production setup instructions
- [CHANGELOG](CHANGELOG.md) - Version history and fixes
- [Technical Architecture](TECHNICAL_ARCHITECTURE.md) - System design details

### Getting Help
- Check the [Troubleshooting](#troubleshooting) section
- Review [CHANGELOG](CHANGELOG.md) for known issues
- Create an issue on GitHub
- Contact the development team

## License

This project is licensed under the MIT License - see LICENSE file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Authors

- **Project Team** - Initial development and maintenance

## Acknowledgments

- PHPMailer library for email functionality
- FPDF library for PDF generation
- Bootstrap framework for UI components
- All contributors and users for feedback and support

---

**Version:** 1.0.0  
**Last Updated:** February 18, 2026  
**Status:** Production Ready ✓
