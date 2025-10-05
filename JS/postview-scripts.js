let currentPost = null;
let currentImageIndex = 0;
let postsData = [];
let currentUser = { id: null, company_id: null, type: null };
let debounceTimer; // To prevent excessive requests while typing for post search

document.addEventListener('DOMContentLoaded', () => {
    // Initial fetch of all posts
    fetchPosts();
    
    // Attach event listeners for forms
    document.getElementById('deleteForm').addEventListener('submit', handleFormSubmit);
    document.getElementById('commentForm').addEventListener('submit', handleFormSubmit);

    // --- START: Search Functionality ---
    const searchInput = document.getElementById('headerSearchInput');
    const searchResultsContainer = document.getElementById('searchResults');

    if (searchInput && searchResultsContainer) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(debounceTimer); // Clear previous timer on new input

            if (query.startsWith('@')) {
                // --- Handle User/Company Dropdown Search ---
                if (query.length > 1) { // A query like '@a' is enough to start
                    fetchUserOrCompanyResults(query, searchResultsContainer);
                } else {
                    // If query is just "@" or empty, hide the dropdown
                    searchResultsContainer.innerHTML = '';
                    searchResultsContainer.style.display = 'none';
                }

            } else {
                // --- Handle Post Grid Filtering ---
                // Hide and clear the user/company dropdown
                searchResultsContainer.style.display = 'none';
                searchResultsContainer.innerHTML = '';

                // Use a debounce timer to wait until the user stops typing
                debounceTimer = setTimeout(() => {
                    fetchPosts(query); // Refetch posts with the search term
                }, 400); // Wait 400ms after last keystroke
            }
        });

        // Hide search results dropdown when clicking outside the search input
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target)) {
                searchResultsContainer.style.display = 'none';
            }
        });
    }
    // --- END: Search Functionality ---
});

/**
 * Fetches posts from the backend. Can be filtered by a search term.
 * @param {string} searchTerm - The term to filter posts by content.
 */
async function fetchPosts(searchTerm = '') {
    // Show improved loading state with skeleton posts
    showLoadingState();
    
    // Abort controller for fetch timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15-second timeout

    try {
        let baseUrl = 'PHP/fetch_posts.php';
        let url = searchTerm ? `${baseUrl}?post_search=${encodeURIComponent(searchTerm)}` : baseUrl;
        
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal // Add signal to fetch
        });
        
        clearTimeout(timeoutId); // Clear timeout if fetch is successful

        if (!response.ok) {
            // This fallback logic might not be necessary, but retained from original code.
            let altUrl = `../PHP/fetch_posts.php`;
            altUrl = searchTerm ? `${altUrl}?post_search=${encodeURIComponent(searchTerm)}` : altUrl;
            
            const altController = new AbortController();
            const altTimeoutId = setTimeout(() => altController.abort(), 15000);

            const altResponse = await fetch(altUrl, {
                 headers: { 'X-Requested-With': 'XMLHttpRequest' },
                 signal: altController.signal
            });
            clearTimeout(altTimeoutId);

            if (!altResponse.ok) {
                throw new Error(`HTTP error! status: ${response.status} and ${altResponse.status}`);
            }
            const data = await altResponse.json();
            processPostData(data);
            return;
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Response is not JSON:', text);
            throw new Error('Response is not JSON');
        }
        
        const data = await response.json();
        processPostData(data);
        
    } catch (error) {
        clearTimeout(timeoutId); // Also clear timeout on error
        if (error.name === 'AbortError') {
            console.error('Fetch request timed out.');
            showMessage('Server is not responding. Please try again later.', 'error');
        } else {
            console.error('Error fetching data:', error);
            showMessage('Failed to load posts. ' + error.message, 'error');
        }
    }
}


/**
 * Fetches user or company results for the search dropdown.
 * @param {string} query - The search query, starting with '@'.
 * @param {HTMLElement} container - The container to display results in.
 */
async function fetchUserOrCompanyResults(query, container) {
    try {
        const response = await fetch(`PHP/fetch_posts.php?search_query=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        displaySearchResults(data.results, container);
    } catch (error) {
        console.error('Search fetch error:', error);
        container.style.display = 'none';
    }
}

/**
 * Renders the user/company search results in the dropdown container.
 * @param {Array} results - The array of result objects.
 * @param {HTMLElement} container - The container to display results in.
 */
function displaySearchResults(results, container) {
    container.innerHTML = ''; // Clear previous results

    if (!results || results.length === 0) {
        container.innerHTML = '<div class="search-result-item" style="justify-content: center;">No results found</div>';
        container.style.display = 'block';
        return;
    }

    results.forEach(item => {
        const profileImage = item.profile_url || 'https://via.placeholder.com/40';

        const resultItem = document.createElement('a');
        resultItem.className = 'search-result-item';
        
        let profileUrl = '#'; // Default fallback URL
        if (item.type === 'user') {
            profileUrl = `Public/profile.html?user_id=${item.id}`;
        } else if (item.type === 'company') {
            profileUrl = `Public/company-profile.html?cuser_id=${item.id}`;
        }
        resultItem.href = profileUrl;
        
        resultItem.innerHTML = `
            <img src="${profileImage}" alt="${item.name}" onerror="this.src='https://via.placeholder.com/40'">
            <div class="search-result-info">
                <div class="search-result-name">${item.name}</div>
                <div class="search-result-type">${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</div>
            </div>
        `;
        container.appendChild(resultItem);
    });

    container.style.display = 'block';
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
    currentUser.profile = data.currentUser.profile || null;

    renderPosts();
    setCommentUserAvatar(); // Set the avatar after data is loaded
    
    if (data.message && data.message.text) {
        showMessage(data.message.text, data.message.type);
    }
    
    const createPostBtn = document.getElementById('createPostBtn');
    if (currentUser.id || currentUser.company_id) {
        createPostBtn.style.display = 'flex';
    }
}

// Function to set the comment user avatar
function setCommentUserAvatar() {
    const commentUserAvatar = document.getElementById('commentUserAvatar');
    if (!commentUserAvatar) {
        console.log('Comment user avatar element not found');
        return;
    }
    
    let currentUserAvatar = 'https://via.placeholder.com/32';
    
    if (currentUser.profile) {
        currentUserAvatar = currentUser.profile;
        console.log('Using profile from currentUser:', currentUserAvatar);
    } else if (currentUser.id || currentUser.company_id) {
        // Fallback: try to get avatar from any post by the current user
        const userPost = postsData.find(post => {
            if (currentUser.id && post.user_type === 'user' && post.user_id == currentUser.id) {
                return post.user_profile;
            } else if (currentUser.company_id && post.user_type === 'company' && post.company_id == currentUser.company_id) {
                return post.company_profile;
            }
            return false;
        });
        
        if (userPost) {
            currentUserAvatar = currentUser.type === 'company' ? 
                (userPost.company_profile || currentUserAvatar) : 
                (userPost.user_profile || currentUserAvatar);
            console.log('Using avatar from user post:', currentUserAvatar);
        }
    }
    
    console.log('Setting comment avatar to:', currentUserAvatar);
    commentUserAvatar.src = currentUserAvatar;
    commentUserAvatar.style.display = 'block';
    commentUserAvatar.onerror = function() {
        console.log('Avatar failed to load, using placeholder');
        this.src = 'https://via.placeholder.com/32';
    };
}

// Function to handle form submissions (delete and comment)
async function handleFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    if (form.id === 'deleteForm') {
        formData.append('delete_post', 'true');
    } else if (form.id === 'commentForm') {
        formData.append('add_comment', 'true');
    }

    try {
        const response = await fetch('PHP/fetch_posts.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
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
    
    // Re-fetch posts to update the UI, maintaining current search filter if any
    const currentSearch = document.getElementById('headerSearchInput').value.trim();
    if (!currentSearch.startsWith('@')) {
        fetchPosts(currentSearch);
    } else {
        fetchPosts(); // If a user search was active, just refresh all posts
    }
    
    if (form.id === 'deleteForm') {
        closeModal();
    } else if (form.id === 'commentForm') {
        form.querySelector('input[name="comment_text"]').value = '';
    }
}

function showLoadingState() {
    const postsGrid = document.getElementById('postsGrid');
    postsGrid.innerHTML = '';
    
    // Create skeleton loading posts
    for (let i = 0; i < 6; i++) {
        const skeletonPost = document.createElement('div');
        skeletonPost.className = 'post-skeleton';
        
        const randomHeight = Math.floor(Math.random() * 100) + 200; // Random height between 200-300px
        
        skeletonPost.innerHTML = `
            <div class="skeleton-image" style="height: ${randomHeight}px;"></div>
            <div class="skeleton-content">
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line short"></div>
                <div class="skeleton-line" style="width: 40%; margin-top: 12px;"></div>
            </div>
        `;
        
        postsGrid.appendChild(skeletonPost);
    }
}

// Function to render the posts on the page
function renderPosts() {
    const postsGrid = document.getElementById('postsGrid');
    postsGrid.innerHTML = '';

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
                        <img src="${post.images[0]}" alt="Post image" class="post-image" onerror="this.src='https://via.placeholder.com/300x400?text=Image+Not+Found'">
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
                <h2>No posts found</h2>
                <p>Try a different search term or clear the search box to see all posts.</p>
            </div>
        `;
    }
}


function openModal(postId) {
    currentPost = postsData.find(post => post.id == postId);
    if (!currentPost) return;
    
    currentImageIndex = 0;
    updateModal();
    
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentPost = null;
}

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

function goToImage(index) {
    currentImageIndex = index;
    updateModal();
}

function updateModal() {
    if (!currentPost) return;
    
    const modalImage = document.getElementById('modalImage');
    const modalDots = document.getElementById('modalDots');
    const modalUsername = document.getElementById('modalUsername');
    const modalUsernameLink = document.getElementById('modalUsernameLink');
    const modalUsernameLink2 = document.getElementById('modalUsernameLink2');
    const modalAvatar = document.getElementById('modalAvatar');
    const modalImageCount = document.getElementById('modalImageCount');
    const modalText = document.getElementById('modalText');
    const modalComments = document.getElementById('modalComments');
    const commentCount = document.getElementById('commentCount');
    const deleteForm = document.getElementById('deleteForm');
    const deletePostId = document.getElementById('deletePostId');
    const commentForm = document.getElementById('commentForm');
    const commentPostId = document.getElementById('commentPostId');
    const readMoreBtn = document.getElementById('readMoreBtn');
    
    modalImage.src = currentPost.images[currentImageIndex];
    
    // Update image dots
    modalDots.innerHTML = '';
    if (currentPost.images.length > 1) {
        for (let i = 0; i < currentPost.images.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'dot' + (i === currentImageIndex ? ' active' : '');
            dot.onclick = () => goToImage(i);
            modalDots.appendChild(dot);
        }
    }
    
    // Set profile URL
    let profileUrl = '#';
    if (currentPost.user_type === 'user' && currentPost.user_id) {
        profileUrl = `Public/profile.html?user_id=${currentPost.user_id}`;
    } else if (currentPost.user_type === 'company' && currentPost.company_id) {
        profileUrl = `Public/company-profile.html?cuser_id=${currentPost.company_id}`;
    }
    modalUsernameLink.href = profileUrl;
    if (modalUsernameLink2) modalUsernameLink2.href = profileUrl;

    // Set username and avatar
    const username = currentPost.user_type === 'user' ? 
        `${currentPost.first_name} ${currentPost.last_name}` : 
        currentPost.company_name;
    modalUsername.textContent = username;
    
    const avatarUrl = currentPost.user_type === 'user' ? 
        currentPost.user_profile : 
        currentPost.company_profile;
    modalAvatar.src = avatarUrl || 'https://via.placeholder.com/48';
    
    // Set comment user avatar (current user)
    setCommentUserAvatar();
    
    // Set image count
    if (currentPost.images.length > 1) {
        modalImageCount.textContent = `Photo ${currentImageIndex + 1} of ${currentPost.images.length}`;
    } else {
        modalImageCount.textContent = '';
    }
    
    // Set description (now scrollable if too long)
    modalText.textContent = currentPost.content;
    if (readMoreBtn) {
        readMoreBtn.style.display = 'none';
    }
    
    // Update comments
    const commentsArray = currentPost.comments || [];
    if (commentsArray.length > 0) {
        commentCount.textContent = `Comments (${commentsArray.length})`;
        modalComments.innerHTML = '';
        commentsArray.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            
            const commentAvatarImgSrc = comment.user_type === 'user' ? 
                (comment.user_profile || 'https://via.placeholder.com/32') : 
                (comment.company_profile || 'https://via.placeholder.com/32');
            
            const commentUsername = comment.user_type === 'user' ? 
                `${comment.first_name} ${comment.last_name}` : 
                comment.company_name;

            // Set profile URL for comment user
            let commentProfileUrl = '#';
            if (comment.user_type === 'user' && comment.user_id) {
                commentProfileUrl = `Public/profile.html?user_id=${comment.user_id}`;
            } else if (comment.user_type === 'company' && comment.company_id) {
                commentProfileUrl = `Public/company-profile.html?cuser_id=${comment.company_id}`;
            }

            commentDiv.innerHTML = `
                <a href="${commentProfileUrl}" class="comment-avatar-link">
                    <div class="comment-avatar">
                        <img src="${commentAvatarImgSrc}" alt="Profile" onerror="this.src='https://via.placeholder.com/32'">
                    </div>
                </a>
                <div class="comment-content">
                    <a href="${commentProfileUrl}" class="comment-username-link">
                        <div class="comment-username">${commentUsername}</div>
                    </a>
                    <div class="comment-text">${comment.comment}</div>
                </div>
            `;
            modalComments.appendChild(commentDiv);
        });
    } else {
        commentCount.textContent = 'Comments';
        modalComments.innerHTML = '<div style="color: #999; text-align: center; padding: 20px; font-size: 14px;">No comments yet. Be the first to comment!</div>';
    }
    
    // Show/hide delete button
    deleteForm.style.display = 'none';
    const isOwner = (
        (currentUser.id && currentPost.user_type === 'user' && currentPost.user_id == currentUser.id) ||
        (currentUser.company_id && currentPost.user_type === 'company' && currentPost.company_id == currentUser.company_id)
    );
    if (isOwner) {
        deleteForm.style.display = 'block';
        deletePostId.value = currentPost.id;
    }
    
    commentPostId.value = currentPost.id;
}

// Helper function to focus comment input
function focusCommentInput() {
    const commentInput = document.getElementById('commentTextInput');
    if (commentInput) {
        commentInput.focus();
        // Scroll to comment input if needed
        commentInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function showMessage(text, type) {
    const messageBox = document.getElementById('messageBox');
    messageBox.textContent = text;
    messageBox.className = `message ${type}`;
    messageBox.style.display = 'block';
    
    setTimeout(() => {
        messageBox.style.display = 'none';
    }, 5000);
}

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (currentPost) {
        if (e.key === 'Escape') closeModal();
        else if (e.key === 'ArrowLeft') navigateImage(-1);
        else if (e.key === 'ArrowRight') navigateImage(1);
    }
});

