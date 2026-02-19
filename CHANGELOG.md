# Changelog

All notable changes to the Smart Billing & Inventory system are documented in this file.

## [Version 1.0.0] - February 18, 2026

### Fixed

#### Database Configuration
- **Fixed:** Missing `config/db.php` file causing "Failed to open stream" errors
- **Added:** Database connection configuration file with MySQLi connection
- **Improved:** Connection error handling with user-friendly messages

#### Email/Mailing System
- **Fixed:** Hardcoded email credentials scattered across multiple files
- **Created:** Centralized `config/mail.php` for email configuration
- **Fixed:** PHPMailer class not found error by properly reinstalling vendor dependencies
- **Updated:** All email functions to use centralized mail functions
  - `auth/index.php` - OTP verification emails use unified sendEmail()
  - `auth/forgot_password.php` - Password reset emails use unified sendEmail()
  - `modules/settings/delete_account.php` - Account deletion OTP emails use unified sendEmail()
- **Improved:** Email error handling with logging for debugging
- **Added:** Proper exception handling for SMTP failures
- **Fixed:** Inconsistent email sender credentials (now uses "BillMitra" as sender)

#### PDF Font Files
- **Fixed:** Hardcoded OneDrive paths in font configuration files
  - `includes/fpdf/font/unifont/dejavusans.mtx.php`
  - `includes/fpdf/font/unifont/dejavusans-bold.mtx.php`
- **Changed:** Absolute paths to relative paths using `__DIR__` constant
- **Impact:** PDF generation now works regardless of project installation location

#### File Path Consistency
- **Fixed:** Inconsistent require_once statements using relative paths
- **Updated:** `modules/dashboard.php` to use `__DIR__` constant
- **Verified:** All other files properly use `__DIR__` for includes
- **Benefit:** Application works correctly when accessed from different entry points

#### Excel Export
- **Fixed:** CSV file format mismatch error in Excel
- **Changed:** `modules/sales/export_sales.php` to export proper CSV format
- **Updated:** File extension from `.xls` to `.csv`
- **Updated:** MIME type to `text/csv` for proper Excel recognition
- **Added:** Proper CSV escaping for product names containing special characters
- **Result:** Files open in Excel without format warnings

#### Composer Dependencies
- **Fixed:** Missing PHPMailer package (vendor directory was empty)
- **Reconfigured:** Vendor dependencies through composer update
- **Result:** All PHP dependencies properly installed and autoloaded

### Added

#### Configuration Files
- **Created:** `config/mail.php` - Centralized email configuration
- **Updated:** `.gitignore` - Comprehensive list of files to exclude from version control
- **Created:** `DEPLOYMENT_GUIDE.md` - Complete deployment instructions

#### Documentation
- **Added:** This changelog documenting all modifications
- **Added:** Deployment guide with step-by-step instructions
- **Updated:** README.md with project information and setup instructions

#### Security
- **Added:** `.gitignore` entries for:
  - Configuration files with credentials (`config/db.php`, `config/mail.php`)
  - Vendor dependencies (`vendor/`)
  - Composer lock file
  - IDE files (`.vscode/`, `.idea/`)
  - Temporary and log files
  - Session and cache directories

### Changed

#### Email Configuration
- Migrated from scattered email passwords to centralized config
- Created reusable `sendEmail()` function with standardized error handling
- Updated all OTP sending functions to use new centralized approach

#### File Paths
- Standardized all includes to use `__DIR__` constant
- Ensures consistency whether application is accessed from any entry point
- Removes dependency on working directory

### Technical Details

#### Files Modified
1. `config/db.php` - Created
2. `config/mail.php` - Created
3. `.gitignore` - Updated
4. `DEPLOYMENT_GUIDE.md` - Created
5. `auth/index.php` - Updated email handling
6. `auth/forgot_password.php` - Updated email handling
7. `modules/settings/delete_account.php` - Updated email handling
8. `modules/dashboard.php` - Fixed include path
9. `modules/sales/export_sales.php` - Fixed CSV format
10. `includes/fpdf/font/unifont/dejavusans.mtx.php` - Fixed font path
11. `includes/fpdf/font/unifont/dejavusans-bold.mtx.php` - Fixed font path

#### Dependencies
- PHPMailer/PHPMailer: ^7.0 (v7.0.2)

### Notes

- All configuration files with sensitive data are properly excluded from Git
- Application is now ready for deployment to production
- All file paths use `__DIR__` constant for portability
- Email system is centralized for easier maintenance and updates
- PDF generation uses relative paths and works on any system

### Future Recommendations

1. Implement environment variables for database configuration
2. Add database migration management system
3. Implement automated backup strategy
4. Add API rate limiting and authentication
5. Implement request logging for security audit trails
6. Add email queue system for better performance
7. Implement caching strategy for frequently accessed data
