# InfinityFree Deployment Guide

## Smart Billing & Inventory on InfinityFree Hosting

This guide shows you how to deploy the Smart Billing & Inventory system on **InfinityFree** hosting service.

**Last Updated:** February 18, 2026  
**Status:** Ready for InfinityFree deployment

---

## Prerequisites

1. InfinityFree account created
2. FTP access credentials from InfinityFree
3. Database credentials (provided by InfinityFree)
4. All project files ready to upload

---

## Step 1: Prepare Your Project Files

### Files Structure
Your project should have this structure before uploading:

```
smart-billing-inventory/
├── index.php                    ← NEW! Entry point (routes logged users)
├── .gitignore
├── README.md
├── DEPLOYMENT_GUIDE.md
├── CHANGELOG.md
├── SETUP_SUMMARY.md
│
├── config/
│   ├── db.php                  ← YOU MUST UPDATE with InfinityFree DB credentials
│   └── mail.php                ← Update with your email settings
│
├── auth/                        ← Login & authentication
│   ├── index.php               ← Login page
│   ├── forgot_password.php
│   ├── verify_otp.php
│   └── ...
│
├── modules/                     ← Main application
│   ├── dashboard.php
│   ├── billing/
│   ├── sales/
│   ├── products/
│   ├── reports/
│   └── ...
│
├── components/                  ← UI components
│   ├── navbar.php
│   └── sidebar.php
│
├── assets/                      ← CSS, JS, images
│   ├── css/
│   └── js/
│
└── vendor/                      ← Dependencies
    └── phpmailer/
```

### Create Root index.php (if not already done)

The **root `index.php`** is already created. It:
- ✅ Checks if user is logged in
- ✅ Redirects to dashboard if logged in
- ✅ Redirects to login if not logged in

---

## Step 2: Get InfinityFree Database Credentials

1. Log in to **InfinityFree Control Panel**
2. Go to **Databases**
3. Click **MySQL Info** button
4. You'll see:
   - **Database Name:** (e.g., `xxxxx_billmitra`)
   - **Database User:** (e.g., `xxxxx_billmitra`)
   - **Database Password:** (provided email or change it)
   - **Database Host:** `127.0.0.1` or hostname provided

**Save these credentials!** You'll need them next.

---

## Step 3: Update Configuration Files

### 3.1 Update Database Configuration

Before uploading, edit `config/db.php`:

```php
<?php
/**
 * File: config/db.php
 * InfinityFree Database Configuration
 */

// InfinityFree Database credentials (REPLACE WITH YOUR ACTUAL DETAILS)
$db_host = '127.0.0.1';              // Usually this for InfinityFree
$db_user = 'xxxxx_billmitra';        // Your actual DB username from InfinityFree
$db_pass = 'your-db-password';       // Your actual DB password
$db_name = 'xxxxx_billmitra';        // Your actual database name

// Create MySQLi connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Enable MySQLi exceptions for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
```

### 3.2 Update Email Configuration (Optional)

SmartEdit `config/mail.php` if you want OTP/email features:

```php
<?php
/**
 * File: config/mail.php
 * Email Configuration for InfinityFree
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email Configuration
$mail_config = [
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_port'    => 587,
    'smtp_auth'    => true,
    'smtp_secure'  => 'tls',
    'from_email'   => 'your-email@gmail.com',      // CHANGE THIS
    'from_name'    => 'BillMitra',
    'username'     => 'your-email@gmail.com',      // CHANGE THIS
    'password'     => 'your-app-password',         // CHANGE THIS (Gmail App Password)
];
?>
```

---

## Step 4: Upload Files to InfinityFree

### Option A: Using FTP (Recommended)

1. **Download FTP Client:**
   - FileZilla (free, recommended)
   - Or use your hosting control panel's file manager

2. **Get FTP Credentials from InfinityFree:**
   - Control Panel → FTP Accounts
   - Use default FTP account or create new one

3. **Connect via FTP:**
   - Host: `innisfree.com` or your FTP host
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21 (or 22 for SFTP)

4. **Upload Process:**
   - Navigate to `htdocs` folder
   - Delete any default `index.html` files
   - Upload entire `smart-billing-inventory` folder contents
   - **Or** upload each folder (auth, modules, config, vendor, etc.) directly

5. **Verify Upload:**
   - `index.php` should be in root of `htdocs`
   - `auth/` folder should contain login files
   - `config/` folder should contain your updated `db.php`

### Option B: Using File Manager (InfinityFree Control Panel)

1. Log in to InfinityFree Control Panel
2. Click **File Manager**
3. Navigate to `htdocs`
4. Click **Upload Files** button
5. Select all your project files and upload
6. Extract `.zip` if you uploaded as archive

---

## Step 5: Create Database & Import Schema

### 5.1 Access Database Management

1. InfinityFree Control Panel → **Databases**
2. Click **MySQL Info**
3. Look for **phpMyAdmin** link or connect via CLI

### 5.2 Import Database Schema

Using **phpMyAdmin** (easiest):

1. Open phpMyAdmin from InfinityFree Control Panel
2. Select your database (e.g., `xxxxx_billmitra`)
3. Click **Import** tab
4. Upload database schema file or paste SQL

If you don't have a database schema file, InfinityFree will use the default tables.

### 5.3 Create Initial Tables

Create basic tables for first-time setup (if needed):

```sql
-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores table
CREATE TABLE IF NOT EXISTS stores (
    store_id INT PRIMARY KEY AUTO_INCREMENT,
    store_name VARCHAR(255) NOT NULL,
    store_code VARCHAR(50) UNIQUE NOT NULL,
    store_address TEXT,
    store_email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    category_id INT,
    barcode VARCHAR(100) UNIQUE,
    cost_price DECIMAL(10, 2),
    selling_price DECIMAL(10, 2) NOT NULL,
    gst_percent DECIMAL(5, 2) DEFAULT 0,
    stock_quantity INT DEFAULT 0,
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales table
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id VARCHAR(50) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    customer_name VARCHAR(255),
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50),
    created_by INT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sale items table
CREATE TABLE IF NOT EXISTS sale_items (
    sale_item_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    gst_percent DECIMAL(5, 2),
    profit DECIMAL(12, 2),
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) NOT NULL,
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Step 6: Access Your Application

### First Time Setup

1. **Open your domain in browser:**
   ```
   https://yourdomain.infinityfree.com
   ```
   (or your custom domain if configured)

2. **You'll be automatically redirected to:**
   ```
   https://yourdomain.infinityfree.com/auth/index.php
   ```

3. **Create First Admin User:**
   - Fill in registration details
   - Set up admin credentials
   - You'll be the first admin

4. **Configure Store:**
   - Add store name, code, address
   - Set up billing details (GST number, etc.)

5. **Start Using:**
   - Add products
   - Add users (manager, cashiers)
   - Process sales

---

## Step 7: Configure File Permissions

**InfinityFree typically handles this automatically**, but if you have issues:

1. Using File Manager, right-click folders
2. Set permissions:
   - `logs/` - 755 or 777
   - `uploads/` - 755 or 777
   - `cache/` - 755 or 777
   - `sessions/` - 755 or 777

---

## Troubleshooting on InfinityFree

### Issue 1: "Cannot write to logs directory"

**Solution:**
- Log into File Manager
- Right-click `logs/` folder
- Set permissions to 777 (chmod 777)
- Same for: `uploads/`, `cache/`, `sessions/`

### Issue 2: "Database connection failed"

**Solution:**
- Verify `config/db.php` has correct InfinityFree credentials
- Check database name in phpMyAdmin
- Verify username and password are correct
- Host should be `127.0.0.1`

### Issue 3: "Class 'PHPMailer' not found"

**Solution:**
- Ensure `vendor/` folder is uploaded
- If missing, re-upload vendor folder
- Or run `composer install` (if SSH available)

### Issue 4: "Fatal error: Uncaught Error: Failed opening required"

**Solution:**
- Check file paths in error message
- Verify all files are uploaded correctly
- Check file permissions
- Review `config/` files exist and readable

### Issue 5: White blank page

**Solution:**
```php
// Add to config/db.php for debugging:
ini_set('display_errors', 1);
error_reporting(E_ALL);
```
- Check InfinityFree error logs
- Look in `/var/log/` or File Manager logs

---

## Security Tips for InfinityFree

1. ✅ **Update config files:**
   - `config/db.php` - Use your actual DB credentials
   - `config/mail.php` - Use your actual email credentials

2. ✅ **Ensure `.gitignore` is working:**
   - Sensitive files should NOT be in version control
   - Check what you're committing to Git

3. ✅ **Use HTTPS:**
   - InfinityFree provides free SSL certificate
   - Enable it in Control Panel → SSL

4. ✅ **Set strong passwords:**
   - Admin user password
   - Database password
   - FTP password

5. ✅ **Regular backups:**
   - Download database backup regularly
   - Download file backups periodically

6. ✅ **Monitor logs:**
   - Check error logs for issues
   - Review access logs for suspicious activity

---

## Accessing Admin Features on InfinityFree

### Dashboard
```
https://yourdomain.infinityfree.com
(auto-redirects to dashboard if logged in)
```

### Settings
```
https://yourdomain.infinityfree.com/modules/settings/settings.php
```

### Reports
```
https://yourdomain.infinityfree.com/modules/reports/report_data.php
```

### Products
```
https://yourdomain.infinityfree.com/modules/products/products.php
```

---

## InfinityFree Limitations & Solutions

| Limitation | Impact | Solution |
|-----------|--------|----------|
| Max file upload 10MB | Large imports | Split uploads or use smaller chunks |
| No SSH access | Can't run Composer | Upload vendor folder separately |
| Max execution time 120s | Long operations timeout | Optimize database queries |
| Max script memory 32MB | Large reports | Export data in batches |

---

## After Deployment Checklist

- [ ] Domain is accessible
- [ ] Login page loads
- [ ] Can create admin user
- [ ] Can add products
- [ ] Can process sales
- [ ] Can generate invoices/PDFs
- [ ] Can export reports (CSV)
- [ ] HTTPS/SSL is enabled
- [ ] Backups are scheduled
- [ ] Email OTP works (if configured)

---

## Quick Reference

**Your Application URL:**
```
Home: https://yourdomain.infinityfree.com
Login: https://yourdomain.infinityfree.com/auth/index.php
Dashboard: https://yourdomain.infinityfree.com/modules/dashboard.php
```

**File Locations (on server):**
```
Application files: /home/username/public_html/htdocs/
Config files: /home/username/public_html/htdocs/config/
Database: InfinityFree MySQL
Logs: Check Control Panel → Logs
```

**Get Help:**
1. Check ERROR messages in browser
2. Check logs in File Manager
3. Review DEPLOYMENT_GUIDE.md
4. Review SETUP_SUMMARY.md

---

## Next Steps

1. ✅ Update `config/db.php` with InfinityFree credentials
2. ✅ Update `config/mail.php` (optional, for OTP)
3. ✅ Upload all files via FTP to `htdocs`
4. ✅ Import database schema via phpMyAdmin
5. ✅ Access your domain and create admin user
6. ✅ Test all features
7. ✅ Share with team

---

**Status:** Ready for InfinityFree Deployment ✅  
**Version:** 1.0.0  
**Last Updated:** February 18, 2026
