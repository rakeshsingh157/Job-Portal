// Custom alert function to replace window.alert
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

// Function to show and hide the loading screen
function showLoader(message = 'Loading...') {
    const loaderOverlay = document.getElementById('loader-overlay');
    const loaderText = document.querySelector('.loader-text');
    loaderText.textContent = message;
    loaderOverlay.style.display = 'flex';
}

function hideLoader() {
    const loaderOverlay = document.getElementById('loader-overlay');
    loaderOverlay.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    fetchProfileData();
    document.getElementById('exp-btn').addEventListener('click', openAddexpModal);
    document.getElementById('edu-btn').addEventListener('click', openAddeduModal);
    document.getElementById('lang-btn').addEventListener('click', openAddlangModal); // Add event listener for language button
    
    // Event listeners for employment type radio buttons
    const employmentTypeRadios = document.querySelectorAll('input[name="employment_type"]');
    employmentTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'Full Time') {
                document.getElementById('shift_type').style.display = 'block';
                document.getElementById('part_time').style.display = 'none';
            } else if (this.value === 'Part Time') {
                document.getElementById('shift_type').style.display = 'none';
                document.getElementById('part_time').style.display = 'block';
            }
        });
    });
});

async function fetchProfileData() {
    showLoader("Fetching profile data...");
    try {
        // Fetch user's basic profile data
        const profileResponse = await fetch('profile.php', { method: 'GET' });
        const profileResult = await profileResponse.json();

        // Fetch user's experience and education data
        const updateResponse = await fetch('profile_update.php', { method: 'GET' });
        const updateResult = await updateResponse.json();

        // Ensure both API calls were successful
        if (profileResponse.ok && updateResponse.ok) {
            const profileData = profileResult.profile_data;
            const experienceData = updateResult.data.experience;
            const educationData = updateResult.data.education;
            updateProfileUI(profileData, experienceData, educationData);
        } else {
            showCustomAlert('Error', (profileResult.error || updateResult.error) || 'Failed to fetch profile data.');
        }
    } catch (error) {
        console.error('Error fetching profile data:', error);
        showCustomAlert('Network Error', 'Failed to connect to the server.');
    } finally {
        hideLoader();
    }
}

function updateProfileUI(profileData, experienceData, educationData) {
    // Update basic profile info on the main page
    document.getElementById('profileName').textContent = `${profileData.first_name} ${profileData.last_name}`;
    document.getElementById('profilePosition').textContent = profileData.job_field;
    document.getElementById('profileBio').textContent = profileData.bio;
    document.getElementById('profileAge').textContent = `${profileData.age} years`;
    document.getElementById('profileLocation').textContent = profileData.address;
    document.getElementById('profileEmail').textContent = profileData.email;
    document.getElementById('navbarLocation').textContent = profileData.address;
    
    // Display gender on the main profile page
    document.getElementById('profilegender').textContent = profileData.gender; 

    const profilePictureUrl = profileData.profile_url || 'https://placehold.co/150x150/png?text=P';
    document.getElementById('mainProfilePicture').src = profilePictureUrl;
    document.getElementById('navbarProfilePicture').src = profilePictureUrl;
    
    // Populate form inputs in the modal
    document.getElementById('first_name').value = profileData.first_name;
    document.getElementById('last_name').value = profileData.last_name;
    document.getElementById('job_field').value = profileData.job_field;
    document.getElementById('age').value = profileData.age;
    document.getElementById('bio').value = profileData.bio;
    document.getElementById('address').value = profileData.address;
    document.getElementById('phone_number').value = profileData.phone_number;
    document.getElementById('email').value = profileData.email;
    document.getElementById('part_time_hours').value = profileData.part_time_hours;
    
    const genderSelector = `input[name="gender"][value="${profileData.gender}"]`;
    const selectedGenderRadio = document.querySelector(genderSelector);
    if (selectedGenderRadio) {
        selectedGenderRadio.checked = true;
    }

    // Select Employment Type radio button based on value
    const employmentTypeSelector = `input[name="employment_type"][value="${profileData.employment_type}"]`;
    const selectedEmploymentRadio = document.querySelector(employmentTypeSelector);
    if (selectedEmploymentRadio) {
        selectedEmploymentRadio.checked = true;
    }

    // Select Shift Type radio button based on value
    const shiftTypeSelector = `input[name="shift_type"][value="${profileData.shift_type}"]`;
    const selectedShiftRadio = document.querySelector(shiftTypeSelector);
    if (selectedShiftRadio) {
        selectedShiftRadio.checked = true;
    }

    // Also ensure the correct dependent fields are shown/hidden on load
    if (profileData.employment_type === 'Full Time') {
        document.getElementById('shift_type').style.display = 'block';
        document.getElementById('part_time').style.display = 'none';
    } else if (profileData.employment_type === 'Part Time') {
        document.getElementById('shift_type').style.display = 'none';
        document.getElementById('part_time').style.display = 'block';
    } else {
        // Hide both if no selection is made
        document.getElementById('shift_type').style.display = 'none';
        document.getElementById('part_time').style.display = 'none';
    }
    
    // Populate skills
    const skillTagsContainer = document.getElementById('skillTagsContainer');
    skillTagsContainer.innerHTML = '';
    if (profileData.skills && Array.isArray(profileData.skills)) {
        profileData.skills.forEach(skill => {
            const tag = document.createElement('span');
            tag.className = 'skill-tag';
            tag.textContent = skill;
            skillTagsContainer.appendChild(tag);
        });
    }

    // Populate skills in the new modal
    const currentSkillsDiv = document.getElementById('currentSkills');
    currentSkillsDiv.innerHTML = '';
    if (profileData.skills && Array.isArray(profileData.skills)) {
        profileData.skills.forEach(skill => {
            const newSkillTag = document.createElement('span');
            newSkillTag.classList.add('skill-tag');
            newSkillTag.innerHTML = `${skill} <span class="remove-skill" onclick="removeSkill(this, '${skill}')">&times;</span>`;
            currentSkillsDiv.appendChild(newSkillTag);
        });
    }

    // Populate languages
    const languagesList = document.getElementById('languagesList');
    languagesList.innerHTML = '';
    const currentLangDiv = document.getElementById('currentlang'); 
    currentLangDiv.innerHTML = '';
    if (profileData.languages && Array.isArray(profileData.languages)) {
        profileData.languages.forEach(language => {
            
            const li = document.createElement('li');
            li.textContent = language;
            languagesList.appendChild(li);

            const newLangTag = document.createElement('span');
            newLangTag.classList.add('lang-tag');
            newLangTag.innerHTML = `${language} <span class="remove-lang"  onclick="deleteLanguage(this, '${language}')">&times;</span>`;
            currentLangDiv.appendChild(newLangTag);
        });
    }


    // Populate Experience
    const experienceContainer = document.getElementById('experienceContainer');
    experienceContainer.innerHTML = '';
    if (experienceData && experienceData.length > 0) {
        experienceData.forEach(exp => {
            const expItem = document.createElement('div');
            expItem.className = 'experience-item';
            expItem.innerHTML = `
                <i class="fas fa-briefcase icon"></i>
                <div class="details">
                    <p class="position">${exp.postion}</p>
                    <p class="company">${exp.company_name}</p>
                    <p class="duration">${exp.date}</p>
                </div>
                <button style="cursor: pointer; float: right;  border: 2px solid black; height: 30px; width: 30px; border-radius: 100px; align-items: center; text-align: center; justify-content: center; justify-items: center; font-size: 15px; font-weight: 700;" class="delete-btn" onclick="deleteEntry('experience', ${exp.id})">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            experienceContainer.appendChild(expItem);
        });
    } else {
         experienceContainer.innerHTML = '<p>No experience added yet.</p>';
    }

    // Populate Education
    const educationContainer = document.getElementById('educationContainer');
    educationContainer.innerHTML = '';
    if (educationData && educationData.length > 0) {
         educationData.forEach(edu => {
            const eduItem = document.createElement('div');
            eduItem.className = 'education-item';
            eduItem.innerHTML = `
                <i class="fas fa-graduation-cap icon"></i>
                <div class="details">
                    <p class="degree">${edu.class}</p>
                    <p class="college">${edu.instude_name}</p>
                    <p class="duration">${edu.years}</p>
                    <p class="duration">${edu.percentage}</p>
                </div>
                <button style="cursor: pointer; float: right;  border: 2px solid black; height: 30px; width: 30px; border-radius: 100px; align-items: center; text-align: center; justify-content: center; justify-items: center; font-size: 15px; font-weight: 700;" class="delete-btn" onclick="deleteEntry('education', ${edu.id})">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            educationContainer.appendChild(eduItem);
        });
    } else {
         educationContainer.innerHTML = '<p>No education added yet.</p>';
    }
}

// Handle profile photo change
function changeProfilePhoto() {
    document.getElementById('profilePhotoInput').click();
}

document.getElementById('profilePhotoInput').addEventListener('change', handleProfilePhotoChange);

async function handleProfilePhotoChange(event) {
    const file = event.target.files[0];
    if (file) {
        showLoader("Uploading photo...");

        const formData = new FormData();
        formData.append('profile_photo', file);

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                
                document.getElementById('mainProfilePicture').src = result.photo_url;
                document.getElementById('navbarProfilePicture').src = result.photo_url;
                showCustomAlert('Success', 'Profile photo updated successfully!');
            } else {
                showCustomAlert('Upload Failed', result.error || 'An unknown error occurred during upload.');
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
            showCustomAlert('Network Error', 'Failed to upload photo. Please check your connection.');
        } finally {
            hideLoader();
        }
    }
}

// Handle form submission for editing profile
document.getElementById('editProfileForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    showLoader("Saving profile...");
    
    const formData = new FormData(this);

    try {
        const response = await fetch('profile.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message);
            closeEditProfileModal();
            fetchProfileData(); 
        } else {
            showCustomAlert('Error', result.error || 'Failed to update profile.');
        }
    } catch (error) {
        console.error('Error saving profile:', error);
        showCustomAlert('Network Error', 'Failed to save profile. Please check your connection.');
    } finally {
        hideLoader();
    }
});

// New event listener for adding experience
document.getElementById('addexpForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    showLoader("Adding experience...");

    const formData = new FormData(this);
    formData.append('action', 'add_experience');

    try {
        const response = await fetch('profile_update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message);
            closeAddexpModal();
            fetchProfileData(); // Refresh the profile data
            document.getElementById('addexpForm').reset();
        } else {
            showCustomAlert('Error', result.error || 'Failed to add experience.');
        }
    } catch (error) {
        console.error('Error adding experience:', error);
        showCustomAlert('Network Error', 'Failed to add experience. Please check your connection.');
    } finally {
        hideLoader();
    }
});

// New event listener for adding education
document.getElementById('addeduForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    showLoader("Adding education...");

    const formData = new FormData(this);
    formData.append('action', 'add_education');

    try {
        const response = await fetch('profile_update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message);
            closeAddeduModal();
            fetchProfileData(); // Refresh the profile data
            document.getElementById('addeduForm').reset();
        } else {
            showCustomAlert('Error', result.error || 'Failed to add education.');
        }
    } catch (error) {
        console.error('Error adding education:', error);
        showCustomAlert('Network Error', 'Could not connect to the server. Please check your internet connection.');
    } finally {
        hideLoader();
    }
});

// New function to handle deleting an entry
async function deleteEntry(type, id) {
    const action = type === 'experience' ? 'delete_experience' : 'delete_education';
    showLoader(`Deleting ${type}...`);

    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);

    try {
        const response = await fetch('profile_update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message);
            fetchProfileData(); // Refresh the profile data
        } else {
            showCustomAlert('Error', result.error || `Failed to delete ${type}.`);
        }
    } catch (error) {
        console.error(`Error deleting ${type}:`, error);
        showCustomAlert('Network Error', `Failed to delete ${type}. Please check your connection.`);
    } finally {
        hideLoader();
    }
}

// Function to handle adding a language
document.getElementById('addlangForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    showLoader("Adding language...");
    const languageInput = document.getElementById('newlangInput');
    const newLanguage = languageInput.value.trim();

    if (!newLanguage) {
        showCustomAlert('Invalid Input', 'Please enter a language name.');
        hideLoader();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_language');
    formData.append('newLanguage', newLanguage);

    try {
        const response = await fetch('profile.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showCustomAlert('Success', result.message);
            languageInput.value = ''; // Clear the input field
            fetchProfileData(); // Refresh the profile data to show the new language
        } else {
            showCustomAlert('Error', result.error || 'Failed to add language.');
        }
    } catch (error) {
        console.error('Error adding language:', error);
        showCustomAlert('Network Error', 'Failed to add language. Please check your connection.');
    } finally {
        hideLoader();
    }
});

// Function to handle deleting a language
async function deleteLanguage(element, languageName) {
    if (confirm(`Are you sure you want to remove "${languageName}"?`)) {
        showLoader(`Deleting ${languageName}...`);

        const formData = new FormData();
        formData.append('action', 'delete_language');
        formData.append('languageName', languageName);

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showCustomAlert('Success', result.message);
                fetchProfileData(); // Refresh the profile data to remove the language from the UI
            } else {
                showCustomAlert('Error', result.error || `Failed to delete ${languageName}.`);
            }
        } catch (error) {
            console.error(`Error deleting ${languageName}:`, error);
            showCustomAlert('Network Error', `Failed to delete ${languageName}. Please check your connection.`);
        } finally {
            hideLoader();
        }
    }
}

function openEditProfileModal() {
    document.getElementById('editProfileModal').style.display = 'block';
}

function closeEditProfileModal() {
    document.getElementById('editProfileModal').style.display = 'none';
}

function openAddSkillModal() {
    document.getElementById('addSkillModal').style.display = 'block';
    fetchProfileData(); // Refresh skills in modal just in case
}

function closeAddSkillModal() {
    document.getElementById('addSkillModal').style.display = 'none';
}

function openAddexpModal() {
    document.getElementById('addexp').style.display = 'block';
}

function closeAddexpModal() {
    document.getElementById('addexp').style.display = 'none';
}
function openAddeduModal() {
    document.getElementById('addedu').style.display = 'block';
}

function closeAddeduModal() {
    document.getElementById('addedu').style.display = 'none';
}

function openAddlangModal() {
    document.getElementById('addlangModal').style.display = 'block';
    fetchProfileData(); // Refresh languages in modal
}

function closeAddlangModal() {
    document.getElementById('addlangModal').style.display = 'none';
}
// Function to remove a skill
async function removeSkill(element, skillName) {
    if (confirm(`Are you sure you want to remove "${skillName}"?`)) {
        element.parentNode.remove();
        
        
        console.log(`Simulating removal of skill: ${skillName}`);
        showCustomAlert('Simulated Removal', `The skill "${skillName}" has been removed from the UI. A backend call would be made here.`);
        
        
    }
}

document.getElementById('addSkillForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    
    const skillName = document.getElementById('newSkillInput').value.trim();

    if (skillName === "") {
        showCustomAlert('Invalid Input', 'Skill name cannot be empty.');
        return;
    }
    
    closeAddSkillModal(); 
    showLoader(`Generating quiz for "${skillName}"...`);
    
    const formData = new FormData();
    formData.append('field', skillName);

    try {
        const response = await fetch('generate_quiz.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            
            sessionStorage.setItem('userField', skillName);
            sessionStorage.setItem('generatedQuestions', JSON.stringify(result.questions));
            sessionStorage.setItem('userName', 'Demo User'); 
            
          
            window.location.href = 'quiz_page.html';
        } else {
            showCustomAlert('Quiz Generation Failed', result.error || 'An unexpected error occurred while generating the quiz. Please try again.');
        }
    } catch (error) {
        console.error('Error generating quiz:', error);
        showCustomAlert('Network Error', 'Could not connect to the server. Please check your internet connection.');
    } finally {
        hideLoader();
    }
});

// Modal close behavior
window.onclick = function(event) {
    const profileModal = document.getElementById('editProfileModal');
    const addSkillModal = document.getElementById('addSkillModal');
    const addExpModal = document.getElementById('addexp');
    const addEduModal = document.getElementById('addedu');
    const addLangModal = document.getElementById('addlangModal'); // Added language modal

    if (event.target == profileModal) {
        profileModal.style.display = "none";
    }
    if (event.target == addSkillModal) {
        addSkillModal.style.display = "none";
    }
    if (event.target == addExpModal) {
        addExpModal.style.display = "none";
    }
    if (event.target == addEduModal) {
        addEduModal.style.display = "none";
    }
    if (event.target == addLangModal) { // Added language modal
        addLangModal.style.display = "none";
    }
};