/**
 * Registration with OTP - JavaScript
 * Handles two-step registration process with shop photo upload
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeRegistration();
});

let registrationEmail = '';

function initializeRegistration() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistration);
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
            showRegistrationForm();
        });
    }
}

/**
 * Handle registration form submission
 */
async function handleRegistration(e) {
    e.preventDefault();

    const registerBtn = document.getElementById('registerBtn');
    const originalHTML = registerBtn.innerHTML;

    const fullName = document.getElementById('registerName').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const phone = document.getElementById('registerPhone').value.trim();
    const termsAccepted = document.getElementById('termsConditions').checked;

    if (!termsAccepted) {
        showToast('Please accept the terms and conditions', 'error');
        return;
    }

    registerBtn.disabled = true;
    registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';

    const data = {
        email: email,
        full_name: fullName,
        phone: phone,
        user_type: 'customer'
    };

    try {
        const response = await fetch('../api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            registrationEmail = email;
            showToast(result.message, 'success');

            showOTPForm(email);
        } else {
            showToast(result.message, 'error');
            registerBtn.disabled = false;
            registerBtn.innerHTML = originalHTML;
        }
    } catch (error) {
        console.error('Registration error:', error);
        showToast('Failed to send OTP. Please try again.', 'error');
        registerBtn.disabled = false;
        registerBtn.innerHTML = originalHTML;
    }
}

/**
 * Show OTP verification form
 */
function showOTPForm(email) {
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('otpForm').style.display = 'block';

    document.getElementById('step1-indicator').classList.remove('active');
    document.getElementById('step1-indicator').classList.add('completed');
    document.getElementById('step2-indicator').classList.add('active');

    document.getElementById('otpEmail').textContent = email;
    document.getElementById('otp1').focus();
}

/**
 * Show registration form (go back)
 */
function showRegistrationForm() {
    document.getElementById('registerForm').style.display = 'block';
    document.getElementById('otpForm').style.display = 'none';

    document.getElementById('step1-indicator').classList.add('active');
    document.getElementById('step1-indicator').classList.remove('completed');
    document.getElementById('step2-indicator').classList.remove('active');

    clearOTPInputs();

    const registerBtn = document.getElementById('registerBtn');
    registerBtn.disabled = false;
    registerBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
}

/**
 * Initialize OTP inputs
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

function getOTPValue() {
    let otp = '';
    for (let i = 1; i <= 6; i++) otp += document.getElementById('otp' + i).value;
    return otp;
}

function clearOTPInputs() {
    for (let i = 1; i <= 6; i++) document.getElementById('otp' + i).value = '';
}

/**
 * Handle OTP verification — sends photo if shop owner
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
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';

    const data = {
        email:     registrationEmail,
        otp:       otp,
        full_name: document.getElementById('registerName').value.trim(),
        phone:     document.getElementById('registerPhone').value.trim(),
        user_type: 'customer'
    };

    try {
        const response = await fetch('../api/verify-registration.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Registration successful! Redirecting...', 'success');

            // Set localStorage auth state
            if (result.data && result.data.user) {
                const user = result.data.user;
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userName', user.full_name || user.email);
                localStorage.setItem('userEmail', user.email);
                localStorage.setItem('user', JSON.stringify(user));
            }

            setTimeout(() => {
                window.location.href = result.data.redirect;
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

    const data = {
        email: registrationEmail,
        full_name: document.getElementById('registerName').value.trim(),
        phone: document.getElementById('registerPhone').value.trim(),
        user_type: 'customer'
    };

    try {
        const response = await fetch('../api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
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
