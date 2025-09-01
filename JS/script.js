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

    /**
     * Shows the loading overlay.
     */
    function showLoading() {
        loadingOverlay.classList.add('visible');
    }

    /**
     * Hides the loading overlay.
     */
    function hideLoading() {
        loadingOverlay.classList.remove('visible');
    }

    /**
     * Displays a message to the user.
     * @param {string} msg The message to display.
     * @param {'success'|'error'} type The type of message (for styling).
     */
    function showMessage(msg, type) {
        messageDiv.textContent = msg;
        messageDiv.className = `message ${type}`; // Apply class for styling (e.g., green for success, red for error)
        messageDiv.style.display = 'block';
        setTimeout(() => {
            messageDiv.style.display = 'none'; // Hide message after 5 seconds
        }, 5000);
    }

    /**
     * Hides the message display div.
     */
    function hideMessage() {
        messageDiv.style.display = 'none';
    }

    /**
     * Navigates between signup and OTP sections.
     * @param {HTMLElement} sectionToShow The section to display.
     */
    function navigateToSection(sectionToShow) {
        const sections = [signupContainer, otpContainer];
        sections.forEach(sec => {
            if (sec) {
                sec.style.display = 'none';
            }
        });
        if (sectionToShow) {
            sectionToShow.style.display = 'flex'; // Use 'flex' as per your HTML structure
        }
    }

    // Function to handle the first signup form submission (sending OTPs)
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

            // Client-side password confirmation check
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
                const response = await fetch('PHP/email.php', { // Path to your PHP backend
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) { // Check if HTTP status is 2xx
                    showMessage(result.message, 'success');
                    // Store the email in a temporary hidden field if needed for OTP verification
                    // (Though for signup, it's usually passed via session on the backend)
                    // You might need a hidden input in otp-form if email isn't session-managed for verification
                    // For now, assuming email is handled by session for OTP verification in PHP
                    navigateToSection(otpContainer); // Switch to the OTP verification container
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

    // Function to handle the OTP verification form submission
    if (otpForm) {
        otpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideMessage();
            showLoading();

            // Assuming the email for OTP verification is either available globally (e.g., from a hidden input
            // or if stored in session on backend and not strictly needed from client for this step).
            // For robustness, you might add a hidden input in otp-container for the email.
            // For now, we'll assume the email used for sending OTP is the one to verify against.
            // If you need it from the client, you'd add:
            // const email = document.getElementById('signup-email').value; // Or a hidden field in OTP form

            const email = document.getElementById('signup-email').value; // Get email from the signup form's field
            const otpCodeEmail = document.getElementById('email-otp').value;
            const otpCodePhone = document.getElementById('phone-otp').value;

            if (!email || !otpCodeEmail || !otpCodePhone) {
                showMessage('All OTPs and email are required.', 'error');
                hideLoading();
                return;
            }

            const payload = {
                action: 'verify_otp', // Action for verifying signup OTPs
                email: email, // Pass email for backend to match against session
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
                    // Redirect to login page or a success page after successful verification
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

    // Add a message div if it doesn't exist (for showMessage function)
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
