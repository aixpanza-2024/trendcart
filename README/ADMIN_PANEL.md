# TrenCart Admin Panel - Documentation

## Overview

The Admin Panel is a comprehensive management system for TrenCart platform administrators. It provides full control over shops, orders, customers, revenue tracking, and payment settlements.

**Access URL:** `http://localhost/trencart_new/admin/dashboard.html`

**Login:** Use the common login page (`/pages/login.html`) with an admin account. The system auto-redirects admin users to the admin dashboard.

---

## Modules

### 1. Dashboard (`admin/dashboard.html`)

**Purpose:** Central overview of the entire platform.

**Features:**
- **Primary Stats**: Total orders, total revenue, active shops, total customers
- **Revenue Cards**: Today's, this week's, and this month's revenue (dark themed)
- **Pending Orders Table**: Recent orders awaiting action (quick view + link)
- **Top Shops**: Top 5 shops by sales with status
- **Recent Customers**: Latest 5 registered customers
- **Order Status Breakdown**: Visual progress bars showing order distribution

**API:** `GET /api/admin/dashboard.php`

---

### 2. Shop Management (`admin/shops.html`)

**Purpose:** Add, list, search, and manage all shops on the platform.

**Features:**
- **Add Shop (Modal)**: Register new shop owners (creates user account + shop profile + shops entry)
- **List Shops**: Searchable table with name, email, products, orders, sales, status
- **Status Management**: Open / Closed / Suspended via dropdown menu
- **Suspension**: Suspending a shop also deactivates the user account; reactivating restores it

**APIs:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/shops.php` | GET | List shops with search/filter |
| `/api/admin/add-shop.php` | POST | Create new shop + owner account |
| `/api/admin/update-shop-status.php` | POST | Update shop status |

**Add Shop Fields:** Owner Name*, Email*, Phone*, Shop Name*, Description, City, Phone

---

### 3. Order Management (`admin/orders.html`)

**Purpose:** View and manage all platform orders with status updates.

**Features:**
- **Filters**: Search (order #, customer), status, payment status, date range
- **Order Table**: Order #, date, customer, shop(s), amount, payment status, order status
- **Status Update Modal**: Change status with tracking number and note support
- **Status Flow**: Pending → Confirmed → Processing → Shipped → Delivered
- **Other Statuses**: Cancelled, Returned, Refunded

**APIs:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/orders.php` | GET | List orders with filters |
| `/api/admin/update-order-status.php` | POST | Update order status + log history |

**Status Update Fields:** New Status*, Tracking Number (optional), Note (optional)

**Auto-timestamps:** confirmed_at, shipped_at, delivered_at, cancelled_at are set automatically.

---

### 4. Customer Management (`admin/customers.html`)

**Purpose:** View all registered customers and their activity.

**Features:**
- **Search**: By name, email, or phone
- **Filter**: Active / Inactive
- **Stats Cards**: Total customers, active customers, new this month
- **Customer Table**: Name, email, phone, order count, total spent, join date, status

**API:** `GET /api/admin/customers.php` (returns both stats and customer list)

---

### 5. Revenue & Analytics (`admin/revenue.html`)

**Purpose:** Track platform revenue by different time periods and per shop.

**Features:**
- **Period Toggle**: Daily / Weekly / Monthly view
- **Summary Cards**: Total revenue, total orders, delivered revenue, cancelled revenue
- **Shop-wise Revenue Table**: Per-shop breakdown showing today, weekly, monthly, total, and pending payout
- **Revenue Log**: Day-by-day revenue data for the last 30 days

**API:** `GET /api/admin/revenue.php?period=daily|weekly|monthly`

---

### 6. Shop Payments (`admin/payments.html`)

**Purpose:** Manage weekly payment settlements to shops.

**Features:**
- **Generate Weekly Payments**: Creates payment records for current week based on confirmed/delivered order items
- **Payment Stats**: Total unpaid, total paid, commission earned, shops with pending payments
- **Filter**: By payment status, by shop
- **Mark as Paid (Modal)**: Record payment with method, transaction reference, and notes
- **Commission**: Automatically calculated based on platform `commission_rate` setting (default 10%)

**Payment Cycle:**
1. Admin clicks "Generate Weekly" → System calculates each shop's sales for the week
2. Deducts platform commission → Creates payment record with `payable_amount`
3. Admin reviews and marks each payment as paid when settled

**APIs:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/payments.php` | GET | List payment records |
| `/api/admin/generate-payments.php` | POST | Generate weekly payment records |
| `/api/admin/mark-paid.php` | POST | Mark a payment as paid |

**Payment Methods Supported:** Bank Transfer, UPI, Cash, Cheque

---

### 7. Category Management (`admin/categories.html`)

**Purpose:** Manage product categories and sub-categories.

**Features:**
- **Add/Edit Category (Modal)**: Name, parent category, description, display order
- **Parent-Child**: Categories can be nested (e.g., Sarees → Silk Sarees)
- **Activate/Deactivate**: Toggle category visibility
- **Product Count**: Shows how many products are in each category

**APIs:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/categories.php` | GET | List all categories with product count |
| `/api/admin/create-category.php` | POST | Create new category |
| `/api/admin/update-category.php` | POST | Update existing category |
| `/api/admin/toggle-category.php` | POST | Activate/deactivate category |

---

## Database Tables (Admin-Specific)

| Table | Purpose |
|-------|---------|
| `shop_payments` | Weekly payment tracking per shop |
| `admin_activity_log` | Audit trail of all admin actions |
| `platform_settings` | Configurable platform settings |

**Schema File:** `database/admin_schema.sql`

---

## Architecture

### Frontend
- **Framework:** Bootstrap 5.3.0 (mobile-first responsive)
- **Icons:** Font Awesome 6.4.0
- **CSS:** `assets/css/admin.css` (shared admin styles) + `assets/css/theme.css` (color variables)
- **JavaScript:** `admin-common.js` (shared utilities) + module-specific JS files

### Backend
- **Language:** Core PHP (three-tier architecture)
- **Database:** MySQL via PDO prepared statements
- **Auth:** Session-based with admin role check on every API
- **Logging:** All admin actions logged to `admin_activity_log`

### Security
- Every API checks `$_SESSION['user_type'] === 'admin'`
- PDO prepared statements prevent SQL injection
- Activity logging for audit trails
- Suspending a shop deactivates the user account

---

## File Structure

```
admin/
├── dashboard.html        - Main dashboard
├── shops.html            - Shop management
├── orders.html           - Order management
├── customers.html        - Customer listing
├── revenue.html          - Revenue analytics
├── payments.html         - Shop payment settlements
└── categories.html       - Category management

api/admin/
├── dashboard.php         - Dashboard statistics
├── shops.php             - List shops
├── add-shop.php          - Create shop + owner
├── update-shop-status.php - Update shop status
├── orders.php            - List orders
├── update-order-status.php - Update order status
├── customers.php         - List customers
├── revenue.php           - Revenue data
├── payments.php          - Payment records
├── generate-payments.php - Generate weekly payments
├── mark-paid.php         - Mark payment as paid
├── categories.php        - List categories
├── create-category.php   - Create category
├── update-category.php   - Update category
└── toggle-category.php   - Toggle category status

assets/js/
├── admin-common.js       - Shared admin utilities
├── admin-dashboard.js    - Dashboard logic
├── admin-shops.js        - Shop management logic
├── admin-orders.js       - Order management logic
├── admin-customers.js    - Customer listing logic
├── admin-revenue.js      - Revenue analytics logic
├── admin-payments.js     - Payment management logic
└── admin-categories.js   - Category management logic

assets/css/
└── admin.css             - All admin panel styles

database/
└── admin_schema.sql      - Payment & admin tables
```

---

## Setup Steps

1. Run `database/schema.sql` in phpMyAdmin
2. Run `database/products_orders_schema.sql`
3. Run `database/admin_schema.sql`
4. Create an admin user manually in the database:
   ```sql
   INSERT INTO users (full_name, email, phone, user_type, is_active, is_verified)
   VALUES ('Admin', 'admin@trencart.com', '9999999999', 'admin', 1, 1);

   INSERT INTO admin_profiles (user_id, admin_level, permissions)
   VALUES (LAST_INSERT_ID(), 'super_admin', '{"all": true}');
   ```
5. Login at `/pages/login.html` with the admin email → receive OTP → access dashboard

---

## Mobile Responsiveness

All admin pages are **100% mobile-friendly**:
- Collapsible sidebar with hamburger menu (auto-hides on mobile, always visible on desktop)
- Responsive stat cards (2 columns on mobile, 4 on desktop)
- Scrollable tables with hidden columns on mobile (marked with `hide-mobile` class)
- Touch-friendly buttons and modals
- Optimized padding and font sizes for small screens
