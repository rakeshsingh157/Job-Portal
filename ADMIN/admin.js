$(document).ready(function() {
    // Function to load all forms from the backend
    function loadForms() {
        $.ajax({
            url: 'fetch_forms.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                const tableBody = $('#formsTable tbody');
                tableBody.empty(); // Clear existing rows

                if (data.length > 0) {
                    data.forEach(form => {
                        let statusClass = '';
                        let statusText = '';
                        switch (form.status) {
                            case 'pending':
                                statusClass = 'status-pending';
                                statusText = 'Pending';
                                break;
                            case 'done':
                                statusClass = 'status-done';
                                statusText = 'Done';
                                break;
                            case 'rejected':
                                statusClass = 'status-rejected';
                                statusText = 'Rejected';
                                break;
                            default:
                                statusText = 'Unknown';
                        }
                        
                        const row = `
                            <tr>
                                <td>${form.id}</td>
                                <td>${form.company_name}</td>
                                <td>${form.created_at}</td>
                                <td class="${statusClass}">${statusText}</td>
                                <td>
                                    <button class="action-button view-details-btn" data-id="${form.id}">View Details</button>
                                </td>
                            </tr>
                        `;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.append('<tr><td colspan="5">No forms found.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching forms: " + error);
                const tableBody = $('#formsTable tbody');
                tableBody.empty();
                tableBody.append('<tr><td colspan="5">Error loading data. Please try again later.</td></tr>');
            }
        });
    }

    // Handle the "View Details" button click
    $(document).on('click', '.view-details-btn', function() {
        const formId = $(this).data('id');
        
        // Fetch all details for the selected form
        $.ajax({
            url: `fetch_form_details.php?id=${formId}`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Populate the modal with all details
                let modalHtml = '';
                for (const key in data) {
                    if (key !== 'id' && key !== 'cuser_id') {
                        let value = data[key];
                        // Format status for display
                        if (key === 'status') {
                            value = value.charAt(0).toUpperCase() + value.slice(1);
                        }
                        const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
                        modalHtml += `<div class="detail-item"><strong>${formattedKey}:</strong> ${value}</div>`;
                    }
                }
                $('#modalBody').html(modalHtml);

                // Set the form ID on the buttons
                $('#verifyModalBtn').data('id', formId);
                $('#rejectModalBtn').data('id', formId);

                // Show the modal
                $('#detailsModal').show();
            },
            error: function() {
                alert("An error occurred while fetching details.");
            }
        });
    });

    // Handle the "Mark as Done" button click inside the modal
    $('#verifyModalBtn').on('click', function() {
        const formId = $(this).data('id');
        
        if (confirm(`Are you sure you want to mark this form as done?`)) {
            $.ajax({
                url: 'verify_form.php',
                type: 'POST',
                data: { id: formId, action: 'done' }, // Send the new action 'done'
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#detailsModal').hide();
                        loadForms(); // Reload the table to reflect the change
                    } else {
                        alert("Verification failed: " + response.message);
                    }
                },
                error: function() {
                    alert("An error occurred. Please try again.");
                }
            });
        }
    });

    // Handle the "Reject" button click inside the modal
    $('#rejectModalBtn').on('click', function() {
        const formId = $(this).data('id');
        
        if (confirm(`Are you sure you want to reject this form?`)) {
            $.ajax({
                url: 'verify_form.php',
                type: 'POST',
                data: { id: formId, action: 'rejected' }, // Send the new action 'rejected'
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#detailsModal').hide();
                        loadForms(); // Reload the table to reflect the change
                    } else {
                        alert("Rejection failed: " + response.message);
                    }
                },
                error: function() {
                    alert("An error occurred. Please try again.");
                }
            });
        }
    });

    // Close the modal when the user clicks on 'x' or outside the modal
    $('.close-btn').on('click', function() {
        $('#detailsModal').hide();
    });

    $(window).on('click', function(event) {
        if ($(event.target).is('#detailsModal')) {
            $('#detailsModal').hide();
        }
    });

    // Initial load of forms when the page is ready
    loadForms();
});
