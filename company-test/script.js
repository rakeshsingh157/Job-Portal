document.getElementById('verificationForm').addEventListener('submit', function(event) {
    // Prevent the default form submission
    event.preventDefault();
    
    // Simple validation check
    const companyName = document.getElementById('companyName').value;
    const email = document.getElementById('email').value;

    if (!companyName || !email) {
        alert('Please fill out all required fields.');
        return;
    }

    // You can add more complex validation here
    // For example, checking if the email format is valid

    // If all checks pass, you can process the form data.
    // For a real-world application, this is where you would send the data to a server.
    // For this example, we'll just log it to the console and show a success message.
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    console.log('Form Data Submitted:', data);

    alert('Form submitted successfully! Check the console for data.');
    
    // Clear the form after submission
    this.reset();
});