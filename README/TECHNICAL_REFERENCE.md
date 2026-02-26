# TrenCart - Technical Reference Guide

## System Overview

TrenCart is an e-commerce platform for dress materials and fabrics, built with:
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend**: PHP 7.4+ (Core PHP, Three-Tier Architecture)
- **Database**: MySQL 5.7+ (via phpMyAdmin in XAMPP)
- **Authentication**: OTP-based (Email + 6-digit OTP)
- **Server**: Apache (XAMPP)

---

## 📚 Documentation Index

### Core Documentation
1. **[AUTH_OTP.md](AUTH_OTP.md)** - Complete OTP Authentication System
   - Registration flow
   - Login flow
   - API endpoints
   - Security features
   - Testing guide

2. **[INDEX.md](INDEX.md)** - Homepage documentation
3. **[SHOPS.md](SHOPS.md)** - Shops listing page
4. **[PRODUCTS.md](PRODUCTS.md)** - Products page with filters
5. **[CART.md](CART.md)** - Shopping cart
6. **[CHECKOUT.md](CHECKOUT.md)** - Checkout process

---

## 🏗️ Architecture

### Three-Tier Architecture

```
┌─────────────────────────────────────────────────┐
│                PRESENTATION TIER                │
│                                                 │
│  • HTML5 Pages (Bootstrap 5)                    │
│  • JavaScript (Vanilla JS)                      │
│  • CSS3 (Custom + Bootstrap)                    │
│  • Responsive Design                            │
└─────────────────────────────────────────────────┘
                        ↓ HTTP/AJAX
┌─────────────────────────────────────────────────┐
│              BUSINESS LOGIC TIER                │
│                                                 │
│  • Controllers (AuthController, etc.)           │
│  • Business Rules & Validation                  │
│  • Session Management                           │
│  • API Endpoints                                │
└─────────────────────────────────────────────────┘
                        ↓ PDO
┌─────────────────────────────────────────────────┐
│               DATA ACCESS TIER                  │
│                                                 │
│  • Models (User, Product, Order, etc.)          │
│  • Utils (OTPManager, EmailManager)             │
│  • Database Operations (CRUD)                   │
└─────────────────────────────────────────────────┘
                        ↓ SQL
┌─────────────────────────────────────────────────┐
│                DATABASE TIER                    │
│                                                 │
│  • MySQL Database                               │
│  • Managed via phpMyAdmin                       │
│  • Tables, Views, Procedures                    │
└─────────────────────────────────────────────────┘
```

---

## 📁 Project Structure

```
trencart_new/
├── index.html                    # Homepage
│
├── pages/                        # All page files
│   ├── register.html             # ✅ OTP Registration
│   ├── login.html                # ⏳ To be updated with OTP
│   ├── shops.html                # Shops listing
│   ├── products.html             # Products with filters
│   ├── cart.html                 # Shopping cart
│   └── checkout.html             # Checkout page
│
├── assets/                       # Static assets
│   ├── css/
│   │   ├── theme.css             # Color theme variables
│   │   └── style.css             # Custom styles
│   ├── js/
│   │   ├── main.js               # Core JavaScript
│   │   ├── cart.js               # Cart functionality
│   │   ├── auth.js               # ⏳ To be updated
│   │   ├── register-otp.js       # ✅ Registration OTP logic
│   │   └── login-otp.js          # ⏳ To be created
│   └── images/
│       ├── shops/                # Shop images
│       └── trencartlogo.png      # Logo
│
├── api/                          # ✅ Backend PHP API
│   ├── config/
│   │   └── database.php          # Database connection
│   ├── models/
│   │   └── User.php              # User model
│   ├── controllers/
│   │   └── AuthController.php    # Auth controller
│   ├── utils/
│   │   ├── OTPManager.php        # OTP management
│   │   └── EmailManager.php      # Email sending
│   ├── register.php              # Registration API
│   ├── verify-registration.php   # Verify registration OTP
│   ├── login.php                 # Login API
│   ├── verify-login.php          # Verify login OTP
│   ├── check-auth.php            # Check auth status
│   └── logout.php                # Logout
│
├── database/
│   └── schema.sql                # ✅ Complete database schema
│
├── README/                       # Documentation
│   ├── AUTH_OTP.md               # ✅ OTP Authentication docs
│   ├── TECHNICAL_REFERENCE.md    # This file
│   ├── INDEX.md                  # Homepage docs
│   ├── SHOPS.md                  # Shops page docs
│   ├── PRODUCTS.md               # Products page docs
│   ├── CART.md                   # Cart page docs
│   └── CHECKOUT.md               # Checkout page docs
│
├── SETUP_GUIDE.md                # Setup instructions
└── QUICKSTART.md                 # Quick start guide
```

---

## 🗄️ Database Schema

### Core Tables

#### Users & Authentication
- **users** - Main user table (email, phone, user_type)
- **otp_verification** - OTP codes with expiration
- **user_sessions** - Active user sessions
- **login_attempts** - Security tracking

#### User Profiles (by Role)
- **customer_profiles** - Customer-specific data
- **shop_profiles** - Shop owner data
- **admin_profiles** - Admin permissions
- **delivery_profiles** - Delivery personnel (future)

#### E-commerce (Frontend only - Backend pending)
- **addresses** - User shipping addresses
- Products tables (to be added)
- Orders tables (to be added)
- Cart tables (to be added)

---

## 🔐 Security Implementation

### 1. **SQL Injection Prevention**
```php
// Using PDO Prepared Statements
$stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
$stmt->bindParam(":email", $email);
$stmt->execute();
```

### 2. **XSS Prevention**
```php
// Sanitizing inputs
$this->email = htmlspecialchars(strip_tags($this->email));
```

### 3. **OTP Security**
- 6-digit random generation
- 10-minute expiration
- One-time use
- Database storage with timestamps

### 4. **Session Security**
```php
// Session regeneration on login
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;
$_SESSION['logged_in'] = true;
```

### 5. **Input Validation**
- Email format validation
- Phone number validation (Indian: 10 digits, starts with 6-9)
- User type whitelist validation
- Required field checks

---

## 🎨 Frontend Technologies

### CSS Framework
- **Bootstrap 5.3.0**
  - Grid system
  - Components (navbar, cards, forms, buttons)
  - Utilities
  - Responsive breakpoints

### Custom Styling
- **Color Theme**: 30% Black, 5% Grey, 65% White
  - Primary Black: `#1a1a1a`
  - Grey: `#6c757d`
  - White: `#ffffff`, `#f8f9fa`

### JavaScript
- **Vanilla JavaScript** (No jQuery)
- ES6+ features
- Fetch API for AJAX
- LocalStorage for cart (temporary)
- Session-based authentication (PHP)

### Icons
- **Font Awesome 6.4.0**
  - Used throughout the application
  - Icons for navigation, buttons, features

---

## 🔌 API Architecture

### RESTful Principles
- HTTP methods: GET, POST
- JSON request/response format
- Standard HTTP status codes
- CORS headers for development

### Response Format
```json
{
    "success": true|false,
    "message": "Human-readable message",
    "data": {
        // Optional response data
    }
}
```

### Status Codes
- **200** - Success
- **201** - Created (registration)
- **400** - Bad Request (validation error)
- **401** - Unauthorized
- **403** - Forbidden
- **404** - Not Found
- **409** - Conflict (duplicate email/phone)
- **500** - Server Error

---

## 🔄 Data Flow

### Registration Flow
```
User Input (HTML Form)
    ↓
JavaScript Validation (register-otp.js)
    ↓
AJAX POST to /api/register.php
    ↓
AuthController::register()
    ↓
Validation (email, phone formats)
    ↓
Check duplicates (User Model)
    ↓
Generate OTP (OTPManager)
    ↓
Save to database (OTPManager)
    ↓
Send email (EmailManager)
    ↓
Store temp data in PHP session
    ↓
Response with success
    ↓
Show OTP input (JavaScript)
    ↓
User enters OTP
    ↓
AJAX POST to /api/verify-registration.php
    ↓
AuthController::verifyRegistrationOTP()
    ↓
Verify OTP (OTPManager)
    ↓
Create user (User Model)
    ↓
Create profile (customer_profiles or shop_profiles)
    ↓
Create session
    ↓
Response with user data
    ↓
Auto-login & redirect
```

### Login Flow
```
User Input (Email only)
    ↓
JavaScript Validation
    ↓
AJAX POST to /api/login.php
    ↓
AuthController::login()
    ↓
Check user exists (User Model)
    ↓
Generate OTP (OTPManager)
    ↓
Send email (EmailManager)
    ↓
Response with success
    ↓
Show OTP input
    ↓
User enters OTP
    ↓
AJAX POST to /api/verify-login.php
    ↓
AuthController::verifyLoginOTP()
    ↓
Verify OTP (OTPManager)
    ↓
Update last_login (User Model)
    ↓
Create session
    ↓
Response with user data
    ↓
Redirect to dashboard
```

---

## 🎯 User Roles & Permissions

### 1. **Customer** (user_type: 'customer')
- Browse shops and products
- Add items to cart
- Place orders
- Manage profile
- View order history
- **Profile Table**: `customer_profiles`

### 2. **Shop Owner** (user_type: 'shop')
- Manage shop details
- Add/edit products
- View orders
- Manage inventory
- View analytics (future)
- **Profile Table**: `shop_profiles`
- **Additional Field**: `shop_name` required during registration

### 3. **Admin** (user_type: 'admin')
- Manage all users
- Manage all shops
- Manage all orders
- System settings
- Analytics dashboard (future)
- **Profile Table**: `admin_profiles`
- **Default Admin**: admin@trencart.com

### 4. **Delivery Boy** (user_type: 'delivery_boy') - Future
- View assigned deliveries
- Update delivery status
- Navigation to customer
- **Profile Table**: `delivery_profiles`

---

## 📱 Responsive Design

### Breakpoints
- **Desktop**: ≥992px (3-column layouts)
- **Tablet**: 768px-991px (2-column layouts)
- **Mobile**: <768px (single column)

### Mobile Features
- Collapsible navbar
- Stacked cards
- Full-width buttons
- Touch-friendly OTP inputs
- Optimized images

---

## 🧪 Testing

### Unit Testing (To be implemented)
- PHPUnit for backend
- Jest for JavaScript

### Manual Testing Checklist

**Registration:**
- [ ] Form validation works
- [ ] OTP sent successfully
- [ ] OTP verification works
- [ ] User created in database
- [ ] Profile created based on type
- [ ] Auto-login after registration
- [ ] Shop name required for shop type

**Login:**
- [ ] Email validation
- [ ] OTP sent for existing users
- [ ] OTP verification works
- [ ] Session created
- [ ] Redirect based on user type

**Security:**
- [ ] SQL injection prevented
- [ ] XSS attacks prevented
- [ ] OTP expires after 10 minutes
- [ ] OTP single-use enforced

---

## 🚀 Deployment Checklist

### Before Production

1. **Security**
   - [ ] Change database credentials
   - [ ] Enable HTTPS (SSL certificate)
   - [ ] Update CORS headers
   - [ ] Set `isDevelopmentMode()` to false
   - [ ] Remove OTP from API responses
   - [ ] Enable rate limiting

2. **Email**
   - [ ] Configure SMTP for email sending
   - [ ] Use PHPMailer or similar
   - [ ] Test email delivery
   - [ ] Set up email templates

3. **Database**
   - [ ] Use strong database password
   - [ ] Create database backups
   - [ ] Optimize queries
   - [ ] Add indexes

4. **Performance**
   - [ ] Enable caching
   - [ ] Compress assets
   - [ ] Optimize images
   - [ ] Minify CSS/JS

5. **Monitoring**
   - [ ] Set up error logging
   - [ ] Monitor API performance
   - [ ] Track user analytics

---

## 🔧 Configuration Files

### Database Configuration
**File**: `api/config/database.php`
```php
private $host = "localhost";        // Database host
private $db_name = "trencart_db";   // Database name
private $username = "root";         // Database user
private $password = "";             // Database password
```

### Email Configuration
**File**: `api/utils/EmailManager.php`
```php
private $from_email = "noreply@trencart.com";
private $from_name = "TrenCart";

private function isDevelopmentMode() {
    return true;  // Set to false for production
}
```

---

## 📊 Performance Considerations

### Database
- Indexed columns: email, phone, user_type
- Foreign key constraints for data integrity
- Prepared statements for security & performance

### Caching (To be implemented)
- OPcache for PHP
- Browser caching for static assets
- Session caching

### Optimization
- Lazy loading for images
- Minified CSS/JS in production
- CDN for Bootstrap & Font Awesome

---

## 🐛 Debugging

### Development Mode
- OTPs shown in browser alerts
- Console logging enabled
- Error messages displayed
- Stack traces visible

### Production Mode
- OTPs sent via email only
- Errors logged to files
- Generic error messages to users
- Stack traces hidden

### Debugging Tools
- Browser DevTools (F12)
- PHP error logs (XAMPP/logs)
- MySQL query logs
- Network tab for API calls

---

## 📈 Future Roadmap

### Phase 1 (Current) - Authentication ✅
- [x] OTP-based registration
- [x] OTP-based login
- [x] Multi-role users
- [ ] Complete login page
- [ ] Update old auth.js

### Phase 2 - E-commerce Core
- [ ] Product management
- [ ] Shopping cart (convert from localStorage to database)
- [ ] Order processing
- [ ] Payment gateway integration

### Phase 3 - Shop Management
- [ ] Shop dashboard
- [ ] Product CRUD for shops
- [ ] Inventory management
- [ ] Order fulfillment

### Phase 4 - Admin Panel
- [ ] User management
- [ ] Shop approval workflow
- [ ] Analytics dashboard
- [ ] System settings

### Phase 5 - Advanced Features
- [ ] Delivery tracking
- [ ] Reviews & ratings
- [ ] Wishlist
- [ ] Notifications
- [ ] SMS OTP alternative

---

## 💡 Best Practices Followed

1. **Separation of Concerns**
   - Three-tier architecture
   - Models handle data
   - Controllers handle logic
   - Views handle presentation

2. **Security First**
   - Input validation
   - SQL injection prevention
   - XSS protection
   - Secure sessions

3. **Code Reusability**
   - Utility classes (OTPManager, EmailManager)
   - Base Model class (to be implemented)
   - Common JavaScript functions

4. **Error Handling**
   - Try-catch blocks
   - Error logging
   - User-friendly messages

5. **Documentation**
   - Inline comments
   - README files
   - API documentation
   - Code structure explanation

---

## 📞 Support & Maintenance

### Logs Location (XAMPP)
- **Apache Error Log**: `xampp/apache/logs/error.log`
- **Apache Access Log**: `xampp/apache/logs/access.log`
- **MySQL Error Log**: `xampp/mysql/data/*.err`

### Common Issues
See **SETUP_GUIDE.md** and **QUICKSTART.md** for troubleshooting

---

## ✅ System Status

### Completed ✅
- Database schema with multi-role support
- Three-tier PHP architecture
- OTP generation and management
- Email sending system
- Registration API (complete)
- Login API (complete)
- Registration frontend (complete)
- User model with CRUD operations
- Security implementations

### In Progress ⏳
- Login frontend (APIs ready)
- Login JavaScript (to be created)

### Pending 📝
- Update old auth.js
- Product management backend
- Order processing backend
- Admin panel
- Shop dashboard
- Email templates (HTML)
- SMS OTP integration

---

## 🎓 Learning Resources

### PHP
- PDO Documentation
- PHP Security Best Practices
- Three-Tier Architecture

### MySQL
- Database Normalization
- Index Optimization
- Query Performance

### Security
- OWASP Top 10
- SQL Injection Prevention
- XSS Prevention
- Session Management

---

**Last Updated**: February 2024
**Version**: 1.0.0
**Architecture**: Three-Tier
**Status**: Registration Complete, Login Pending
