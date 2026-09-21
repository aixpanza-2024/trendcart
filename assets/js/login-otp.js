/**
 * Login with OTP - JavaScript
 * Timeline stepper login. Accepts an email or a 10-digit mobile number;
 * the OTP arrives by email or SMS. Resend is locked for 2 minutes after each send.
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeLogin();
});

const RESEND_SECONDS_DEFAULT = 120;

// {email: ...} or {phone: ...}, exactly as normalised by the server
let loginPayload = null;
let resendTimerId = null;

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
            if (resendOtpLink.classList.contains('disabled')) return;
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
    const value = document.getElementById('loginEmail').value.trim();

    if (!value) {
        showToast('Please enter your email or mobile number', 'error');
        return;
    }

    const payload = value.includes('@') ? { email: value } : { phone: value };

    loginBtn.disabled = true;
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';

    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        const data = result.data || {};

        // 429 with the email/phone echoed back = a code was sent moments ago; carry on to step 2
        // with the time left. Any other 429 (rate limit) falls through to the error message.
        const alreadySent = response.status === 429 && data.retry_after && (data.phone || data.email);

        if (result.success || alreadySent) {
            loginPayload = data.phone ? { phone: data.phone } : { email: data.email };

            if (alreadySent) {
                showToast('A code was already sent. You can request a new one when the timer ends.', 'success');
            } else {
                showToast(loginPayload.phone ? 'OTP sent to your mobile!' : 'OTP sent! Check your inbox.', 'success');
            }

            goToStep2(loginPayload, alreadySent ? data.retry_after : (data.resend_after || RESEND_SECONDS_DEFAULT));
            if (fillDebugOtp(result)) {
                autoSubmitOtp();
            }
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
function goToStep2(payload, resendSeconds) {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const progressLine = document.getElementById('progressLine');
    const isPhone = !!payload.phone;

    // Mark step 1 as completed
    step1.classList.remove('active');
    step1.classList.add('completed');
    step1.querySelector('.step-dot').innerHTML = '<i class="fas fa-check" style="font-size:0.7rem"></i>';

    // Show completed email / mobile, hide form
    document.getElementById('emailFormContent').style.display = 'none';
    document.getElementById('emailCompleted').style.display = 'block';
    document.getElementById('completedEmailText').textContent = isPhone ? '+91 ' + payload.phone : payload.email;

    // The "check your spam folder" tip only applies to email
    const spamNote = document.getElementById('emailSpamNote');
    if (spamNote) spamNote.style.display = isPhone ? 'none' : '';

    // Animate progress line
    progressLine.style.height = '100%';

    // Activate step 2
    step2.classList.add('active');

    startResendTimer(resendSeconds);

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
    clearInterval(resendTimerId);

    // Reset progress line
    progressLine.style.height = '0';

    // Reset step 1
    step1.classList.remove('completed');
    step1.classList.add('active');
    step1.querySelector('.step-dot').textContent = '1';

    // Show form, hide completed
    document.getElementById('emailFormContent').style.display = 'block';
    document.getElementById('emailCompleted').style.display = 'none';

    // Clear OTP inputs
    clearOTPInputs();

    // Re-enable login button
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = false;
    loginBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
}

/**
 * Lock the Resend link and count down. Uses an end timestamp rather than
 * counting ticks, so the timer stays correct when a phone throttles background tabs.
 */
function startResendTimer(seconds) {
    const link = document.getElementById('resendOtp');
    const label = document.getElementById('resendOtpLabel');
    if (!link || !label) return;

    clearInterval(resendTimerId);

    const endAt = Date.now() + seconds * 1000;
    link.classList.add('disabled');
    link.setAttribute('aria-disabled', 'true');

    const tick = () => {
        const remaining = Math.ceil((endAt - Date.now()) / 1000);

        if (remaining <= 0) {
            clearInterval(resendTimerId);
            link.classList.remove('disabled');
            link.removeAttribute('aria-disabled');
            label.textContent = 'Resend OTP';
            return;
        }

        const mins = Math.floor(remaining / 60);
        const secs = String(remaining % 60).padStart(2, '0');
        label.textContent = 'Resend OTP in ' + mins + ':' + secs;
    };

    tick();
    resendTimerId = setInterval(tick, 1000);
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
            body: JSON.stringify(Object.assign({}, loginPayload, { otp: otp }))
        });

        const result = await response.json();

        if (result.success) {
            showToast('Verified! Logging you in...', 'success');

            if (result.data && result.data.user) {
                const user = result.data.user;
                const expiry = Date.now() + 365 * 24 * 60 * 60 * 1000; // 365 days
                // Display name: full_name if set, otherwise email prefix, otherwise the mobile number
                const displayName = (user.full_name && user.full_name.trim())
                    ? user.full_name.trim()
                    : (user.email ? user.email.split('@')[0] : (user.phone || 'User'));
                localStorage.setItem('user', JSON.stringify(user));
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userName', displayName);
                localStorage.setItem('userEmail', user.email || '');
                localStorage.setItem('loginExpiry', String(expiry));
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
 * Resend OTP (the link is locked until the 2-minute timer ends)
 */
async function resendOTP() {
    // Lock immediately so a double click cannot send two requests
    startResendTimer(RESEND_SECONDS_DEFAULT);
    showToast('Resending OTP...', 'success');

    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(loginPayload)
        });

        const result = await response.json();
        const data = result.data || {};

        if (result.success) {
            showToast('OTP resent successfully!', 'success');
            startResendTimer(data.resend_after || RESEND_SECONDS_DEFAULT);
            clearOTPInputs();
            fillDebugOtp(result);
            document.getElementById('otp1').focus();
        } else if (response.status === 429 && data.retry_after) {
            startResendTimer(data.retry_after);
            showToast(result.message, 'error');
        } else {
            // Nothing was sent, so release the lock
            startResendTimer(0);
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Resend OTP error:', error);
        startResendTimer(0);
        showToast('Failed to resend OTP', 'error');
    }
}

/**
 * Test servers only: when the API returns debug_otp (the server opted in for
 * this email or mobile number), fill the OTP boxes so the login can be completed
 * without email or SMS. Returns true when the boxes were filled.
 */
function fillDebugOtp(result) {
    const otp = result && result.data && result.data.debug_otp;
    if (!otp || !/^\d{6}$/.test(otp)) return false;
    otp.split('').forEach((digit, i) => {
        const box = document.getElementById('otp' + (i + 1));
        if (box) box.value = digit;
    });
    showToast('TEST MODE: OTP ' + otp + ' filled in', 'success');
    return true;
}

/**
 * Test servers only: the OTP was filled in for us, so verify it straight away
 * (auto login). The short delay lets the step change and toast be seen.
 */
function autoSubmitOtp() {
    const form = document.getElementById('verifyOtpForm');
    if (!form) return;
    setTimeout(() => {
        if (form.requestSubmit) {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    }, 700);
}

// showToast is provided globally by main.js
