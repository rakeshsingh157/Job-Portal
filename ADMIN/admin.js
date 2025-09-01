$(document).ready(function() {
    let allFormsData = []; 


    function loadForms() {
        $.ajax({
            url: 'fetch_forms.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                allFormsData = data; 
                renderForms(allFormsData);
                updateStats(data);
            },
            error: function(xhr, status, error) {
                console.error("Error fetching forms: " + error);
                const tableBody = $('#formsTable tbody');
                tableBody.empty();
                tableBody.append('<tr><td colspan="5">Error loading data. Please try again later.</td></tr>');
            }
        });
    }

    function renderForms(formsToRender) {
        const tableBody = $('#formsTable tbody');
        tableBody.empty();

        if (formsToRender.length > 0) {
            formsToRender.forEach(form => {
                let statusClass = '';
                let statusText = '';
                let actionButtonsHtml = '';

                switch (form.status) {
                    case 'pending':
                        statusClass = 'status-pending';
                        statusText = 'Pending';
                        actionButtonsHtml = `
                            <button class="action-button view-details-btn" data-id="${form.id}"><i class="fas fa-eye"></i> View</button>
                            <button class="action-button verify-btn" data-id="${form.id}"><i class="fas fa-check"></i> Verify</button>
                            <button class="action-button reject-btn" data-id="${form.id}"><i class="fas fa-times"></i> Reject</button>
                        `;
                        break;
                    case 'done':
                        statusClass = 'status-done';
                        statusText = 'Verified';
                        actionButtonsHtml = `
                            <button class="action-button view-details-btn" data-id="${form.id}"><i class="fas fa-eye"></i> View</button>
                            <button class="action-button edit-btn" data-id="${form.id}"><i class="fas fa-edit"></i> Edit</button>
                            
                        `;
                        break;
                    case 'rejected':
                        statusClass = 'status-rejected';
                        statusText = 'Rejected';
                        actionButtonsHtml = `
                            <button class="action-button view-details-btn" data-id="${form.id}"><i class="fas fa-eye"></i> View</button>
                            <button class="action-button verify-btn" data-id="${form.id}"><i class="fas fa-redo"></i> Restore</button>
                        `;
                        break;
                    default:
                        statusText = 'Unknown';
                        actionButtonsHtml = `
                            <button class="action-button view-details-btn" data-id="${form.id}"><i class="fas fa-eye"></i> View</button>
                        `;
                }
                
                const row = `
                    <tr>
                        <td>#CV-${form.id}</td>
                        <td>${form.company_name}</td>
                        <td>${form.created_at}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <div class="action-buttons">
                                ${actionButtonsHtml}
                            </div>
                        </td>
                    </tr>
                `;
                tableBody.append(row);
            });
        } else {
            tableBody.append('<tr><td colspan="5">No forms found.</td></tr>');
        }
    }

    function updateStats(data) {
        const pendingCount = data.filter(form => form.status === 'pending').length;
        const verifiedCount = data.filter(form => form.status === 'done').length;
        const rejectedCount = data.filter(form => form.status === 'rejected').length;

        $('#pendingCount').text(pendingCount);
        $('#verifiedCount').text(verifiedCount);
        $('#rejectedCount').text(rejectedCount);
    }

    $(document).on('click', '.view-details-btn', function() {
        const formId = $(this).data('id');
        
        $.ajax({
            url: `fetch_form_details.php?id=${formId}`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                for (const key in data) {
                    const elementId = `#detail-${key}`;
                    const element = $(elementId);
                    if (element.length) {
                        let value = data[key];
                        if (key === 'status') {
                            value = value.charAt(0).toUpperCase() + value.slice(1);
                            let statusClass = '';
                            switch (data[key]) {
                                case 'pending': statusClass = 'status-pending'; break;
                                case 'done': statusClass = 'status-done'; break;
                                case 'rejected': statusClass = 'status-rejected'; break;
                            }
                            element.html(`<span class="status-badge ${statusClass}">${value}</span>`);
                        } else if (key === 'documents') {
                            const documentLabels = [
                                "Certificate of Incorporation",
                                "Tax Registration Certificate",
                                "Bank Statement",
                                "Proof of Address"
                            ];
                            const docUrls = value.split(',').map(url => url.trim()).filter(url => url !== '');
                            
                            let docLinksHtml = '';
                            if (docUrls.length > 0) {
                                docUrls.forEach((docUrl, index) => {
                                    const label = documentLabels[index] || `Document ${index + 1}`; // Fallback label
                                    docLinksHtml += `
                                        <div>
                                            <strong>${label}:</strong> 
                                            <a href="${docUrl}" target="_blank" style="color: var(--primary); text-decoration: underline;">View Document</a>
                                        </div>
                                    `;
                                });
                            } else {
                                docLinksHtml = 'N/A';
                            }
                            element.html(docLinksHtml);
                        }
                        else {
                            element.text(value);
                        }
                    }
                }

                $('#verifyModalBtn').data('id', formId);
                $('#rejectModalBtn').data('id', formId);
                $('#pendingModalBtn').data('id', formId);

                const currentStatus = data.status;
                if (currentStatus === 'pending') {
                    $('#verifyModalBtn').show();
                    $('#rejectModalBtn').show();
                    $('#pendingModalBtn').hide();
                } else if (currentStatus === 'done') {
                    $('#verifyModalBtn').hide();
                    $('#rejectModalBtn').show();
                    $('#pendingModalBtn').show();
                } else if (currentStatus === 'rejected') {
                    $('#verifyModalBtn').show();
                    $('#rejectModalBtn').hide();
                    $('#pendingModalBtn').show();
                }

                $('#detailsModal').show();
            },
            error: function() {
                alert("An error occurred while fetching details.");
            }
        });
    });

    function updateFormStatus(formId, action) {
        const confirmMessage = `Are you sure you want to set this form to '${action}'?`;
        if (confirm(confirmMessage)) {
            $.ajax({
                url: 'verify_form.php',
                type: 'POST',
                data: { id: formId, action: action },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#detailsModal').hide();
                        loadForms();
                    } else {
                        alert(`Action failed: ${response.message}`);
                    }
                },
                error: function() {
                    alert("An error occurred. Please try again.");
                }
            });
        }
    }

    $(document).on('click', '.verify-btn', function() {
        const formId = $(this).data('id');
        const action = $(this).text().trim() === 'Restore' ? 'done' : 'done';
        updateFormStatus(formId, action);
    });

    $(document).on('click', '.reject-btn', function() {
        const formId = $(this).data('id');
        updateFormStatus(formId, 'rejected');
    });

    $(document).on('click', '.edit-btn', function() {
        const formId = $(this).data('id');
        alert(`Edit functionality for Form ID: ${formId} would be implemented here.`);
    });

    $(document).on('click', '.download-btn', function() {
        const formId = $(this).data('id');
        alert(`Download documents functionality for Form ID: ${formId} would be implemented here.`);
    });


    $('#verifyModalBtn').on('click', function() {
        const formId = $(this).data('id');
        updateFormStatus(formId, 'done');
    });

    $('#rejectModalBtn').on('click', function() {
        const formId = $(this).data('id');
        updateFormStatus(formId, 'rejected');
    });

    $('#pendingModalBtn').on('click', function() {
        const formId = $(this).data('id');
        updateFormStatus(formId, 'pending');
    });

    $('.close-btn').on('click', function() {
        $('#detailsModal').hide();
    });

    $(window).on('click', function(event) {
        if ($(event.target).is('#detailsModal')) {
            $('#detailsModal').hide();
        }
    });

    $('.filter-btn').on('click', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        const filter = $(this).data('filter');
        let filteredForms = [];

        if (filter === 'all') {
            filteredForms = allFormsData;
        } else {
            filteredForms = allFormsData.filter(form => form.status === filter);
        }
        renderForms(filteredForms);
    });

    $('th[data-sort]').on('click', function() {
        const column = $(this).data('sort');
        const isAsc = $(this).hasClass('sort-asc');
        const isDesc = $(this).hasClass('sort-desc');

        $('th[data-sort]').removeClass('sort-asc sort-desc');

        let sortOrder = 'asc';
        if (!isAsc && !isDesc) {
            $(this).addClass('sort-asc');
            sortOrder = 'asc';
        } else if (isAsc) {
            $(this).removeClass('sort-asc');
            $(this).addClass('sort-desc');
            sortOrder = 'desc';
        } else {
            $(this).removeClass('sort-desc');
            $(this).addClass('sort-asc');
            sortOrder = 'asc';
        }

        const sortedForms = [...allFormsData].sort((a, b) => {
            let valA = a[column];
            let valB = b[column];

            if (column === 'id') {
                valA = parseInt(valA);
                valB = parseInt(valB);
            }

            if (valA < valB) {
                return sortOrder === 'asc' ? -1 : 1;
            }
            if (valA > valB) {
                return sortOrder === 'asc' ? 1 : -1;
            }
            return 0;
        });
        renderForms(sortedForms);
    });

    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const filteredForms = allFormsData.filter(form => 
            form.company_name.toLowerCase().includes(searchTerm) ||
            form.id.toString().includes(searchTerm) ||
            form.status.toLowerCase().includes(searchTerm)
        );
        renderForms(filteredForms);
    });

    loadForms();
});
