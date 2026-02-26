# Authentication Pages (login.html & register.html) - Documentation

## Overview
The authentication pages handle user login and registration for TrenCart. These pages provide secure access to user-specific features like adding to cart, checkout, and order tracking.

---

# LOGIN PAGE (login.html)

## Page Overview
Allows existing users to log in to their TrenCart account. Features form validation, password visibility toggle, and "remember me" functionality.

## Structure

### 1. Navigation Bar
- Simplified navbar (no Login/Register buttons)
- Cart icon visible but login required to add items

### 2. Login Form (Centered)
Contained in `.auth-card` component:

- **Form Fields**:
  1. Email Address (required, email validation)
  2. Password (required, min 6 characters)

- **Additional Options**:
  - "Remember me" checkbox
  - "Forgot Password?" link

- **Action Buttons**:
  - Submit button: "Login"
  - Link to registration: "Register here"

### 3. Form Elements
- Email input with placeholder
- Password input with show/hide toggle
- Checkbox for remember me
- Submit button with icon

### 4. Footer
- Standard footer layout

## Features

### Interactive Features

1. **Form Validation**:
   - Email format validation
   - Password length check (min 6 chars)
   - Required field validation
   - Real-time error messages

2. **Password Visibility Toggle**:
   - Eye icon to show/hide password
   - Toggles input type between 'password' and 'text'
   - Icon changes from eye to eye-slash

3. **Remember Me**:
   - Stores preference in localStorage
   - Auto-fills credentials on return (currently UI only)

4. **Forgot Password**:
   - Prompts for email
   - Shows success message (currently simulated)

5. **Login Process**:
   - Button shows loading state (spinner)
   - Validates credentials against localStorage
   - Shows success/error toast
   - Redirects to previous page or home
   - Stores user session in localStorage

## JavaScript Functions (from auth.js)

### 1. handleLogin(event)
```javascript
- Prevents form submission
- Gets email and password
- Validates inputs
- Checks against stored users in localStorage
- Sets login status
- Redirects after successful login
```

### 2. togglePasswordVisibility(inputId, iconId)
```javascript
- Toggles password visibility
- Changes input type
- Updates icon
```

### 3. forgotPassword()
```javascript
- Prompts for email
- Validates email format
- Shows success toast
- Would send reset email (backend needed)
```

### 4. Auto-redirect if Logged In
```javascript
- Checks if user already logged in
- Redirects to home if authenticated
```

---

# REGISTRATION PAGE (register.html)

## Page Overview
Allows new users to create a TrenCart account. Includes comprehensive form validation, password strength checking, and terms acceptance.

## Structure

### 1. Navigation Bar
- Simplified navbar
- No authentication buttons shown

### 2. Registration Form (Centered)
Contained in `.auth-card` component:

- **Form Fields**:
  1. Full Name (required)
  2. Email Address (required, email validation)
  3. Phone Number (required, 10 digits)
  4. Password (required, strength indicator)
  5. Confirm Password (required, must match)

- **Additional Requirements**:
  - Terms and Conditions checkbox (required)

- **Action Buttons**:
  - Submit button: "Create Account"
  - Link to login: "Login here"

### 3. Form Elements
- Text inputs with placeholders
- Password inputs with show/hide toggles
- Password strength indicator
- Checkbox for terms
- Submit button with icon

### 4. Footer
- Standard footer layout

## Features

### Interactive Features

1. **Form Validation**:
   - Name: Required, non-empty
   - Email: Valid email format
   - Phone: Exactly 10 digits, starts with 6-9
   - Password: Minimum 6 characters
   - Confirm Password: Must match password
   - Terms: Must be checked

2. **Password Strength Indicator**:
   - Weak (red): Basic password
   - Medium (orange): Moderate security
   - Strong (green): High security
   - Checks:
     - Length (8+, 12+)
     - Lowercase letters
     - Uppercase letters
     - Numbers
     - Special characters

3. **Password Match Validation**:
   - Real-time comparison with password field
   - Visual feedback (green/red border)
   - Bootstrap validation classes

4. **Password Visibility Toggles**:
   - Separate toggles for password and confirm password
   - Eye icons that change on toggle

5. **Registration Process**:
   - Button shows loading state
   - Checks if email already registered
   - Creates new user object
   - Stores in localStorage users array
   - Auto-login after registration
   - Redirects to home page
   - Shows success toast

## JavaScript Functions (from auth.js)

### 1. handleRegistration(event)
```javascript
- Prevents form submission
- Validates all fields
- Checks password strength
- Verifies passwords match
- Ensures terms accepted
- Checks for duplicate email
- Creates user object
- Stores in localStorage
- Auto-login
- Redirects to home
```

### 2. checkPasswordStrength(password)
```javascript
- Returns: 'weak', 'medium', or 'strong'
- Checks length and character variety
```

### 3. updatePasswordStrength()
```javascript
- Called on password input
- Updates strength indicator display
- Shows colored label
```

### 4. validateConfirmPassword()
```javascript
- Compares password fields
- Adds Bootstrap validation classes
- Provides visual feedback
```

### 5. togglePasswordVisibility()
```javascript
- Same as login page
- Works for both password fields
```

---

# SHARED COMPONENTS

## Auth Card Styling
- Centered on page with `.auth-container`
- White card on grey background
- Max-width: 500px
- Rounded corners
- Shadow effect
- Responsive padding

## Form Styling
- Bootstrap form controls
- Custom focus states
- Validation feedback
- Password toggle icons positioned absolutely

## Color Theme Application
- **Black (30%)**: Form buttons, navbar
- **Grey (5%)**: Labels, borders, placeholders
- **White (65%)**: Card background, page background

---

# AUTHENTICATION LOGIC

## Current Implementation (localStorage)

### User Object Structure
```javascript
{
    id: "timestamp",
    name: "John Doe",
    email: "john@example.com",
    phone: "9876543210",
    password: "hashed_password", // Currently plain text
    createdAt: "2024-02-14T10:30:00.000Z"
}
```

### Session Storage
```javascript
localStorage.setItem('isLoggedIn', 'true');
localStorage.setItem('userName', 'John Doe');
localStorage.setItem('userEmail', 'john@example.com');
```

### Users Array
```javascript
localStorage.setItem('users', JSON.stringify([user1, user2, ...]));
```

## Future Backend Integration

### Database Schema
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_token VARCHAR(255),
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### PHP Backend Requirements

1. **Login API**:
```php
POST /api/auth/login
{
    "email": "user@example.com",
    "password": "password123"
}
// Returns: JWT token or session ID
```

2. **Register API**:
```php
POST /api/auth/register
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "9876543210",
    "password": "password123"
}
```

3. **Security Implementation**:
   - Password hashing (bcrypt or Argon2)
   - CSRF protection
   - Rate limiting
   - Email verification
   - Session management

### API Endpoints Needed
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `POST /api/auth/logout` - User logout
- `GET /api/auth/check` - Check session status
- `POST /api/auth/forgot-password` - Password reset request
- `POST /api/auth/reset-password` - Reset password with token
- `GET /api/auth/verify-email` - Verify email address

---

# SECURITY CONSIDERATIONS

## Current Limitations
1. Passwords stored in plain text (localStorage)
2. No email verification
3. No HTTPS enforcement
4. No rate limiting
5. Client-side only validation

## Production Requirements
1. **Password Security**:
   - Hash passwords with bcrypt/Argon2
   - Minimum 8 characters
   - Enforce password complexity

2. **Session Management**:
   - Secure HTTP-only cookies
   - CSRF tokens
   - Session expiration
   - Logout functionality

3. **Email Verification**:
   - Send verification email
   - Confirm before activation

4. **Rate Limiting**:
   - Prevent brute force attacks
   - Limit login attempts

5. **HTTPS**:
   - Encrypt all data in transit

---

# RESPONSIVE BEHAVIOR

### Desktop (≥768px)
- Centered auth card
- Max-width 500px
- Full form spacing

### Mobile (<768px)
- Full-width card with reduced padding
- Stacked form fields
- Larger tap targets
- Adjusted font sizes

---

# FILE DEPENDENCIES
- **CSS**: theme.css, style.css
- **JavaScript**: main.js, cart.js, auth.js
- **External**: Bootstrap 5, Font Awesome

---

# TESTING CHECKLIST

## Login Page
- [ ] Email validation works
- [ ] Password validation works
- [ ] Password toggle functions
- [ ] Remember me checkbox works
- [ ] Forgot password shows prompt
- [ ] Valid login redirects correctly
- [ ] Invalid login shows error
- [ ] Already logged in users redirect
- [ ] Toast notifications appear
- [ ] Loading state shows during login
- [ ] Responsive on mobile

## Registration Page
- [ ] All fields validate correctly
- [ ] Email format check works
- [ ] Phone number accepts 10 digits only
- [ ] Password strength indicator updates
- [ ] Password match validation works
- [ ] Both password toggles function
- [ ] Terms checkbox is required
- [ ] Duplicate email check works
- [ ] Successful registration auto-logs in
- [ ] Registration redirects to home
- [ ] Toast notifications appear
- [ ] Loading state shows
- [ ] Responsive on mobile
- [ ] Password strength colors display correctly
