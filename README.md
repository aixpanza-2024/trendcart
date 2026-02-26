# TrenCart — Development Log

## Project Stack
- **Backend**: PHP (Three-tier architecture — Controller → Model → DB)
- **Database**: MySQL via PDO (XAMPP)
- **Frontend**: Bootstrap 5 + Vanilla JS
- **Auth**: OTP-based (email) with PHP sessions + localStorage

---

## Database Setup
1. Import `database/trencart_complete_schema.sql` in phpMyAdmin
2. Import `database/category_requests.sql` (run after main schema)
3. Default admin: `admin@trencart.com`

---

## Database Setup
3. Import `database/rating_migration.sql` (run after main schema — adds unique constraint + shop rating trigger)

## Update History

### Session 22–23 — UI Modernisation, Payment Generation & Registration Cleanup
- **Modified**: `assets/css/style.css` — cart items use flex-column card layout (`.cart-item-top` + `.cart-item-bottom`); `.cart-summary` sticky removed (wrapped with shipping info in single sticky div); `.filter-strip` dropdown styles; `.features-strip` dark trust bar; removed `margin-top:4rem` from `.footer`
- **Modified**: `pages/products.html` — removed sidebar; added horizontal `.filter-strip` with search, sort, category dropdown, price range chip
- **Modified**: `pages/cart.html` — `#cartItems` is now `row g-3`; cart summary + shipping info share a single `position:sticky` wrapper
- **Modified**: `assets/js/cart.js` — `renderCartItems()` wraps each item in `col-12 col-md-6` Bootstrap columns; new `.cart-item-top`/`.cart-item-bottom` inner HTML
- **Modified**: `index.html` — features section replaced with dark `.features-strip` trust bar (Free Shipping, Secure Payment, 24/7 Support, Kerala Delivery)
- **Modified**: `api/admin/generate-payments.php` — rewrote to accept `period` (daily/weekly) from POST JSON; only counts `item_status = 'delivered'` items; duplicate check includes `period_type`
- **Modified**: `api/shop/finance.php` — summary query now filters `oi.item_status = 'delivered'` only
- **Modified**: `admin/payments.html` — single "Generate Weekly" button replaced with dropdown (Daily / Weekly options)
- **Modified**: `assets/js/admin-payments.js` — unified `generatePayments(period)` function; `generateWeeklyPayments()` kept as alias
- **Modified**: `assets/js/auth.js` — logout no longer clears localStorage cart
- **Modified**: `pages/register.html` — removed "Register As" radio selector, shop name field, shop photo upload, and related CSS (shop onboarding is admin-only)
- **Modified**: `assets/js/register-otp.js` — removed all shop-owner logic: radio listeners, `shopPhotoBase64`, `handlePhotoSelect`, `resizeImageToMaxOneMB`; `user_type` hardcoded to `'customer'`

### Session 19 — Mobile Navbar Rework + Shop Order Notifications
- **Modified**: `assets/css/style.css` — added full mobile navbar block (≤991px): hides `.navbar-toggler` + `.navbar-collapse`; `.mob-top-icons` (search icon + cart); `.mob-search-bar` (slide-down); `.mob-bottom-bar` + `.mob-tab` (5-tab bottom nav); `.mob-cart-dot` (red badge on cart tab)
- **Modified**: `assets/js/main.js` — new `injectMobileNav()` called on DOMContentLoaded (skips shop/admin pages); injects search toggle + cart icon into navbar top, slide-down search bar, and 5-tab bottom bar (Home/Shops/Products/Cart/Account); `updateCartBadge()` now syncs all `.cart-badge` + `#mobBottomCartDot`; new `mobileProductSearch()` function
- **Modified**: `assets/js/shop-orders.js` — `startOrderNotifications()` polls `orders.php?status=pending` every 30 s; detects new order IDs vs `_knownOrderIds` Set; plays 3-tone Web Audio API ding (`playOrderSound()`) + shows toast + reloads orders table
- **Modified**: `assets/js/shop-dashboard.js` — same notification system (`pollDashNewOrders()`, `playDashOrderSound()`); on new order also refreshes dashboard stats via `loadShopDashboard()`

### Session 18 — Product Rating System
- **New file**: `database/rating_migration.sql` — unique constraint on `(product_id, user_id, order_id)` in `product_reviews`; updated `after_review_insert` trigger to also recalculate `shops.rating_average` and `shops.total_ratings` from all its products' reviews
- **New file**: `api/customer/submit-review.php` — POST API; validates item belongs to customer, item_status must be 'delivered', prevents duplicate reviews, auto-approves + marks verified_purchase
- **Modified**: `api/customer/my-orders.php` — item detail query now LEFT JOINs `product_reviews` to return `has_reviewed` (0/1) and `review_rating` per item
- **Modified**: `api/customer/product-detail.php` — also fetches latest 10 approved reviews (rating, review_text, reviewer_name, created_at); removed redundant `?>`
- **Modified**: `pages/orders.html` — added Rating Modal (star selector + optional text + submit button)
- **Modified**: `assets/js/customer-orders.js` — delivered items show "Rate" button (or existing star rating if already reviewed); `openRatingModal()`, interactive star hover/click, `submitRating()` with auto-refresh of detail modal; `buildOrderStars()` helper
- **Modified**: `pages/product-detail.html` — added Customer Reviews section above "More from this Shop"
- **Modified**: `assets/js/product-detail.js` — `renderReviews()` renders reviewer avatar, stars, date, review text, "Verified Purchase" badge

### Session 1 — Initial Build
- Three-tier PHP architecture set up (config, models, controllers, API endpoints)
- OTP-based auth for customer, shop, admin, delivery
- Admin dashboard with shop management, category management, user management

### Session 2 — Bug Fixes & Shop Panel
- **Fixed**: All 15 admin PHP files had `$_SESSION(['user_type'] ?? '')` (wrong syntax) → corrected to `($_SESSION['user_type'] ?? '')`
- **Fixed**: `Unknown column 'shop_description'` in `api/admin/add-shop.php` → column is `description` in `shop_profiles`
- **Created**: `shop/orders.html` + `api/shop/orders.php` — shop order management
- **Created**: `shop/profile.html` + `api/shop/profile.php` — shop profile edit
- **Fixed**: `Unknown column 'oi.unit_price'` in shop orders → column is `price`
- **Fixed**: `showToast` recursion bug in `shop-add-product.js`

### Session 3 — Cascading Categories & Category Requests
- **Updated**: `shop/add-product.html` — cascading parent/subcategory dropdowns (two selects)
- **Updated**: `admin/categories.html` — added "+" button per parent row to add subcategory directly; Category Type radio toggle in modal
- **Created**: Category Request system:
  - `database/category_requests.sql` — new table
  - `api/shop/request-category.php` — shop submits request
  - `api/admin/category-requests.php` — admin views pending
  - `api/admin/approve-category-request.php` — admin approves/rejects (auto-creates category)
  - Yellow pending-requests banner on `admin/categories.html`
  - "Request from Admin" modal on `shop/add-product.html`

### Session 4 — OTP Fix & Customer Pages Dynamic
- **Fixed OTP login bug** in `api/utils/OTPManager.php`:
  - `bindParam` → `bindValue` with `PDO::PARAM_INT` for `user_id` (was causing NULL user_id in DB)
  - `rowCount()` → `fetch()` for SELECT (rowCount unreliable for SELECT in some PDO drivers)
- **Created**: `api/customer/` — public API endpoints (no auth required):
  - `shops.php` — shop listing with optional search
  - `products.php` — product listing with shop/category/price/sort filters
  - `categories.php` — categories list for filter sidebar
- **Made dynamic** without changing design:
  - `index.html` — featured shops from DB via `assets/js/home.js`
  - `pages/shops.html` — all shops from DB + search bar via `assets/js/shops.js`
  - `pages/products.html` — products from DB + dynamic category filter via `assets/js/products.js`
  - Cart page (`pages/cart.html`) was already dynamic (localStorage)
- **Fixed**: `assets/js/cart.js` login redirect broken from `/pages/` subfolder — now detects path level

### Session 5 — Customer Auth UX & Customer Pages
- **Fixed**: Register button now hides from navbar when customer is logged in (`checkAuthStatus` in `main.js`)
- **Fixed**: Login→User dropdown was nesting `<li>` inside `<li>` (invalid HTML) — now uses `outerHTML`
- **Updated**: `assets/js/home.js` — shop cards on homepage now show real rating and product count from DB
- **Created**: Customer-authenticated pages:
  - `pages/orders.html` + `api/customer/my-orders.php` + `assets/js/customer-orders.js`
  - `pages/profile.html` + `api/customer/my-profile.php` + `assets/js/customer-profile.js`

### Session 6 — Product Card Improvements
- **Fixed**: Product images from `uploads/products/` now display correctly (path was `/uploads/...` absolute; converted to `../uploads/...` relative from `pages/`)
- **Improved**: Product card height reduced (image: 250px → 180px)
- **Improved**: Discount % badge moved from card body to overlay on top-left of product image (`position: absolute` inside `.product-img-wrap`)
- **Improved**: Card body more compact (smaller title, tighter spacing, `btn-sm`)
- **Created**: This README file

### Session 7 — Filter Fixes (Category, Price Range, Sort By)
- **Fixed**: Category filter on `pages/products.html` was not showing products in subcategories when a parent category was checked
  - Root cause 1: `applyFilters()` in `assets/js/products.js` only matched exact `p.category_id` against checked parent IDs — subcategory products were excluded
  - Root cause 2: `p.category_id` was missing from the SQL `SELECT` in `api/customer/products.php` — JS always got `undefined`
  - Fix: Added `p.category_id` to SELECT; added `allCategories` module variable; `applyFilters()` now builds an `expandedCats` Set that includes all child category IDs for each checked parent
- **Fixed**: Price range slider had no effect
  - Root cause: `input` event listener only updated the label text, never called `applyFilters()`
  - Fix: Added `applyFilters()` call inside the `input` listener; slider `max` and `value` are now set dynamically to the highest product price (rounded to nearest ₹500) after products load
- **Fixed**: "Newest" and "Popular" sort options had no effect
  - Root cause: `p.created_at` and `p.orders_count` were missing from `products.php` SELECT — client-side sort comparisons always got `undefined`
  - Fix: Added both columns to the SQL SELECT

### Session 8 — Checkout DB Integration & Kerala State
- **Updated**: `pages/checkout.html` — state field replaced with Kerala-only readonly input (app is Kerala-specific); moved inline script to external `assets/js/checkout.js`
- **Created**: `api/customer/place-order.php` — authenticated POST endpoint that:
  - Validates products against DB (uses DB price for security, skips inactive products/shops)
  - Calculates totals server-side (18% GST, free shipping above ₹1,000)
  - Generates unique order number (`TC` + date + 4-digit random, with collision check)
  - Inserts into `orders` and `order_items` tables in a transaction
- **Created**: `assets/js/checkout.js` — replaces the old static inline script:
  - Redirects to `cart.html` if cart is empty
  - Pre-fills form from localStorage (name/email) and `api/customer/my-profile.php` (phone + saved address)
  - `placeOrder()` POSTs to `place-order.php`, clears localStorage cart on success, redirects to `orders.html`

### Session 9 — Orders Fix, Email Notifications & Success Animation
- **Fixed**: `api/customer/my-orders.php` — `oi.item_id` → `oi.order_item_id` (column name mismatch caused SQLSTATE[42S22] error, orders page was blank)
- **Added**: Order confirmation emails via `api/utils/EmailManager.php`:
  - `sendOrderConfirmationEmail()` — sent to customer after order placed; includes itemised table, totals, delivery address
  - `sendNewOrderAdminEmail()` — sent to admin (first admin user in DB); includes order summary and customer contact
  - Both respect dev mode (`isDevelopmentMode()` = true → `error_log` only; switch to false for live SMTP)
- **Updated**: `api/customer/place-order.php` — after transaction commit, fetches customer + admin emails and calls EmailManager (wrapped in try/catch so email failure doesn't break the order response)
- **Added**: Animated order-success overlay on `pages/checkout.html`:
  - Full-screen white overlay with SVG circular checkmark animation (circle draws itself → fill turns green → check stroke appears → scale bounce)
  - CSS keyframes: `stroke-draw`, `checkmark-fill`, `checkmark-scale`, `fadeIn` added to `assets/css/style.css`
  - `checkout.js`: `showOrderSuccessOverlay(orderNumber)` shows the overlay, auto-redirects to `orders.html` after 3.2 s

### Session 11 — Checkout Success Overlay Bug Fix
- **Fixed**: Order-placed animation overlay was showing immediately on `pages/checkout.html` page load instead of only after clicking Place Order
  - Root cause: `.order-success-overlay` in `style.css` had `display: flex !important` which overrode the `style="display:none"` inline attribute on the element
  - Fix: Removed `!important` — the overlay stays hidden via inline style, and `checkout.js` sets `display: flex` only on successful order placement

### Session 10 — Toast Position, Address Auto-fill & Memory
- **Changed**: Toast notifications moved from top-right to **bottom-right** — changed `top-0` → `bottom-0` in `showToast()` in `assets/js/main.js`
- **Added**: Shipping address auto-saved and auto-filled on next checkout:
  - `api/customer/place-order.php` — after commit, upserts shipping details into `addresses` table as the customer's default address (updates if default exists, inserts otherwise)
  - `assets/js/checkout.js` — on success, saves `{phone, address, city, pincode}` to `localStorage.shippingAddress`; on page load, reads from localStorage first (instant fill), then overrides with DB default address from `api/customer/my-profile.php` (most up-to-date)

### Session 12 — Order Delivery Timeline with Bike Animation & Status Sync Fix
- **Replaced**: Status badge on `pages/orders.html` order cards → animated delivery timeline
  - 5-step horizontal timeline: **Order Placed → Confirmed → Processing → Shipped → Delivered**
  - Steps before current status show a filled dark circle with a ✓ checkmark
  - Active (current) step shows an empty circle with a pulsing **motorcycle icon** floating above it (`bikeFloat` CSS animation)
  - Future steps are grey/empty
  - Dark progress bar fills the track up to the current step position
  - Cancelled / Refunded orders still show a simple red/grey badge (no timeline)
- **Updated**: `assets/js/customer-orders.js`:
  - Added `buildTimeline(status)` function — generates the 5-step HTML with fill bar at correct position
  - Updated `buildOrderCard()` — removed status badge, calls `buildTimeline()` instead
- **Added**: Delivery timeline CSS to `assets/css/style.css`:
  - `.order-timeline`, `.tl-steps`, `.tl-fill-bar`, `.tl-step`, `.tl-circle`, `.tl-icon-slot`, `.tl-label`
  - `.tl-done` (completed step) and `.tl-active` (current step) modifier classes
- **Improved**: Bike now animates with a sliding transition effect instead of just appearing at the active step:
  - `.tl-bike-rider` (outer `div`) — absolutely positioned; CSS vars `--bike-to` and `--fill-to` set on `.tl-steps` parent; `@keyframes bikeRide` slides `left` from `10%` (step 0 center) to `var(--bike-to)` over 1.2 s with ease-out curve
  - `.tl-bike-float` (inner `span`) — handles the up-down bounce via `@keyframes bikeFloat` independently, no `transform` conflict with the outer rider
  - `.tl-fill-bar` — `width` animated from `0` to `var(--fill-to)` via `@keyframes trackFill` (1.1 s, slightly faster so the track fills just before the bike arrives)
  - Two-element structure avoids CSS `transform` conflicts: outer element owns `left` animation; inner element owns Y-axis float
- **Fixed**: Timeline not updating when shop changes order status
  - Root cause: Shop updates `item_status` on `order_items` table; customer timeline reads `order_status` on `orders` table — which was never being synced
  - Fix: `api/shop/update-item-status.php` now syncs `orders.order_status` after every item status change:
    - Fetches all `item_status` values for the order
    - Ignores cancelled items; takes the **minimum progress** status across remaining items
    - If all items cancelled → `orders.order_status = 'cancelled'`
    - Updates `orders.order_status` accordingly so the customer timeline reflects real progress

### Session 13 — Shops Page Filters (Sort & Rating)
- **Updated**: `pages/shops.html` — added filter bar alongside the search input:
  - **Sort By** dropdown: Featured (default DB order), Top Rated, Most Products, Newest, Name A–Z
  - **Rating** dropdown: All Ratings, 4★ & above, 3★ & above, 2★ & above
- **Updated**: `assets/js/shops.js` — all filtering/sorting done client-side (one API call on load):
  - Loads up to 100 shops once into `allShops` array
  - `applyFilters()` runs on every search keystroke (300 ms debounce), sort change, or rating change
  - Search matches against shop name, description, and city
  - Rating filter: `parseFloat(rating_average) >= minRating`
  - Sort: `rating_average DESC` / `total_products DESC` / `created_at DESC` / `shop_name ASC` / featured (preserves DB order)
  - Shop cards now display a **star rating row** (full / half / empty stars) via `buildStars()` helper
- **Updated**: `api/customer/shops.php` — added `s.created_at` to SELECT so "Newest" sort works in JS

### Session 14 — Products Nav Link & Product Search
- **Added**: "Products" nav link to all customer-facing pages between Shops and Cart:
  - `pages/shops.html`, `pages/cart.html`, `pages/checkout.html`, `pages/orders.html`, `pages/profile.html`, `pages/login.html` — `href="products.html"`
  - `index.html` — `href="pages/products.html"`
  - `pages/products.html` — marked as `active` so it highlights when on the products page
- **Added**: Inline search bar to `pages/products.html` (in the product grid header row):
  - Input with id `productSearch`, 220 px wide, sits alongside the product count
  - Filters in real-time with 300 ms debounce
  - Matches against `product_name`, `shop_name`, and `category_name`
- **Updated**: `assets/js/products.js`:
  - Added `debounce()` helper (same pattern as shops.js)
  - `productSearch` input event triggers `applyFilters()` on every keystroke (debounced)
  - `applyFilters()` now reads `productSearch` value and filters `allProducts` by text before applying category/price/sort filters
  - When no `shop_id` URL param: title set to "All Products - TrenCart", header text updated to "All Products / Browse all products across every shop", and the breadcrumb "Shops" step is hidden so the path reads Home → All Products
  - When `shop_id` present: behaviour unchanged — shop name in title/header/breadcrumb as before
- **Note**: `products.html?shop_id=X` still works as before — shows only that shop's products. Visiting `products.html` (no param) now shows all products with the full search + filter sidebar

### Session 15 — Shops Page: Category / Price / Offers / Top Sellers Filters + Sidebar Layout

#### Filter additions
- **Added 4 new filters** to `pages/shops.html` — Category, Price, Offers, Top Sellers
- **Restructured layout** — search bar spans full width at the top; all filters moved to a left sidebar (`col-lg-3`) matching the `products.html` pattern; shop grid sits in `col-lg-9`
- **Updated** `api/customer/shops.php` — SQL now LEFT JOINs `products` and returns 4 aggregated fields per shop:
  - `min_price` — lowest active product price in the shop
  - `has_offers` — `1` if any product has `discount_percentage > 0`
  - `total_orders` — sum of all `orders_count` across active products
  - `category_ids` — comma-separated list of distinct category IDs stocked by the shop
- **Updated** `assets/js/shops.js`:
  - `loadCategories()` — fetches categories API; populates `#shopCategory` dropdown with parents + indented children
  - `getCategoryIds(id)` — expands a parent category ID to include child IDs for hierarchy-aware filtering
  - `applyFilters()` — handles all 6 filters: search, rating, sort, category, price, offers, top sellers
  - **Category filter**: matches shops whose `category_ids` includes selected ID or any of its children
  - **Price filter**: keeps shops where `min_price ≤ selected max`
  - **Offers toggle**: keeps shops where `has_offers = 1`; button turns `btn-primary` when active
  - **Top Sellers toggle**: filters shops with `total_orders > 0`, sorts by `total_orders DESC`; button turns `btn-primary` when active
  - `resetShopFilters()` — clears all inputs and toggle states, calls `applyFilters()`
  - Shop cards now show **Offers** (yellow badge) and **Top Seller** (green badge) based on aggregated data

#### Sidebar layout (matching products.html)
- Sidebar uses existing `.filter-sidebar` / `.filter-section` CSS classes (no new styles needed)
- **Sort By** and **Rating** dropdowns moved from top bar into sidebar
- **Category** select dropdown (dynamically populated)
- **Price** select: Any Price / Under ₹500 / Under ₹1,000 / Under ₹2,000 / Under ₹5,000
- **Quick Filters** section: Offers toggle button + Top Sellers toggle button
- **Reset Filters** button at the bottom of sidebar

---

### Session 16 — Product Detail Page with Magnifying Lens Zoom

#### New files
- **`api/customer/product-detail.php`** — public product detail API (`?id=X`); returns product fields + all images (primary first) + shop info + category name
- **`pages/product-detail.html`** — full product detail page:
  - Left col (`col-lg-6`): vertical thumbnail strip + main image with zoom container
  - Right col (`col-lg-6`): category badge, product name, price / original price / discount %, star rating + reviews + sold count, description, "Sold by" shop card, quantity selector (`−/+`), Add to Cart + View Shop buttons
  - Below the fold: "More from this shop" grid (up to 8 products)
- **`assets/js/product-detail.js`** — all detail page logic:
  - `loadProduct(id)` — fetches `product-detail.php`, calls `renderProduct()` and `loadMoreProducts()`
  - `renderProduct(p)` — populates all DOM sections; resolves image paths (`/uploads/...` → `../uploads/...`); wires Add to Cart with qty multiplier
  - **Magnifying lens zoom** (`initZoom()`):
    - A `.zoom-lens` div (120×120 px border box) tracks the mouse over the main image
    - A `.zoom-result` panel (`position: fixed`, 420×420 px) appears alongside — to the right by default, flips to the left if near the screen right edge; auto-adjusts vertically if near the bottom
    - Zoom ratio calculated as `result.size / lens.size`; `background-position` mirrors the lens position scaled by the ratio
    - Disabled on touch devices (`hover: none` media query check)
  - `switchImage(thumbEl, newSrc)` — switches main image, marks active thumbnail, re-inits zoom
  - `changeQty(delta)` — `+/−` quantity controls (min 1)
  - `loadMoreProducts(shopId, excludeId)` — fetches shop's products, excludes current product, renders up to 8 cards in `#moreProductsGrid`
  - `buildMoreCard(p)` — card HTML with clickable image/title linking to `product-detail.html?id=X`

#### Updated files
- **`assets/js/products.js`** — `buildProductCard()`: product image and name are now `<a href="product-detail.html?id=X">` links so users can click through to the detail page

---

## Key File Paths

| Layer | Path |
|---|---|
| DB config | `api/config/database.php` |
| Auth controller | `api/controllers/AuthController.php` |
| OTP utility | `api/utils/OTPManager.php` |
| Email utility | `api/utils/EmailManager.php` |
| Admin APIs | `api/admin/*.php` |
| Shop APIs | `api/shop/*.php` |
| Customer APIs | `api/customer/*.php` |
| Place order API | `api/customer/place-order.php` |
| Admin pages | `admin/*.html` |
| Shop pages | `shop/*.html` |
| Customer pages | `pages/*.html` + `index.html` |
| JS files | `assets/js/*.js` |
| Checkout JS | `assets/js/checkout.js` |
| Uploaded images | `uploads/products/`, `uploads/shops/` |

---

### Session 17 — Commission, Finance, Reports, Shop Header, Shipping, Breadcrumbs

#### Shipping — Free on all orders
- **`assets/js/cart.js`** — `calculateCartTotals()`: `shipping = 0` always (was conditional `subtotal > 1000 ? 0 : 50`)
- **`assets/js/cart.js`** — `updateCartSummary()`: shipping cell now shows `<s>₹50</s> FREE` with strikethrough
- **`assets/js/checkout.js`** — order summary shipping row now shows strikethrough ₹50 → FREE
- **`api/customer/place-order.php`** — `$shipping_amount = 0.00` (was conditional)
- **`pages/cart.html`** — "Free shipping on orders above ₹1000" replaced with "FREE shipping on all orders" (₹50 struck off)

#### Breadcrumbs removed
- Removed `<!-- Breadcrumb -->` sections from all 7 customer pages: `cart.html`, `orders.html`, `profile.html`, `checkout.html`, `shops.html`, `products.html`, `product-detail.html`

#### Shop logo path fix (customer shops listing)
- **`assets/js/shops.js`** — `buildShopCard()`: `shop_logo` path changed from raw DB value to `'../' + shop.shop_logo.replace(/^\//, '')` to correctly resolve from `pages/shops.html`

#### Better shop header in products page
- **`api/customer/products.php`** — shop_info query now includes `shop_logo`, `shop_city`, `total_ratings`
- **`pages/products.html`** — shop header section replaced with rich card: shop logo (circle, initials fallback), name, description, rating, reviews, product count, city, "All Shops" button
- **`assets/js/products.js`** — `loadProducts()` now populates the rich shop card using all new fields; hides default header when shop-specific view

#### Commission system (Admin)
- **`api/admin/settings.php`** — new API: GET returns all `platform_settings`, POST batch-updates by key
- **`admin/settings.html`** — new Settings page: commission rate %, min payout, payment cycle, order auto-confirm hours, max return days, COD toggle
- **All 7 admin pages** (`dashboard.html`, `shops.html`, `orders.html`, `customers.html`, `categories.html`, `revenue.html`, `payments.html`) — sidebar now includes **Reports** and **Settings** links under new "Config" section

#### Shop Finance page
- **`api/shop/finance.php`** — new API: reads `commission_rate` from `platform_settings`; returns summary (gross sales, commission, net earnings, total orders), filtered items sold list with per-item commission breakdown, payment history from `shop_payments`
- **`shop/finance.html`** — new Finance page: 4 summary stat cards, filterable items sold table (date range + status) with footer totals, payment history table, CSV/Excel export

#### Admin Reports
- **`api/admin/reports.php`** — new API: returns orders with customer name, shops involved, item count, filtered by date range, status, shop
- **`admin/reports.html`** — new Reports page: date range + status + shop filters, 3 summary cards (orders, revenue, delivered), orders table, CSV export

#### Shop Reports
- **`api/shop/reports.php`** — new API: returns shop's orders (aggregated from `order_items`) filtered by date range and status
- **`shop/reports.html`** — new Reports page: date range + status filters, stat cards, orders table, CSV export
- **All 5 existing shop pages** (`dashboard.html`, `products.html`, `add-product.html`, `orders.html`, `profile.html`) — sidebar now includes **Finance** and **Reports** links

---

### Session 18 — Shop Logo Upload (Admin & Shop)

#### Admin side — Upload shop logo from Manage Shops
- **`admin/shops.html`** — Added "Upload Logo" modal (`#uploadLogoModal`): shows current logo preview, file picker (JPG/PNG/WebP, max 2 MB), and Upload button
- **`assets/js/admin-shops.js`**:
  - `renderShops()` — each shop row now shows a 32×32 thumbnail of the logo (or a grey store icon if none set) beside the shop name
  - "Upload Logo" item added to the existing Actions dropdown for every shop (above the divider)
  - `openLogoModal(shopId, shopName, logoPath)` — populates and shows the modal with the shop's current logo
  - `previewAdminLogo(input)` — FileReader-based live preview before upload
  - `uploadAdminLogo()` — POSTs `FormData{logo, shop_id}` to `api/admin/upload-shop-logo.php`; refreshes shop table on success
- **`api/admin/upload-shop-logo.php`** (NEW):
  - Admin-only (`user_type = admin`)
  - Validates `shop_id` from POST, MIME type (JPEG/PNG/WebP), file size (< 2 MB)
  - Saves to `uploads/shops/shop_{id}_{timestamp}.ext`, deletes old logo file
  - Updates `shops.shop_logo` in DB to `/uploads/shops/filename`

#### Shop side — Upload logo from Shop Profile page
- **`shop/profile.html`** — New "Shop Logo" card at the top of the profile form:
  - 100×100 px rounded preview area (shows current logo or store icon placeholder)
  - "Choose Image" button → hidden `<input type="file">` → `previewLogo()`
  - "Upload Logo" button appears after a file is chosen; calls `uploadLogo()`
  - Filename shown next to the button
- **`assets/js/shop-profile.js`**:
  - `fillProfile()` — now also sets the logo preview from `p.shop_logo`
  - `previewLogo(input)` — FileReader live preview, reveals "Upload Logo" button
  - `uploadLogo()` — POSTs `FormData{logo}` to `api/shop/upload-logo.php`; clears file picker and hides button on success; shows toast
- **`api/shop/upload-logo.php`** (NEW):
  - Shop-only (`user_type = shop`)
  - Validates MIME type (JPEG/PNG/WebP), file size (< 2 MB)
  - Resolves `shop_id` from session (`user_id → shops`)
  - Saves to `uploads/shops/shop_{id}_{timestamp}.ext`, deletes old logo file
  - Updates `shops.shop_logo` in DB to `/uploads/shops/filename`

#### Storage details
- Upload directory: `uploads/shops/` (relative path `../../uploads/shops/` from `api/shop/` or `api/admin/`)
- DB value format: `/uploads/shops/shop_1_1700000000.jpg`
- From `shop/` pages: `../ + logoPath.replace(/^\//, '')` resolves correctly
- From `admin/` pages: same pattern used in thumbnail display

---

### Session 19 — Image Path Fixes & UI Improvements

#### Product image not showing in products.html
- **Root cause**: `api/customer/products.php` used `LEFT JOIN product_images pi ... AND pi.is_primary = 1` — if no image has `is_primary=1`, the join returns NULL and no image appears
- **Fix**: Replaced the LEFT JOIN with a correlated subquery that tries primary first then falls back to any image:
  ```sql
  (SELECT image_url FROM product_images
   WHERE product_id = p.product_id
   ORDER BY is_primary DESC, display_order ASC
   LIMIT 1) AS primary_image
  ```
- **`assets/js/products.js`** — `buildProductCard()` already had correct path resolution: `'../' + p.primary_image.replace(/^\//, '')`

#### Product image not showing in orders.html modal
- **Root cause 1**: Same `is_primary=1` JOIN issue in `api/customer/my-orders.php` — fixed with the same correlated subquery
- **Root cause 2**: `assets/js/customer-orders.js` used raw DB path `/uploads/products/filename` directly; from `pages/orders.html` this resolves to XAMPP web root, not the project folder
- **Fix**: `'../' + item.product_image.replace(/^\//, '')` — strips leading `/` and adds `../` prefix

#### Shop logo not showing on home page (index.html)
- **Root cause**: `assets/js/home.js` `buildShopCard()` used raw DB path `/uploads/shops/filename`; `index.html` is at project root so leading `/` resolves to XAMPP root
- **Fix**: `shop.shop_logo.replace(/^\//, '')` — strip the leading `/` only (no `../` needed from root)

#### Offers badge moved to image overlay (shops.html)
- **`assets/js/shops.js`** — Offers and Top Seller badges moved from card title to absolute-positioned overlay on top-left of the shop image
- Image wrapped in `<div style="position:relative">` with badges in `position:absolute; top:10px; left:10px`

#### Shop card height reduced (shops.html)
- **`assets/css/style.css`** — added `.shop-card img { height: 170px; }` override (was 250px shared with product cards)
- **`assets/js/shops.js`** — `buildShopCard()` redesigned: shop name reduced to `<h6 14px>`, description capped to 1 line (CSS clamp), rating + products + city merged into a single 12px row with `·` separators, button changed to `btn-sm`

#### Image path convention (summary)
| File location | DB path (`/uploads/X/file.jpg`) | Resolved as |
|---|---|---|
| `index.html` (root) | strip `/` only | `uploads/X/file.jpg` |
| `pages/*.html` | `../` + strip `/` | `../uploads/X/file.jpg` |
| `shop/*.html` | `../` + strip `/` | `../uploads/X/file.jpg` |
| `admin/*.html` | `../` + strip `/` | `../uploads/X/file.jpg` |

---

### Session 22 — 2025 Modern Design Refresh (Customer Pages)

#### Goal
Replace the narrow `container`-boxed layouts with full-width, 2025-trend designs across all customer-facing pages. Key improvements: dark page banners, horizontal filter strip for shops, wider content areas, modern card shadows, updated profile and cart layouts.

#### Global CSS additions (`assets/css/style.css`)
- **`.page-banner`** — Full-width dark gradient header with `.pb-eyebrow`, `.pb-title`, `.pb-sub`, `.pb-count` pill
- **`.filter-strip`** — Sticky horizontal filter bar with pill-style selects and toggle chips (shops page)
- **`.filter-sidebar`** — Borderless white card, rounded-16, shadow, tiny uppercase labels
- **`.cart-item` / `.cart-summary`** — Borderless shadow cards, rounded-16, hover lift
- **`.checkout-section` / `.payment-option`** — Borderless shadow cards, rounded-16
- **`.profile-avatar-card`** — Dark gradient left column card
- **`.profile-form-card`** — White right column card with shadow
- **`.page-body`** — `background:#f7f7f7` content wrapper

#### `pages/shops.html`
- Replaced container header + sidebar layout with `.page-banner` + `.filter-strip` (horizontal pill filters) + full-width grid in `container-xl`
- All filter IDs preserved — shops.js unchanged

#### `pages/products.html`
- `#shopHeaderSection` is now a `.page-banner` (dark); JS-toggled `#shopHeaderDefault` / `#shopHeaderCard` work as before
- Sidebar narrowed: `col-lg-3` → `col-lg-2 col-md-3`; products grid: `col-lg-9` → `col-lg-10 col-md-9`

#### `pages/cart.html`, `pages/checkout.html`, `pages/orders.html`
- Plain `<h2>` header replaced with `.page-banner`; `container` → `container-xl px-4`; wrapped in `.page-body`

#### `pages/profile.html`
- Left column: old grey box → `.profile-avatar-card` (dark gradient, avatar circle, white text, pill button)
- Right column: old grey box → `.profile-form-card` (white card, shadow, uppercase section label)
- Layout: `col-lg-4/8` → `col-lg-3/9`

---

### Session 21 — New Arrivals Section on Home Page

#### Feature: New Arrivals horizontal scroll section
- **`index.html`** — New dark-background section (`na-section`) inserted between Featured Shops and the Features (free shipping / secure payment / support) section
  - Section title: "New Arrivals" / "Fresh styles just added — be the first to explore"
  - `#newArrivalsTrack` div populated dynamically by `home.js`
  - "Explore All Products" button links to `pages/products.html?sort=newest`

- **`assets/js/home.js`**:
  - `DOMContentLoaded` now calls both `loadFeaturedShops()` and `loadNewArrivals()` in parallel
  - `loadNewArrivals()` — fetches `api/customer/products.php?sort=newest`, slices to first 12 results, renders `.na-card` elements in the scroll track
  - `buildArrivalCard(p)` — builds a portrait-orientation card (aspect-ratio 3:4) with:
    - Product image with scale-on-hover effect
    - "New" pill badge (top-left, dark background)
    - Category pill badge (top-right, frosted white) — hidden if no category
    - Product name (bold, single-line truncate)
    - Shop name with a store icon
    - Clicking the card navigates to `pages/products.html?shop_id=X`

- **`assets/css/style.css`** — New Arrivals styles added:
  - `.na-section` — dark (`#1a1a1a`) background, white heading + muted subtitle
  - `.na-scroll-track` — `display:flex`, horizontal overflow scroll with CSS scroll-snap, styled thin scrollbar
  - `.na-card` — `flex: 0 0 200px`, `border-radius:14px`, white background, large drop shadow, lift-on-hover
  - `.na-img-wrap` — `aspect-ratio: 3/4`, image scale on card hover
  - `.na-badge-new` — dark pill, uppercase "NEW" label
  - `.na-badge-cat` — frosted white pill, category label (truncated at 90px)
  - `.na-info` — compact padding block
  - `.na-name` — 0.84rem bold, single-line ellipsis
  - `.na-shop` — 0.73rem grey, flex row with store icon

#### Design rationale
- Dark background creates strong contrast and a premium feel between the white Featured Shops section and the light-grey Features section
- Horizontal scroll (portrait cards) is a modern mobile-first pattern common in fashion/e-commerce apps
- No price shown — keeps focus on discovery; clicking goes to the shop's product listing
- First 12 newest products shown; uses the existing `api/customer/products.php?sort=newest` endpoint (no new API needed)

---

### Session 20 — Reports & Finance Bug Fixes

#### Bug: Shop reports and finance showed no data
- **Root cause**: `api/shop/reports.php` and `api/shop/finance.php` both used `$shop_id = $_SESSION['user_id']` directly. `$_SESSION['user_id']` is `users.user_id`, but all SQL queries compare against `order_items.shop_id` and `shop_payments.shop_id`, which are `shops.shop_id` — a different, unrelated value. The mismatch meant no rows ever matched.
- **Fix applied to both files**: After getting the DB connection, resolve the real `shop_id` with:
  ```php
  $sr = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
  $sr->bindValue(':uid', $user_id, PDO::PARAM_INT);
  $sr->execute();
  $shop_id = (int)$sr->fetch(PDO::FETCH_ASSOC)['shop_id'];
  ```

#### Bug: Admin reports showed no data on page load
- **Root cause**: `admin/reports.html` defaulted to last 30 days on `DOMContentLoaded` — if no orders existed in that narrow window the table showed "No orders found"
- **Fix**: Removed the auto-set date range; page now loads all orders with no default date filter; user applies dates manually as needed

---

## Pending / Known Issues
- Delivery panel not yet started
- Admin analytics dashboard uses static placeholder data
- Email sending (OTP + order notifications) requires SMTP config — currently in dev mode (`isDevelopmentMode() = true` in `api/utils/EmailManager.php`), which logs emails to `error_log` instead of sending them; set to `false` and configure PHP mail/SMTP for production
