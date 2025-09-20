document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signup-form');
    const otpForm = document.getElementById('otp-form');
    const signupContainer = document.getElementById('signup-container');
    const otpContainer = document.getElementById('otp-container');
    const loadingOverlay = document.getElementById('loading-overlay');

    // Get or create the message display div
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


    function navigateToSection(sectionToShow) {
        const sections = [signupContainer, otpContainer];
        sections.forEach(sec => {
            if (sec) {
                sec.style.display = 'none';
            }
        });
        if (sectionToShow) {
            sectionToShow.style.display = 'flex';
        }
    }


    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideMessage();
            showLoading();

            const firstName = document.getElementById('first-name').value;
            const lastName = document.getElementById('last-name').value;
            const email = document.getElementById('signup-email').value;
            const password = document.getElementById('signup-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const phoneNumber = document.getElementById('phone-number').value;


            if (password !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                hideLoading();
                return;
            }

            const payload = {
                action: 'send_otp',
                firstName,
                lastName,
                email,
                password,
                phoneNumber
            };

            try {
                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    showMessage(result.message, 'success');

                    navigateToSection(otpContainer);
                } else {
                    showMessage(result.message || 'Failed to send OTPs.', 'error');
                }
            } catch (error) {
                console.error('Error sending signup OTP:', error);
                showMessage('An unexpected error occurred. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }


    if (otpForm) {
        otpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideMessage();
            showLoading();



            const email = document.getElementById('signup-email').value;
            const otpCodeEmail = document.getElementById('email-otp').value;
            const otpCodePhone = document.getElementById('phone-otp').value;

            if (!email || !otpCodeEmail || !otpCodePhone) {
                showMessage('All OTPs and email are required.', 'error');
                hideLoading();
                return;
            }

            const payload = {
                action: 'verify_otp',
                email: email,
                otpCodeEmail: otpCodeEmail,
                otpCodePhone: otpCodePhone
            };

            try {
                const response = await fetch('PHP/email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    showMessage(result.message, 'success');

                    window.location.href = 'wokersignin.html';
                } else {
                    showMessage(result.message || 'OTP verification failed.', 'error');
                }
            } catch (error) {
                console.error('Error verifying signup OTP:', error);
                showMessage('An unexpected error occurred during OTP verification. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        });
    }


    if (!document.getElementById('form-message-signup')) {
        const msgDiv = document.createElement('div');
        msgDiv.id = 'form-message-signup';
        msgDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-align: center;
        `;
        document.body.appendChild(msgDiv);
    }
});
