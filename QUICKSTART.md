# 🚀 TrenCart - OTP Authentication Quick Start

## ✅ COMPLETED - What You Have Now

### 1. **MySQL Database Schema** ✅
- Multi-role user system (Customer, Shop, Admin, Delivery Boy)
- OTP verification system
- Secure session management
- File: `database/schema.sql`

### 2. **PHP Three-Tier Architecture** ✅
- **Config Layer**: Database connection
- **Model Layer**: User data operations
- **Controller Layer**: Business logic
- **Utils**: OTP & Email management

### 3. **API Endpoints** ✅
- Registration with OTP
- Login with OTP
- OTP verification
- Session management

### 4. **Updated Frontend** ✅
- Registration page with 2-step OTP flow
- Beautiful OTP input interface
- User type selection (Customer/Shop)

---

## 🎯 5-MINUTE SETUP

### Step 1: Start XAMPP (1 min)
```
1. Open XAMPP Control Panel
2. Click "Start" for Apache
3. Click "Start" for MySQL
4. Wait for green indicators
```

### Step 2: Create Database (2 min)
```
1. Open browser: http://localhost/phpmyadmin
2. Click "New" button (left sidebar)
3. Database name: trencart_db
4. Collation: utf8mb4_unicode_ci
5. Click "Create"

6. Click on "trencart_db" database
7. Click "Import" tab
8. Click "Choose File"
9. Select: f:\xampp\htdocs\trencart_new\database\schema.sql
10. Click "Go" at bottom
11. Wait for success message
```

### Step 3: Test It! (2 min)
```
1. Open browser: http://localhost/trencart_new/pages/register.html

2. Fill the form:
   - Select: Customer or Shop
   - Name: Test User
   - Email: test@example.com
   - Phone: 9876543210
   - Accept terms

3. Click "Send OTP"

4. An alert will show your OTP (Development Mode)
   Example: "OTP is 123456"

5. Enter the 6-digit OTP

6. Click "Verify & Register"

7. You're registered! ✅
```

---

## 🔐 Development Mode Features

**OTP Display:**
- OTPs shown in alert boxes
- OTPs logged in console
- No actual email needed for testing

**Check OTP in:**
1. Alert popup (easiest!)
2. Browser Console (F12)
3. Apache error logs

---

## 📱 Test Scenarios

### Test 1: Customer Registration
```
User Type: Customer
Name: John Doe
Email: john@example.com
Phone: 9876543210
```

### Test 2: Shop Owner Registration
```
User Type: Shop Owner
Shop Name: My Fabric Store
Name: Jane Smith
Email: shop@example.com
Phone: 9123456789
```

### Test 3: Login (After Registration)
```
1. Go to login page (to be updated next)
2. Enter registered email
3. Get OTP
4. Enter OTP
5. Login success!
```

---

## 📊 Database Tables Created

```
✅ users - Main user table
✅ otp_verification - OTP codes
✅ customer_profiles - Customer data
✅ shop_profiles - Shop owner data
✅ admin_profiles - Admin data
✅ delivery_profiles - Delivery boy data
✅ addresses - User addresses
✅ user_sessions - Login sessions
✅ login_attempts - Security tracking
```

---

## 🎨 Features Implemented

### Security ✅
- SQL injection prevention (PDO prepared statements)
- XSS protection
- OTP expires in 10 minutes
- One-time use OTPs
- Secure password hashing (removed - using OTP only)

### User Experience ✅
- Beautiful 2-step registration
- Auto-focus OTP inputs
- Number-only OTP inputs
- Resend OTP option
- Change email option
- Real-time validation

### Multi-Role Support ✅
- Customer registration
- Shop owner registration (with shop name)
- Admin accounts
- Delivery boy (future)

---

## 🔄 What's Next (Login Page)

The login page needs to be updated with OTP flow (similar to registration).

**Files to create:**
1. Updated `pages/login.html`
2. JavaScript `assets/js/login-otp.js`

**Flow will be:**
1. Enter email
2. Receive OTP
3. Enter OTP
4. Login!

---

## 🐛 Troubleshooting

### ❌ "Database connection failed"
**Fix:**
- Check MySQL is running in XAMPP
- Check `api/config/database.php` has correct credentials
- Default: username=root, password=empty

### ❌ "Cannot find database"
**Fix:**
- Import `database/schema.sql` in phpMyAdmin
- Make sure database name is `trencart_db`

### ❌ "Failed to send OTP"
**Fix:**
- This is normal - emails not configured yet
- OTP will show in alert/console instead
- Check browser console (F12)

### ❌ "Invalid or expired OTP"
**Fix:**
- OTPs expire in 10 minutes
- Each OTP can only be used once
- Request new OTP if expired

---

## 📞 API Testing (Optional)

Test APIs directly using Postman or browser:

### Register API:
```
URL: http://localhost/trencart_new/api/register.php
Method: POST
Body (JSON):
{
    "email": "test@test.com",
    "full_name": "Test User",
    "phone": "9876543210",
    "user_type": "customer"
}
```

### Verify OTP API:
```
URL: http://localhost/trencart_new/api/verify-registration.php
Method: POST
Body (JSON):
{
    "email": "test@test.com",
    "otp": "123456"
}
```

---

## ✨ Cool Features

1. **Auto-fill Next OTP Box**
   - Type one digit, cursor moves to next box automatically

2. **Backspace Navigation**
   - Press backspace on empty box, goes to previous box

3. **Number-Only Input**
   - Can't type letters in OTP fields

4. **Visual Step Indicator**
   - See progress: Details → Verify OTP

5. **Multi-User Type**
   - Register as Customer or Shop Owner
   - More types coming soon!

---

## 🎓 Understanding the Code

### Three-Tier Architecture:
```
┌─────────────────────┐
│  Presentation Layer │ ← HTML, JavaScript
├─────────────────────┤
│  Business Logic     │ ← Controllers
├─────────────────────┤
│  Data Access        │ ← Models
├─────────────────────┤
│  Database (MySQL)   │ ← phpMyAdmin
└─────────────────────┘
```

### Registration Flow:
```
User fills form
    ↓
JavaScript validates
    ↓
Calls /api/register.php
    ↓
Controller validates data
    ↓
Generates OTP
    ↓
Saves to database
    ↓
Sends email (shows alert in dev)
    ↓
User enters OTP
    ↓
Calls /api/verify-registration.php
    ↓
Verifies OTP from database
    ↓
Creates user account
    ↓
Creates user profile
    ↓
Logs in user
    ↓
Redirects to home
```

---

## 🎯 Next Steps

1. ✅ Test registration
2. ✅ Check database in phpMyAdmin
3. ⏳ I'll update login page next
4. ⏳ Then update old auth.js

---

## 💡 Pro Tips

1. **Keep XAMPP Running**
   - Both Apache and MySQL must be green

2. **Check Console**
   - F12 in browser shows OTPs and errors

3. **phpMyAdmin**
   - View registered users in `users` table
   - See OTPs in `otp_verification` table

4. **Clear OTPs**
   - Old OTPs auto-delete after 10 minutes
   - Or manually delete from database

---

## ✅ You're All Set!

Your OTP authentication system is ready to test!

Try it now:
**http://localhost/trencart_new/pages/register.html**
