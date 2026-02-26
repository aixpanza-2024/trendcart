# Checkout Page (checkout.html) - Documentation

## Page Overview
The checkout page is the final step before order placement. Users provide shipping information, select payment method (Cash on Delivery), review their order, and complete the purchase. This page requires user authentication.

## Structure

### 1. Navigation Bar
- Standard navbar
- Cart badge shows current item count
- User must be logged in to access

### 2. Breadcrumb Navigation
- Home > Shopping Cart > Checkout
- Provides navigation context

### 3. Page Header
- Title: "Checkout" with credit card icon
- Subtitle: "Complete your order"

### 4. Main Content Layout (Two Columns)

#### Left Column - Checkout Form (66% width)

**Section 1: Shipping Address**
- Personal Information:
  - First Name (required)
  - Last Name (required)
  - Email Address (required, pre-filled if logged in)
  - Phone Number (required, 10 digits)

- Address Information:
  - Street Address (textarea, required)
  - City (required)
  - State (dropdown select, required)
  - Pincode (required, 6 digits)

**Section 2: Payment Method**
- Cash on Delivery (COD):
  - Selected by default
  - Only active payment option
  - Description: "Pay when you receive your order"

- Other Methods (Disabled):
  - Credit/Debit Card (Coming Soon)
  - UPI (Coming Soon)
  - Styled with reduced opacity

#### Right Column - Order Summary (33% width)
- **Sticky Summary Card**:
  - Order items list (scrollable if many items)
  - Subtotal
  - Tax (GST 18%)
  - Shipping
  - Total Amount
  - "Place Order" button
  - "Back to Cart" link

### 5. Checkout Sections Styling
Each section has:
- Header with icon
- Bordered container
- Rounded corners
- Proper spacing

### 6. Footer
- Standard footer layout

## Features

### Interactive Features

1. **Authentication Check**:
   - Runs on page load
   - Uses `requireLogin()` function
   - Redirects to login if not authenticated
   - Stores checkout URL for return after login

2. **Auto-fill User Data**:
   - Retrieves logged-in user info
   - Pre-fills email and name fields
   - Uses `getCurrentUser()` from auth.js

3. **Load Order Data**:
   - Gets cart items from localStorage
   - Displays order items in summary
   - Calculates totals
   - Pre-populates order summary

4. **Form Validation**:
   - All fields required
   - Email format validation
   - Phone number: 10 digits
   - Pincode: 6 digits
   - State selection required
   - HTML5 validation + JavaScript

5. **State Dropdown**:
   - Pre-populated with Indian states:
     - Maharashtra (MH)
     - Delhi (DL)
     - Karnataka (KA)
     - Tamil Nadu (TN)
     - Uttar Pradesh (UP)
     - West Bengal (WB)
     - Gujarat (GJ)
     - Rajasthan (RJ)

6. **Payment Method Selection**:
   - Radio buttons for payment
   - Only COD enabled
   - Future methods shown as "Coming Soon"

7. **Place Order**:
   - Validates form completeness
   - Collects all form data
   - Creates order object
   - Stores in localStorage
   - Generates order ID
   - Clears cart
   - Shows confirmation alert
   - Redirects to home

## JavaScript Functions

### Page-Specific Functions

#### 1. loadCheckoutData()
```javascript
- Calls getCartForCheckout()
- Renders order items in summary
- Updates totals display
- Pre-fills user information
- Formats currency
```

#### 2. placeOrder()
```javascript
- Validates form
- Collects form data
- Creates order object
- Generates order ID (ORD + timestamp)
- Saves to localStorage orders array
- Clears cart using clearCart()
- Shows confirmation alert
- Redirects to home
```

### Functions from cart.js
- **getCartForCheckout()**: Returns `{ items, totals }`
- **calculateCartTotals()**: Calculates subtotal, tax, shipping, total
- **clearCart()**: Removes all items from cart
- **formatCurrency()**: Formats numbers as ₹

### Functions from auth.js
- **requireLogin()**: Checks authentication, redirects if needed
- **getCurrentUser()**: Gets logged-in user info
- **isUserLoggedIn()**: Returns boolean

### Functions from main.js
- **showToast()**: Display notifications
- **formatCurrency()**: Currency formatting

## Components Used

### Bootstrap 5 Components
- Container and Grid System
- Breadcrumb
- Forms (inputs, select, textarea)
- Radio buttons
- Buttons
- Sticky sidebar

### Custom Components
- `.checkout-section`: Bordered section containers
- `.order-item`: Summary item display
- `.payment-option`: Payment method cards
- `.cart-summary`: Order summary (reused from cart page)

### Font Awesome Icons
- fa-credit-card (page header)
- fa-map-marker-alt (shipping section)
- fa-wallet (payment section)
- fa-receipt (order summary)
- fa-money-bill-wave (COD)
- fa-credit-card (card payment)
- fa-mobile-alt (UPI)
- fa-check-circle (place order)
- fa-arrow-left (back to cart)

## Color Theme Application
- **Black (30%)**: Navbar, footer, buttons
- **Grey (5%)**: Form borders, disabled payment options
- **White (65%)**: Page background, form sections, cards

## Data Structures

### Order Object
```javascript
{
    orderId: "ORD1707890123456",
    firstName: "John",
    lastName: "Doe",
    email: "john@example.com",
    phone: "9876543210",
    address: "123 Main Street",
    city: "Mumbai",
    state: "MH",
    pincode: "400001",
    paymentMethod: "cod",
    items: [
        {
            id: "prod1",
            name: "Premium Silk Saree",
            price: 2499,
            quantity: 1,
            shop: "Elegant Fabrics"
        }
    ],
    totals: {
        subtotal: 2499,
        tax: 449.82,
        shipping: 0,
        total: 2948.82,
        itemCount: 1
    },
    orderDate: "2024-02-14T10:30:00.000Z"
}
```

### Orders Array (localStorage)
```javascript
localStorage.setItem('orders', JSON.stringify([order1, order2, ...]));
```

## Responsive Behavior

### Desktop (≥992px)
- Two-column layout (8-4 split)
- Sticky order summary
- Full form layout

### Tablet (768px-991px)
- Two-column maintained
- Summary stays sticky
- Compressed spacing

### Mobile (<768px)
- Single column layout
- Form fields stack vertically
- Summary below form (not sticky)
- Full-width buttons
- Larger touch targets

## Future Backend Integration

### Database Schema
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL,
    shipping_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    order_status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE shipping_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

### PHP Backend Requirements

#### 1. Place Order API
```php
POST /api/orders/place
{
    "shipping": {
        "firstName": "John",
        "lastName": "Doe",
        "email": "john@example.com",
        "phone": "9876543210",
        "address": "123 Main St",
        "city": "Mumbai",
        "state": "MH",
        "pincode": "400001"
    },
    "paymentMethod": "cod"
}
// Backend retrieves cart from session
// Creates order record
// Creates order items
// Stores shipping address
// Clears cart
// Sends confirmation email
// Returns order ID and details
```

#### 2. Order Processing
- Transaction management (all-or-nothing)
- Inventory reduction
- Email notifications
- Order number generation
- Payment processing (for non-COD)

#### 3. Payment Gateway Integration
When adding card/UPI:
- Razorpay, Paytm, or Stripe integration
- Payment verification
- Callback handling
- Refund processing

### API Endpoints Needed
- `POST /api/orders/place` - Create new order
- `GET /api/orders/{id}` - Get order details
- `GET /api/orders/user/{userId}` - Get user's orders
- `PUT /api/orders/{id}/status` - Update order status
- `POST /api/orders/{id}/cancel` - Cancel order

## Order Confirmation

### Current Implementation
- JavaScript alert with order details
- Includes:
  - Order ID
  - Total amount
  - Delivery time
  - Payment method
  - Thank you message

### Future Enhancement
- Dedicated order confirmation page
- Email confirmation
- SMS notification
- Order tracking link
- Print invoice option

## Validation Rules

### Field Validations
1. **First Name**: Required, non-empty
2. **Last Name**: Required, non-empty
3. **Email**: Required, valid email format
4. **Phone**: Required, exactly 10 digits, starts with 6-9
5. **Address**: Required, minimum 10 characters
6. **City**: Required, non-empty
7. **State**: Required, must select from dropdown
8. **Pincode**: Required, exactly 6 digits

### Business Rules
1. Cart must not be empty
2. User must be logged in
3. All form fields must be valid
4. Payment method must be selected (COD default)

## Error Handling

1. **Not Logged In**:
   - Redirect to login page
   - Store checkout URL for return
   - Show "Please login" toast

2. **Empty Cart**:
   - Alert user
   - Redirect to shopping pages

3. **Invalid Form Data**:
   - HTML5 validation messages
   - Prevent form submission
   - Highlight invalid fields

4. **Order Placement Failure** (future):
   - Show error message
   - Preserve form data
   - Suggest retry or contact support

## File Dependencies
- **CSS**: theme.css, style.css
- **JavaScript**: main.js, cart.js, auth.js
- **External**: Bootstrap 5, Font Awesome

## Testing Checklist
- [ ] Page requires login to access
- [ ] Redirects to login if not authenticated
- [ ] User data pre-fills correctly
- [ ] Order items display in summary
- [ ] Totals calculate correctly
- [ ] All form fields validate
- [ ] Email validation works
- [ ] Phone accepts 10 digits only
- [ ] Pincode accepts 6 digits only
- [ ] State dropdown works
- [ ] COD is selected by default
- [ ] Other payment methods are disabled
- [ ] Place order validates form first
- [ ] Order creates with unique ID
- [ ] Order saves to localStorage
- [ ] Cart clears after order
- [ ] Confirmation alert shows
- [ ] Redirects to home after order
- [ ] Toast notifications appear
- [ ] Responsive layout on mobile
- [ ] Summary sticky on desktop
- [ ] Back to cart link works

## Enhancement Opportunities

### Immediate Improvements
1. **Saved Addresses**:
   - Store user addresses
   - Quick select from saved addresses
   - Edit/delete addresses

2. **Order Notes**:
   - Text area for special instructions
   - Gift message option

3. **Delivery Time Selection**:
   - Choose preferred delivery slot
   - Morning/afternoon/evening

### Future Features
1. **Multiple Payment Methods**:
   - Credit/debit cards
   - UPI
   - Net banking
   - Wallets

2. **Order Review Page**:
   - Detailed confirmation page
   - Download invoice
   - Track order link

3. **Gift Options**:
   - Gift wrap selection
   - Gift message
   - Hide prices

4. **Express Checkout**:
   - One-click ordering
   - Saved preferences
   - Quick buy option
