
document.addEventListener('DOMContentLoaded', () => {

    const mainLoginContainer = document.getElementById('main-login-container');
    const loginSection = document.getElementById('login-section');
    const recoverEmailSection = document.getElementById('recover-email-section');
    const verifyOtpSection = document.getElementById('verify-otp-section');
    const resetPasswordSection = document.getElementById('reset-password-section');
    const loadingOverlay = document.getElementById('loading-overlay');
    const messageDiv = document.getElementById('form-message') || document.createElement('div');

    if (!document.getElementById('form-message')) {
        messageDiv.id = 'form-message';
        document.body.appendChild(messageDiv);
    }

    function showLoading() {
        loadingOverlay.classList.add('visible');
    }

    function hideLoading() {
        loadingOverlay.classList.remove('visible');
    }

    function showMessage(msg, type) {
        messageDiv.textContent = msg;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }

    function hideMessage() {
        messageDiv.style.display = 'none';
    }

    function navigateToSection(section) {
        const sections = [loginSection, recoverEmailSection, verifyOtpSection, resetPasswordSection];
        sections.forEach(sec => {
            if (sec) {
                sec.style.display = 'none';
            }
        });
        if (section) {
            section.style.display = 'flex';
        }
    }

    document.getElementById('recover-back-to-login')?.addEventListener('click', (event) => {
        event.preventDefault();
        navigateToSection(loginSection);
    });

    document.getElementById('verify-back-to-recover')?.addEventListener('click', (event) => {
        event.preventDefault();
        navigateToSection(recoverEmailSection);
    });

    document.getElementById('reset-back-to-login')?.addEventListener('click', (event) => {
        event.preventDefault();
        navigateToSection(loginSection);
    });

    document.getElementById('forgot-password-link')?.addEventListener('click', (event) => {
        event.preventDefault();
        navigateToSection(recoverEmailSection);
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideMessage();
            showLoading();

            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;

            const payload = {
                action: 'login',
                email: email,
                password: password
            };

            try {
                // Corrected path for login form
                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (response.ok) {
                    showMessage(data.message, 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showMessage(data.message || 'Login failed.', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showMessage('An error occurred during login. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }

    const recoverEmailForm = document.getElementById('recover-email-form');
    if (recoverEmailForm) {
        recoverEmailForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideMessage();
            showLoading();

            const email = document.getElementById('recover-email').value;

            const payload = {
                action: 'send_reset_otp',
                email: email
            };

            try {
                
                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (response.ok) {
                    showMessage(data.message, 'success');
                    document.getElementById('verify-otp-email').value = email;
                    navigateToSection(verifyOtpSection);
                    document.getElementById('email-otp').focus();
                } else {
                    showMessage(data.message || 'Failed to send reset OTP.', 'error');
                }
            } catch (error) {
                console.error('Error sending reset OTP:', error);
                showMessage('An error occurred. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }

    const verifyOtpForm = document.getElementById('verify-otp-form');
    if (verifyOtpForm) {
        verifyOtpForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideMessage();
            showLoading();

            const email = document.getElementById('verify-otp-email').value;
            const otpCodeEmail = document.getElementById('email-otp').value;

            if (!email || !otpCodeEmail) {
                showMessage('Email and OTP are required.', 'error');
                hideLoading();
                return;
            }

            const payload = {
                action: 'verify_reset_otp',
                email: email,
                otpCodeEmail: otpCodeEmail
            };

            try {

                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (response.ok) {
                    showMessage(data.message, 'success');
                    navigateToSection(resetPasswordSection);
                    document.getElementById('new-password').focus();
                } else {
                    showMessage(data.message || 'OTP verification failed.', 'error');
                }
            } catch (error) {
                console.error('Error verifying OTP:', error);
                showMessage('An error occurred during verification. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }

    const resetPasswordForm = document.getElementById('reset-password-form');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideMessage();
            showLoading();

            const email = document.getElementById('verify-otp-email').value;

            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-new-password').value;

            if (newPassword !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                hideLoading();
                return;
            }

            const payload = {
                action: 'reset_password',
                email: email,
                newPassword: newPassword
            };

            try {
                
                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (response.ok) {
                    showMessage(data.message, 'success');
                    resetPasswordForm.reset();
                    navigateToSection(loginSection);
                    document.getElementById('verify-otp-email').value = '';
                } else {
                    showMessage(data.message || 'Password reset failed.', 'error');
                }
            } catch (error) {
                console.error('Error resetting password:', error);
                showMessage('An error occurred during password reset. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }

});