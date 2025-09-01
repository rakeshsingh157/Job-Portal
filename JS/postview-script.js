let currentPost = null;
let currentImageIndex = 0;
let postsData = [];
let currentUser = { id: null, company_id: null, type: null };

document.addEventListener('DOMContentLoaded', () => {
    fetchPosts();
    
    // Attach event listeners for forms
    document.getElementById('deleteForm').addEventListener('submit', handleFormSubmit);
    document.getElementById('commentForm').addEventListener('submit', handleFormSubmit);
});

// Function to fetch posts from the PHP backend
async function fetchPosts() {
    try {
        console.log('Fetching posts from fetch_posts.php');
        const response = await fetch('PHP/fetch_posts.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        // Check if response is OK
        if (!response.ok) {
            // Try alternative path if first attempt fails
            const alternativeResponse = await fetch('../fetch_posts.php', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!alternativeResponse.ok) {
                throw new Error(`HTTP error! status: ${response.status} and ${alternativeResponse.status}`);
            }
            
            const data = await alternativeResponse.json();
            processPostData(data);
            return;
        }
        
        // Check content type to ensure it's JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Response is not JSON:', text);
            throw new Error('Response is not JSON');
        }
        
        const data = await response.json();
        processPostData(data);
        
    } catch (error) {
        console.error('Error fetching data:', error);
        showMessage('Failed to load posts. ' + error.message, 'error');
    }
}

// Process post data after successful fetch
function processPostData(data) {
    console.log('Data received:', data);

    if (data.error) {
        console.error('Error in data:', data.error);
        showMessage(data.error, 'error');
        return;
    }

    postsData = data.posts;
    currentUser.id = data.currentUser.id;
    currentUser.company_id = data.currentUser.company_id;
    currentUser.type = data.currentUser.type;

    renderPosts();
    if (data.message && data.message.text) {
        showMessage(data.message.text, data.message.type);
    }
    
    // Show create post button if a user is logged in
    const createPostBtn = document.getElementById('createPostBtn');
    if (currentUser.id || currentUser.company_id) {
        createPostBtn.style.display = 'flex';
    }
}

// Function to handle form submissions (delete and comment)
async function handleFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Add the appropriate action parameter based on form ID
    if (form.id === 'deleteForm') {
        formData.append('delete_post', 'true');
    } else if (form.id === 'commentForm') {
        formData.append('add_comment', 'true');
    }

    try {
        const response = await fetch('PHP/fetch_posts.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        // Check if response is OK
        if (!response.ok) {
            // Try alternative path if first attempt fails
            const alternativeResponse = await fetch('../fetch_posts.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!alternativeResponse.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await alternativeResponse.json();
            handleFormResponse(data, form);
            return;
        }
        
        const data = await response.json();
        handleFormResponse(data, form);

    } catch (error) {
        console.error('Error submitting form:', error);
        showMessage('Failed to perform action.', 'error');
    }
}

// Handle form response
function handleFormResponse(data, form) {
    if (data.message) {
        showMessage(data.message.text, data.message.type);
    }
    
    // Re-fetch posts to update the UI
    fetchPosts();
    
    // Close modal after form submission if it's a delete action
    if (form.id === 'deleteForm') {
        closeModal();
    } else if (form.id === 'commentForm') {
        // Clear the comment input field
        form.querySelector('input[name="comment_text"]').value = '';
    }
}

// Function to render the posts on the page
function renderPosts() {
    const postsGrid = document.getElementById('postsGrid');
    postsGrid.innerHTML = ''; // Clear existing posts

    if (postsData && postsData.length > 0) {
        postsData.forEach(post => {
            if (post.images && post.images.length > 0) {
                const postCard = document.createElement('div');
                postCard.className = 'post-card';
                postCard.setAttribute('onclick', `openModal(${post.id})`);

                const username = post.user_type === 'user' ? `${post.first_name} ${post.last_name}` : post.company_name;
                const postText = post.content.substring(0, 100) + (post.content.length > 100 ? '...' : '');

                let commentPreview = '';
                if (post.comments && post.comments.length > 0) {
                    const firstComment = post.comments[0];
                    const commentUsername = firstComment.user_type === 'user' ? `${firstComment.first_name} ${firstComment.last_name}` : firstComment.company_name;
                    const commentText = firstComment.comment.substring(0, 50) + (firstComment.comment.length > 50 ? '...' : '');
                    commentPreview = `<div class="post-comment-preview"><b>${commentUsername}:</b> ${commentText}</div>`;
                }

                postCard.innerHTML = `
                    <div class="post-image-container">
                        <img src="${post.images[0]}" 
                             alt="Post image" 
                             class="post-image"
                             onerror="this.src='https://via.placeholder.com/300x400?text=Image+Not+Found'">
                        
                        ${post.images.length > 1 ? `<div class="multi-image-indicator">${post.images.length} photos</div>` : ''}
                    </div>
                    <div class="post-content">
                        <div class="post-username">${username}</div>
                        <div class="post-text">${postText}</div>
                        ${commentPreview}
                    </div>
                `;
                postsGrid.appendChild(postCard);
            }
        });
    } else {
        postsGrid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #777;">
                <h2>No posts yet</h2>
                <p>When people share posts, they'll appear here.</p>
            </div>
        `;
    }
}

// Function to open the modal
function openModal(postId) {
    currentPost = postsData.find(post => post.id == postId);
    if (!currentPost) return;
    
    currentImageIndex = 0;
    updateModal();
    
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Function to close the modal
function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentPost = null;
}

// Function to navigate between images in the modal
function navigateImage(direction) {
    if (!currentPost) return;
    
    currentImageIndex += direction;
    
    if (currentImageIndex < 0) {
        currentImageIndex = currentPost.images.length - 1;
    } else if (currentImageIndex >= currentPost.images.length) {
        currentImageIndex = 0;
    }
    
    updateModal();
}

// Function to go to a specific image index
function goToImage(index) {
    currentImageIndex = index;
    updateModal();
}

// Function to update the modal content
function updateModal() {
    if (!currentPost) return;
    
    const modalImage = document.getElementById('modalImage');
    const modalDots = document.getElementById('modalDots');
    const modalUsername = document.getElementById('modalUsername');
    const modalAvatar = document.getElementById('modalAvatar');
    const modalImageCount = document.getElementById('modalImageCount');
    const modalText = document.getElementById('modalText');
    const modalComments = document.getElementById('modalComments');
    const deleteForm = document.getElementById('deleteForm');
    const deletePostId = document.getElementById('deletePostId');
    const commentForm = document.getElementById('commentForm');
    const commentPostId = document.getElementById('commentPostId');
    
    modalImage.src = currentPost.images[currentImageIndex];
    
    modalDots.innerHTML = '';
    if (currentPost.images.length > 1) {
        for (let i = 0; i < currentPost.images.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'dot' + (i === currentImageIndex ? ' active' : '');
            dot.onclick = () => goToImage(i);
            modalDots.appendChild(dot);
        }
    }
    
    modalUsername.textContent = currentPost.user_type === 'user' ? `${currentPost.first_name} ${currentPost.last_name}` : currentPost.company_name;
    
    const avatarUrl = currentPost.user_type === 'user' ? currentPost.user_profile : currentPost.company_profile;
    if (avatarUrl) {
        modalAvatar.src = avatarUrl;
    } else {
        modalAvatar.src = 'https://via.placeholder.com/48';
    }
    
    modalImageCount.textContent = `${currentImageIndex + 1} of ${currentPost.images.length}`;
    
    modalText.textContent = currentPost.content;
    
    modalComments.innerHTML = '';
    if (currentPost.comments && currentPost.comments.length > 0) {
        currentPost.comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            
            const commentAvatar = document.createElement('div');
            commentAvatar.className = 'comment-avatar';
            const commentAvatarImg = document.createElement('img');
            commentAvatarImg.src = comment.user_type === 'user' ? (comment.user_profile || 'https://via.placeholder.com/32') : (comment.company_profile || 'https://via.placeholder.com/32');
            commentAvatarImg.alt = 'Profile';
            commentAvatarImg.onerror = function() { this.src = 'https://via.placeholder.com/32'; };
            commentAvatar.appendChild(commentAvatarImg);
            
            const commentContent = document.createElement('div');
            commentContent.className = 'comment-content';
            const commentUsernameEl = document.createElement('div');
            commentUsernameEl.className = 'comment-username';
            commentUsernameEl.textContent = comment.user_type === 'user' ? `${comment.first_name} ${comment.last_name}` : comment.company_name;
            const commentTextEl = document.createElement('div');
            commentTextEl.className = 'comment-text';
            commentTextEl.textContent = comment.comment;
            
            commentContent.appendChild(commentUsernameEl);
            commentContent.appendChild(commentTextEl);
            
            commentDiv.appendChild(commentAvatar);
            commentDiv.appendChild(commentContent);
            
            modalComments.appendChild(commentDiv);
        });
    } else {
        const noComments = document.createElement('div');
        noComments.textContent = 'No comments yet.';
        noComments.style.color = '#777';
        noComments.style.textAlign = 'center';
        noComments.style.padding = '20px';
        modalComments.appendChild(noComments);
    }
    
    // Set up delete form visibility
    deleteForm.style.display = 'none';
    const isOwner = (
        (currentUser.id && currentPost.user_type === 'user' && currentPost.user_id == currentUser.id) ||
        (currentUser.company_id && currentPost.user_type === 'company' && currentPost.company_id == currentUser.company_id)
    );
    if (isOwner) {
        deleteForm.style.display = 'block';
        deletePostId.value = currentPost.id;
    }
    
    // Set up comment form
    commentPostId.value = currentPost.id;
}

// Function to display messages
function showMessage(text, type) {
    const messageBox = document.getElementById('messageBox');
    messageBox.textContent = text;
    messageBox.className = `message ${type}`;
    messageBox.style.display = 'block';
    
    setTimeout(() => {
        messageBox.style.display = 'none';
    }, 5000);
}

// Close modal when clicking outside the content
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (currentPost) {
        if (e.key === 'Escape') {
            closeModal();
        } else if (e.key === 'ArrowLeft') {
            navigateImage(-1);
        } else if (e.key === 'ArrowRight') {
            navigateImage(1);
        }
    }
});

// Swipe support for mobile
let touchStartX = 0;
const modalImageContainer = document.querySelector('.modal-image-container');
if (modalImageContainer) {
    modalImageContainer.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    modalImageContainer.addEventListener('touchend', e => {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchEndX - touchStartX;
        const minSwipeDistance = 50;
        
        if (Math.abs(diff) > minSwipeDistance) {
            if (diff > 0) {
                navigateImage(-1); // Swipe right - previous image
            } else {
                navigateImage(1); // Swipe left - next image
            }
        }
    });
}