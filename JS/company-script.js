
let isEditing = false;
let editingJobIndex = -1;
let jobs = [];


const editProfileBtn = document.getElementById('edit-profile-btn');
const saveProfileBtn = document.getElementById('save-profile-btn');
const profileDisplay = document.getElementById('profile-display');
const profileEdit = document.getElementById('profile-edit');
const overviewDisplay = document.getElementById('overview-display');
const overviewEdit = document.getElementById('overview-edit');
const addJobBtn = document.getElementById('add-job-btn');
const jobModal = document.getElementById('job-modal');
const jobForm = document.getElementById('job-form');
const closeModal = document.getElementById('close-modal');
const cancelJob = document.getElementById('cancel-job');
const jobsList = document.getElementById('jobs-list');
const avatarContainer = document.getElementById('avatar-container');
const avatarInput = document.getElementById('avatar-input');
const profileImage = document.getElementById('profile-image');
const avatarPlaceholder = document.getElementById('avatar-placeholder');
const displayContact = document.getElementById('display-contact');


const profileImageHeader = document.getElementById('profile-image2');
const loadingOverlay = document.getElementById('loading-overlay');


function toggleLoading(show) {
    if (show) {
        loadingOverlay.classList.add('visible');
    } else {
        loadingOverlay.classList.remove('visible');
    }
}


function showMessage(message, isConfirmation = false, onConfirm = null) {
    const modal = document.createElement('div');
    modal.className = 'custom-modal';
    modal.innerHTML = `
        <div class="custom-modal-content">
            <p>${message}</p>
            <div class="custom-modal-actions">
                <button class="custom-btn-ok">OK</button>
                ${isConfirmation ? '<button class="custom-btn-cancel">Cancel</button>' : ''}
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const okBtn = modal.querySelector('.custom-btn-ok');
    okBtn.addEventListener('click', () => {
        if (onConfirm) onConfirm(true);
        modal.remove();
    });

    if (isConfirmation) {
        const cancelBtn = modal.querySelector('.custom-btn-cancel');
        cancelBtn.addEventListener('click', () => {
            if (onConfirm) onConfirm(false);
            modal.remove();
        });
    }
}


avatarContainer.addEventListener('click', () => {
    avatarInput.click();
});

avatarInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        toggleLoading(true);
        const reader = new FileReader();
        reader.onloadend = async () => {
            const base64Data = reader.result;

            try {
                const response = await fetch('PHP/company-profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'upload_photo',
                        image_data: base64Data
                    })
                });
                
                const result = await response.json();

                if (result.success) {
                    showMessage('Profile photo uploaded successfully!');

                    profileImage.src = result.photo_url;
                    profileImage.style.display = 'block';
                    avatarPlaceholder.style.display = 'none';
                    profileImageHeader.src = result.photo_url;
                    profileImageHeader.style.display = 'block';
                } else {
                    showMessage('Failed to upload photo: ' + result.message);
                }
            } catch (error) {
                console.error('Error uploading photo:', error);
                showMessage('An error occurred during photo upload.');
            } finally {
                toggleLoading(false);
            }
        };

        reader.readAsDataURL(file);
    }
});



async function toggleEditMode() {
    isEditing = !isEditing;

    if (isEditing) {
        profileDisplay.style.display = 'none';
        profileEdit.classList.add('active');
        overviewDisplay.style.display = 'none';
        overviewEdit.classList.add('active');
        editProfileBtn.textContent = 'Cancel';
        editProfileBtn.style.background = '#dc2626';
    } else {
        profileDisplay.style.display = 'block';
        profileEdit.classList.remove('active');
        overviewDisplay.style.display = 'block';
        overviewEdit.classList.remove('active');
        editProfileBtn.textContent = 'Edit Profile';
        editProfileBtn.style.background = 'var(--primary)';
    }
}

async function saveProfile() {
    toggleLoading(true);

    const profileData = {
        action: 'update_profile',
        company_name: document.getElementById('edit-name').value.trim(),
        headquarter: document.getElementById('edit-hq').value.trim(),
        industry: document.getElementById('edit-industry').value.trim(),
        company_type: document.getElementById('edit-company-type').value.trim(),
        website: document.getElementById('edit-website').value.trim(),
        overview: document.getElementById('edit-overview').value.trim(),
        founded_year: document.getElementById('edit-founded').value.trim(),
        email: document.getElementById('edit-email').value.trim(),
        phone_number: document.getElementById('edit-phone').value.trim()
    };
    
    try {
        const response = await fetch('PHP/company-profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(profileData)
        });

        const result = await response.json();

        if (result.success) {
            console.log(result.message);
            showMessage(result.message);

            await fetchProfileAndJobs();

            toggleEditMode();
        } else {
            console.error(result.message);
            showMessage('Error saving profile: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving profile:', error);
        showMessage('An error occurred while saving the profile.');
    } finally {
        toggleLoading(false);
    }
}


function openJobModal(jobIndex = -1) {
    editingJobIndex = jobIndex;
    const isEditMode = jobIndex >= 0;

    document.getElementById('modal-title').textContent = isEditMode ? 'Edit Job Position' : 'Add New Job';

    if (isEditMode) {
        const job = jobs[jobIndex];
        document.getElementById('job-title-input').value = job.job_title;
        document.getElementById('job-desc-input').value = job.job_desc;
        document.getElementById('job-location-input').value = job.location;
        document.getElementById('job-work-mode-input').value = job.work_mode;
        document.getElementById('job-experience-input').value = job.experience;
        document.getElementById('job-time-input').value = job.time;
        document.getElementById('job-salary-input').value = job.salary;
        document.getElementById('job-skills-input').value = job.skills;
        document.getElementById('job-form-link-input').value = job.form_link;
    } else {
        jobForm.reset();
    }

    jobModal.classList.add('active');
}

function closeJobModal() {
    jobModal.classList.remove('active');
    editingJobIndex = -1;
    jobForm.reset();
}

async function saveJob(event) {
    event.preventDefault();
    toggleLoading(true);

    const jobData = {
        job_title: document.getElementById('job-title-input').value.trim(),
        job_desc: document.getElementById('job-desc-input').value.trim(),
        location: document.getElementById('job-location-input').value.trim(),
        work_mode: document.getElementById('job-work-mode-input').value.trim(),
        experience: document.getElementById('job-experience-input').value.trim(),
        time: document.getElementById('job-time-input').value.trim(),
        salary: document.getElementById('job-salary-input').value.trim(),
        skills: document.getElementById('job-skills-input').value.trim(),
        form_link: document.getElementById('job-form-link-input').value.trim(),
    };

    if (!jobData.job_title || !jobData.job_desc || !jobData.location || !jobData.work_mode) {
        showMessage('Please fill in all required fields (Job Title, Description, Location, and Work Mode).');
        toggleLoading(false);
        return;
    }

    let url = 'PHP/company-profile.php';
    let method = 'POST';
    let payload = { ...jobData };

    if (editingJobIndex >= 0) {
        method = 'PUT';
        payload.job_id = jobs[editingJobIndex].id;
    } else {
        payload.action = 'add_job';
    }
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            console.log(result.message);
            showMessage(result.message);
            await fetchProfileAndJobs();
            closeJobModal();
        } else {
            console.error(result.message);
            showMessage('Error saving job: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving job:', error);
        showMessage('An error occurred while saving the job.');
    } finally {
        toggleLoading(false);
    }
}

function getJobIcon(title) {
    const lower = title.toLowerCase();
    if (lower.includes('engineer') || lower.includes('developer')) return '💻';
    if (lower.includes('design')) return '🎨';
    if (lower.includes('manager') || lower.includes('product')) return '💼';
    if (lower.includes('sales') || lower.includes('marketing')) return '📈';
    if (lower.includes('hr') || lower.includes('human')) return '👥';
    if (lower.includes('data') || lower.includes('analyst')) return '📊';
    return '💼';
}

function renderJobs() {
    if (jobs.length === 0) {
        jobsList.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--muted);">
                <div style="font-size: 48px; margin-bottom: 16px;">💼</div>
                <div>No job postings yet</div>
                <div style="font-size: 14px; margin-top: 8px;">Click the + button to add your first job posting</div>
            </div>
        `;
        return;
    }

    jobsList.innerHTML = '';

    jobs.forEach((job, index) => {
        const jobItem = document.createElement('div');
        jobItem.className = 'job-item';

        jobItem.innerHTML = `
            <div class="job-icon">${getJobIcon(job.job_title)}</div>
            <div class="job-details">
                <div class="job-title">${job.job_title}</div>
                <div class="job-description">${job.job_desc}</div>
                <div class="job-meta"><br>
                    <span class="job-meta-item">📍 ${job.location || 'N/A'}</span>
                    <span class="job-meta-item">💼 ${job.work_mode || 'N/A'}</span>
                    <span class="job-meta-item">⏳ ${job.time || 'N/A'}</span>
                </div>
            </div>
            <div class="job-actions">
                <a style="text-decoration: none;"  href="details.html?job_id=${job.id}" class="btn-outline view-job">Details</a>
                <button class="btn-dark edit-job">Edit</button>
                <a href="${job.form_link}" target="_blank" class="btn-apply">Apply</a>
                <button class="btn-danger delete-job">Delete</button>
            </div>
        `;


        jobItem.querySelector('.edit-job').addEventListener('click', () => openJobModal(index));

        jobItem.querySelector('.delete-job').addEventListener('click', () => {
            showMessage('Are you sure you want to delete this job posting?', true, async (confirmed) => {
                if (confirmed) {
                    try {
                        const response = await fetch('PHP/company-profile.php', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ job_id: job.id })
                        });
                        const result = await response.json();
                        if (result.success) {
                            console.log(result.message);
                            showMessage(result.message);
                            await fetchProfileAndJobs();
                        } else {
                            console.error(result.message);
                            showMessage('Error deleting job: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error deleting job:', error);
                        showMessage('An error occurred while deleting the job.');
                    }
                }
            });
        });

        jobsList.appendChild(jobItem);
    });
}

async function fetchProfileAndJobs() {
    toggleLoading(true);
    try {
        const response = await fetch('PHP/company-profile.php', {
            method: 'GET'
        });
        const result = await response.json();
        if (result.success) {
            // Update profile display
            if (result.profile) {
                const profile = result.profile;
                document.getElementById('display-name').textContent = profile.company_name;
                document.getElementById('display-type').textContent = profile.company_type;
                document.getElementById('display-hq').textContent = profile.headquarter;
                document.getElementById('display-founded').textContent = profile.founded_year;
                document.getElementById('display-industry').textContent = profile.industry;
                document.getElementById('display-company-type').textContent = profile.company_type;
                document.getElementById('display-website').textContent = profile.website;
                document.getElementById('display-website').href = profile.website;
                document.getElementById('display-overview').textContent = profile.overview;
                

                displayContact.innerHTML = `${profile.email || 'N/A'}<br>Phone: ${profile.phone_number || 'N/A'}`;
                

                document.getElementById('edit-name').value = profile.company_name;
                document.getElementById('edit-email').value = profile.email;
                document.getElementById('edit-phone').value = profile.phone_number;
                document.getElementById('edit-hq').value = profile.headquarter;
                document.getElementById('edit-industry').value = profile.industry;
                document.getElementById('edit-company-type').value = profile.company_type;
                document.getElementById('edit-website').value = profile.website;
                document.getElementById('edit-overview').value = profile.overview;
                document.getElementById('edit-founded').value = profile.founded_year;
                document.getElementById('profile-image').src = profile.profile_photo;
                if (profile.profile_photo) {
                    profileImage.style.display = 'block';
                    avatarPlaceholder.style.display = 'none';
                    profileImageHeader.src = profile.profile_photo;
                    profileImageHeader.style.display = 'block';
                }
            }


            jobs = result.jobs;
            renderJobs();
        } else {
            console.error('Failed to fetch profile and jobs:', result.message);
        }
    } catch (error) {
        console.error('Error fetching profile and jobs:', error);
    } finally {
        toggleLoading(false);
    }
}


editProfileBtn.addEventListener('click', toggleEditMode);
saveProfileBtn.addEventListener('click', saveProfile);
addJobBtn.addEventListener('click', () => openJobModal());
closeModal.addEventListener('click', closeJobModal);
cancelJob.addEventListener('click', closeJobModal);
jobForm.addEventListener('submit', saveJob);


jobModal.addEventListener('click', (e) => {
    if (e.target === jobModal) closeJobModal();
});


document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && jobModal.classList.contains('active')) {
        closeJobModal();
    }
});


document.addEventListener('DOMContentLoaded', fetchProfileAndJobs);
