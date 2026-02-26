# Products Page (products.html) - Documentation

## Page Overview
The products page displays all products from a specific shop with comprehensive filtering and sorting options. Users can browse products, apply filters, and add items to their cart. This is the main product discovery and selection page.

## Structure

### 1. Navigation Bar
- Same structure as other pages
- Cart badge dynamically updates when items are added

### 2. Breadcrumb Navigation
- Home > Shops > [Shop Name]
- Shop name dynamically populated based on URL parameter

### 3. Shop Header
- **Shop Information Banner**:
  - Shop name (dynamic)
  - Shop description
  - Rating and product count
- **Background**: Light grey section with rounded corners

### 4. Main Content Layout (Two Columns)

#### Left Column - Filter Sidebar (25% width)
**Sticky sidebar** with the following filters:

1. **Price Range Filter**:
   - Range slider (₹0 to ₹10,000)
   - Real-time price display
   - HTML range input

2. **Category Filter** (Checkboxes):
   - Sarees
   - Dress Materials
   - Fabrics
   - Cotton
   - Silk
   - All checked by default

3. **Color Filter** (Checkboxes):
   - Red
   - Blue
   - Green
   - Black
   - White

4. **Sort By** (Dropdown):
   - Featured (default)
   - Price: Low to High
   - Price: High to Low
   - Newest First
   - Most Popular

5. **Reset Filters Button**:
   - Resets all filters to default
   - Shows success toast

#### Right Column - Products Grid (75% width)
- **Product Count Display**: Shows number of products found
- **Mobile Filter Toggle**: Button to show filters on small screens
- **Products Grid**: 3 columns on desktop, 2 on tablet, 1 on mobile

### 5. Product Cards (12 Sample Products)

Each product card includes:
- Product image (400x250px)
- Product name
- Description
- Price (₹ format)
- "Add to Cart" button

**Sample Products**:
1. Premium Silk Saree - ₹2,499
2. Pure Cotton Fabric - ₹899
3. Designer Dress Material - ₹1,799
4. Silk Blend Saree - ₹1,599
5. Ethnic Cotton Dress - ₹1,299
6. Georgette Fabric - ₹999
7. Banarasi Silk Saree - ₹3,999
8. Chiffon Material - ₹1,499
9. Printed Cotton Dress - ₹799
10. Tussar Silk Saree - ₹2,799
11. Premium Linen Fabric - ₹1,199
12. Handloom Cotton Saree - ₹1,899

### 6. Footer
- Standard footer layout

## Features

### Interactive Features

1. **Price Range Slider**:
   - Real-time value update
   - Event listener on input
   - Displays formatted price

2. **Add to Cart**:
   - Quick add function with animation
   - Login check before adding
   - Button state changes: Loading → Success → Reset
   - Cart badge updates automatically
   - Toast notification

3. **Filter Functionality** (Frontend):
   - Checkboxes for category and color
   - Sort dropdown
   - Reset filters button

4. **Dynamic Shop Name**:
   - Reads shop parameter from URL
   - Updates breadcrumb and shop header
   - JavaScript URL parameter parsing

5. **Sticky Sidebar**:
   - Filter sidebar stays visible while scrolling
   - Position: sticky with top: 100px

## Components Used

### Bootstrap 5 Components
- Container and Grid System
- Breadcrumb
- Cards for products
- Forms (checkboxes, range, select)
- Buttons
- Responsive columns

### Custom Components
- `.filter-sidebar`: Sticky filter panel
- `.product-card`: Enhanced product display
- `.shop-header`: Shop information banner
- `.quantity-controls`: Button group

### Font Awesome Icons
- fa-filter (filters icon)
- fa-shopping-cart (add to cart)
- fa-redo (reset filters)
- fa-spinner (loading state)
- fa-check (success state)

## JavaScript Functions

### Page-Specific Functions

1. **Price Range Update**:
```javascript
priceRange.addEventListener('input', function() {
    priceValue.textContent = '₹' + parseInt(this.value).toLocaleString();
});
```

2. **Reset Filters**:
```javascript
function resetFilters() {
    // Reset price range to max
    // Uncheck all checkboxes
    // Reset sort to 'featured'
    // Show success toast
}
```

3. **Shop Name Population**:
```javascript
const urlParams = new URLSearchParams(window.location.search);
const shopParam = urlParams.get('shop');
// Map shop slugs to names
// Update DOM elements
```

### Functions from cart.js

1. **quickAddToCart()**:
   - Takes product details as parameters
   - Shows loading state on button
   - Checks login status
   - Adds item to cart
   - Updates button to success state
   - Resets button after 2 seconds

2. **getCart()**: Retrieve cart from localStorage
3. **saveCart()**: Save cart to localStorage
4. **updateCartBadge()**: Update navbar badge

### Functions from auth.js
- **isUserLoggedIn()**: Check authentication status

## Color Theme Application
- **Black (30%)**: Navbar, footer, buttons
- **Grey (5%)**: Borders, secondary text, filter labels
- **White (65%)**: Page background, cards, filter sidebar

## Responsive Behavior

### Desktop (≥992px)
- Sidebar: 25% width, sticky
- Products: 75% width, 3 columns
- All filters visible

### Tablet (768px-991px)
- Sidebar: 30% width
- Products: 70% width, 2 columns
- Maintained filter visibility

### Mobile (<768px)
- Sidebar: Full width, not sticky, appears above products
- Products: Full width, 1 column
- Mobile filter toggle button appears
- Collapsible filter section

## Future Backend Integration

### Database Schema
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT,
    name VARCHAR(255),
    description TEXT,
    price DECIMAL(10, 2),
    category VARCHAR(100),
    color VARCHAR(50),
    size VARCHAR(50),
    image_url VARCHAR(255),
    stock INT,
    created_at TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id)
);
```

### PHP Backend Requirements

1. **Product Listing API**:
```php
GET /api/products?shop_id={id}&category={cat}&color={color}&min_price={min}&max_price={max}&sort={sort}
```

2. **Add to Cart API**:
```php
POST /api/cart/add
{
    "product_id": 123,
    "quantity": 1
}
```

3. **Filter Logic**:
   - Move filter logic to backend
   - SQL queries with WHERE clauses
   - Dynamic query building based on filters

### API Endpoints Needed
- `GET /api/products?shop_id={id}` - Get products by shop
- `GET /api/products/{id}` - Get single product
- `POST /api/cart/add` - Add product to cart
- `GET /api/products/filter` - Filter products (with query params)

## Advanced Features (Future Enhancements)

1. **Real-time Filtering**:
   - AJAX requests on filter change
   - Update products without page reload
   - Loading states

2. **Product Quick View**:
   - Modal with product details
   - Larger images
   - Full description
   - Size/color selector

3. **Wishlist**:
   - Heart icon to save favorites
   - Persistent wishlist storage

4. **Product Comparison**:
   - Compare multiple products side-by-side

5. **Reviews and Ratings**:
   - Show product reviews
   - Star ratings
   - Customer photos

## File Dependencies
- **CSS**: theme.css, style.css
- **JavaScript**: main.js, cart.js, auth.js
- **External**: Bootstrap 5, Font Awesome

## Testing Checklist
- [ ] Shop name updates based on URL parameter
- [ ] Price range slider updates value display
- [ ] All filters render correctly
- [ ] Reset filters button works
- [ ] Add to cart requires login
- [ ] Add to cart updates badge
- [ ] Product cards display properly
- [ ] Responsive layout works on all devices
- [ ] Sticky sidebar functions correctly
- [ ] Sort dropdown is functional (UI only)
- [ ] Mobile filter toggle works
- [ ] Toast notifications appear
- [ ] All product images load
- [ ] Button animations work (loading, success)
