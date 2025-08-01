  document.addEventListener('DOMContentLoaded', () => {
        const signupForm = document.getElementById('signup-form');
        const otpForm = document.getElementById('otp-form');
        const signupContainer = document.getElementById('signup-container');
        const otpContainer = document.getElementById('otp-container');
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

        if (signupForm) {
            signupForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideMessage();
                showLoading(); // Show loading screen

                const companyName = document.getElementById('company-name').value;
                const email = document.getElementById('signup-email').value;
                const password = document.getElementById('signup-password').value;
                const phoneNumber = document.getElementById('phone-number').value;

                const confirmPassword = document.getElementById('confirm-password').value;
                if (password !== confirmPassword) {
                    showMessage('Passwords do not match.', 'error');
                    hideLoading(); // Hide loading screen
                    return;
                }

                const countryCode = '+91';

                const payload = {
                    action: 'send_otp',
                    email: email,
                    companyName: companyName,
                    
                    password: password,
                    countryCode: countryCode,
                    phoneNumber: phoneNumber
                };

                try {
                    const response = await fetch('email2.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (response.ok) {
                        showMessage(data.message, 'success');
                        signupContainer.style.display = 'none';
                        otpContainer.style.display = 'flex';
                        document.getElementById('email-otp').focus();
                    } else {
                        showMessage(data.message || 'Failed to send OTP.', 'error');
                    }
                } catch (error) {
                    console.error('Error sending OTP:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                } finally {
                    hideLoading(); // Hide loading screen
                }
            });
        }

        if (otpForm) {
            otpForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideMessage();
                showLoading(); // Show loading screen

                const email = document.getElementById('signup-email').value;
                const otpCodeEmail = document.getElementById('email-otp').value;
                const otpCodePhone = document.getElementById('phone-otp').value; // Get Phone OTP

                const payload = {
                    action: 'verify_otp',
                    email: email,
                    otpCodeEmail: otpCodeEmail,
                    otpCodePhone: otpCodePhone
                };

                try {
                    const response = await fetch('email2.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (response.ok) {
                        showMessage(data.message, 'success');
                        signupForm.reset();
                        otpForm.reset();
                        signupContainer.style.display = 'flex';
                        otpContainer.style.display = 'none';
                    } else {
                        showMessage(data.message || 'OTP verification failed.', 'error');
                    }
                } catch (error) {
                    console.error('Error verifying OTP:', error);
                    showMessage('An error occurred during verification. Please try again.', 'error');
                } finally {
                    hideLoading(); // Hide loading screen
                }
            });
        }
    });