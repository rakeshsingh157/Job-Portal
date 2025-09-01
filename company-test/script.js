$(document).ready(function() {
    let currentStep = 1;
    const progressBar = $('#progressBar');
    
    const loadingOverlay = $('#loading-overlay');
    
    const step1Indicator = $('#step1Indicator');
    const step2Indicator = $('#step2Indicator');
    const step3Indicator = $('#step3Indicator');
    
    const step1Section = $('#step1');
    const step2Section = $('#step2');
    const step3Section = $('#step3');
    const confirmationSection = $('#confirmation');
    
    const incCertInput = $('#incCert');
    const taxCertInput = $('#taxCert');
    const bankStatementInput = $('#bankStatement');
    const addressProofInput = $('#addressProof');

    const messageBox = $('#messageBox');
    const messageTitle = $('#messageTitle');
    const messageText = $('#messageText');
    const messageBoxCloseBtn = $('#messageBoxCloseBtn');

    function showMessageBox(title, message, type) {
        messageTitle.text(title);
        messageText.text(message);
        messageBox.removeClass('success error').addClass(type).show();
    }

    messageBoxCloseBtn.on('click', function() {
        messageBox.hide();
    });

    const getCssVariable = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const var_primary = getCssVariable('--primary');
    const var_light_gray = getCssVariable('--light-gray');

    // These lines trigger the click on the actual file input when the custom upload area is clicked.
    // This is the core logic for the "Choose File" button.
    $('#incCertUpload').on('click', function() { 
        console.log('incCertUpload clicked, attempting to click #incCert input.');
        incCertInput.click(); 
    });
    $('#taxCertUpload').on('click', function() { 
        console.log('taxCertUpload clicked, attempting to click #taxCert input.');
        taxCertInput.click(); 
    });
    $('#bankStatementUpload').on('click', function() { 
        console.log('bankStatementUpload clicked, attempting to click #bankStatement input.');
        bankStatementInput.click(); 
    });
    $('#addressProofUpload').on('click', function() { 
        console.log('addressProofUpload clicked, attempting to click #addressProof input.');
        addressProofInput.click(); 
    });

    // Event listener for when a file is selected (either by programmatic click or drag/drop)
    $('input[type="file"]').on('change', function() {
        console.log('File input changed:', this.id, 'files selected:', this.files.length);
        handleFileDisplay($(this));
    });

    // Drag and Drop functionality
    $('.file-upload').on('dragover', function(e) {
        e.preventDefault();
        $(this).css('border-color', var_primary);
    });

    $('.file-upload').on('dragleave', function(e) {
        e.preventDefault();
        $(this).css('border-color', var_light_gray);
    });

    $('.file-upload').on('drop', function(e) {
        e.preventDefault();
        $(this).css('border-color', var_light_gray);
        const inputId = $(this).find('input[type="file"]').attr('id');
        const fileInput = document.getElementById(inputId);
        fileInput.files = e.originalEvent.dataTransfer.files;
        console.log('Files dropped, assigning to input:', inputId, 'files selected:', fileInput.files.length);
        handleFileDisplay($(fileInput));
    });
    
    function showLoading() {
        console.log('Showing loading overlay.');
        loadingOverlay.addClass('visible');
    }
    
    function hideLoading() {
        console.log('Hiding loading overlay.');
        loadingOverlay.removeClass('visible');
    }

    function handleFileDisplay(fileInputElem) {
        const filesContainer = $(`#${fileInputElem.attr('id')}Files`);
        filesContainer.empty();

        if (fileInputElem[0].files.length > 0) {
            const file = fileInputElem[0].files[0];
            const fileSize = (file.size / (1024 * 1024)).toFixed(2);

            if (file.size > 5 * 1024 * 1024) {
                showMessageBox('File Too Large', `The file "${file.name}" is ${fileSize} MB. Maximum allowed size is 5 MB.`, 'error');
                fileInputElem.val('');
                console.warn('File too large, input cleared.');
                return;
            }
            
            const fileItem = `
                <div class="file-item">
                    <i class="fas fa-file-pdf"></i>
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${fileSize} MB</div>
                    </div>
                    <div class="file-remove" data-input-id="${fileInputElem.attr('id')}">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
            `;
            filesContainer.append(fileItem);
            console.log('File displayed:', file.name);
        } else {
            console.log('No file selected for input:', fileInputElem.attr('id'));
        }
    }

    $(document).on('click', '.file-remove', function() {
        const inputId = $(this).data('input-id');
        const input = $(`#${inputId}`);
        input.val('');
        $(`#${inputId}Files`).empty();
        console.log('File removed for input:', inputId);
    });

    $('#nextToStep2').on('click', () => {
        console.log('Next to Step 2 clicked.');
        if (validateStep1()) {
            navigateToStep(2);
        }
    });
    
    $('#nextToStep3').on('click', () => {
        console.log('Next to Step 3 clicked.');
        if (validateStep2()) {
            populateReviewSection();
            navigateToStep(3);
        }
    });
    
    $('#prevToStep1').on('click', () => {
        console.log('Previous to Step 1 clicked.');
        navigateToStep(1);
    });
    
    $('#prevToStep2').on('click', () => {
        console.log('Previous to Step 2 clicked.');
        navigateToStep(2);
    });
    
    function navigateToStep(step) {
        console.log('Navigating to step:', step);
        $('.form-section').removeClass('active');
        
        $(`#step${step}`).addClass('active');
        
        step1Indicator.removeClass('active completed');
        step2Indicator.removeClass('active completed');
        step3Indicator.removeClass('active completed');
        
        if (step === 1) {
            step1Indicator.addClass('active');
            progressBar.css('width', '0%');
        } else if (step === 2) {
            step1Indicator.addClass('completed');
            step2Indicator.addClass('active');
            progressBar.css('width', '50%');
        } else if (step === 3) {
            step1Indicator.addClass('completed');
            step2Indicator.addClass('completed');
            step3Indicator.addClass('active');
            progressBar.css('width', '100%');
        } else if (step === 4) {
            step1Indicator.addClass('completed');
            step2Indicator.addClass('completed');
            step3Indicator.addClass('completed');
            confirmationSection.addClass('active');
            progressBar.css('width', '100%');
        }
        currentStep = step;
    }
    
    function validateStep1() {
        console.log('Validating Step 1.');
        const requiredFields = [
            $('#companyName'), $('#registrationNumber'), $('#physicalAddress'),
            $('#contactPerson'), $('#phoneNumber'), $('#email'), $('#dateEstablished')
        ];
        
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.val().trim()) {
                isValid = false;
                field.css('border-color', getCssVariable('--danger'));
                field.animate([
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-5px)' },
                    { transform: 'translateX(5px)' },
                    { transform: 'translateX(0)' }
                ], {
                    duration: 300,
                    iterations: 2
                });
            } else {
                field.css('border-color', '');
            }
        });
        
        if (!isValid) {
            showMessageBox('Validation Error', 'Please fill in all required fields in Company Information.', 'error');
            console.warn('Step 1 validation failed.');
        } else {
            console.log('Step 1 validation passed.');
        }
        return isValid;
    }
    
    function validateStep2() {
        console.log('Validating Step 2 (documents).');
        const incCert = incCertInput[0].files.length;
        const taxCert = taxCertInput[0].files.length;
        
        if (!incCert || !taxCert) {
            showMessageBox('Validation Error', 'Please upload the required documents: Certificate of Incorporation and Tax Registration Certificate.', 'error');
            console.warn('Step 2 validation failed: Required documents missing.');
            return false;
        }
        console.log('Step 2 validation passed.');
        return true;
    }
    
    function populateReviewSection() {
        console.log('Populating review section.');
        $('#reviewCompanyName').text($('#companyName').val());
        $('#reviewRegNumber').text($('#registrationNumber').val());
        $('#reviewBusinessType').text($('#businessType').val() || 'N/A');
        $('#reviewLegalStatus').text($('#legalStatus').val() || 'N/A');
        $('#reviewPhysicalAddress').text($('#physicalAddress').val());
        $('#reviewContactPerson').text($('#contactPerson').val());
        $('#reviewContactTitle').text($('#contactTitle').val() || 'N/A');
        $('#reviewPhoneNumber').text($('#phoneNumber').val());
        $('#reviewEmail').text($('#email').val());
        $('#reviewWebsite').text($('#website').val() || 'N/A');
        $('#reviewDateEstablished').text($('#dateEstablished').val());
        $('#reviewNatureOfBusiness').text($('#natureOfBusiness').val() || 'N/A');
        $('#reviewProductsServices').text($('#productsServices').val() || 'N/A');
        $('#reviewHoursOfOperation').text($('#hoursOfOperation').val() || 'N/A');
        $('#reviewNumEmployees').text($('#numEmployees').val() || 'N/A');
        
        $('#reviewIncCert').text(incCertInput[0].files.length ? incCertInput[0].files[0].name : 'Not uploaded');
        $('#reviewTaxCert').text(taxCertInput[0].files.length ? taxCertInput[0].files[0].name : 'Not uploaded');
        $('#reviewBankStatement').text(bankStatementInput[0].files.length ? bankStatementInput[0].files[0].name : 'Not uploaded');
        $('#reviewAddressProof').text(addressProofInput[0].files.length ? addressProofInput[0].files[0].name : 'Not uploaded');
    }
    
    $('#verificationForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submission event triggered.');

        if (!validateStep1()) {
            console.warn('Submission stopped: Step 1 failed validation.');
            return;
        }
        if (!validateStep2()) {
            console.warn('Submission stopped: Step 2 failed validation.');
            return;
        }

        if (!$('#termsAgreement').is(':checked')) {
            showMessageBox('Agreement Required', 'Please agree to the terms and conditions by checking the box.', 'error');
            console.warn('Submission stopped: Terms not agreed.');
            return;
        }
       
        console.log('All validations passed, preparing AJAX request.');
        const formData = new FormData(this);

        // --- Debugging FormData contents ---
        console.log('FormData contents:');
        for (let pair of formData.entries()) {
            if (pair[1] instanceof File) {
                console.log(pair[0] + ': File - ' + pair[1].name + ' (' + pair[1].size + ' bytes)');
            } else {
                console.log(pair[0] + ': ' + pair[1]);
            }
        }
        // --- End Debugging FormData contents ---

        $.ajax({
            beforeSend: function() {
                showLoading();
                console.log('AJAX: beforeSend - Loading screen shown.');
            },
            url: 'submit_form.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX: Success callback triggered. Response:', response);
                if (response.success) {
                    showMessageBox('Success!', response.message, 'success');
                    navigateToStep(4);
                    $('#verificationForm')[0].reset();
                    $('#incCertFiles').empty();
                    $('#taxCertFiles').empty();
                    $('#bankStatementFiles').empty();
                    $('#addressProofFiles').empty();
                    console.log('Form successfully submitted and reset.');
                } else {
                    showMessageBox('Error!', response.message, 'error');
                    console.error('AJAX: Backend reported an error:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX: Error callback triggered.");
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
                console.error("XHR Object:", xhr);
                showMessageBox('Error!', 'An error occurred while submitting your form. Please check the console for details and try again.', 'error');
            },
            complete: function(xhr, status) {
                console.log("AJAX: complete callback triggered. Status:", status);
                hideLoading();
                console.log('AJAX: complete - Loading screen hidden.');
            }
        });
    });

    navigateToStep(1);
});
