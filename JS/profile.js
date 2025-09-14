// Custom alert function
function showCustomAlert(title, message) {
    const modalOverlay = document.getElementById('custom-modal-overlay');
    const modalTitle = document.getElementById('custom-modal-title');
    const modalMessage = document.getElementById('custom-modal-message');
    const okBtn = document.getElementById('custom-modal-ok-btn');

    modalTitle.textContent = title;
    modalMessage.textContent = message;
    modalOverlay.style.display = 'flex';

    okBtn.onclick = function() {
        modalOverlay.style.display = 'none';
    };
}

// Loading screen functions
function showLoader() {
    document.getElementById('loading-overlay')?.classList.add('visible');
}

function hideLoader() {
    document.getElementById('loading-overlay')?.classList.remove('visible');
}

// Main function to fetch all profile data
async function fetchProfileData(showSpinner = true) {
    if (showSpinner) showLoader();

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15-second timeout

    try {
        // Restored paths to include the "PHP/" prefix
        const [profileResponse, updateResponse] = await Promise.all([
            fetch('PHP/profile.php', { method: 'GET', signal: controller.signal }),
            fetch('PHP/profile_update.php', { method: 'GET', signal: controller.signal })
        ]);

        clearTimeout(timeoutId);

        if (!profileResponse.ok || !updateResponse.ok) {
           throw new Error(`Server responded with status: ${profileResponse.status}, ${updateResponse.status}`);
        }

        const profileResult = await profileResponse.json();
        const updateResult = await updateResponse.json();
        
        if (!profileResult.success || !updateResult.success) {
            throw new Error(profileResult.error || updateResult.error || 'Failed to get data from server.');
        }
        
        const profileData = profileResult.profile_data;
        const experienceData = updateResult.data.experience;
        const educationData = updateResult.data.education;

        if (!profileData || !updateResult.data) {
             throw new Error('Incomplete profile data received.');
        }

        updateProfileUI(profileData, experienceData, educationData);

    } catch (error) {
        clearTimeout(timeoutId);
        console.error('Error fetching profile data:', error);
        const errorMessage = error.name === 'AbortError' 
            ? 'Server is not responding. Please try again later.'
            : error.message || 'Failed to connect to the server.';
        showCustomAlert('Error', errorMessage);
    } finally {
        if (showSpinner) hideLoader();
    }
}

// Function to update the entire UI with new data
function updateProfileUI(profileData, experienceData, educationData) {
    // Basic Info
    document.getElementById('profileName').textContent = profileData.name || 'N/A';
    document.getElementById('profilePosition').textContent = profileData.job_field;
    document.getElementById('profileBio').textContent = profileData.bio;
    document.getElementById('profileAge').textContent = profileData.age;
    document.getElementById('profileLocation').textContent = profileData.address;
    document.getElementById('profileEmail').textContent = profileData.email;
    document.getElementById('navbarLocation').textContent = profileData.address;
    document.getElementById('profilegender').textContent = profileData.gender;

    const profilePictureUrl = profileData.profile_url || 'https://placehold.co/150x150/png?text=P';
    document.getElementById('mainProfilePicture').src = profilePictureUrl;
    document.getElementById('navbarProfilePicture').src = profilePictureUrl;
    
    // Populate Edit Profile Modal
    document.getElementById('first_name').value = profileData.first_name || '';
    document.getElementById('last_name').value = profileData.last_name || '';
    document.getElementById('job_field').value = profileData.job_field || '';
    document.getElementById('age').value = profileData.age || '';
    document.getElementById('bio').value = profileData.bio || '';
    document.getElementById('address').value = profileData.address || '';
    document.getElementById('phone_number').value = profileData.phone_number || '';
    document.getElementById('email').value = profileData.email || '';
    document.getElementById('part_time_hours').value = profileData.part_time_hours || '';
    
    // Set radio buttons
    setCheckedValue('gender', profileData.gender);
    setCheckedValue('employment_type', profileData.employment_type);
    setCheckedValue('shift_type', profileData.shift_type);

    // Show/hide conditional fields for employment type
    toggleEmploymentFields(profileData.employment_type);
    
    // Populate Skills
    const skillTagsContainer = document.getElementById('skillTagsContainer');
    const currentSkillsDiv = document.getElementById('currentSkills');
    skillTagsContainer.innerHTML = '';
    currentSkillsDiv.innerHTML = '';
    if (profileData.skills && profileData.skills.length > 0) {
        profileData.skills.forEach(skill => {
            const tag = `<span class="skill-tag">${skill}</span>`;
            skillTagsContainer.innerHTML += tag;
            const editTag = `<span class="skill-tag">${skill} <span class="remove-skill" onclick="removeSkill(this, '${skill}')" title="This is a demo, does not delete from DB">&times;</span></span>`;
            currentSkillsDiv.innerHTML += editTag;
        });
    } else {
        skillTagsContainer.innerHTML = '<p class="empty-state">No skills added yet.</p>';
    }

    // Populate Languages
    const languagesList = document.getElementById('languagesList');
    const currentLangDiv = document.getElementById('currentlang');
    languagesList.innerHTML = '';
    currentLangDiv.innerHTML = '';
    if (profileData.languages && profileData.languages.length > 0) {
        profileData.languages.forEach(language => {
            languagesList.innerHTML += `<li>${language}</li>`;
            const langTag = `<span class="lang-tag">${language} <span class="remove-lang" onclick="deleteLanguage('${language}')" title="Delete Language">&times;</span></span>`;
            currentLangDiv.innerHTML += langTag;
        });
    } else {
        languagesList.innerHTML = '<p class="empty-state">No languages added yet.</p>';
    }

    // Populate Experience
    const experienceContainer = document.getElementById('experienceContainer');
    experienceContainer.innerHTML = '';
    if (experienceData && experienceData.length > 0) {
        experienceData.forEach(exp => {
            experienceContainer.innerHTML += `
                <div class="experience-item">
                    <i class="fas fa-briefcase icon"></i>
                    <div class="details">
                        <p class="position">${exp.postion}</p>
                        <p class="company">${exp.company_name}</p>
                        <p class="duration">${exp.date}</p>
                    </div>
                    <button class="delete-btn" onclick="deleteEntry('experience', ${exp.id})" title="Delete Experience">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>`;
        });
    } else {
         experienceContainer.innerHTML = '<p class="empty-state">No experience added yet.</p>';
    }

    // Populate Education
    const educationContainer = document.getElementById('educationContainer');
    educationContainer.innerHTML = '';
    if (educationData && educationData.length > 0) {
         educationData.forEach(edu => {
            educationContainer.innerHTML += `
                <div class="education-item">
                    <i class="fas fa-graduation-cap icon"></i>
                    <div class="details">
                        <p class="degree">${edu.class}</p>
                        <p class="college">${edu.instude_name}</p>
                        <p class="duration">${edu.years}</p>
                        <p class="duration">Grade: ${edu.percentage || 'N/A'}</p>
                    </div>
                    <button class="delete-btn" onclick="deleteEntry('education', ${edu.id})" title="Delete Education">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>`;
        });
    } else {
         educationContainer.innerHTML = '<p class="empty-state">No education added yet.</p>';
    }
}

// Helper to set checked value for radio buttons
function setCheckedValue(name, value) {
    const selector = `input[name="${name}"][value="${value}"]`;
    const radio = document.querySelector(selector);
    if (radio) radio.checked = true;
}

// Helper to toggle employment fields display
function toggleEmploymentFields(value) {
    const shiftContainer = document.getElementById('shift_type_container');
    const partTimeContainer = document.getElementById('part_time_container');
    shiftContainer.style.display = value === 'Full Time' ? 'block' : 'none';
    partTimeContainer.style.display = value === 'Part Time' ? 'block' : 'none';
}

// Generic function to handle form submissions
async function handleFormSubmit(form, actionUrl, additionalData = {}) {
    showLoader();
    try {
        const formData = form ? new FormData(form) : new FormData();
        for (const key in additionalData) {
            formData.append(key, additionalData[key]);
        }

        const response = await fetch(actionUrl, { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message || 'Action completed successfully.');
            fetchProfileData(false); // Refresh all data
            return true; // Indicate success
        } else {
            throw new Error(result.error || 'An unknown error occurred.');
        }
    } catch (error) {
        console.error('Form submission error:', error);
        showCustomAlert('Error', error.message);
        return false; // Indicate failure
    } finally {
        hideLoader();
    }
}


// EVENT LISTENERS
document.addEventListener('DOMContentLoaded', () => {
    fetchProfileData();

    // Modal Triggers
    document.getElementById('exp-btn').addEventListener('click', () => openModal('addexp'));
    document.getElementById('edu-btn').addEventListener('click', () => openModal('addedu'));
    document.getElementById('lang-btn').addEventListener('click', () => openModal('addlangModal'));
    document.getElementById('skill-btn').addEventListener('click', () => openModal('addSkillModal'));

    // Employment type change listener
    document.querySelectorAll('input[name="employment_type"]').forEach(radio => {
        radio.addEventListener('change', e => toggleEmploymentFields(e.target.value));
    });

    // Form Submissions
    document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const success = await handleFormSubmit(this, 'PHP/profile.php');
        if (success) closeModal('editProfileModal');
    });

    document.getElementById('addexpForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const success = await handleFormSubmit(this, 'PHP/profile_update.php', { action: 'add_experience' });
        if (success) {
            this.reset();
            closeModal('addexp');
        }
    });

    document.getElementById('addeduForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const success = await handleFormSubmit(this, 'PHP/profile_update.php', { action: 'add_education' });
        if (success) {
            this.reset();
            closeModal('addedu');
        }
    });
    
    document.getElementById('addlangForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const languageInput = document.getElementById('newlangInput');
        const newLanguage = languageInput.value.trim();
        if (newLanguage) {
             const success = await handleFormSubmit(this, 'PHP/profile.php', { action: 'add_language', newLanguage });
             if (success) {
                languageInput.value = ''; // Clear input on success
             }
        } else {
            showCustomAlert('Input Error', 'Please enter a language.');
        }
    });

    document.getElementById('addSkillForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        showLoader();
        try {
            const skillName = document.getElementById('newSkillInput').value.trim();
            if (!skillName) {
                showCustomAlert('Invalid Input', 'Skill name cannot be empty.');
                hideLoader();
                return;
            }
            
            const formData = new FormData();
            formData.append('field', skillName);

            const response = await fetch('PHP/generate_quiz.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                sessionStorage.setItem('userField', skillName);
                sessionStorage.setItem('generatedQuestions', JSON.stringify(result.questions));
                closeModal('addSkillModal');
                window.location.href = 'quiz_page.html';
            } else {
                showCustomAlert('Quiz Failed', result.error || 'Error generating quiz.');
            }
        } catch (error) {
            showCustomAlert('Network Error', 'Could not connect to the server.');
        } finally {
            hideLoader();
        }
    });

    // Profile photo change
    document.getElementById('profilePhotoInput').addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (file) {
            await handleFormSubmit(null, 'PHP/profile.php', { profile_photo: file });
        }
    });
});

function changeProfilePhoto() {
    document.getElementById('profilePhotoInput').click();
}

async function deleteEntry(type, id) {
    if (!confirm(`Are you sure you want to delete this ${type} entry?`)) return;
    const action = type === 'experience' ? 'delete_experience' : 'delete_education';
    await handleFormSubmit(null, 'PHP/profile_update.php', { action, id });
}

async function deleteLanguage(languageName) {
    if (!confirm(`Are you sure you want to remove "${languageName}"?`)) return;
    await handleFormSubmit(null, 'PHP/profile.php', { action: 'delete_language', languageName });
}

// This is a demo function as there's no backend logic for skill deletion provided.
async function removeSkill(element, skillName) {
    showCustomAlert('Demo', `This would normally delete "${skillName}" from the database.`);
    element.parentElement.remove();
}


// Modal handling
function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
function openEditProfileModal() { openModal('editProfileModal'); }
function closeEditProfileModal() { closeModal('editProfileModal'); }
function closeAddSkillModal() { closeModal('addSkillModal'); }
function openAddexpModal() { openModal('addexp'); }
function closeAddexpModal() { closeModal('addexp'); }
function openAddeduModal() { openModal('addedu'); }
function closeAddeduModal() { closeModal('addedu'); }
function openAddlangModal() { openModal('addlangModal'); }
function closeAddlangModal() { closeModal('addlangModal'); }


// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
};

