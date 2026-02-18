# BillMitra Deployment Guide

**Project**: Smart Billing & Inventory Management System  
**Product Name**: BillMitra  
**Environment**: Production  
**Last Updated**: February 18, 2026

---

## Quick Overview

BillMitra is a comprehensive billing and inventory management system built with PHP, MySQL, and Bootstrap. All critical errors have been fixed and the system is ready for production deployment.

### What We Fixed

1. **Database Connection** - Created `config/db.php` with centralized connection
2. **Email System** - Installed PHPMailer v7.0.2 and configured with BillMitra branding
3. **PDF Generation** - Fixed FPDF font paths to use dynamic `__DIR__` instead of hardcoded paths
4. **Excel Export** - Implemented CSV export with date filter support
5. **Error Handling** - Suppressed console errors in production
6. **Dependencies** - Installed all Composer packages (PHPMailer, etc.)
7. **Email Branding** - Updated all email templates from "SmartBiz" to "BillMitra"

---

## System Requirements

- **PHP**: 8.3.23 or higher
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache/Nginx with PHP-FPM
- **Extensions**:
  - mysqli (MySQL database)
  - json (JSON processing)
  - SPL (Standard PHP Library)
- **RAM**: Minimum 512 MB
- **Disk Space**: 500 MB minimum (including database)

---

## Pre-Deployment Checklist

- [ ] Server has PHP 8.3+ installed
- [ ] MySQL/MariaDB running and accessible
- [ ] SSL certificate installed
- [ ] Email SMTP configured
- [ ] File permissions set correctly
- [ ] Database backup created
- [ ] Error logging directory created
- [ ] All dependencies installed via Composer

---

## 8-Step Installation & Deployment

### Step 1: Upload Files to Server

```bash
# Using SFTP or git clone
git clone https://github.com/Jeelkathiria/smart-billing-inventory.git /var/www/BillMitra
cd /var/www/BillMitra
```

### Step 2: Set File Permissions

```bash
# Make directories writable for logs
chmod 755 /var/www/BillMitra
chmod 755 /var/www/BillMitra/includes
chmod 755 /var/www/BillMitra/modules

# Create logs directory
mkdir -p /var/log/billmitra
chmod 755 /var/log/billmitra
chown www-data:www-data /var/log/billmitra
```

### Step 3: Install Composer Dependencies

```bash
# If Composer not already installed
composer install --no-dev

# Verify PHPMailer installed
ls -la vendor/phpmailer/phpmailer/src/
```

### Step 4: Configure Database

```sql
-- Import database schema
mysql -u root -p smart_billing < database_backup.sql

-- Verify tables
mysql -u root -p smart_billing -e "SHOW TABLES;"
```

**Expected Output** (8 tables):
```
categories
customers
deleted_stores
products
sale_items
sales
stores
users
```

### Step 5: Update Configuration File

Edit `config/db.php`:

```php
$db_host = 'localhost';      // or your server IP
$db_user = 'root';           // or your MySQL user
$db_password = 'Jeel@9920';  // UPDATE TO PRODUCTION PASSWORD
$db_name = 'smart_billing';  // Database name
```

**⚠️ IMPORTANT**: Update credentials with production values.

### Step 6: Configure Email

Edit `auth/index.php` (around line 30):

```php
$mail->setFrom('your-email@gmail.com', 'BillMitra');
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';  // Google App Password, not regular password
```

**Gmail Setup for SMTP**:
1. Enable 2-Factor Authentication on Gmail
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use 16-character password in code

### Step 7: Configure Web Server

#### Apache (.htaccess)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirect to HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Prevent directory listing
    Options -Indexes
    
    # Block access to config files
    <FilesMatch "\.php$">
        Deny from all
    </FilesMatch>
    <FilesMatch "^config">
        Deny from all
    </FilesMatch>
</IfModule>
```

#### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    root /var/www/BillMitra;
    index index.php;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    location ~ /\. {
        deny all;
    }
    
    location ~ ^/config/ {
        deny all;
    }
}
```

### Step 8: Verify Deployment

```bash
# Test database connection
php -r "
\$conn = new mysqli('localhost', 'root', 'Jeel@9920', 'smart_billing');
if (\$conn->connect_error) {
    die('Connection Failed: ' . \$conn->connect_error);
}
echo 'Database Connected Successfully!\n';
echo 'Tables: ' . count(array(\$conn->query('SHOW TABLES')->fetch_assoc())) . '\n';
\$conn->close();
"

# Test file permissions
ls -la config/db.php
ls -la auth/index.php

# Test web access
curl https://yourdomain.com/auth/index.php
```

---

## Database Schema

### tables Overview

| Table | Purpose | Records |
|-------|---------|---------|
| users | User accounts & login | Multiple per store |
| stores | Store information | One per merchant |
| products | Product catalog | Hundreds per store |
| categories | Product categories | Dozens per store |
| customers | Customer records | Hundreds to thousands |
| sales | Transaction headers | Daily |
| sale_items | Transaction line items | Multiple per sale |
| deleted_stores | Soft-delete archive | Archived stores |

### Key Relationships

```
stores (1) ──→ (Many) users
stores (1) ──→ (Many) products
stores (1) ──→ (Many) customers
stores (1) ──→ (Many) sales
categories (1) ──→ (Many) products
customers (1) ──→ (Many) sales
sales (1) ──→ (Many) sale_items
products (1) ──→ (Many) sale_items
```

---

## Security Hardening

### 1. File Permissions

```bash
# Restrict sensitive directories
chmod 700 /var/www/BillMitra/config
chmod 700 /var/www/BillMitra/includes

# Make uploads writable but not executable
chmod 755 /var/www/BillMitra/uploads
find /var/www/BillMitra/uploads -type f -exec chmod 644 {} \;
```

### 2. Database User Privileges

```sql
-- Create limited database user
CREATE USER 'billmitra'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Grant only necessary privileges
GRANT SELECT, INSERT, UPDATE, DELETE ON smart_billing.* TO 'billmitra'@'localhost';
GRANT CREATE TEMPORARY TABLES ON smart_billing.* TO 'billmitra'@'localhost';

-- Do NOT grant: CREATE, DROP, ALTER, LOCK TABLES
FLUSH PRIVILEGES;
```

### 3. PHP Security Settings

```php
# In php.ini or .htaccess
php_flag display_errors Off           # Hide errors from users
php_flag log_errors On                # But log them
php_value error_log /var/log/billmitra/errors.log

php_flag session.secure On            # HTTPS only cookies
php_flag session.httponly On          # No JavaScript access
php_flag session.samesite Strict      # CSRF protection

# Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

### 4. .htaccess Security

```apache
# Prevent unauthorized access
<FilesMatch "\.(env|json|lock|sql)$">
    Deny from all
</FilesMatch>

# Disable PHP in uploads directory
<Directory /var/www/BillMitra/uploads>
    php_flag engine off
    AddType text/plain .php .phtml .php3 .php4 .php5 .php6 .php7 .php8 .phps
</Directory>
```

---

## Troubleshooting

### Problem 1: "Database Connection Failed"

**Cause**: MySQL credentials incorrect or service not running  
**Solution**:
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -h localhost -u root -p smart_billing -e "SELECT VERSION();"

# Verify config/db.php credentials match
cat config/db.php | grep "db_"
```

### Problem 2: "Class PHPMailer Not Found"

**Cause**: Composer packages not installed  
**Solution**:
```bash
composer install
composer dump-autoload -o
php -r "require 'vendor/autoload.php'; echo 'PHPMailer installed!';"
```

### Problem 3: "FPDF Error: Font File Not Found"

**Cause**: Font paths contain hardcoded paths  
**Solution**:
```bash
# Verify font files exist
ls -la includes/fpdf/font/unifont/DejaVuSans.ttf

# Check file uses __DIR__ not hardcoded paths
grep "__DIR__" includes/fpdf/font/unifont/dejavusans.mtx.php
```

### Problem 4: "Session Lost After Login"

**Cause**: Session timeout or cookie settings  
**Solution**:
```php
# In php.ini
session.gc_maxlifetime = 25200     # 7 hours
session.cookie_httponly = 1
session.cookie_secure = 1           # HTTPS only
```

### Problem 5: "Email Not Sending"

**Cause**: SMTP credentials or firewall blocking  
**Solution**:
```bash
# Test email function
php -r "
require 'vendor/autoload.php';
\$mail = new PHPMailer\PHPMailer\PHPMailer();
\$mail->isSMTP();
\$mail->Host = 'smtp.gmail.com';
\$mail->Port = 587;
\$mail->SMTPAuth = true;
\$mail->SMTPSecure = 'tls';
\$mail->Username = 'your-email@gmail.com';
\$mail->Password = 'app-password';
\$mail->setFrom('your-email@gmail.com');
\$mail->addAddress('test@example.com');
\$mail->Subject = 'Test Email';
\$mail->Body = 'This is a test';
if(\$mail->send()) {
    echo 'Email sent successfully!';
} else {
    echo 'Error: ' . \$mail->ErrorInfo;
}
"
```

### Problem 6: "404 Errors on All Pages"

**Cause**: Web server not routing to PHP files correctly  
**Solution**:
```apache
# Add to .htaccess
AddType application/x-httpd-php .php
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^([^/]+)\.php$ index.php [QSA,L]
</IfModule>
```

---

## Backup & Recovery

### Daily Backup Script

```bash
#!/bin/bash
# /usr/local/bin/backup-billmitra.sh

BACKUP_DIR="/var/backups/billmitra"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u root -p'password' smart_billing > $BACKUP_DIR/db_$DATE.sql

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/BillMitra

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

**Schedule via Crontab**:
```bash
# Run daily at 2 AM
0 2 * * * /usr/local/bin/backup-billmitra.sh
```

### Recovery Procedure

```bash
# Restore database from backup
mysql -u root -p smart_billing < /var/backups/billmitra/db_20260218_020000.sql

# Restore files from backup
tar -xzf /var/backups/billmitra/files_20260218_020000.tar.gz -C /

# Verify restoration
php -r "
\$conn = new mysqli('localhost', 'root', 'password', 'smart_billing');
\$result = \$conn->query('SELECT COUNT(*) as count FROM sales');
\$row = \$result->fetch_assoc();
echo 'Sales records restored: ' . \$row['count'] . '\n';
\$conn->close();
"
```

---

## Monitoring & Maintenance

### Monitor Error Logs

```bash
# Real-time error monitoring
tail -f /var/log/billmitra/errors.log

# Search for specific errors
grep "Database Error" /var/log/billmitra/errors.log

# Weekly error report
find /var/log/billmitra -name "errors.log" -mtime -7 -exec grep "Error" {} \; | sort | uniq -c
```

### Performance Monitoring

```bash
# Check response times
apache2ctl status

# Monitor MySQL performance
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check disk space
df -h /var/www/BillMitra
```

### Database Maintenance

```sql
-- Optimize tables (run weekly)
OPTIMIZE TABLE users;
OPTIMIZE TABLE products;
OPTIMIZE TABLE sales;
OPTIMIZE TABLE sale_items;

-- Check table integrity
CHECK TABLE users;
CHECK TABLE products;
```

---

## Post-Deployment Checklist

- [ ] All pages load without errors
- [ ] Login/logout working
- [ ] Email OTP sending successfully
- [ ] Billing/POS interface functional
- [ ] Reports generating correctly
- [ ] Export to CSV working
- [ ] PDF invoices generating
- [ ] Error logs recording properly
- [ ] Database backups running
- [ ] SSL certificate active

---

## Support & Maintenance

### Contact Information

**Developer**: Jeel Kathiria  
**Email**: testing992017@gmail.com  
**Repository**: https://github.com/Jeelkathiria/smart-billing-inventory

### Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Feb 18, 2026 | Initial production release |

---

**Document Version**: 1.0.0  
**Last Updated**: February 18, 2026  
**Status**: Ready for Production Deployment
