# TrenCart - OTP Authentication Setup Guide

## Complete MySQL + PHP Three-Tier Architecture

This guide will help you set up the OTP-based authentication system with MySQL database via phpMyAdmin in XAMPP.

---

## 📋 What Has Been Created

### 1. **Database Layer** ✅
- `database/schema.sql` - Complete database schema with:
  - Multi-role user system (Customer, Shop, Admin, Delivery Boy)
  - OTP verification table
  - User profiles for each role
  - Sessions and security tables

### 2. **Three-Tier PHP Architecture** ✅

#### Configuration Layer
- `api/config/database.php` - Database connection

#### Data Access Layer (Models)
- `api/models/User.php` - User CRUD operations

#### Business Logic Layer (Controllers)
- `api/controllers/AuthController.php` - Authentication logic

#### Utility Layer
- `api/utils/OTPManager.php` - OTP generation and verification
- `api/utils/EmailManager.php` - Email sending for OTP

### 3. **API Endpoints** ✅
- `api/register.php` - Send OTP for registration
- `api/verify-registration.php` - Verify OTP and complete registration
- `api/login.php` - Send OTP for login
- `api/verify-login.php` - Verify OTP and complete login
- `api/check-auth.php` - Check authentication status
- `api/logout.php` - Logout user

### 4. **Frontend Pages** ✅
- Updated `pages/register.html` - Two-step registration with OTP
- Login page (to be updated next)

---

## 🚀 SETUP INSTRUCTIONS

### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache** server
3. Start **MySQL** server

### Step 2: Create Database via phpMyAdmin
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click on "New" in the left sidebar
3. Or directly run the SQL file:
   - Click "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

**Alternative:** Run SQL manually:
```sql
-- Copy contents from database/schema.sql and execute in phpMyAdmin SQL tab
```

### Step 3: Configure Database Connection
Open: `api/config/database.php`

Update these lines if needed:
```php
private $host = "localhost";      // Usually localhost for XAMPP
private $db_name = "trencart_db";  // Database name
private $username = "root";        // Default XAMPP username
private $password = "";            // Default XAMPP password is empty
```

### Step 4: Test Database Connection
1. Create a test file: `test_connection.php` in root folder
```php
<?php
require_once 'api/config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "✅ Database connected successfully!";
} else {
    echo "❌ Database connection failed!";
}
?>
```

2. Visit: `http://localhost/trencart_new/test_connection.php`

### Step 5: Verify API Endpoints
Test the APIs using browser or Postman:

**Test Registration:**
```
URL: http://localhost/trencart_new/api/register.php
Method: POST
Body (JSON):
{
    "email": "test@example.com",
    "full_name": "Test User",
    "phone": "9876543210",
    "user_type": "customer"
}
```

---

## 📂 Project Structure

```
trencart_new/
├── api/
│   ├── config/
│   │   └── database.php          ✅ Database connection
│   ├── models/
│   │   └── User.php               ✅ User model
│   ├── controllers/
│   │   └── AuthController.php     ✅ Auth controller
│   ├── utils/
│   │   ├── OTPManager.php         ✅ OTP management
│   │   └── EmailManager.php       ✅ Email sending
│   ├── register.php               ✅ Registration API
│   ├── verify-registration.php    ✅ Verify registration OTP
│   ├── login.php                  ✅ Login API
│   ├── verify-login.php           ✅ Verify login OTP
│   ├── check-auth.php             ✅ Check auth status
│   └── logout.php                 ✅ Logout API
├── database/
│   └── schema.sql                 ✅ MySQL database schema
├── pages/
│   ├── register.html              ✅ Updated with OTP flow
│   ├── login.html                 ⏳ To be updated
│   └── ... (other pages)
├── assets/
│   └── js/
│       ├── register-otp.js        ⏳ To be created
│       └── login-otp.js           ⏳ To be created
```

---

## 🔐 Security Features Implemented

1. **Three-Tier Architecture**
   - Separation of concerns
   - Data Access, Business Logic, Presentation layers

2. **SQL Injection Prevention**
   - PDO prepared statements
   - Parameter binding

3. **Input Validation**
   - Email format validation
   - Phone number validation (Indian format)
   - XSS protection with htmlspecialchars

4. **OTP Security**
   - 10-minute expiration
   - One-time use only
   - Automatic cleanup of expired OTPs

5. **Session Management**
   - Secure session regeneration
   - Session-based authentication

---

## 🎯 How OTP Flow Works

### Registration Flow:
```
1. User fills registration form
2. Frontend sends data to /api/register.php
3. Backend validates data
4. Backend generates 6-digit OTP
5. OTP saved to database with 10-min expiry
6. OTP sent to user's email
7. User enters OTP in frontend
8. Frontend sends OTP to /api/verify-registration.php
9. Backend verifies OTP
10. User account created
11. User logged in automatically
```

### Login Flow:
```
1. User enters email
2. Frontend sends to /api/login.php
3. Backend checks if user exists
4. Backend generates OTP
5. OTP sent to user's email
6. User enters OTP
7. Frontend sends to /api/verify-login.php
8. Backend verifies OTP
9. User logged in
```

---

## 📧 Email Configuration

Currently in **DEVELOPMENT MODE** - OTPs are logged instead of emailed.

**Check OTP in:**
- XAMPP Control Panel → Apache → Logs
- Or check PHP error log

**To enable actual email sending:**
1. Edit `api/utils/EmailManager.php`
2. Change `isDevelopmentMode()` to return `false`
3. Configure PHP mail settings or use PHPMailer

---

## 🧪 Testing Checklist

### Database:
- [ ] Database `trencart_db` created
- [ ] All tables created successfully
- [ ] Default admin user exists

### APIs:
- [ ] Registration API works
- [ ] OTP verification works
- [ ] Login API works
- [ ] Login OTP verification works

### Frontend:
- [ ] Registration form displays
- [ ] OTP input screen appears
- [ ] Can switch between Customer/Shop registration
- [ ] Phone validation works

---

## 🔄 What's Next

The following files still need to be created:

1. **JavaScript for Registration**
   - `assets/js/register-otp.js`

2. **Updated Login Page**
   - `pages/login.html` (with OTP flow)

3. **JavaScript for Login**
   - `assets/js/login-otp.js`

4. **Update auth.js**
   - Remove old password-based auth
   - Add OTP-based auth functions

---

## 💡 Important Notes

1. **For Development:**
   - OTPs are displayed in console/logs
   - Check browser console for OTP in development mode

2. **Database:**
   - Default admin: admin@trencart.com
   - All passwords removed (OTP-only authentication)

3. **User Types:**
   - `customer` - Regular customers
   - `shop` - Shop owners (requires shop name)
   - `admin` - Administrators
   - `delivery_boy` - Delivery personnel (future)

4. **Security:**
   - Never store plain OTPs in production
   - Use HTTPS in production
   - Enable email sending for production
   - Change database credentials

---

## 🐛 Troubleshooting

### Database Connection Failed:
- Check MySQL is running in XAMPP
- Verify credentials in `api/config/database.php`
- Check database name is `trencart_db`

### OTP Not Received:
- Check in development mode OTP is logged
- Check Apache error logs
- Verify EmailManager is working

### API Returns 500 Error:
- Check PHP error logs in XAMPP
- Verify all required files exist
- Check database connection

---

## 📱 Default Test Account

After running schema.sql:
- Email: `admin@trencart.com`
- User Type: `admin`
- You can test login by requesting OTP for this email

---

## ✅ Ready to Test!

Your OTP-based authentication system is set up and ready!

Next: I'll create the remaining JavaScript files for registration and login.
