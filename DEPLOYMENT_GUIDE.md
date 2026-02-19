# Smart Billing & Inventory - Deployment Guide

## Overview
This document outlines the deployment process for the Smart Billing & Inventory system from development to production.

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web Server (Apache, Nginx, etc.)
- Composer (for dependency management)
- Git (for version control)

## Step 1: Clone the Repository

```bash
git clone <repository-url> smart-billing-inventory
cd smart-billing-inventory
```

## Step 2: Install Dependencies

```bash
# Install Composer dependencies (includes PHPMailer)
composer install
```

## Step 3: Configure Database Connection

1. Copy the database configuration template (if needed):
   ```bash
   cp config/db.php.example config/db.php
   ```

2. Edit `config/db.php` with your database credentials:
   ```php
   $db_host = 'your-database-host';     // e.g., localhost or IP address
   $db_user = 'your-database-user';     // e.g., root
   $db_pass = 'your-database-password'; // Your secure password
   $db_name = 'smart_billing';          // Database name
   ```

## Step 4: Configure Email Settings

1. Edit `config/mail.php` with your SMTP settings:
   ```php
   $mail_config = [
       'smtp_host'    => 'smtp.gmail.com',
       'smtp_port'    => 587,
       'smtp_auth'    => true,
       'smtp_secure'  => 'tls',
       'from_email'   => 'your-email@gmail.com',
       'from_name'    => 'BillMitra',
       'username'     => 'your-email@gmail.com',
       'password'     => 'your-app-password', // Use App Password, not main password
   ];
   ```

   **For Gmail:**
   - Enable 2-Factor Authentication
   - Generate an [App Password](https://myaccount.google.com/apppasswords)
   - Use the generated 16-character password

## Step 5: Set Up the Database

1. Import the database schema and migrations:
   ```bash
   mysql -u your_user -p your_database < database/schema.sql
   ```

2. Run any additional migrations from the `migrations/` folder:
   ```bash
   # Apply migrations in order
   mysql -u your_user -p your_database < migrations/202512_add_store_address_and_note.sql
   mysql -u your_user -p your_database < migrations/20251213_add_deleted_stores_table.sql
   mysql -u your_user -p your_database < migrations/20251214_add_deleted_by_to_deleted_stores.sql
   mysql -u your_user -p your_database < migrations/20251217_add_product_name_to_sale_items.sql
   mysql -u your_user -p your_database < migrations/20251218_rename_price_to_total_price.sql
   ```

## Step 6: Set Web Server Configuration

### Apache (.htaccess)
Ensure `.htaccess` support is enabled and create/update the root `.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?/$1 [QSA,L]
</IfModule>
```

### Nginx
Add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## Step 7: Set File Permissions

```bash
# Set proper permissions for web server write access
chmod -R 755 ./
chmod -R 775 logs/
chmod -R 775 uploads/
chmod -R 775 cache/
chmod -R 775 sessions/

# Set ownership to web server user (e.g., www-data on Ubuntu)
chown -R www-data:www-data ./
```

## Step 8: Verify Installation

1. Access the application in your browser:
   ```
   http://yourdomain.com
   ```

2. You should be redirected to the login page at:
   ```
   http://yourdomain.com/auth/
   ```

3. Log in with your admin credentials

## Security Checklist

- [ ] Database credentials are in `config/db.php` (not committed to Git)
- [ ] Email credentials are in `config/mail.php` (not committed to Git)
- [ ] `.gitignore` properly excludes sensitive files
- [ ] File permissions are set correctly (775 for writable directories)
- [ ] PHP `display_errors` is disabled in production
- [ ] HTTPS/SSL certificate is installed
- [ ] Firewall rules restrict database access
- [ ] Regular database backups are scheduled
- [ ] Log files are in a non-web-accessible directory

## Troubleshooting

### PHPMailer Not Found
```bash
composer update
```

### Database Connection Failed
Check:
- Database host, username, and password in `config/db.php`
- MySQL service is running
- User has proper database privileges
- Firewall allows connection

### Font Files Missing for PDF
The font files are already included in `includes/fpdf/font/unifont/`. If missing:
```bash
# Ensure DejaVuSans fonts exist:
ls includes/fpdf/font/unifont/DejaVuSans*
```

### Permission Denied Errors
```bash
# Fix ownership and permissions
sudo chown -R www-data:www-data /path/to/app
sudo chmod -R 775 logs/ uploads/ cache/ sessions/
```

## Backup Strategy

Create regular database backups:
```bash
# Daily backup script
mysqldump -u [user] -p[password] [database] > backup_$(date +%Y%m%d_%H%M%S).sql
```

Schedule via cron:
```bash
0 2 * * * mysqldump -u root -p[password] smart_billing > /backup/smart_billing_$(date +\%Y\%m\%d).sql
```

## Updates & Maintenance

1. **Before updating:**
   ```bash
   # Backup database
   mysqldump -u user -p database > backup_$(date +%Y%m%d).sql
   
   # Backup files
   cp -r . ../backup_$(date +%Y%m%d)/
   ```

2. **Update code:**
   ```bash
   git pull origin main
   composer update
   ```

3. **Run migrations:**
   Apply any new migration files in the `migrations/` folder

4. **Clear cache:**
   ```bash
   rm -rf cache/*
   ```

## Contact & Support

For issues or questions, please refer to the README.md or contact the development team.
