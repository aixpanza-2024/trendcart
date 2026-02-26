# Shopping Cart Page (cart.html) - Documentation

## Page Overview
The shopping cart page displays all items a user has added to their cart. Users can review items, update quantities, remove products, and proceed to checkout. This page provides a summary of the order before purchase.

## Structure

### 1. Navigation Bar
- Cart icon highlighted as active
- Cart badge shows current item count

### 2. Breadcrumb Navigation
- Home > Shopping Cart

### 3. Page Header
- Title: "Shopping Cart" with cart icon
- Subtitle: "Review your items before checkout"

### 4. Main Content Layout (Two Columns)

#### Left Column - Cart Items (66% width)
- **Empty Cart State**:
  - Cart icon
  - "Your cart is empty" message
  - "Continue Shopping" button

- **Cart Items** (when not empty):
  - Each item displayed as a card
  - Items dynamically loaded from localStorage
  - Scrollable list if many items

#### Right Column - Order Summary (33% width)
- **Sticky Summary Card**:
  - Subtotal
  - Tax (GST 18%)
  - Shipping (FREE above ₹1000, else ₹50)
  - Total amount
  - "Proceed to Checkout" button
  - "Continue Shopping" link

- **Shipping Information Box**:
  - Free shipping threshold
  - Delivery time (3-5 days)
  - Cash on delivery availability

### 5. Cart Item Structure
Each cart item displays:
- Product image (100x100px thumbnail)
- Product name
- Shop name (with store icon)
- Price per unit
- Quantity controls (+/- buttons with count)
- Item subtotal
- Remove button (trash icon)

### 6. Footer
- Standard footer layout

## Features

### Interactive Features

1. **Dynamic Cart Loading**:
   - Reads cart from localStorage on page load
   - Renders items using JavaScript
   - Shows empty state if no items

2. **Update Quantity**:
   - Plus button: Increases quantity
   - Minus button: Decreases quantity
   - Updates subtotal in real-time
   - Removes item if quantity reaches 0
   - Updates cart summary automatically

3. **Remove Item**:
   - Trash icon button
   - Removes item from cart
   - Updates display immediately
   - Shows toast notification

4. **Real-time Calculations**:
   - Subtotal: Sum of all items
   - Tax: 18% GST on subtotal
   - Shipping: ₹50 or FREE (if subtotal > ₹1000)
   - Total: Subtotal + Tax + Shipping

5. **Proceed to Checkout**:
   - Validates cart is not empty
   - Checks user login status
   - Redirects to login if not authenticated
   - Redirects to checkout if authenticated

6. **Continue Shopping**:
   - Returns to homepage
   - Cart data persists in localStorage

## Components Used

### Bootstrap 5 Components
- Container and Grid System
- Breadcrumb
- Buttons (primary, outline)
- Custom cart item cards

### Custom Components
- `.cart-item`: Individual item card
- `.cart-summary`: Order summary panel
- `.quantity-controls`: +/- button group

### Font Awesome Icons
- fa-shopping-cart (page header, empty state)
- fa-store (shop name)
- fa-minus, fa-plus (quantity controls)
- fa-trash (remove item)
- fa-receipt (order summary)
- fa-arrow-left (continue shopping)
- fa-truck (shipping info)

## JavaScript Functions

### Functions from cart.js

1. **renderCartItems()**:
   - Called on page load
   - Gets cart from localStorage
   - Generates HTML for each item
   - Handles empty cart state
   - Updates cart summary

2. **updateQuantity(productId, change)**:
   - Increases or decreases item quantity
   - Updates cart in localStorage
   - Re-renders cart items
   - Updates badge and summary
   - Removes item if quantity <= 0

3. **removeFromCart(productId)**:
   - Removes item from cart array
   - Updates localStorage
   - Re-renders cart
   - Shows toast notification

4. **updateCartSummary()**:
   - Calculates all totals
   - Updates summary display
   - Formats currency
   - Handles shipping logic
   - Disables/enables checkout button

5. **calculateCartTotals()**:
   - Returns object with:
     - subtotal
     - tax (18%)
     - shipping (₹0 or ₹50)
     - total
     - itemCount

6. **proceedToCheckout()**:
   - Validates cart not empty
   - Checks user authentication
   - Redirects to login or checkout
   - Shows toast notifications

### Functions from main.js
- **formatCurrency()**: Format numbers as ₹ currency
- **showToast()**: Display notifications
- **updateCartBadge()**: Update navbar badge

### Functions from auth.js
- **isUserLoggedIn()**: Check authentication status

## Color Theme Application
- **Black (30%)**: Navbar, footer, buttons
- **Grey (5%)**: Secondary text, borders
- **White (65%)**: Page background, cart items, summary card

## Responsive Behavior

### Desktop (≥992px)
- Two-column layout (8-4 split)
- Sticky summary sidebar
- Full cart item details

### Tablet (768px-991px)
- Two-column layout maintained
- Summary remains sticky
- Slightly compressed spacing

### Mobile (<768px)
- Single column layout
- Cart items stack vertically
- Summary below cart items (not sticky)
- Full-width buttons
- Reduced image sizes

## Data Structure

### Cart Item Object (localStorage)
```javascript
{
    id: "prod1",
    name: "Premium Silk Saree",
    price: 2499,
    image: "path/to/image.jpg",
    shop: "Elegant Fabrics",
    quantity: 1
}
```

### Cart Array
```javascript
[
    { id: "prod1", name: "...", price: 2499, ... },
    { id: "prod2", name: "...", price: 899, ... }
]
```

## Future Backend Integration

### Database Schema
```sql
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    quantity INT,
    added_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
```

### PHP Backend Requirements

1. **Get Cart API**:
```php
GET /api/cart
// Returns user's cart with product details
```

2. **Update Cart API**:
```php
PUT /api/cart/{item_id}
{
    "quantity": 2
}
```

3. **Remove from Cart API**:
```php
DELETE /api/cart/{item_id}
```

4. **Session-based Cart**:
   - Associate cart with user session
   - Persist across browser sessions
   - Merge cart on login

### API Endpoints Needed
- `GET /api/cart` - Get user's cart
- `POST /api/cart/add` - Add item to cart
- `PUT /api/cart/update/{id}` - Update quantity
- `DELETE /api/cart/remove/{id}` - Remove item
- `DELETE /api/cart/clear` - Clear entire cart
- `GET /api/cart/count` - Get item count

## Advanced Features (Future Enhancements)

1. **Save for Later**:
   - Move items to wishlist
   - Separate saved items section

2. **Promo Codes**:
   - Input field for discount codes
   - Apply and validate coupons
   - Show discount in summary

3. **Estimated Delivery**:
   - Show expected delivery date
   - Based on location

4. **Product Recommendations**:
   - "You may also like" section
   - Based on cart items

5. **Cart Persistence**:
   - Save cart to database
   - Sync across devices
   - Email cart to user

6. **Stock Availability**:
   - Check stock before checkout
   - Show "Only X left" warnings
   - Handle out-of-stock items

## Error Handling

1. **Empty Cart**:
   - Shows friendly empty state
   - Provides navigation back to shopping

2. **Login Required**:
   - Redirects to login for checkout
   - Stores return URL for after login

3. **Invalid Quantities**:
   - Prevents negative quantities
   - Removes item if quantity becomes 0

## File Dependencies
- **CSS**: theme.css, style.css
- **JavaScript**: main.js, cart.js, auth.js
- **External**: Bootstrap 5, Font Awesome

## Testing Checklist
- [ ] Empty cart displays correct message
- [ ] Cart items load from localStorage
- [ ] Quantity controls update item count
- [ ] Quantity controls update subtotal
- [ ] Minus button removes item at quantity 0
- [ ] Remove button deletes item
- [ ] Cart summary calculates correctly
- [ ] Tax (18%) is calculated accurately
- [ ] Free shipping applies above ₹1000
- [ ] Shipping fee applies below ₹1000
- [ ] Cart badge updates with item count
- [ ] Proceed to checkout validates login
- [ ] Toast notifications appear
- [ ] Continue shopping link works
- [ ] Responsive layout on mobile
- [ ] Summary sticky on desktop
- [ ] All calculations use correct currency format
