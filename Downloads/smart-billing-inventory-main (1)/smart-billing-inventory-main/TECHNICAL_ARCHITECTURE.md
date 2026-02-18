# BillMitra - Technical Architecture & Implementation Guide

**Document Type**: Technical Reference  
**Target Audience**: Developers, DevOps Engineers  
**Last Updated**: February 18, 2026

---

## Table of Contents

1. [Project Structure](#project-structure)
2. [Architecture Overview](#architecture-overview)
3. [Database Schema](#database-schema)
4. [Connection Configuration](#connection-configuration)
5. [Module Descriptions](#module-descriptions)
6. [API Endpoints](#api-endpoints)
7. [Security Implementation](#security-implementation)
8. [Error Handling](#error-handling)
9. [Code Quality Standards](#code-quality-standards)

---

## Project Structure

```
smart-billing-inventory-main/
├── config/
│   └── db.php                          # Centralized database configuration
├── auth/
│   ├── index.php                       # Login page with OTP
│   ├── auth_check.php                  # Session validation & role-based access
│   ├── forgot_password.php             # Password reset flow
│   ├── verify_otp.php                  # OTP verification
│   ├── logout.php                      # Session termination
│   └── check_store_code.php            # Store code validation (AJAX)
├── modules/
│   ├── dashboard.php                   # Main dashboard with KPIs
│   ├── categories.php                  # Category management
│   ├── billing/
│   │   ├── billing.php                 # Live POS interface
│   │   ├── checkout.php                # Cart processing
│   │   ├── generate_invoice.php        # PDF invoice generation
│   │   ├── fetch_products.php          # Product search (AJAX)
│   │   └── fetch_product_by_barcode.php # Barcode search (AJAX)
│   ├── sales/
│   │   ├── sales.php                   # Sales history & filters
│   │   ├── export_sales.php            # CSV export with filters
│   │   ├── view_invoice.php            # Invoice web view
│   │   ├── fetch_price.php             # Price lookup (AJAX)
│   │   └── fetch_products.php          # Product list (AJAX)
│   ├── products/
│   │   ├── products.php                # Product CRUD
│   │   └── check_barcode.php           # Barcode uniqueness check (AJAX)
│   ├── customers/
│   │   └── customers.php               # Customer management
│   ├── settings/
│   │   ├── settings.php                # User & store settings
│   │   ├── update_profile.php          # Profile update (AJAX)
│   │   ├── update_password.php         # Password change (AJAX)
│   │   ├── update_store.php            # Store info update (AJAX)
│   │   ├── update_billing_fields.php   # Billing field config (AJAX)
│   │   ├── delete_account.php          # Account deletion flow
│   │   ├── get_profile_data.php        # Profile data (AJAX)
│   │   └── get_store_data.php          # Store data (AJAX)
│   ├── reports/
│   │   ├── Report.php                  # Reports dashboard
│   │   ├── report_data.php             # Report data aggregation
│   │   ├── get_sales_chart_data.php    # Chart data (AJAX)
│   │   ├── get_daily_sales.php         # Daily sales metrics
│   │   ├── get_sales_by_date.php       # Date-specific sales
│   │   ├── get_monthly_top_products.php # Monthly top products
│   │   ├── get_monthly_top_categories.php # Monthly top categories
│   │   └── get_top_products.php        # Overall top products
│   └── users/
│       └── users.php                   # Cashier management
├── includes/
│   ├── fpdf/                           # FPDF library for PDF generation
│   │   ├── tfpdf.php                   # TFPDF main class
│   │   ├── fpdf.php                    # Standard FPDF
│   │   └── font/unifont/               # Unicode fonts (DejaVu)
│   └── navbar.php                      # Navigation component
├── assets/
│   ├── css/
│   │   └── common.css                  # Global styles
│   └── js/
│       └── common.js                   # Global JavaScript
├── vendor/                             # Composer dependencies
├── migrations/                         # Database migration scripts
├── scripts/                            # Development scripts
├── composer.json                       # Dependency manifest
└── composer.lock                       # Locked dependency versions
```

---

## Architecture Overview

### Request Flow

```
User Request
    ↓
Entry Point (*.php)
    ├── Require: config/db.php [Database Connection]
    ├── Require: auth/auth_check.php [Session Validation]
    └── Require: vendor/autoload.php [Dependencies]
    ↓
Role-Based Access Check
    ├── Super Admin? → Full Access
    ├── Manager? → Restricted Access
    └── Cashier? → Limited Access
    ↓
Business Logic Processing
    ├── Data Validation
    ├── Database Operations
    └── Response Generation
    ↓
Response
    ├── HTML Page
    ├── JSON (AJAX)
    └── File Download (CSV/PDF)
```

### Connection Architecture

```
All Requests
    ↓
config/db.php (Single Source of Truth)
    ├── Database Connection
    ├── Error Handling
    ├── Charset Configuration
    └── Connection Pooling
    ↓
All Modules & Scripts
    └── Use $conn Global Variable
```

---

## Database Schema

### Core Tables

#### users
```sql
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(100) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,  -- SHA2 hash
  role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
  store_id INT NOT NULL,
  status ENUM('active', 'inactive') DEFAULT 'active',
  last_activity TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (store_id) REFERENCES stores(store_id)
);
```

#### stores
```sql
CREATE TABLE stores (
  store_id INT PRIMARY KEY AUTO_INCREMENT,
  store_code VARCHAR(50) UNIQUE NOT NULL,
  store_name VARCHAR(255) NOT NULL,
  store_email VARCHAR(100),
  contact_number VARCHAR(20),
  gstin VARCHAR(15),
  store_address TEXT,
  note TEXT,
  billing_fields JSON,  -- Customizable billing field configuration
  store_type VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### products
```sql
CREATE TABLE products (
  product_id INT PRIMARY KEY AUTO_INCREMENT,
  store_id INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  category_id INT,
  barcode VARCHAR(100) UNIQUE,
  purchase_price DECIMAL(10, 2),
  selling_price DECIMAL(10, 2) NOT NULL,
  gst_percent DECIMAL(5, 2) DEFAULT 0,
  stock INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (store_id) REFERENCES stores(store_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id),
  INDEX idx_store_id (store_id),
  INDEX idx_barcode (barcode)
);
```

#### sales
```sql
CREATE TABLE sales (
  sale_id INT PRIMARY KEY AUTO_INCREMENT,
  invoice_id VARCHAR(100) UNIQUE NOT NULL,
  store_id INT NOT NULL,
  customer_id INT,
  customer_name VARCHAR(255),
  subtotal DECIMAL(12, 2),
  tax_amount DECIMAL(10, 2),
  total_amount DECIMAL(12, 2) NOT NULL,
  payment_method VARCHAR(50),
  billing_meta JSON,  -- Store additional billing fields
  created_by INT,
  sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (store_id) REFERENCES stores(store_id),
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id),
  INDEX idx_store_date (store_id, sale_date),
  INDEX idx_invoice (invoice_id)
);
```

#### sale_items
```sql
CREATE TABLE sale_items (
  item_id INT PRIMARY KEY AUTO_INCREMENT,
  sale_id INT NOT NULL,
  store_id INT NOT NULL,
  product_id INT,
  product_name VARCHAR(255),  -- Denormalized for historical data
  quantity INT NOT NULL,
  total_price DECIMAL(12, 2) NOT NULL,  -- Incl. GST
  gst_percent DECIMAL(5, 2),
  profit DECIMAL(10, 2),
  sale_date DATETIME,
  FOREIGN KEY (sale_id) REFERENCES sales(sale_id),
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  INDEX idx_sale (sale_id),
  INDEX idx_product (product_id)
);
```

---

## Connection Configuration

### Single Configuration File: config/db.php

**Purpose**: Centralized database connection management  
**Used By**: Every PHP file in the application

```php
// Example from config/db.php
$db_host = 'localhost';
$db_user = 'root';
$db_password = 'Jeel@9920';
$db_name = 'smart_billing';

// Connection with error logging (not display)
try {
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    if ($conn->connect_error) {
        error_log('Database Connection Error: ' . $conn->connect_error);
        die('Database connection failed. Contact administrator.');
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log('Connection Exception: ' . $e->getMessage());
    die('Database connection failed. Contact administrator.');
}
```

### Usage Pattern

Every module follows this pattern:

```php
<?php
// ALWAYS at the top
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

// Now $conn is available globally
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
```

---

## Module Descriptions

### Authentication Module (/auth)

**Files**: index.php, auth_check.php, forgot_password.php, verify_otp.php

**Flow**:
1. User submits login credentials
2. System generates OTP
3. OTP sent via email (PHPMailer)
4. User enters OTP to verify
5. Session created with role & store info

**Session Variables** (auth_check.php sets):
```php
$_SESSION['user_id']      // Unique user identifier
$_SESSION['store_id']     // User's store
$_SESSION['role']         // admin, manager, or cashier
$_SESSION['username']     // Display name
$_SESSION['login_time']   // Activity tracking
```

### Billing Module (/modules/billing)

**Files**: billing.php, checkout.php, generate_invoice.php

**Key Features**:
- Real-time product search
- Dynamic cart management
- Instant GST calculation
- PDF invoice generation using FPDF

**Output Formats**:
- HTML (Live UI)
- PDF (Invoice download)

### Sales Module (/modules/sales)

**Files**: sales.php, export_sales.php, view_invoice.php

**Filtering Capabilities**:
- Date range filtering
- Invoice ID search
- Cashier-specific filtering
- Export to CSV with filters

### Reports Module (/modules/reports)

**Files**: Report.php, get_monthly_top_products.php, etc.

**Metrics Calculated**:
- Daily/Monthly Revenue
- Profit Analysis
- Tax Breakdown
- Top Products/Categories
- Trend Analysis

---

## API Endpoints

### AJAX Endpoints (JSON Response)

All AJAX endpoints require:
- Valid session (auth_check.php)
- JSON header: `Content-Type: application/json`
- POST/GET method as specified

#### Product Search
```
POST /modules/billing/fetch_products.php
Params: store_id, search_term
Response: {products: [...]}
```

#### Barcode Lookup
```
POST /modules/billing/fetch_product_by_barcode.php
Params: barcode, store_id
Response: {product: {...}}
```

#### Price Fetch
```
POST /modules/sales/fetch_price.php
Params: product_id, store_id
Response: {price: decimal}
```

#### Invoice Export
```
GET /modules/sales/export_sales.php?filter_date=YYYY-MM-DD&invoice_id=XXX
Response: CSV file download with metadata
```

---

## Security Implementation

### Session Management (auth/auth_check.php)

**Features**:
- Session timeout: 7 hours of inactivity
- AJAX detection for proper error handling
- Activity tracking (last_activity column)
- Role-based access control

**Code**:
```php
$timeout_duration = 7 * 60 * 60; // 7 hours

if (isset($_SESSION['login_time']) && 
    (time() - $_SESSION['login_time']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: /auth/index.php?timeout=1');
    exit();
}
```

### Access Control

**Role Mapping**:
```php
$accessMap = [
    'admin' => ['all modules + user management'],
    'manager' => ['all except user management'],
    'cashier' => ['billing, sales, dashboard, settings']
];
```

### Error Handling

**Production Mode** (config/db.php):
```php
error_reporting(E_ALL);
ini_set('display_errors', 0);      // Don't show to users
ini_set('log_errors', 1);          // Log all errors
```

---

## Error Handling

### Database Errors
```php
if (!$stmt) {
    error_log('Prepare Error: ' . $conn->error);
    echo json_encode(['status' => 'error', 'msg' => 'Operation failed']);
    exit;
}
```

### Email Sending
```php
try {
    $mail->send();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('Mail Error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Email failed']);
}
```

### File Operations
```php
if (!file_exists($fontpath)) {
    error_log('Font not found: ' . $fontpath);
    die('PDF generation failed');
}
```

---

## Code Quality Standards

### Naming Conventions

**Files**: `lowercase_with_underscore.php`  
**Functions**: `camelCase()`  
**Variables**: `$camelCase` or `$lowercase_with_underscore`  
**Constants**: `UPPERCASE_WITH_UNDERSCORE`  
**Classes**: `PascalCase`

### Include Path Pattern

```php
// ✓ CORRECT - Always use __DIR__ for flexibility
require_once __DIR__ . '/../config/db.php';

// ✗ WRONG - Hardcoded paths fail after relocation
require_once '/var/www/html/config/db.php';
```

### SQL Injection Prevention

```php
// ✓ CORRECT - Prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// ✗ WRONG - SQL injection vulnerability
$result = $conn->query("SELECT * FROM users WHERE email = '$email'");
```

### Type Declarations

```php
// Specify parameter types
$stmt->bind_param("i", $user_id);      // "i" = integer
$stmt->bind_param("s", $username);    // "s" = string
$stmt->bind_param("d", $price);       // "d" = double
```

---

## Performance Considerations

### Database Optimization

1. **Indexes**: Added on frequently queried columns
   - users: user_id, email, store_id
   - products: store_id, barcode
   - sales: store_id, sale_date, invoice_id
   - sale_items: sale_id, product_id

2. **Query Optimization**:
   - Pagination (8 records per page)
   - Limit in searches
   - Aggregate functions at DB level

3. **Connection Management**:
   - Single connection per request
   - Closed after response
   - No persistent connections

### Caching Strategy

- Session data cached in `$_SESSION`
- Store info cached per request
- Font metadata cached by FPDF

---

## Deployment Checklist

- [ ] Update `config/db.php` with production credentials
- [ ] Update email credentials in auth files
- [ ] Set file permissions (755 for directories, 644 for files)
- [ ] Remove test files (test_*.php)
- [ ] Enable HTTPS
- [ ] Set up error logging
- [ ] Configure backups
- [ ] Test all major features
- [ ] Monitor error logs post-deployment

---

**Version**: 1.0.0  
**Last Updated**: February 18, 2026
