/* ===================================
   TRENCART - Authentication JavaScript
   Login, Registration, and Auth Management
   =================================== */

/* ===================================
   LOGIN FORM HANDLING
   =================================== */
function handleLogin(event) {
    event.preventDefault();

    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const rememberMe = document.getElementById('rememberMe').checked;

    // Validation
    if (!email || !password) {
        showToast('Please fill in all fields', 'error');
        return;
    }

    if (!isValidEmail(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }

    // Simulate login (replace with actual backend API call)
    // For now, we'll accept any valid email and password
    if (password.length < 6) {
        showToast('Password must be at least 6 characters', 'error');
        return;
    }

    // Simulate API call delay
    const submitBtn = document.getElementById('loginBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';

    setTimeout(() => {
        // Simulate successful login
        // In production, this would be replaced with actual backend authentication
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        const user = users.find(u => u.email === email && u.password === password);

        if (user) {
            // Login successful
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('userName', user.name);
            localStorage.setItem('userEmail', user.email);

            if (rememberMe) {
                localStorage.setItem('rememberMe', 'true');
            }

            showToast('Login successful! Redirecting...', 'success');

            // Redirect to home page or previous page
            setTimeout(() => {
                const redirectUrl = sessionStorage.getItem('redirectAfterLogin') || '../index.html';
                sessionStorage.removeItem('redirectAfterLogin');
                window.location.href = redirectUrl;
            }, 1500);
        } else {
            // Login failed
            showToast('Invalid email or password', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    }, 1000);
}

/* ===================================
   REGISTRATION FORM HANDLING
   =================================== */
function handleRegistration(event) {
    event.preventDefault();

    const name = document.getElementById('registerName').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const phone = document.getElementById('registerPhone').value.trim();
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const termsAccepted = document.getElementById('termsConditions').checked;

    // Validation
    if (!name || !email || !phone || !password || !confirmPassword) {
        showToast('Please fill in all fields', 'error');
        return;
    }

    if (!isValidEmail(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }

    if (!isValidPhone(phone)) {
        showToast('Please enter a valid 10-digit phone number', 'error');
        return;
    }

    if (password.length < 6) {
        showToast('Password must be at least 6 characters', 'error');
        return;
    }

    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    if (!termsAccepted) {
        showToast('Please accept the terms and conditions', 'error');
        return;
    }

    // Check password strength
    const passwordStrength = checkPasswordStrength(password);
    if (passwordStrength === 'weak') {
        showToast('Please choose a stronger password', 'error');
        return;
    }

    // Simulate registration
    const submitBtn = document.getElementById('registerBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';

    setTimeout(() => {
        // Store user data (in production, this would be sent to backend)
        const users = JSON.parse(localStorage.getItem('users') || '[]');

        // Check if email already exists
        if (users.some(u => u.email === email)) {
            showToast('Email already registered. Please login.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return;
        }

        // Add new user
        const newUser = {
            id: Date.now().toString(),
            name: name,
            email: email,
            phone: phone,
            password: password, // In production, this should be hashed
            createdAt: new Date().toISOString()
        };

        users.push(newUser);
        localStorage.setItem('users', JSON.stringify(users));

        // Auto login after registration
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userName', name);
        localStorage.setItem('userEmail', email);

        showToast('Registration successful! Redirecting...', 'success');

        setTimeout(() => {
            window.location.href = '../index.html';
        }, 1500);
    }, 1000);
}

/* ===================================
   PASSWORD VISIBILITY TOGGLE
   =================================== */
function togglePasswordVisibility(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

/* ===================================
   PASSWORD STRENGTH CHECKER
   =================================== */
function checkPasswordStrength(password) {
    let strength = 0;

    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;

    // Character variety checks
    if (/[a-z]/.test(password)) strength++; // lowercase
    if (/[A-Z]/.test(password)) strength++; // uppercase
    if (/[0-9]/.test(password)) strength++; // numbers
    if (/[^a-zA-Z0-9]/.test(password)) strength++; // special characters

    if (strength <= 2) return 'weak';
    if (strength <= 4) return 'medium';
    return 'strong';
}

/* ===================================
   UPDATE PASSWORD STRENGTH INDICATOR
   =================================== */
function updatePasswordStrength() {
    const password = document.getElementById('registerPassword').value;
    const strengthIndicator = document.getElementById('passwordStrength');

    if (!strengthIndicator) return;

    if (password.length === 0) {
        strengthIndicator.innerHTML = '';
        return;
    }

    const strength = checkPasswordStrength(password);
    let html = '<div class="password-strength mt-2">';
    html += '<small>Password Strength: ';

    if (strength === 'weak') {
        html += '<span class="text-danger fw-bold">Weak</span>';
    } else if (strength === 'medium') {
        html += '<span class="text-warning fw-bold">Medium</span>';
    } else {
        html += '<span class="text-success fw-bold">Strong</span>';
    }

    html += '</small></div>';
    strengthIndicator.innerHTML = html;
}

/* ===================================
   CONFIRM PASSWORD VALIDATION
   =================================== */
function validateConfirmPassword() {
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const confirmPasswordInput = document.getElementById('confirmPassword');

    if (confirmPassword.length === 0) {
        confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
        return;
    }

    if (password === confirmPassword) {
        confirmPasswordInput.classList.remove('is-invalid');
        confirmPasswordInput.classList.add('is-valid');
    } else {
        confirmPasswordInput.classList.remove('is-valid');
        confirmPasswordInput.classList.add('is-invalid');
    }
}

/* ===================================
   LOGOUT FUNCTION
   =================================== */
function logout() {
    // Clear auth data only — cart is preserved across logout
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userName');
    localStorage.removeItem('userEmail');

    showToast('Logged out successfully', 'success');

    setTimeout(() => {
        const inPages = window.location.pathname.replace(/\\/g, '/').includes('/pages/');
        window.location.href = inPages ? '../index.html' : 'index.html';
    }, 1000);
}

/* ===================================
   CHECK IF USER IS LOGGED IN
   =================================== */
function isUserLoggedIn() {
    return localStorage.getItem('isLoggedIn') === 'true';
}

/* ===================================
   REQUIRE LOGIN FOR PAGE ACCESS
   =================================== */
function requireLogin() {
    if (!isUserLoggedIn()) {
        // Store current page for redirect after login
        sessionStorage.setItem('redirectAfterLogin', window.location.href);

        showToast('Please login to access this page', 'error');

        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);

        return false;
    }
    return true;
}

/* ===================================
   GET CURRENT USER INFO
   =================================== */
function getCurrentUser() {
    if (!isUserLoggedIn()) return null;

    return {
        name: localStorage.getItem('userName'),
        email: localStorage.getItem('userEmail')
    };
}

/* ===================================
   INITIALIZE AUTH FORMS
   =================================== */
document.addEventListener('DOMContentLoaded', function() {
    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);

        // Add password toggle
        const passwordInput = document.getElementById('loginPassword');
        const togglePassword = document.getElementById('toggleLoginPassword');

        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility('loginPassword', 'toggleLoginPasswordIcon');
            });
        }

        // Check if already logged in
        if (isUserLoggedIn()) {
            window.location.href = '../index.html';
        }
    }

    // Registration form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistration);

        // Password strength indicator
        const passwordInput = document.getElementById('registerPassword');
        if (passwordInput) {
            passwordInput.addEventListener('input', updatePasswordStrength);
        }

        // Confirm password validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        }

        // Password toggles
        const togglePassword = document.getElementById('toggleRegisterPassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility('registerPassword', 'toggleRegisterPasswordIcon');
            });
        }

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        if (toggleConfirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                togglePasswordVisibility('confirmPassword', 'toggleConfirmPasswordIcon');
            });
        }

        // Check if already logged in
        if (isUserLoggedIn()) {
            window.location.href = '../index.html';
        }
    }
});

/* ===================================
   FORGOT PASSWORD (placeholder)
   =================================== */
function forgotPassword() {
    const email = prompt('Enter your registered email address:');

    if (email && isValidEmail(email)) {
        showToast('Password reset link sent to your email', 'success');
        // In production, this would send an actual password reset email
    } else if (email) {
        showToast('Please enter a valid email address', 'error');
    }
}
