# Shops Listing Page (shops.html) - Documentation

## Page Overview
The shops listing page displays all available dress material shops on TrenCart. Users can browse through the complete collection of shops and click on any shop to view its products. This page serves as a directory of all vendors.

## Structure

### 1. Navigation Bar
- Same as homepage
- "Shops" link highlighted as active

### 2. Breadcrumb Navigation
- Home > All Shops
- Helps users understand their location in the site hierarchy

### 3. Page Header
- Section title: "All Shops"
- Descriptive subtitle

### 4. Shops Grid
- **9 Shop Cards** displayed in responsive grid
- **3 columns** on desktop, **2 columns** on tablet, **1 column** on mobile

### 5. Shop Cards
Each card includes:
- Shop image (400x250px placeholder)
- Shop name
- Description
- Rating (stars) and review count
- Product count
- "View Products" button

### Shops Listed:
1. Elegant Fabrics (4.8★, 250 reviews, 150+ products)
2. Royal Sarees (4.9★, 320 reviews, 200+ products)
3. Fashion Hub (4.7★, 180 reviews, 175+ products)
4. Traditional Touch (4.8★, 210 reviews, 130+ products)
5. Designer Collection (4.9★, 290 reviews, 160+ products)
6. Cotton Corner (4.6★, 165 reviews, 140+ products)
7. Silk Paradise (4.9★, 310 reviews, 180+ products)
8. Modern Trends (4.7★, 195 reviews, 155+ products)
9. Ethnic Elegance (4.8★, 235 reviews, 145+ products)

### 6. Footer
- Same as homepage

## Features

### Interactive Features
1. **Card Hover Effects**: Cards lift and shadow on hover
2. **Animated Entry**: Cards fade in on page load
3. **Click to View**: Each card links to products page with shop parameter
4. **Responsive Grid**: Auto-adjusts columns based on screen size

### URL Parameters
- Shop cards link to: `products.html?shop={shop-slug}`
- Example: `products.html?shop=elegant-fabrics`

## Components Used

### Bootstrap 5 Components
- Container and Grid System (row, col-*)
- Breadcrumb navigation
- Cards with card-body
- Buttons (btn-primary)

### Custom Components
- `.shop-card`: Enhanced card styling
- `.section-title`: Page header styling
- `.breadcrumb`: Custom breadcrumb styling

### Font Awesome Icons
- fa-star (ratings)
- fa-box (product count)
- fa-arrow-right (CTA buttons)

## JavaScript Functions

### Functions from main.js
1. **initNavbar()**: Navbar functionality
2. **initAnimations()**: Card animations on load
3. **updateCartBadge()**: Cart count display
4. **checkAuthStatus()**: User authentication status

### No Page-Specific JavaScript
- This page uses only global JavaScript functions
- All shop data is currently static HTML

## Color Theme Application
- **Black (30%)**: Navbar, footer
- **Grey (5%)**: Text accents for ratings and product count
- **White (65%)**: Main background, card backgrounds

## Responsive Behavior

### Desktop (≥992px)
- 3-column grid layout
- Full shop card details visible
- Hover effects active

### Tablet (768px-991px)
- 2-column grid layout
- Maintained card details
- Touch-friendly spacing

### Mobile (<768px)
- Single column layout
- Full-width cards
- Stacked information
- Larger tap targets for buttons

## Future Backend Integration

### Database Schema
```sql
CREATE TABLE shops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    image_url VARCHAR(255),
    rating DECIMAL(2,1),
    review_count INT,
    product_count INT,
    created_at TIMESTAMP
);
```

### PHP Backend Requirements
1. **Shop Listing API**:
   - `GET /api/shops` - Fetch all shops
   - Returns JSON array of shop objects
   - Includes pagination support

2. **Dynamic Content**:
   - Replace static HTML with PHP loop
   - Fetch shop data from database
   - Generate cards dynamically

### Example PHP Code
```php
<?php
$shops = $db->query("SELECT * FROM shops ORDER BY rating DESC");
foreach($shops as $shop) {
    // Generate shop card HTML
}
?>
```

### API Endpoints Needed
- `GET /api/shops` - Get all shops
- `GET /api/shops/{id}` - Get single shop details
- `GET /api/shops/search?q={query}` - Search shops

## Enhancement Opportunities

### Future Features
1. **Search Functionality**:
   - Search bar to filter shops by name
   - Real-time search results

2. **Filter Options**:
   - Filter by rating
   - Filter by product category
   - Filter by location

3. **Sort Options**:
   - Sort by rating
   - Sort by popularity
   - Sort by newest

4. **Pagination**:
   - Load more button
   - Infinite scroll
   - Page numbers

5. **Shop Categories**:
   - Traditional
   - Modern
   - Designer
   - Budget-friendly

## File Dependencies
- **CSS**: theme.css, style.css, Bootstrap 5 CDN
- **JavaScript**: main.js, cart.js, auth.js, Bootstrap JS CDN
- **Parent Directory**: Links use `../` for assets and index.html

## Testing Checklist
- [ ] All shop cards display correctly
- [ ] Ratings and product counts visible
- [ ] "View Products" buttons navigate correctly with shop parameter
- [ ] Breadcrumb navigation works
- [ ] Responsive grid adjusts properly
- [ ] Hover effects work on desktop
- [ ] Cards animate on page load
- [ ] All placeholder images load
- [ ] Mobile layout is user-friendly
- [ ] Footer links work correctly
