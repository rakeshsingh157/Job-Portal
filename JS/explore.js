function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.querySelector('.filter-toggle-btn');
    sidebar.classList.toggle('sidebar-visible');
    if (sidebar.classList.contains('sidebar-visible')) {
        toggleButton.textContent = 'Hide Filters';
    } else {
        toggleButton.textContent = 'Show Filters';
    }
}

function toggleMenuOverlay() {
    const menuOverlay = document.getElementById('mobileMenuOverlay');
    menuOverlay.classList.toggle('visible');
}

function toggleSearchOverlay() {
    const searchOverlay = document.getElementById('mobileSearchOverlay');
    searchOverlay.classList.toggle('visible');
}


function showCustomAlert(message) {
   
}

function redirectToDetails(jobId) {
    showCustomAlert(`Redirecting to job details for Job ID: ${jobId}`);
    setTimeout(() => {
        window.location.href = `details.html?job_id=${jobId}`;
    }, 10);
}

function getRandomColorClass() {
    const colors = ['light-blue', 'light-green', 'light-pink', 'light-purple', 'light-yellow', 'light-orange', 'light-teal'];
    const randomIndex = Math.floor(Math.random() * colors.length);
    return colors[randomIndex];
}

function showJobSkeletonLoading() {
    const jobGrid = document.getElementById('job-grid');
    if (!jobGrid) return;
    
    const skeletonHTML = `
        ${Array.from({ length: 6 }, () => `
            <div class="job-skeleton">
                <div class="skeleton-job-header">
                    <div class="skeleton skeleton-company-logo"></div>
                    <div class="skeleton-job-info">
                        <div class="skeleton skeleton-job-title"></div>
                        <div class="skeleton skeleton-company-name"></div>
                    </div>
                </div>
                <div class="skeleton skeleton-job-description"></div>
                <div class="skeleton skeleton-job-description"></div>
                <div class="skeleton skeleton-job-description"></div>
                <div class="skeleton-job-tags">
                    <div class="skeleton skeleton-job-tag"></div>
                    <div class="skeleton skeleton-job-tag"></div>
                    <div class="skeleton skeleton-job-tag"></div>
                    <div class="skeleton skeleton-job-tag"></div>
                </div>
                <div class="skeleton-job-footer">
                    <div class="skeleton-job-details">
                        <div class="skeleton skeleton-job-detail"></div>
                        <div class="skeleton skeleton-job-detail"></div>
                        <div class="skeleton skeleton-job-detail"></div>
                    </div>
                    <div class="skeleton skeleton-apply-btn"></div>
                </div>
            </div>
        `).join('')}
    `;
    
    jobGrid.innerHTML = skeletonHTML;
}

async function fetchJobs(filters = {}) {
    const jobGrid = document.getElementById('job-grid');
    if (!jobGrid) return;
    
    // Show skeleton loading
    showJobSkeletonLoading();

    const params = new URLSearchParams(filters).toString();
    const url = `PHP/explore.php?${params}`;

    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        if (data.success && data.jobs.length > 0) {
            jobGrid.innerHTML = '';
            data.jobs.forEach(job => {
                const card = document.createElement('div');
                card.className = `job-card ${getRandomColorClass()}`;


                const skillsList = job.skills ? job.skills.split(',').map(skill => `<span class="tag">${skill.trim()}</span>`).join('') : '';

                card.innerHTML = `
                    <div class="job-header">
                        <div class="company-logo">
                            <img src="${job.profile_photo || 'https://via.placeholder.com/50'}" alt="Company Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/50';">
                        </div>
                        <div class="company-info">
                            <h4>${job.company_name}</h4>
                            <strong>${job.job_title}</strong>
                        </div>
                    </div>
                    <p>${job.job_desc}</p>
                    <div class="tags">
                        <span class="tag">${job.time}</span>
                        <span class="tag">${job.experience}</span>
                        ${skillsList}
                    </div>
                    <div class="buttons">
                        <button class="btn details-btn" onclick="redirectToDetails(${job.id})">Details</button>
                        <a href="${job.form_link}" target="_blank" class="btn apply-btn">Apply Now</a>
                    </div>
                `;
                jobGrid.appendChild(card);
            });
        } else {
            jobGrid.innerHTML = '<div style="text-align: center; padding: 20px;"><p>No jobs found matching your criteria.</p></div>';
        }

    } catch (error) {
        console.error("Failed to fetch jobs:", error);
        jobGrid.innerHTML = '<div style="text-align: center; padding: 20px;"><p>Failed to load jobs. Please try again later.</p></div>';
    }
}

function collectAndFetchJobs() {
    const filters = {};

    // Get values from main search bar inputs
    const mainJobTitle = document.querySelector('.search-container input[placeholder="Job title"]').value;
    if (mainJobTitle) {
        filters.job_title = mainJobTitle;
    }

    const mainLocation = document.querySelector('.search-container input[placeholder="Location"]').value;
    if (mainLocation) {
        filters.location = mainLocation;
    }
    
    // Get value from the sidebar search bar
    const sideJobTitle = document.querySelector('.search-container-sidebar .side-input').value;
    if (sideJobTitle) {
        // Use this as a keyword search, prioritizing it if both are entered
        filters.job_title = sideJobTitle;
    }

    // Collect checkbox values
    const experienceChecks = Array.from(document.querySelectorAll('.filter-section[data-filter-type="experience"] input:checked')).map(cb => cb.value);
    if (experienceChecks.length > 0) {
        filters.experience = experienceChecks.join(',');
    }

    const jobTypeChecks = Array.from(document.querySelectorAll('.filter-section[data-filter-type="job_type"] input:checked')).map(cb => cb.value);
    if (jobTypeChecks.length > 0) {
        filters.time = jobTypeChecks.join(',');
    }
    
    const salaryChecks = Array.from(document.querySelectorAll('.filter-section[data-filter-type="salary"] input:checked')).map(cb => cb.value);
    if (salaryChecks.length > 0) {
        filters.salary = salaryChecks.join(',');
    }

    const workModeChecks = Array.from(document.querySelectorAll('.filter-section[data-filter-type="location"] input:checked')).map(cb => cb.value);
    if (workModeChecks.length > 0) {
        filters.work_mode = workModeChecks.join(',');
    }
    
    fetchJobs(filters);
}

document.addEventListener('DOMContentLoaded', () => {
    fetchJobs();

    // Attach event listeners to all search buttons
    document.querySelector('.search-btn-main').addEventListener('click', collectAndFetchJobs);
    document.querySelector('.search-container-sidebar .search-btn').addEventListener('click', collectAndFetchJobs);
    document.querySelector('.btn.apply-btn').addEventListener('click', collectAndFetchJobs);

    // Attach change listeners to all checkboxes for dynamic filtering
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', collectAndFetchJobs);
    });

    // Attach keyup listeners to search inputs
    document.querySelectorAll('.search-field input').forEach(input => {
        input.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                collectAndFetchJobs();
            }
        });
    });
    document.querySelector('.search-container-sidebar .side-input').addEventListener('keyup', (event) => {
        if (event.key === 'Enter') {
            collectAndFetchJobs();
        }
    });
});
