document.addEventListener('DOMContentLoaded', () => {
    // Function to fetch and display data for the currently logged-in user in the header
    function loadLoggedInUserData() {
        // Fetch data from the PHP script that gets session user data
        fetch('PHP/get_logged_in_user_data.php')
            .then(response => response.json())
            .then(data => {
                // Check if the request was successful
                if (data.success) {
                    const locationText = document.getElementById('location-text');
                    const profileImage = document.getElementById('profile-image2');

                    // Update the location text
                    if (locationText) {
                        locationText.textContent = data.location;
                    }
                    // Update the profile image source and make it visible
                    if (profileImage) {
                        profileImage.src = data.profile_url;
                        profileImage.style.display = 'block';
                    }
                } else {
                    console.error('Could not get logged-in user data:', data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching logged-in user data:', error);
            });
    }

    // Call the function to load header data as soon as the page is ready
    loadLoggedInUserData();

    // --- Existing code for loading the company profile ---
    const getUrlParameter = (name) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    };

    const cuserId = getUrlParameter('cuser_id');

    if (cuserId) {
        // Show skeleton loading screen
        showSkeletonLoading();
        
        // Fetch company data
        fetch(`PHP/company-profile.php?cuser_id=${cuserId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Hide skeleton loading screen
                hideSkeletonLoading();
                
                if (data.error) {
                    console.error('Error fetching company data:', data.error);
                    showError(data.error);
                    return;
                }
                
                console.log('Company data received:', data); // Debug log
                
                // Populate company profile fields
                populateCompanyData(data);
            })
            .catch(error => {
                // Hide skeleton loading screen
                hideSkeletonLoading();
                
                console.error('Fetch error:', error);
                showError('Could not load profile data. Please check your connection.');
            });

        // Add contact button functionality
        const contactButton = document.getElementById('contactButton');
        if (contactButton) {
            contactButton.addEventListener('click', function() {
                window.location.href = `../chat.html?company_id=${cuserId}`;
            });
        }
    } else {
        showError('Please specify a valid company user ID in the URL, for example: company-profile.html?cuser_id=1');
    }

    function populateCompanyData(data) {
        // Basic company info
        document.getElementById('display-name').textContent = data.company_name || 'Company Name';
        document.getElementById('display-type').textContent = data.industry || 'Industry Type';
        document.getElementById('display-hq').textContent = data.headquarter || 'City, Country';
        document.getElementById('display-founded').textContent = data.founded_year || 'Year';
        
        // Contact info
        const contactHtml = `${data.email || 'email@company.com'}<br>Phone: ${data.country_code || ''} ${data.phone_number || '+1-000-000-0000'}`;
        document.getElementById('display-contact').innerHTML = contactHtml;
        
        // Other fields
        document.getElementById('display-industry').textContent = data.industry || 'Your Industry';
        document.getElementById('display-company-type').textContent = data.company_type || 'Company Type';
        
        // Website
        const websiteEl = document.getElementById('display-website');
        websiteEl.textContent = data.website || 'www.yourcompany.com';
        websiteEl.href = data.website || '#';
        
        // Overview
        document.getElementById('display-overview').textContent = data.overview || 'Add your company overview here...';

        // Profile image
        if (data.profile_photo) {
            const profileImg = document.getElementById('profile-image');
            profileImg.src = data.profile_photo;
            profileImg.style.display = 'block';
            document.getElementById('avatar-placeholder').style.display = 'none';
        }

        // Render Job Posts
        renderJobPosts(data.job_posts || []);
    }

    // New helper function to get an icon based on the job title
    function getJobIcon(jobTitle) {
        if (!jobTitle) return '💼'; // Default icon
        const lowerTitle = jobTitle.toLowerCase();
        if (lowerTitle.includes('developer') || lowerTitle.includes('engineer')) {
            return '💻';
        } else if (lowerTitle.includes('manager')) {
            return '📈';
        } else if (lowerTitle.includes('designer')) {
            return '🎨';
        } else if (lowerTitle.includes('analyst')) {
            return '📊';
        } else if (lowerTitle.includes('marketing')) {
            return '📢';
        } else {
            return '💼';
        }
    }

    function renderJobPosts(jobs) {
        const jobsList = document.getElementById('jobs-list');
        jobsList.innerHTML = '';
        
        if (jobs && jobs.length > 0) {
            jobs.forEach(job => {
                const jobElement = document.createElement('div');
                jobElement.className = 'job-item';
                
                jobElement.innerHTML = `
                    <div class="job-icon">${getJobIcon(job.job_title)}</div>
                    <div class="job-details">
                        <div class="job-title">${job.job_title || 'Untitled Position'}</div>
                        <div class="job-description">${job.job_desc || 'No description available'}</div>
                        <div class="job-meta"><br>
                            <span class="job-meta-item">📍 ${job.location || 'Not specified'}</span><br>
                            <span class="job-meta-item">💼 ${job.work_mode || 'Not specified'}</span><br>
                            <span class="job-meta-item">⏳ ${job.time || 'Not specified'}</span><br>
                        </div>
                    </div>
                    <div class="job-actions">
                        <a style="text-decoration: none;" href="../details.html?job_id=${job.id}" class="btn-outline view-job">Details</a>
                       
                        <a href="${job.form_link}" target="_blank" class="btn-apply">Apply</a>
                       
                    </div>
                `;
                jobsList.appendChild(jobElement);
            });
        } else {
            jobsList.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--muted);">
                    <div style="font-size: 48px; margin-bottom: 16px;">💼</div>
                    <div>No job postings yet</div>
                    <div style="font-size: 14px; margin-top: 8px;">Click the + button to add your first job posting</div>
                </div>
            `;
        }
    }

    function showSkeletonLoading() {
        const skeletonContainer = document.getElementById('skeleton-container');
        if (skeletonContainer) {
            skeletonContainer.classList.add('visible');
        }
    }
    
    function hideSkeletonLoading() {
        const skeletonContainer = document.getElementById('skeleton-container');
        if (skeletonContainer) {
            skeletonContainer.classList.remove('visible');
        }
    }

    function showError(message) {
        // Hide skeleton loading on error
        hideSkeletonLoading();
        
        document.body.innerHTML = `
            <div style="text-align: center; padding: 50px;">
                <h2>Error</h2>
                <p>${message}</p>
                <button onclick="window.location.reload()" style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Try Again</button>
            </div>
        `;
    }
});