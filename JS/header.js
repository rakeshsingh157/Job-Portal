document.addEventListener('DOMContentLoaded', () => {
    const profileImage = document.getElementById('profile-image2');
    const locationInfo = document.querySelector('.location-info');

    fetch('PHP/get_user_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update profile image
                if (data.profile_url) {
                    profileImage.src = data.profile_url;
                    profileImage.style.display = 'block';
                } else {

                    profileImage.style.display = 'none';
                }

                // Update location info
                if (data.location) {
                 locationInfo.textContent = data.location  ;                      
                } else {
                    locationInfo.textContent = '';
                }
            } else {
                console.error('Failed to fetch user data:', data.error);

                profileImage.style.display = 'none';
                locationInfo.textContent = '';
            }
        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);

            profileImage.style.display = 'none';
            locationInfo.textContent = '';
        });
});