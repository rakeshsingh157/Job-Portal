document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const jobId = params.get('job_id');
    console.log('Fetching data for job ID:', jobId);

    if (!jobId) {
        console.error('Error: Job ID not found in URL. Please use a URL like http://localhost/details.html?job_id=1');
        return;
    }

    fetch(`PHP/job-details.php?job_id=${jobId}`)
        .then(response => {
            console.log('Received response from server:', response);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data);
            if (data.success) {
                const job = data.job;
                const company = data.company;
                const cuserId = job.cuser_id;

                document.querySelector('h1').textContent = job.job_title;

                // Corrected line to update the text of the anchor tag
                document.querySelector('#companyNameLink').textContent = company.company_name;

                document.querySelector('.bg-white p.text-gray-600.leading-relaxed.text-sm').textContent = job.job_desc;

                const skillsContainer = document.querySelector('.flex.flex-wrap.gap-3');
                skillsContainer.innerHTML = '';
                if (job.skills) {
                    const skillsArray = job.skills.split(',').map(skill => skill.trim());
                    skillsArray.forEach(skill => {
                        const skillSpan = document.createElement('span');
                        skillSpan.className = 'bg-gray-100 text-gray-800 px-4 py-2 rounded-lg font-medium text-sm';
                        skillSpan.textContent = skill;
                        skillsContainer.appendChild(skillSpan);
                    });
                }

                document.querySelector('.bg-white.p-8.rounded-xl.shadow-md.flex.items-start.gap-6 p.text-gray-600.leading-relaxed.text-sm').textContent = company.overview;

                // Corrected line for the sidebar company name
                document.querySelector('#companyNameLinkSide').textContent = company.company_name;

                if (company.profile_photo) {
                    const companyLogoElements = document.querySelectorAll('.pink-box');
                    companyLogoElements.forEach(el => {
                        el.style.backgroundColor = 'transparent';
                        el.innerHTML = `<img src="${company.profile_photo}" alt="Company Logo" class="w-full h-full object-cover rounded-lg">`;
                    });
                }

                document.querySelector('h3.text-xl.font-bold.text-gray-800').textContent = `₹${job.salary}`;
                document.querySelector('.h1 p.text-gray-800').textContent = company.email;
                document.querySelector('.flex.items-star.space-x-3.mb-9 p.text-gray-800').textContent = company.industry;

                const infoBlocks = document.querySelectorAll('.flex.items-star.space-x-3');
                infoBlocks.forEach(block => {
                    const titleElement = block.querySelector('h4');
                    const valueElement = block.querySelector('p');

                    if (titleElement && valueElement) {
                        const titleText = titleElement.textContent.trim();
                        if (titleText === 'Experience Level') {
                            valueElement.textContent = job.experience;
                        } else if (titleText === 'Employment Type') {
                            valueElement.textContent = job.work_mode;
                        }
                    }
                });

                const applyButton = document.querySelector('button.bg-black');
                if (job.form_link) {
                    applyButton.addEventListener('click', () => {
                        window.location.href = job.form_link;
                    });
                } else {
                    applyButton.disabled = true;
                    applyButton.textContent = 'Apply link not available';
                }

                const companyNameLinks = document.querySelectorAll('#companyNameLink, #companyNameLinkSide');
                companyNameLinks.forEach(link => {
                    link.href = `Public/company-profile.html?cuser_id=${cuserId}`;
                });

                const companyPhotoElements = document.querySelectorAll('.pink-box');
                companyPhotoElements.forEach(photo => {
                    photo.style.cursor = 'pointer';
                    photo.addEventListener('click', () => {
                        window.location.href = `Public/company-profile.html?cuser_id=${cuserId}`;
                    });
                });

            } else {
                console.error('Error from PHP:', data.message);
            }
        })
        .catch(error => {
            console.error('Failed to fetch job details:', error);
        });
});