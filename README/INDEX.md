# Homepage (index.html) - Documentation

## Page Overview
The homepage serves as the main landing page for TrenCart, showcasing the brand, featured shops, and providing easy navigation to all sections of the website. It creates the first impression and guides users to explore various dress material shops.

## Structure

### 1. Navigation Bar
- **Brand Logo**: TrenCart logo with shopping bag icon
- **Navigation Links**:
  - Home (active)
  - Shops
  - Shopping Cart (with badge showing item count)
  - Login/Register buttons
- **Features**:
  - Sticky navigation that stays at top during scroll
  - Responsive collapsible menu for mobile devices
  - Active link highlighting
  - Cart badge shows number of items

### 2. Hero Section
- **Elements**:
  - Large heading: "Welcome to TrenCart"
  - Descriptive tagline
  - Call-to-action button: "Explore Shops"
- **Design**:
  - Full-width section with gradient background (black to grey)
  - Centered content with fade-in animation
  - Minimum height of 70vh for visual impact

### 3. Featured Shops Section
- **Layout**: Responsive grid (3 columns on desktop, 2 on tablet, 1 on mobile)
- **Shop Cards** (6 featured shops):
  1. Elegant Fabrics
  2. Royal Sarees
  3. Fashion Hub
  4. Traditional Touch
  5. Designer Collection
  6. Cotton Corner
- **Card Components**:
  - Shop image (placeholder)
  - Shop name
  - Brief description
  - "View Products" button linking to products page
- **Effects**: Hover animation (lift and shadow)

### 4. Features Section
- **3-Column Layout** displaying:
  - Free Shipping (orders above ₹1000)
  - Secure Payment (cash on delivery)
  - 24/7 Support
- **Icons**: Font Awesome icons for visual representation

### 5. Footer
- **3-Column Layout**:
  - Column 1: Brand info and description
  - Column 2: Quick links (Home, Shops, Cart, Login)
  - Column 3: Contact information
- **Footer Bottom**: Copyright notice

## Features

### Interactive Features
1. **Smooth Scroll**: Smooth scrolling for anchor links
2. **Navbar Scroll Effect**: Navbar becomes smaller when scrolling
3. **Card Animations**: Fade-in animation on scroll using Intersection Observer
4. **Hover Effects**: Cards lift and shadow on hover
5. **Cart Badge**: Dynamic cart item count

### Responsive Behavior
- **Desktop (≥992px)**: 3-column shop grid
- **Tablet (768px-991px)**: 2-column shop grid
- **Mobile (<768px)**: Single column layout, collapsed navbar menu

## Components Used

### Bootstrap 5 Components
- **Container**: For content width management
- **Grid System**: row/col-* for responsive layouts
- **Navbar**: navbar-expand-lg, navbar-dark
- **Cards**: For shop displays
- **Buttons**: btn-primary, btn-outline-primary
- **Badge**: For cart item count

### Custom Components
- `.hero-section`: Custom hero banner
- `.shop-card`: Enhanced Bootstrap card with custom styling
- `.section-title`: Styled section headers
- `.cart-badge`: Custom badge for cart count

### Font Awesome Icons
- fa-shopping-bag (brand logo)
- fa-shopping-cart (cart icon)
- fa-user (login icon)
- fa-arrow-right (CTA buttons)
- fa-truck, fa-shield-alt, fa-headset (features section)

## JavaScript Functions

### main.js Functions Used
1. **initNavbar()**:
   - Adds scroll effect to navbar
   - Highlights active navigation link
   - Handles mobile menu collapse

2. **initAnimations()**:
   - Sets up Intersection Observer for card animations
   - Applies fade-in effect when cards enter viewport

3. **updateCartBadge()**:
   - Reads cart from localStorage
   - Updates badge count
   - Hides badge when cart is empty

4. **checkAuthStatus()**:
   - Checks if user is logged in
   - Updates navbar to show user menu if logged in

### cart.js Functions Used
- **getCart()**: Retrieves cart data from localStorage

### Event Listeners
- `DOMContentLoaded`: Initializes all components
- `scroll`: Navbar scroll effect
- `click`: Navigation and CTA button clicks

## Color Theme Application
- **Black (30%)**: Navbar, footer background (#1a1a1a)
- **Grey (5%)**: Text accents, icons (#6c757d)
- **White (65%)**: Main background, card backgrounds (#ffffff, #f8f9fa)

## Responsive Behavior Details

### Desktop (≥1200px)
- Full 3-column shop grid
- Expanded navbar with all links visible
- Hero section at full 70vh height

### Tablet (768px-991px)
- 2-column shop grid
- Navbar hamburger menu
- Adjusted hero content sizing

### Mobile (<768px)
- Single column shop grid
- Fully collapsed navbar
- Hero section reduced to 50vh
- Smaller font sizes for headings
- Stacked features section

## Future Backend Integration

### Data Points for PHP Backend
1. **Shops Data**:
   - Shop ID, name, description, image
   - Products count, ratings
   - Currently using static HTML

2. **User Authentication**:
   - Login status currently in localStorage
   - Will be replaced with PHP sessions

3. **Cart Data**:
   - Currently stored in localStorage
   - Will be moved to database with user association

### API Endpoints Needed
- `GET /api/shops/featured` - Fetch featured shops
- `GET /api/user/auth-status` - Check user login status
- `GET /api/cart/count` - Get cart item count

## File Dependencies
- **CSS**:
  - theme.css (color variables)
  - style.css (custom styles)
  - Bootstrap 5.3.0 CDN
  - Font Awesome 6.4.0 CDN

- **JavaScript**:
  - main.js (core functionality)
  - cart.js (cart management)
  - auth.js (authentication)
  - Bootstrap 5.3.0 JS Bundle CDN

## Testing Checklist
- [ ] All navigation links work correctly
- [ ] Hero CTA button redirects to shops page
- [ ] Shop cards display properly in all screen sizes
- [ ] Hover effects work on shop cards
- [ ] Navbar scroll effect activates after 50px scroll
- [ ] Cart badge shows correct item count
- [ ] Mobile menu expands and collapses properly
- [ ] All icons load correctly
- [ ] Page loads within 2 seconds
- [ ] Responsive design works on all breakpoints
