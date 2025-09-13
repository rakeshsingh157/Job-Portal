$(document).ready(function() {
    const loginForm = $('#login-form');
    const loadingOverlay = $('#loading-overlay');
    const formMessage = $('#form-message');

    loginForm.on('submit', function(e) {
        e.preventDefault();

        loadingOverlay.addClass('visible');
        formMessage.text('').removeClass('success error');

        const email = $('#login-email').val();
        const password = $('#login-password').val();

        $.ajax({
            url: 'login.php',
            type: 'POST',
            data: { 
                email: email, 
                password: password 
            },
            dataType: 'json',
            success: function(response) {
                loadingOverlay.removeClass('visible');
                
                if (response.success) {
                    formMessage.text(response.message).addClass('success');
                    window.location.href = 'admin.php'; // Redirect to the secured admin page
                } else {
                    formMessage.text(response.message).addClass('error');
                }
            },
            error: function(xhr, status, error) {
                loadingOverlay.removeClass('visible');
                formMessage.text('An error occurred. Please try again later.').addClass('error');
                console.error("AJAX Error: ", status, error);
            }
        });
    });
});