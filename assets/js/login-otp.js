/**
 * Login with OTP - JavaScript
 * Timeline stepper login with dev OTP display
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeLogin();
});

let loginEmail = '';

function initializeLogin() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    const verifyOtpForm = document.getElementById('verifyOtpForm');
    if (verifyOtpForm) {
        verifyOtpForm.addEventListener('submit', handleOTPVerification);
    }

    initializeOTPInputs();

    const resendOtpLink = document.getElementById('resendOtp');
    if (resendOtpLink) {
        resendOtpLink.addEventListener('click', function(e) {
            e.preventDefault();
            resendOTP();
        });
    }

    const changeEmailLink = document.getElementById('changeEmail');
    if (changeEmailLink) {
        changeEmailLink.addEventListener('click', function(e) {
            e.preventDefault();
            goBackToStep1();
        });
    }
}

/**
 * Handle login form submission - Send OTP
 */
async function handleLogin(e) {
    e.preventDefault();

    const loginBtn = document.getElementById('loginBtn');
    const originalHTML = loginBtn.innerHTML;
    const email = document.getElementById('loginEmail').value.trim();

    if (!email) {
        showToast('Please enter your email address', 'error');
        return;
    }

    loginBtn.disabled = true;
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';

    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });

        const result = await response.json();

        if (result.success) {
            loginEmail = email;
            showToast(result.message, 'success');

            // Move to step 2
            goToStep2(email);
        } else {
            showToast(result.message, 'error');
            loginBtn.disabled = false;
            loginBtn.innerHTML = originalHTML;
        }
    } catch (error) {
        console.error('Login error:', error);
        showToast('Failed to send OTP. Please try again.', 'error');
        loginBtn.disabled = false;
        loginBtn.innerHTML = originalHTML;
    }
}

/**
 * Transition to Step 2 (OTP verification)
 */
function goToStep2(email) {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const progressLine = document.getElementById('progressLine');

    // Mark step 1 as completed
    step1.classList.remove('active');
    step1.classList.add('completed');
    step1.querySelector('.step-dot').innerHTML = '<i class="fas fa-check" style="font-size:0.7rem"></i>';

    // Show completed email, hide form
    document.getElementById('emailFormContent').style.display = 'none';
    document.getElementById('emailCompleted').style.display = 'block';
    document.getElementById('completedEmailText').textContent = email;

    // Animate progress line
    progressLine.style.height = '100%';

    // Activate step 2
    step2.classList.add('active');

    // Focus first OTP input
    setTimeout(() => {
        document.getElementById('otp1').focus();
    }, 400);
}

/**
 * Go back to Step 1
 */
function goBackToStep1() {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const progressLine = document.getElementById('progressLine');

    // Reset step 2
    step2.classList.remove('active');

    // Reset progress line
    progressLine.style.height = '0';

    // Reset step 1
    step1.classList.remove('completed');
    step1.classList.add('active');
    step1.querySelector('.step-dot').textContent = '1';

    // Show form, hide completed
    document.getElementById('emailFormContent').style.display = 'block';
    document.getElementById('emailCompleted').style.display = 'none';

    // Hide dev OTP
    document.getElementById('devOtpBadge').style.display = 'none';

    // Clear OTP inputs
    clearOTPInputs();

    // Re-enable login button
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = false;
    loginBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
}

/**
 * Initialize OTP inputs (auto-focus next input)
 */
function initializeOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-input');

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
        });

        // Handle paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            if (pasted.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    document.getElementById('otp' + (i + 1)).value = pasted[i] || '';
                }
                otpInputs[5].focus();
            }
        });
    });
}

/**
 * Get OTP value from inputs
 */
function getOTPValue() {
    let otp = '';
    for (let i = 1; i <= 6; i++) {
        otp += document.getElementById('otp' + i).value;
    }
    return otp;
}

/**
 * Clear OTP inputs
 */
function clearOTPInputs() {
    for (let i = 1; i <= 6; i++) {
        document.getElementById('otp' + i).value = '';
    }
}

/**
 * Handle OTP verification
 */
async function handleOTPVerification(e) {
    e.preventDefault();

    const verifyBtn = document.getElementById('verifyBtn');
    const originalHTML = verifyBtn.innerHTML;
    const otp = getOTPValue();

    if (otp.length !== 6) {
        showToast('Please enter all 6 digits', 'error');
        return;
    }

    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    try {
        const response = await fetch('../api/verify-login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: loginEmail, otp: otp })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Login successful! Redirecting...', 'success');

            if (result.data && result.data.user) {
                const user = result.data.user;
                localStorage.setItem('user', JSON.stringify(user));
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userName', user.full_name || user.email);
                localStorage.setItem('userEmail', user.email);
            }

            setTimeout(() => {
                if (result.data && result.data.redirect) {
                    window.location.href = result.data.redirect;
                } else {
                    window.location.href = '../index.html';
                }
            }, 2000);
        } else {
            showToast(result.message, 'error');
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalHTML;
            clearOTPInputs();
            document.getElementById('otp1').focus();
        }
    } catch (error) {
        console.error('OTP verification error:', error);
        showToast('Failed to verify OTP. Please try again.', 'error');
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = originalHTML;
    }
}

/**
 * Resend OTP
 */
async function resendOTP() {
    showToast('Resending OTP...', 'success');

    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: loginEmail })
        });

        const result = await response.json();

        if (result.success) {
            showToast('OTP resent successfully!', 'success');
            clearOTPInputs();
            document.getElementById('otp1').focus();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Resend OTP error:', error);
        showToast('Failed to resend OTP', 'error');
    }
}

// showToast is provided globally by main.js
