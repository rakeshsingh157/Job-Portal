 document.addEventListener('DOMContentLoaded', () => {
            // Get step containers
            const mainLoginContainer = document.getElementById('main-login-container'); // The main .container wrapper
            const loginSection = document.getElementById('login-section');
            const recoverEmailSection = document.getElementById('recover-email-section');
            const verifyOtpSection = document.getElementById('verify-otp-section');
            const resetPasswordSection = document.getElementById('reset-password-section');

            // Get forms
            const loginForm = document.getElementById('login-form');
            const recoverEmailForm = document.getElementById('recover-email-form');
            const verifyOtpForm = document.getElementById('verify-otp-form');
            const resetPasswordForm = document.getElementById('reset-password-form');

            // Get links
            const signupLink = document.getElementById('signup-link');
            const forgotPasswordLink = document.getElementById('forgot-password-link');
            const recoverBackToLoginLink = document.getElementById('recover-back-to-login');
            const verifyBackToRecoverLink = document.getElementById('verify-back-to-recover');
            const resetBackToLoginLink = document.getElementById('reset-back-to-login');

            // Get loading overlay and message div
            const loadingOverlay = document.getElementById('loading-overlay');
            const messageDiv = document.getElementById('form-message');

            // --- Helper Functions ---
            function showLoading() {
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex'; // Use flex to center spinner
                    loadingOverlay.style.visibility = 'visible';
                    loadingOverlay.style.opacity = '1';
                }
            }

            function hideLoading() {
                if (loadingOverlay) {
                    loadingOverlay.style.visibility = 'hidden';
                    loadingOverlay.style.opacity = '0';
                    // Optional: Set display to 'none' after transition if needed, to truly remove from layout flow
                    setTimeout(() => {
                        loadingOverlay.style.display = 'none';
                    }, 300); // Match CSS transition duration
                }
            }

            function showMessage(msg, type) {
                if (messageDiv) {
                    messageDiv.textContent = msg;
                    messageDiv.className = `message ${type}`; // Apply CSS classes for styling
                    messageDiv.style.display = 'block';
                    setTimeout(() => {
                        hideMessage();
                    }, 5000); // Hide after 5 seconds
                }
            }

            function hideMessage() {
                if (messageDiv) {
                    messageDiv.style.display = 'none';
                }
            }

            function showStep(stepElement) {
                // Hide all right sections
                [loginSection, recoverEmailSection, verifyOtpSection, resetPasswordSection].forEach(section => {
                    if (section) {
                        section.style.display = 'none';
                        section.classList.remove('active-step'); // Remove active class if you use it for styling
                    }
                });
                // Show the requested step section
                if (stepElement) {
                    stepElement.style.display = 'flex'; // Assuming .right uses display:flex or block
                    stepElement.classList.add('active-step');
                }
                hideMessage(); 
            }

            showStep(loginSection);

            if (forgotPasswordLink) {
                forgotPasswordLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (mainLoginContainer) {
                         mainLoginContainer.style.display = 'flex'; 
                             }
                    showStep(recoverEmailSection);
                    recoverEmailForm.reset(); 
                    const recoverEmailInput = document.getElementById('recover-email');
                    if (recoverEmailInput) {
                        recoverEmailInput.focus();
                    }
                });
            }

         
            if (recoverBackToLoginLink) {
                recoverBackToLoginLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    showStep(loginSection); 
                    loginForm.reset(); 
                    const loginEmailInput = document.getElementById('login-email');
                    if (loginEmailInput) {
                        loginEmailInput.focus();
                    }
                });
            }

            if (verifyBackToRecoverLink) {
                verifyBackToRecoverLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    showStep(recoverEmailSection); 
                    verifyOtpForm.reset();
                    const recoverEmailInput = document.getElementById('recover-email');
                    if (recoverEmailInput) {
                        recoverEmailInput.focus(); 
                    }
                });
            }

            if (resetBackToLoginLink) {
                resetBackToLoginLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    showStep(loginSection); 
                    resetPasswordForm.reset();
                    const loginEmailInput = document.getElementById('login-email');
                    if (loginEmailInput) {
                        loginEmailInput.focus();
                    }
                });
            }

            if (signupLink) {
                signupLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.location.href = 'recruitersignup.html';
                });
            }


           
            if (loginForm) {
                loginForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    hideMessage();
                    showLoading();

                    const email = document.getElementById('login-email').value;
                    const password = document.getElementById('login-password').value;

                    try {
                        const response = await fetch('login_reset_backend.php', { 
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email, password, action: 'login' })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            showMessage(data.message || 'Login successful!', 'success');
                            
                            window.location.href = 'verify_company.php';
                        } else {
                            showMessage(data.message || 'Login failed. Please check your credentials.', 'error');
                        }
                    } catch (error) {
                        console.error('Login request error:', error);
                        showMessage('An error occurred during login. Please try again.', 'error');
                    } finally {
                        hideLoading();
                    }
                });
            }

            if (recoverEmailForm) {
                recoverEmailForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    hideMessage();
                    showLoading();

                    const email = document.getElementById('recover-email').value;

                    sessionStorage.setItem('reset_email_for_otp', email);

                    try {
                        const response = await fetch('login_reset_backend.php', { 
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email, action: 'send_reset_otp' })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            showMessage(data.message || 'OTP sent to your email!', 'success');
                            showStep(verifyOtpSection);
                            verifyOtpForm.reset();
                            const emailOtpInput = document.getElementById('email-otp');
                            if (emailOtpInput) {
                                emailOtpInput.focus();
                            }
                        } else {
                            showMessage(data.message || 'Failed to send OTP. Email might not be registered.', 'error');
                        }
                    } catch (error) {
                        console.error('Forgot password OTP request error:', error);
                        showMessage('An error occurred. Please try again.', 'error');
                    } finally {
                        hideLoading();
                    }
                });
            }

            if (verifyOtpForm) {
                verifyOtpForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    hideMessage();
                    showLoading();

                    const email = sessionStorage.getItem('reset_email_for_otp');
                    const otpCode = document.getElementById('email-otp').value;

                    if (!email) {
                        showMessage('Email not found for verification. Please go back and re-enter your email.', 'error');
                        hideLoading();
                        showStep(recoverEmailSection);
                        return;
                    }

                    try {
                        const response = await fetch('login_reset_backend.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email, otpCodeEmail: otpCode, action: 'verify_reset_otp' })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            showMessage(data.message || 'OTP verified! Proceed to set new password.', 'success');
                            showStep(resetPasswordSection);
                            resetPasswordForm.reset();
                            const newPasswordInput = document.getElementById('new-password');
                            if (newPasswordInput) {
                                newPasswordInput.focus();
                            }
                        } else {
                            showMessage(data.message || 'Invalid OTP. Please try again.', 'error');
                        }
                    } catch (error) {
                        console.error('OTP verification error:', error);
                        showMessage('An error occurred during verification. Please try again.', 'error');
                    } finally {
                        hideLoading();
                    }
                });
            }

            // Reset Password Form Submission
            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    hideMessage();
                    showLoading();

                    const email = sessionStorage.getItem('reset_email_for_otp');
                    const newPassword = document.getElementById('new-password').value;
                    const confirmNewPassword = document.getElementById('confirm-new-password').value;

                    if (newPassword !== confirmNewPassword) {
                        showMessage('New passwords do not match.', 'error');
                        hideLoading();
                        return;
                    }

                    if (!email) {
                        showMessage('Email not found. Please restart the password reset process.', 'error');
                        hideLoading();
                        showStep(recoverEmailSection);
                        return;
                    }

                    try {
                        const response = await fetch('login_reset_backend.php', { 
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email, newPassword, action: 'reset_password' })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            showMessage(data.message || 'Password successfully reset! You can now sign in.', 'success');
                            sessionStorage.removeItem('reset_email_for_otp'); 
                            showStep(loginSection); 
                            loginForm.reset();
                        } else {
                            showMessage(data.message || 'Failed to reset password.', 'error');
                        }
                    } catch (error) {
                        console.error('Password reset request error:', error);
                        showMessage('An error occurred during password reset. Please try again.', 'error');
                    } finally {
                        hideLoading();
                    }
                });
            }
        });