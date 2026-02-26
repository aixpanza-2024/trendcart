# Authentication System - OTP-Based (Email + OTP)

## Overview
TrenCart uses a secure OTP (One-Time Password) based authentication system instead of traditional password-based login. This provides better security and user experience.

**Authentication Method:** Email + 6-Digit OTP
**Architecture:** PHP Three-Tier Architecture
**Database:** MySQL (via phpMyAdmin in XAMPP)

---

## 🏗️ System Architecture

### Three-Tier Architecture

```
┌─────────────────────────────────────────────────┐
│         PRESENTATION LAYER (Frontend)           │
│  - register.html (OTP Registration)             │
│  - login.html (OTP Login)                       │
│  - register-otp.js (Registration Logic)         │
│  - login-otp.js (Login Logic)                   │
└─────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────┐
│      BUSINESS LOGIC LAYER (Controllers)         │
│  - AuthController.php                           │
│    • register()                                 │
│    • verifyRegistrationOTP()                    │
│    • login()                                    │
│    • verifyLoginOTP()                           │
│    • logout()                                   │
│    • checkAuth()                                │
└─────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────┐
│        DATA ACCESS LAYER (Models & Utils)       │
│  - User.php (User CRUD)                         │
│  - OTPManager.php (OTP Operations)              │
│  - EmailManager.php (Email Sending)             │
└─────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────┐
│          DATABASE LAYER (MySQL)                 │
│  - users table                                  │
│  - otp_verification table                       │
│  - customer_profiles, shop_profiles, etc.       │
└─────────────────────────────────────────────────┘
```

---

## 📋 Database Schema

### Main Tables

#### 1. **users** - Core user information
```sql
- user_id (PK)
- email (UNIQUE)
- full_name
- phone (UNIQUE)
- user_type (customer/shop/admin/delivery_boy)
- is_verified (BOOLEAN)
- is_active (BOOLEAN)
- created_at
- updated_at
- last_login
```

#### 2. **otp_verification** - OTP storage
```sql
- otp_id (PK)
- user_id (FK) - Can be NULL for registration
- email
- otp_code (6 digits)
- purpose (registration/login/password_reset)
- is_used (BOOLEAN)
- expires_at (10 minutes from creation)
- created_at
```

#### 3. **customer_profiles** - Customer-specific data
```sql
- profile_id (PK)
- user_id (FK)
- date_of_birth
- gender
- profile_image
```

#### 4. **shop_profiles** - Shop owner data
```sql
- shop_id (PK)
- user_id (FK)
- shop_name
- shop_slug (UNIQUE)
- description
- shop_image
- business_registration
- gst_number
- rating
- total_reviews
- is_verified
```

#### 5. **admin_profiles** - Admin data
```sql
- admin_id (PK)
- user_id (FK)
- admin_level (super_admin/admin/moderator)
- permissions (JSON)
```

#### 6. **delivery_profiles** - Delivery personnel (Future)
```sql
- delivery_id (PK)
- user_id (FK)
- vehicle_type
- vehicle_number
- license_number
- is_available
- rating
- total_deliveries
```

---

## 🔐 Registration Flow

### Step 1: User Fills Registration Form

**Frontend: `pages/register.html`**

User provides:
- User Type: Customer or Shop Owner
- Shop Name (if Shop Owner)
- Full Name
- Email Address
- Phone Number (10 digits, starting with 6-9)
- Accept Terms & Conditions

### Step 2: Send OTP Request

**JavaScript: `assets/js/register-otp.js`**

```javascript
POST /api/register.php

Request Body:
{
    "email": "user@example.com",
    "full_name": "John Doe",
    "phone": "9876543210",
    "user_type": "customer",
    "shop_name": "My Shop" // Only if user_type is 'shop'
}
```

### Step 3: Backend Validation & OTP Generation

**PHP: `api/controllers/AuthController.php` → `register()`**

Process:
1. Validate email format
2. Validate phone format (Indian: 10 digits, starts with 6-9)
3. Check if email already exists → Error if exists
4. Check if phone already exists → Error if exists
5. Generate 6-digit OTP
6. Save OTP to database (expires in 10 minutes)
7. Store registration data in PHP session temporarily
8. Send OTP via email (or log in development mode)

```javascript
Response (Success):
{
    "success": true,
    "message": "OTP sent successfully to your email",
    "data": {
        "email": "user@example.com",
        "otp_for_dev": "123456" // Only in development
    }
}
```

### Step 4: Display OTP Input Screen

**Frontend automatically:**
- Hides registration form
- Shows 6 OTP input boxes
- Displays user's email
- Shows "Resend OTP" option
- Shows "Change Email" option

### Step 5: User Enters OTP

User enters the 6-digit OTP received via email (or from alert in dev mode)

### Step 6: Verify OTP Request

**JavaScript: `assets/js/register-otp.js`**

```javascript
POST /api/verify-registration.php

Request Body:
{
    "email": "user@example.com",
    "otp": "123456"
}
```

### Step 7: Backend Verification & Account Creation

**PHP: `api/controllers/AuthController.php` → `verifyRegistrationOTP()`**

Process:
1. Retrieve registration data from PHP session
2. Verify OTP from database
   - Check email matches
   - Check OTP matches
   - Check not expired (< 10 minutes)
   - Check not already used
3. If valid:
   - Create user account in `users` table
   - Create profile based on user type:
     - Customer → `customer_profiles`
     - Shop → `shop_profiles` (with shop name)
   - Mark OTP as used
   - Create user session
   - Return success with user data

```javascript
Response (Success):
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "user_id": 1,
            "email": "user@example.com",
            "full_name": "John Doe",
            "user_type": "customer",
            // ... other user data
        },
        "redirect": "../index.html"
    }
}
```

### Step 8: Auto-Login & Redirect

- User is automatically logged in
- Session created
- Redirected to appropriate page based on user type:
  - Customer → Homepage
  - Shop → Shop Dashboard (future)
  - Admin → Admin Dashboard (future)

---

## 🔑 Login Flow

### Step 1: User Enters Email

**Frontend: `pages/login.html`** (To be completed)

User provides:
- Email Address only (no password!)

### Step 2: Send Login OTP Request

**JavaScript: `assets/js/login-otp.js`** (To be completed)

```javascript
POST /api/login.php

Request Body:
{
    "email": "user@example.com"
}
```

### Step 3: Backend Validation & OTP Generation

**PHP: `api/controllers/AuthController.php` → `login()`**

Process:
1. Validate email format
2. Check if user exists → Error if not found
3. Check if user is active → Error if deactivated
4. Generate 6-digit OTP
5. Save OTP to database
6. Send OTP via email

```javascript
Response (Success):
{
    "success": true,
    "message": "OTP sent successfully to your email",
    "data": {
        "email": "user@example.com",
        "otp_for_dev": "654321" // Only in development
    }
}
```

### Step 4: User Enters OTP

Similar to registration, user enters 6-digit OTP

### Step 5: Verify Login OTP

```javascript
POST /api/verify-login.php

Request Body:
{
    "email": "user@example.com",
    "otp": "654321"
}
```

### Step 6: Backend Verification & Login

**PHP: `api/controllers/AuthController.php` → `verifyLoginOTP()`**

Process:
1. Verify OTP from database
2. If valid:
   - Get user data
   - Update last_login timestamp
   - Create user session
   - Return success with user data and redirect URL

```javascript
Response (Success):
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            // Full user data with profile
        },
        "redirect": "../index.html" // Or admin/shop dashboard
    }
}
```

---

## 🔧 API Endpoints

### 1. **POST /api/register.php**
Send OTP for registration

**Request:**
```json
{
    "email": "user@example.com",
    "full_name": "John Doe",
    "phone": "9876543210",
    "user_type": "customer|shop|admin|delivery_boy",
    "shop_name": "Optional, required if user_type=shop"
}
```

**Response (Success 200):**
```json
{
    "success": true,
    "message": "OTP sent successfully",
    "data": {
        "email": "user@example.com",
        "otp_for_dev": "123456"
    }
}
```

**Response (Error 409 - Email Exists):**
```json
{
    "success": false,
    "message": "Email already registered"
}
```

---

### 2. **POST /api/verify-registration.php**
Verify OTP and create account

**Request:**
```json
{
    "email": "user@example.com",
    "otp": "123456"
}
```

**Response (Success 201):**
```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": { /* user data */ },
        "redirect": "../index.html"
    }
}
```

**Response (Error 400 - Invalid OTP):**
```json
{
    "success": false,
    "message": "Invalid or expired OTP"
}
```

---

### 3. **POST /api/login.php**
Send OTP for login

**Request:**
```json
{
    "email": "user@example.com"
}
```

**Response (Success 200):**
```json
{
    "success": true,
    "message": "OTP sent successfully",
    "data": {
        "email": "user@example.com",
        "otp_for_dev": "654321"
    }
}
```

---

### 4. **POST /api/verify-login.php**
Verify OTP and login

**Request:**
```json
{
    "email": "user@example.com",
    "otp": "654321"
}
```

**Response (Success 200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": { /* user data */ },
        "redirect": "../index.html"
    }
}
```

---

### 5. **GET /api/check-auth.php**
Check if user is logged in

**Response (Authenticated):**
```json
{
    "success": true,
    "message": "User is authenticated",
    "data": {
        "user": { /* user data */ }
    }
}
```

**Response (Not Authenticated):**
```json
{
    "success": false,
    "message": "Not authenticated"
}
```

---

### 6. **POST /api/logout.php**
Logout user

**Response:**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

## 🎨 Frontend Components

### Registration Page Features

1. **User Type Selection**
   - Radio buttons for Customer/Shop Owner
   - Shop name field appears only for Shop Owner

2. **Form Validation**
   - Email format validation
   - Phone: 10 digits, starts with 6-9
   - All fields required
   - Terms acceptance required

3. **Step Indicator**
   - Visual progress: Step 1 (Details) → Step 2 (Verify OTP)
   - Active step highlighted
   - Completed step marked with checkmark

4. **OTP Input**
   - 6 separate input boxes
   - Auto-focus next box on input
   - Backspace navigation
   - Number-only input
   - Auto-submit when all 6 filled

5. **Additional Features**
   - Resend OTP option
   - Change email option
   - Loading states on buttons
   - Toast notifications

### Login Page Features (To be completed)

1. **Simple Email Entry**
   - Single email field
   - "Send OTP" button

2. **OTP Verification**
   - Same 6-box OTP input as registration
   - Resend OTP option
   - Back to email option

---

## 🔒 Security Features

### 1. **SQL Injection Prevention**
- PDO prepared statements
- Parameter binding
- No direct SQL concatenation

```php
$stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
$stmt->bindParam(":email", $email);
$stmt->execute();
```

### 2. **XSS Protection**
- Input sanitization with `htmlspecialchars()`
- Strip tags before database insertion

```php
$this->email = htmlspecialchars(strip_tags($this->email));
```

### 3. **OTP Security**
- 6-digit random OTP
- 10-minute expiration
- One-time use only (marked as used after verification)
- Automatic cleanup of expired OTPs

### 4. **Session Security**
- Session regeneration on login
- Secure session storage
- Session timeout handling

### 5. **Input Validation**
- Email format validation
- Phone number validation (Indian format)
- User type validation (whitelist)
- Required field checks

### 6. **Rate Limiting** (To be implemented)
- Login attempt tracking in `login_attempts` table
- IP-based rate limiting

---

## 📧 Email Configuration

### Development Mode (Current)
- OTPs displayed in browser alert
- OTPs logged to console
- OTPs logged to Apache error log

### Production Mode (Future)
Edit `api/utils/EmailManager.php`:

```php
private function isDevelopmentMode() {
    return false; // Change to false for production
}
```

**Recommended:** Use PHPMailer or SMTP for production emails

---

## 🧪 Testing Guide

### Test Registration

1. **Navigate to:**
   ```
   http://localhost/trencart_new/pages/register.html
   ```

2. **Fill form:**
   - User Type: Customer
   - Name: Test User
   - Email: test@example.com
   - Phone: 9876543210
   - Accept terms

3. **Click "Send OTP"**
   - Alert will show OTP
   - Example: "OTP is 123456"

4. **Enter OTP in 6 boxes**

5. **Click "Verify & Register"**

6. **Success!** - Redirected to homepage

### Test Different User Types

**Customer:**
```json
{
    "user_type": "customer",
    "email": "customer@test.com",
    "full_name": "Customer Name",
    "phone": "9111111111"
}
```

**Shop Owner:**
```json
{
    "user_type": "shop",
    "shop_name": "My Fabric Shop",
    "email": "shop@test.com",
    "full_name": "Shop Owner Name",
    "phone": "9222222222"
}
```

### Verify in Database

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select database: `trencart_db`
3. Check `users` table - see new user
4. Check `customer_profiles` or `shop_profiles` - see profile
5. Check `otp_verification` - see OTP marked as used

---

## 🔄 Session Management

### Session Variables
```php
$_SESSION['user_id']     // User ID
$_SESSION['logged_in']   // Boolean
$_SESSION['login_time']  // Timestamp
```

### Check Authentication
```javascript
fetch('../api/check-auth.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('User logged in:', data.data.user);
        } else {
            console.log('Not logged in');
        }
    });
```

---

## 🚀 Future Enhancements

### Planned Features

1. **Email Templates**
   - HTML email templates
   - Branded emails with logo
   - Multiple language support

2. **SMS OTP**
   - Alternative to email OTP
   - SMS gateway integration
   - User preference (Email vs SMS)

3. **Rate Limiting**
   - Max 5 OTP requests per hour
   - IP-based blocking
   - CAPTCHA for suspicious activity

4. **Account Recovery**
   - Forgot email feature
   - Account verification via phone

5. **Two-Factor Authentication**
   - Optional 2FA for enhanced security
   - TOTP (Google Authenticator)

6. **Social Login** (Optional)
   - Google OAuth
   - Facebook Login
   - Quick registration/login

---

## 📝 Code Structure

### Files Created

```
trencart_new/
├── api/
│   ├── config/
│   │   └── database.php                  (Database connection)
│   ├── models/
│   │   └── User.php                      (User CRUD operations)
│   ├── controllers/
│   │   └── AuthController.php            (Authentication logic)
│   ├── utils/
│   │   ├── OTPManager.php                (OTP management)
│   │   └── EmailManager.php              (Email sending)
│   ├── register.php                      (Registration endpoint)
│   ├── verify-registration.php           (OTP verification)
│   ├── login.php                         (Login endpoint)
│   ├── verify-login.php                  (Login OTP verification)
│   ├── check-auth.php                    (Auth status check)
│   └── logout.php                        (Logout)
├── database/
│   └── schema.sql                        (Database schema)
├── pages/
│   ├── register.html                     (Registration page)
│   └── login.html                        (Login page - to update)
├── assets/
│   └── js/
│       ├── register-otp.js               (Registration OTP logic)
│       └── login-otp.js                  (Login OTP logic - to create)
└── README/
    └── AUTH_OTP.md                       (This file)
```

---

## 🎯 Summary

**What Changed:**
- ❌ Removed password-based authentication
- ✅ Added OTP-based authentication
- ✅ Multi-role user system (Customer, Shop, Admin, Delivery)
- ✅ Secure three-tier PHP architecture
- ✅ Beautiful 2-step registration flow
- ✅ MySQL database via phpMyAdmin

**Benefits:**
- 🔐 More secure (no password storage)
- 🚀 Better UX (no password to remember)
- 📱 Modern authentication method
- 🛡️ SQL injection prevention
- ⚡ Fast OTP verification
- 🎨 Clean, professional UI

**Status:**
- ✅ Registration: Complete
- ⏳ Login: Pending (APIs ready, frontend to be updated)
- ⏳ Old auth.js: To be updated/removed
