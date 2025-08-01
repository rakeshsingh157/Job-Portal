document.addEventListener('DOMContentLoaded', () => {
    const mainLoginContainer = document.getElementById('main-login-container');
    const loginSection = document.getElementById('login-section');
    const recoverEmailSection = document.getElementById('recover-email-section');
    const verifyOtpSection = document.getElementById('verify-otp-section');
    const resetPasswordSection = document.getElementById('reset-password-section');

    const loginForm = document.getElementById('login-form');
    const recoverEmailForm = document.getElementById('recover-email-form');
    const verifyOtpForm = document.getElementById('verify-otp-form');
    const resetPasswordForm = document.getElementById('reset-password-form');

    const signupLink = document.getElementById('signup-link');
    const forgotPasswordLink = document.getElementById('forgot-password-link');
    const recoverBackToLoginLink = document.getElementById('recover-back-to-login');
    const verifyBackToRecoverLink = document.getElementById('verify-back-to-recover');
    const resetBackToLoginLink = document.getElementById('reset-back-to-login');

    const loadingOverlay = document.getElementById('loading-overlay');
    const messageDiv = document.getElementById('form-message');

    const sections = [loginSection, recoverEmailSection, verifyOtpSection, resetPasswordSection];

    function showLoading() {
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.visibility = 'visible';
            loadingOverlay.style.opacity = '1';
        }
    }

    function hideLoading() {
        if (loadingOverlay) {
            loadingOverlay.style.visibility = 'hidden';
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 300);
        }
    }

    function showMessage(msg, type) {
        if (messageDiv) {
            messageDiv.textContent = msg;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
            setTimeout(() => {
                hideMessage();
            }, 5000);
            
        }
    }

    function hideMessage() {
        if (messageDiv) {
            messageDiv.style.display = 'none';
            

        }
    }

    function showStep(stepElement) {
        sections.forEach(section => {
            if (section) {
                section.style.display = 'none';
                section.classList.remove('active-step');
            }
        });
        if (stepElement) {
            stepElement.style.display = 'flex';
            stepElement.classList.add('active-step');
        }
        hideMessage();
    }

    showStep(loginSection);

    forgotPasswordLink?.addEventListener('click', (e) => {
        e.preventDefault();
        mainLoginContainer && (mainLoginContainer.style.display = 'flex');
        showStep(recoverEmailSection);
        recoverEmailForm.reset();
        document.getElementById('recover-email')?.focus();
    });

    recoverBackToLoginLink?.addEventListener('click', (e) => {
        e.preventDefault();
        showStep(loginSection);
        loginForm.reset();
        document.getElementById('login-email')?.focus();
    });

    verifyBackToRecoverLink?.addEventListener('click', (e) => {
        e.preventDefault();
        showStep(recoverEmailSection);
        verifyOtpForm.reset();
        document.getElementById('recover-email')?.focus();
    });

    resetBackToLoginLink?.addEventListener('click', (e) => {
        e.preventDefault();
        showStep(loginSection);
        resetPasswordForm.reset();
        document.getElementById('login-email')?.focus();
    });

    signupLink?.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'wokersignup.html';
    });

    async function handleSubmit(event, action, successCallback, errorCallback, emailToStore = null) {
        event.preventDefault();
        hideMessage();
        showLoading();

        const formData = {};
        Array.from(event.target.elements).forEach(element => {
            if (element.name) {
                formData[element.name] = element.value;
            }
        });
        formData.action = action;

        if (emailToStore) {
            sessionStorage.setItem('reset_email_for_otp', emailToStore);
        } else if (action === 'verify_reset_otp' || action === 'reset_password') {
            formData.email = sessionStorage.getItem('reset_email_for_otp');
            if (!formData.email) {
                showMessage('Email not found. Please restart the process.', 'error');
                hideLoading();
                showStep(recoverEmailSection);
                return;
            }
        }
        
        if (action === 'reset_password' && formData['new-password'] !== formData['confirm-new-password']) {
            showMessage('New passwords do not match.', 'error');
            hideLoading();
            return;
        }

        try {
            const response = await fetch('email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            if (response.redirected) {
                console.log("PHP initiated a redirect to: ", response.url);
                hideLoading();
                return;
            }

            const data = await response.json();

            if (response.ok) {
                successCallback(data);
            } else {
                errorCallback(data);
            }
        } catch (error) {
            console.error('Request error:', error);
            showMessage('An error occurred. Please try again.', 'error');
        } finally {
            hideLoading();
        }
    }

    loginForm?.addEventListener('submit', (e) => {
        handleSubmit(e, 'login',
            (data) => {
                showMessage(data.message || 'Login successful!', 'success');
                
                window.location.href = 'verify.php';
            },
            (data) => showMessage(data.message || 'Login failed. Please check your credentials.', 'error')
        );
    });

    recoverEmailForm?.addEventListener('submit', (e) => {
        const email = document.getElementById('recover-email').value;
        handleSubmit(e, 'send_reset_otp',
            (data) => {
                showMessage(data.message || 'OTP sent to your email!', 'success');
                showStep(verifyOtpSection);
                verifyOtpForm.reset();
                document.getElementById('email-otp')?.focus();
            },
            (data) => showMessage(data.message || 'Failed to send OTP. Email might not be registered.', 'error'),
            email
        );
    });

    verifyOtpForm?.addEventListener('submit', (e) => {
        handleSubmit(e, 'verify_reset_otp',
            (data) => {
                showMessage(data.message || 'OTP verified! Proceed to set new password.', 'success');
                showStep(resetPasswordSection);
                resetPasswordForm.reset();
                document.getElementById('new-password')?.focus();
            },
            (data) => showMessage(data.message || 'Invalid OTP. Please try again.', 'error')
        );
    });

    resetPasswordForm?.addEventListener('submit', (e) => {
        handleSubmit(e, 'reset_password',
            (data) => {
                showMessage(data.message || 'Password successfully reset! You can now sign in.', 'success');
                sessionStorage.removeItem('reset_email_for_otp');
                showStep(loginSection);
                loginForm.reset();
            },
            (data) => showMessage(data.message || 'Failed to reset password.', 'error')
        );
    });
});

window.addEventListener('load', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const errorMessage = urlParams.get('error');
    const messageDiv = document.getElementById('form-message');

    if (errorMessage && messageDiv) {
        const displayMessages = {
            'missing_credentials': 'Email and password are required.',
            'not_verified': 'Account not verified. Please verify your email and phone number.',
            'invalid_credentials': 'Invalid email or password.',
            'db_connection_failed': 'Internal server error: Database connection failed.',
            'password_update_failed': 'Login failed. Failed to update password during rehash.',
            'unknown_error': 'An unknown error occurred. Please try again.'
        };
        messageDiv.textContent = displayMessages[errorMessage] || 'An error occurred. Please try again.';
        messageDiv.className = 'message error';
        messageDiv.style.display = 'block';
        // history.replaceState(null, '', window.location.pathname);
    }
});