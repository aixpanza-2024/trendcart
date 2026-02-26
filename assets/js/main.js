/* ===================================
   TRENCART - Main JavaScript
   General functionality and initialization
   =================================== */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    initAnimations();
    checkAuthStatus();
    injectMobileNav(); // injects bottom tab bar + mobile search; calls updateCartBadge internally
});

/* ===================================
   NAVBAR FUNCTIONALITY
   =================================== */
function initNavbar() {
    const navbar = document.querySelector('.navbar');

    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Highlight active nav link based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.html')) {
            link.classList.add('active');
        }
    });


    // Mobile menu close on link click
    const navbarCollapse = document.querySelector('.navbar-collapse');
    if (navbarCollapse) {
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
            });
        });
    }
}

/* ===================================
   MOBILE NAVIGATION (bottom tab bar)
   =================================== */
function injectMobileNav() {
    // Skip on shop / admin panels — they have their own sidebar
    if (document.querySelector('.sidebar, .admin-sidebar')) return;

    const navContainer = document.querySelector('.navbar .container-fluid');
    const navbar       = document.querySelector('.navbar');
    if (!navContainer || !navbar) return;

    // Path helpers
    const inPages  = window.location.pathname.replace(/\\/g, '/').includes('/pages/');
    const href     = (page) => inPages ? page : 'pages/' + page;
    const rootHref = inPages ? '../index.html' : 'index.html';

    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    // Active tab detection
    const page     = window.location.pathname.split('/').pop() || 'index.html';
    const active   = (...pages) => pages.includes(page) ? 'active' : '';

    // 1) Top navbar icons: search toggle + cart
    navContainer.insertAdjacentHTML('beforeend', `
        <div class="mob-top-icons" id="mobTopIcons">
            <button class="mob-icon" id="mobSearchToggle" type="button" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>
            <a href="${href('cart.html')}" class="mob-icon" aria-label="Cart" style="position:relative;">
                <i class="fas fa-shopping-bag"></i>
                <span class="cart-badge" id="mobTopCartBadge" style="display:none;">0</span>
            </a>
        </div>`);

    // 2) Slide-down search bar (sits just below sticky navbar)
    navbar.insertAdjacentHTML('afterend', `
        <div class="mob-search-bar" id="mobSearchBar">
            <form class="d-flex gap-0" onsubmit="mobileProductSearch(event)">
                <input type="text" class="form-control" id="mobileSearchInput"
                       placeholder="Search products..." autocomplete="off">
                <button class="btn-mob-search" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>`);

    // Wire toggle button
    document.getElementById('mobSearchToggle').addEventListener('click', function () {
        const bar = document.getElementById('mobSearchBar');
        bar.classList.toggle('open');
        if (bar.classList.contains('open')) {
            document.getElementById('mobileSearchInput')?.focus();
        }
    });

    // Account tab differs: logged-in gets a sheet toggle; guests navigate to login
    const acctTabHtml = isLoggedIn
        ? `<a href="#" class="mob-tab ${active('orders.html', 'profile.html')}" id="mobAcctTab">
               <i class="fas fa-user-circle"></i>
               <span>Account</span>
           </a>`
        : `<a href="${href('login.html')}" class="mob-tab ${active('login.html')}">
               <i class="fas fa-user"></i>
               <span>Login</span>
           </a>`;

    // 3) Bottom tab bar
    document.body.insertAdjacentHTML('beforeend', `
        <nav class="mob-bottom-bar" id="mobBottomBar" aria-label="Mobile navigation">
            <a href="${rootHref}" class="mob-tab ${active('index.html', '')}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="${href('shops.html')}" class="mob-tab ${active('shops.html')}">
                <i class="fas fa-store"></i>
                <span>Shops</span>
            </a>
            <a href="${href('products.html')}" class="mob-tab ${active('products.html')}">
                <i class="fas fa-th-large"></i>
                <span>Products</span>
            </a>
            <a href="${href('cart.html')}" class="mob-tab ${active('cart.html')}">
                <span style="position:relative;display:inline-block;">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="mob-cart-dot" id="mobBottomCartDot"></span>
                </span>
                <span>Cart</span>
            </a>
            ${acctTabHtml}
        </nav>`);

    // If logged in: inject slide-up account sheet + overlay
    if (isLoggedIn) {
        document.body.insertAdjacentHTML('beforeend', `
            <div class="mob-account-overlay" id="mobAcctOverlay"></div>
            <div class="mob-account-sheet" id="mobAcctSheet">
                <div class="mob-account-sheet-handle"></div>
                <a href="${href('orders.html')}" class="mob-account-sheet-item">
                    <i class="fas fa-box"></i> My Orders
                </a>
                <a href="${href('profile.html')}" class="mob-account-sheet-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <button class="mob-account-sheet-item danger" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>`);

        const sheet   = document.getElementById('mobAcctSheet');
        const overlay = document.getElementById('mobAcctOverlay');
        const toggle  = (open) => {
            sheet.classList.toggle('open', open);
            overlay.classList.toggle('open', open);
        };

        document.getElementById('mobAcctTab').addEventListener('click', (e) => {
            e.preventDefault();
            toggle(!sheet.classList.contains('open'));
        });
        overlay.addEventListener('click', () => toggle(false));
        // Close sheet when a link inside it is tapped
        sheet.querySelectorAll('a').forEach(a => a.addEventListener('click', () => toggle(false)));
    }

    // Sync cart counts now that elements exist
    updateCartBadge();
}

/* ===================================
   ANIMATIONS
   =================================== */
function initAnimations() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all cards
    const cards = document.querySelectorAll('.shop-card, .product-card');
    cards.forEach(card => {
        observer.observe(card);
    });
}

/* ===================================
   SMOOTH SCROLL
   =================================== */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href.length > 1) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

/* ===================================
   CART BADGE UPDATE
   =================================== */
function updateCartBadge() {
    const cart = getCart();
    const total = cart.reduce((sum, item) => sum + item.quantity, 0);

    // Update all .cart-badge elements (desktop navbar + mobile top icon)
    document.querySelectorAll('.cart-badge').forEach(badge => {
        badge.textContent = total;
        badge.style.display = total === 0 ? 'none' : 'flex';
    });

    // Update mobile bottom-bar cart dot
    const dot = document.getElementById('mobBottomCartDot');
    if (dot) {
        dot.textContent = total > 9 ? '9+' : total;
        dot.style.display = total === 0 ? 'none' : 'flex';
    }
}

/* ===================================
   AUTH STATUS CHECK
   =================================== */
function checkAuthStatus() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const authButtons = document.querySelector('.auth-buttons');

    // Always hide the Register nav-item when logged in
    if (isLoggedIn) {
        const registerLink = document.querySelector('.navbar a[href*="register"]');
        if (registerLink) {
            registerLink.closest('li').style.display = 'none';
        }
    }

    if (authButtons && isLoggedIn) {
        const userName = localStorage.getItem('userName') || 'User';

        // Build path-aware links (root vs inside /pages/)
        const inPages = window.location.pathname.includes('/pages/');
        const profileLink = inPages ? 'profile.html' : 'pages/profile.html';
        const ordersLink  = inPages ? 'orders.html'  : 'pages/orders.html';

        // Use outerHTML so we replace the whole <li>, not nest another <li> inside it
        authButtons.outerHTML = `
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-1 px-2" href="#"
                   id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                   style="font-size:0.82rem;font-weight:600;letter-spacing:0.07em;text-transform:uppercase;">
                    <i class="fas fa-user-circle" style="font-size:1rem;"></i> ${userName}
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown"
                    style="min-width:180px;font-size:0.9rem;">
                    <li><a class="dropdown-item py-2" href="${ordersLink}"><i class="fas fa-box me-2 text-secondary"></i>My Orders</a></li>
                    <li><a class="dropdown-item py-2" href="${profileLink}"><i class="fas fa-user me-2 text-secondary"></i>My Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </li>`;
    }
}

/* ===================================
   NAVBAR PRODUCT SEARCH
   =================================== */
function navProductSearch(e) {
    e.preventDefault();
    const q = (document.getElementById('navSearchInput')?.value || '').trim();
    if (!q) return;
    // Detect path depth: pages/ subfolder vs root
    const inPages = window.location.pathname.replace(/\\/g, '/').includes('/pages/');
    const base    = inPages ? 'products.html' : 'pages/products.html';
    window.location.href = base + '?search=' + encodeURIComponent(q);
}

/* Mobile search bar submit */
function mobileProductSearch(e) {
    e.preventDefault();
    const q = (document.getElementById('mobileSearchInput')?.value || '').trim();
    if (!q) return;
    const inPages = window.location.pathname.replace(/\\/g, '/').includes('/pages/');
    const base = inPages ? 'products.html' : 'pages/products.html';
    window.location.href = base + '?search=' + encodeURIComponent(q);
}

/* ===================================
   LOGOUT FUNCTION
   =================================== */
function logout() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userName');
    localStorage.removeItem('userEmail');
    const inPages = window.location.pathname.replace(/\\/g, '/').includes('/pages/');
    window.location.href = inPages ? '../index.html' : 'index.html';
}

/* ===================================
   HELPER FUNCTIONS
   =================================== */

// Get cart from localStorage
function getCart() {
    const cart = localStorage.getItem('cart');
    return cart ? JSON.parse(cart) : [];
}

// Show toast notification
function showToast(message, type = 'success') {
    // Create toast element
    const toastHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'dark' : 'danger'} border-0"
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    // Add toast to container
    const toastElement = document.createElement('div');
    toastElement.innerHTML = toastHTML;
    toastContainer.appendChild(toastElement.firstElementChild);

    // Initialize and show toast
    const toast = new bootstrap.Toast(toastContainer.lastElementChild, {
        autohide: true,
        delay: 3000
    });
    toast.show();

    // Remove toast element after it's hidden
    toastContainer.lastElementChild.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Format currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

// Validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validate phone number (Indian format)
function isValidPhone(phone) {
    const phoneRegex = /^[6-9]\d{9}$/;
    return phoneRegex.test(phone.replace(/\s+/g, ''));
}

/* ===================================
   PAGE SPECIFIC INITIALIZATION
   =================================== */

// Initialize Bootstrap components
window.addEventListener('load', function() {
    // Initialize all tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize all popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
