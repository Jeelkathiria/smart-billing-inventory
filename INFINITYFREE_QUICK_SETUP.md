# ⚡ InfinityFree Deployment Checklist

**Quick 10-Step Deployment Guide**

---

## ✅ STEP 1: Prepare Files Locally

- [ ] Edit `config/db.php` with InfinityFree database credentials
  - Get DB name, user, password from InfinityFree Control Panel
  - Update these lines:
    ```php
    $db_host = '127.0.0.1';      // InfinityFree host
    $db_user = 'xxxxx_dbuser';   // Your DB username
    $db_pass = 'your-password';  // Your DB password
    $db_name = 'xxxxx_dbname';   // Your database name
    ```

- [ ] Edit `config/mail.php` (optional, for OTP email)
  - Update with your Gmail credentials
  - Use Gmail App Password, not main password

- [ ] Verify `index.php` exists in root (entry point)
  - This is already created for you ✅

---

## ✅ STEP 2: Connect to InfinityFree via FTP

**Using FileZilla (recommended):**

1. Download FileZilla (free)
2. Get FTP credentials from InfinityFree Control Panel
   - Go to: Control Panel → Accounts → FTP Accounts
   - Note down: FTP Host, Username, Password
3. Open FileZilla and connect:
   - Host: `ftp.yourdomain.com` or provided FTP host
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21
4. Click **Quickconnect**

---

## ✅ STEP 3: Navigate to htdocs Folder

In FileZilla (right panel - remote):
1. Find and open `htdocs` folder
2. Delete any default files (index.html, default pages)
3. **Leave htdocs empty** - you're ready to upload

---

## ✅ STEP 4: Upload Your Project Files

Drag and drop or upload these folders/files to `htdocs`:

```
FROM YOUR COMPUTER          →    TO htdocs FOLDER
├── index.php                    ├── index.php ✅
├── auth/                        ├── auth/
├── modules/                     ├── modules/
├── config/                      ├── config/ (with updated db.php)
├── components/                  ├── components/
├── assets/                      ├── assets/
├── includes/                    ├── includes/
└── vendor/                      └── vendor/
```

**Total time:** 5-15 minutes depending on connection

**Upload Progress:** Look at FileZilla's transfer queue

---

## ✅ STEP 5: Create Database on InfinityFree

1. Log in to **InfinityFree Control Panel**
2. Click **Databases**
3. Click **MySQL Info** button
4. Note down:
   - Database Name: `xxxxx_billmitra`
   - Database User: `xxxxx_billuser`
   - Database Password: (set/change it)

**Database is now ready in InfinityFree MySQL!**

---

## ✅ STEP 6: Import Database Schema (Using phpMyAdmin)

1. In InfinityFree Control Panel, click **Databases**
2. Click **phpMyAdmin** link
3. Select your database from left sidebar
4. Click **Import** tab
5. Option A: Click **Choose File** and select `database.sql` (if you have it)
6. Option B: Paste this SQL in the text area (for first-time setup):

```sql
-- Create Users Table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Stores Table
CREATE TABLE stores (
    store_id INT PRIMARY KEY AUTO_INCREMENT,
    store_name VARCHAR(255) NOT NULL,
    store_code VARCHAR(50) UNIQUE NOT NULL,
    store_address TEXT,
    store_email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Products Table
CREATE TABLE products (
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

-- Create Sales Table
CREATE TABLE sales (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id VARCHAR(50) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    customer_name VARCHAR(255),
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50),
    created_by INT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Sale Items Table
CREATE TABLE sale_items (
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

-- Create Categories Table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) NOT NULL,
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Customers Table
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

7. Click **Go** to execute

**Database tables are now created! ✅**

---

## ✅ STEP 7: Test Your Application

1. Open your browser
2. Go to your InfinityFree domain:
   ```
   https://yourdomain.infinityfree.com
   ```

3. You should see:
   - **Expected:** Redirects to login page
   - **OR direct URL:** `https://yourdomain.infinityfree.com/auth/index.php`

4. **If you see a blank page:**
   - Check File Manager for errors
   - Verify `index.php` is in root `htdocs`
   - Verify `config/db.php` has correct credentials

---

## ✅ STEP 8: Create First Admin User

On the login/registration page:

1. **Register as Admin:**
   - Click "Register" or "Create Account"
   - Fill email: `admin@company.com`
   - Fill username: `admin`
   - Fill password: `Strong@123` (use strong password!)
   - Select role: **Admin**

2. **Set Store Details:**
   - Store Name: Your Store Name
   - Store Code: Unique code (e.g., `STORE001`)
   - Store Address: Your address
   - Store Email: Your email

3. Click **Register/Create**

**You're now logged in! ✅**

---

## ✅ STEP 9: Configure Your Billing System

1. **Add Products:**
   - Go to Products menu
   - Click Add Product
   - Enter: Name, Category, Price, GST, Barcode
   - Save

2. **Add Users (Optional):**
   - Go to Users menu
   - Add users as Cashier or Manager
   - Give them login credentials

3. **Test Billing:**
   - Go to Billing menu
   - Search/add a product
   - Create a sample invoice
   - Print/Download PDF

---

## ✅ STEP 10: Verify Everything Works

- [ ] Login works
- [ ] Dashboard loads
- [ ] Can add products
- [ ] Can create sales
- [ ] Can generate invoices
- [ ] Can export reports
- [ ] PDF downloads work
- [ ] All links work

---

## 🚀 You're Live!

Your application is now running on:
```
https://yourdomain.infinityfree.com
```

Share this URL with your team to start using it!

---

## 🔧 Common Issues & Fixes

### ❌ "Database connection failed"
**Fix:**
- Go to File Manager → config/ → edit db.php
- Verify credentials match InfinityFree MySQL Info
- Check spelling matches exactly

### ❌ "Cannot write to logs"
**Fix:**
- File Manager → Right-click logs/ folder
- Permissions → Set to 777
- Same for: uploads/, cache/, sessions/

### ❌ "Class PHPMailer not found"
**Fix:**
- Verify vendor/ folder uploaded completely
- Re-upload vendor/phpmailer/ folder

### ❌ "File not found" error
**Fix:**
- Verify all folders uploaded to htdocs
- Check index.php is in root htdocs
- Check config/db.php exists

### ❌ Blank white page
**Fix:**
- Enable errors in config/db.php:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Check InfinityFree error logs
- Verify PHP version supports code

---

## 📱 Access Points

**Main Application:**
```
https://yourdomain.infinityfree.com
```

**Direct Login:**
```
https://yourdomain.infinityfree.com/auth/index.php
```

**Dashboard (if logged in):**
```
https://yourdomain.infinityfree.com/modules/dashboard.php
```

**Settings:**
```
https://yourdomain.infinityfree.com/modules/settings/settings.php
```

---

## 💾 Backup Reminder

After successful deployment:

1. **Database Backup:** InfinityFree Control Panel → Databases → Backup
2. **Files Backup:** Use FileZilla to download files periodically
3. **Schedule:** Do this weekly!

---

## ✅ Deployment Complete!

Your **Smart Billing & Inventory** system is now live on InfinityFree! 🎉

**Next Steps:**
- Train your team to use the system
- Add your products and users
- Start processing sales
- Generate reports

---

**Need Help?**
- Read: `INFINITYFREE_DEPLOYMENT.md` - Detailed guide
- Read: `README.md` - Feature documentation
- Read: `DEPLOYMENT_GUIDE.md` - General deployment

**Version:** 1.0.0  
**Last Updated:** February 18, 2026
