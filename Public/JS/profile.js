document.addEventListener('DOMContentLoaded', () => {
    // Helper function to get a URL parameter
    const getUrlParameter = (name) => {
        name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
        const regex = new RegExp("[\\?&]" + name + "=([^&#]*)");
        const results = regex.exec(location.search);
        return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
    };

    const userId = getUrlParameter('user_id');
    // CORRECTED: The ID now matches the HTML element's ID
    const loadingOverlay = document.getElementById('loading-overlay'); 

    const showLoading = () => {
        if (loadingOverlay) {
            // Using the 'visible' class as defined in your CSS
            loadingOverlay.classList.add('visible'); 
        }
    };

    const hideLoading = () => {
        if (loadingOverlay) {
            // Removing the 'visible' class
            loadingOverlay.classList.remove('visible');
        }
    };
    
    const showSkeletonLoading = () => {
        const skeletonContainer = document.getElementById('skeleton-container');
        if (skeletonContainer) {
            skeletonContainer.classList.add('visible');
        }
    };
    
    const hideSkeletonLoading = () => {
        const skeletonContainer = document.getElementById('skeleton-container');
        if (skeletonContainer) {
            skeletonContainer.classList.remove('visible');
        }
    };

    // --- Part 1: Fetch and display the logged-in user's data for the header ---
    showSkeletonLoading(); // Show skeleton loading screen
    
    fetch('PHP/get_logged_in_user_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const navbarProfilePicture = document.getElementById('navbarProfilePicture');
            const navbarLocation = document.getElementById('navbarLocation');
            
            // Set the header profile picture and location from the logged-in user's data
            navbarProfilePicture.src = data.profile_url;
            navbarLocation.textContent = data.location;
        })
        .catch(error => {
            console.error('Error fetching header data:', error);
            // Fallback for when the logged-in user's data can't be fetched
            document.getElementById('navbarProfilePicture').src = 'https://placehold.co/150x150/png?text=P';
            document.getElementById('navbarLocation').textContent = 'N/A';
        })
        .finally(() => {
            // Don't hide skeleton loading here, wait for profile data
        });

    // --- Part 2: Fetch and display the public profile data from the URL ---
    if (userId) {
        fetch(`PHP/profile.php?user_id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error fetching user data:', data.error);
                    document.body.innerHTML = `<div style="text-align: center; padding: 50px;"><h2>Error</h2><p>${data.error}</p></div>`;
                    return;
                }

                // Populate the static HTML fields
                document.getElementById('profileName').textContent = `${data.first_name} ${data.last_name}`;
                document.getElementById('profilePosition').textContent = data.job_field || 'N/A';
                document.getElementById('profileBio').textContent = data.bio || 'Bio not available.';
                document.getElementById('profileAge').textContent = data.age || 'N/A';
                document.getElementById('profilegender').textContent = data.gender || 'N/A';
                document.getElementById('profileLocation').textContent = data.address || 'N/A';
                document.getElementById('profileEmail').textContent = data.email || 'N/A';

                // Render Profile Photo for the main profile page
                const mainProfilePicture = document.getElementById('mainProfilePicture');
                if (data.profile_url) {
                    mainProfilePicture.src = data.profile_url;
                } else {
                    mainProfilePicture.src = 'https://placehold.co/150x150/png?text=P';
                }

                // Render Skills
                const skillsContainer = document.getElementById('skillTagsContainer');
                skillsContainer.innerHTML = '';
                if (Array.isArray(data.skills) && data.skills.length > 0) {
                    data.skills.forEach(skill => {
                        const span = document.createElement('span');
                        span.className = 'skill-tag';
                        span.textContent = skill;
                        skillsContainer.appendChild(span);
                    });
                } else {
                    skillsContainer.innerHTML = '<span class="text-muted">No skills added yet.</span>';
                }

                // Render Languages
                const languagesList = document.getElementById('languagesList');
                languagesList.innerHTML = '';
                if (Array.isArray(data.languages) && data.languages.length > 0) {
                    data.languages.forEach(lang => {
                        const li = document.createElement('li');
                        li.textContent = lang;
                        languagesList.appendChild(li);
                    });
                } else {
                    languagesList.innerHTML = '<span class="text-muted">No languages added yet.</span>';
                }

                // Render Experience
                const experienceContainer = document.getElementById('experienceContainer');
                experienceContainer.innerHTML = '';
                if (Array.isArray(data.experience) && data.experience.length > 0) {
                    data.experience.forEach(exp => {
                        const div = document.createElement('div');
                        div.className = 'experience-item';
                        div.innerHTML = `
                            <i class="fas fa-briefcase icon"></i>
                            <div class="details">
                                <p class="position">${exp.position}</p>
                                <p class="company">${exp.company}</p>
                                <p class="duration">${exp.date}</p>
                            </div>
                        `;
                        experienceContainer.appendChild(div);
                    });
                } else {
                    experienceContainer.innerHTML = '<span class="text-muted">No experience added yet.</span>';
                }

                // Render Education
                const educationContainer = document.getElementById('educationContainer');
                educationContainer.innerHTML = '';
                if (Array.isArray(data.education) && data.education.length > 0) {
                    data.education.forEach(edu => {
                        const div = document.createElement('div');
                        div.className = 'education-item';
                        div.innerHTML = `
                            <i class="fas fa-graduation-cap icon"></i>
                            <div class="details">
                                <p class="degree">${edu.degree}</p>
                                <p class="college">${edu.institute}</p>
                                <p class="duration">${edu.years}</p>
                                <p class="duration">Percentage/CGPA: ${edu.percentage || 'N/A'}</p>
                            </div>
                        `;
                        educationContainer.appendChild(div);
                    });
                } else {
                    educationContainer.innerHTML = '<span class="text-muted">No education added yet.</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideSkeletonLoading();
                document.body.innerHTML = `<div style="text-align: center; padding: 50px;"><h2>Error</h2><p>Could not load profile data.</p></div>`;
            })
            .finally(() => {
                hideSkeletonLoading();
            });

        // This is the code for the contact button. It is now correctly placed
        // inside the single DOMContentLoaded listener.
        const contactButton = document.getElementById('contactButton');

        if (contactButton) {
            contactButton.addEventListener('click', function() {
                window.location.href = `../chat.html?user_id=${userId}`;
            });
        }
    } else {
        document.body.innerHTML = `<div style="text-align: center; padding: 50px;"><h2>Invalid Request</h2><p>Please specify a valid user ID in the URL, for example: profile.html?user_id=1</p></div>`;
        hideSkeletonLoading(); // Hide the skeleton loader if no user ID is found
    }
});