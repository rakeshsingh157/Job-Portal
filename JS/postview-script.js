let currentPost = null;
let currentImageIndex = 0;
let postsData = [];
let currentUser = { id: null, company_id: null, type: null };
let debounceTimer;

document.addEventListener('DOMContentLoaded', () => {

    fetchPosts();
    

    document.getElementById('deleteForm').addEventListener('submit', handleFormSubmit);
    document.getElementById('commentForm').addEventListener('submit', handleFormSubmit);


    const searchInput = document.getElementById('headerSearchInput');
    const searchResultsContainer = document.getElementById('searchResults');

    if (searchInput && searchResultsContainer) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(debounceTimer);

            if (query.startsWith('@')) {

                if (query.length > 1) {

                    fetchUserOrCompanyResults(query, searchResultsContainer);
                } else {

                    searchResultsContainer.innerHTML = '';
                    searchResultsContainer.style.display = 'none';
                }

            } else {


                searchResultsContainer.style.display = 'none';
                searchResultsContainer.innerHTML = '';


                debounceTimer = setTimeout(() => {
                    fetchPosts(query);
                }, 400);
            }
        });


        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target)) {
                searchResultsContainer.style.display = 'none';
            }
        });
    }

});

/**
 * Fetches posts from the backend. Can be filtered by a search term.
 * @param {string} searchTerm - The term to filter posts by content.
 */
async function fetchPosts(searchTerm = '') {
    try {
        let baseUrl = 'PHP/fetch_posts.php';
        let url = searchTerm ? `${baseUrl}?post_search=${encodeURIComponent(searchTerm)}` : baseUrl;
        
        console.log(`Fetching posts from: ${url}`);
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) {

            let altUrl = `../PHP/fetch_posts.php`;
            altUrl = searchTerm ? `${altUrl}?post_search=${encodeURIComponent(searchTerm)}` : altUrl;
            
            const altResponse = await fetch(altUrl, {
                 headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

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
        console.error('Error fetching data:', error);
        showMessage('Failed to load posts. ' + error.message, 'error');
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

        resultItem.href = `#`; 
        
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
    
    const createPostBtn = document.getElementById('createPostBtn');
    if (currentUser.id || currentUser.company_id) {
        createPostBtn.style.display = 'flex';
    }
}


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


function handleFormResponse(data, form) {
    if (data.message) {
        showMessage(data.message.text, data.message.type);
    }
    

    const currentSearch = document.getElementById('headerSearchInput').value.trim();
    if (!currentSearch.startsWith('@')) {
        fetchPosts(currentSearch);
    } else {
        fetchPosts();
    }
    
    if (form.id === 'deleteForm') {
        closeModal();
    } else if (form.id === 'commentForm') {

        const postId = form.querySelector('input[name="post_id"]').value;
        const post = postsData.find(p => p.id == postId);
        if(post) {

        }
        form.querySelector('input[name="comment_text"]').value = '';
    }
}


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
    modalAvatar.src = avatarUrl || 'https://via.placeholder.com/48';
    
    modalImageCount.textContent = `${currentImageIndex + 1} of ${currentPost.images.length}`;
    modalText.textContent = currentPost.content;
    
    modalComments.innerHTML = '';
    if (currentPost.comments && currentPost.comments.length > 0) {
        currentPost.comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            
            const commentAvatarImgSrc = comment.user_type === 'user' ? (comment.user_profile || 'https://via.placeholder.com/32') : (comment.company_profile || 'https://via.placeholder.com/32');

            commentDiv.innerHTML = `
                <div class="comment-avatar">
                    <img src="${commentAvatarImgSrc}" alt="Profile" onerror="this.src='https://via.placeholder.com/32'">
                </div>
                <div class="comment-content">
                    <div class="comment-username">${comment.user_type === 'user' ? `${comment.first_name} ${comment.last_name}` : comment.company_name}</div>
                    <div class="comment-text">${comment.comment}</div>
                </div>
            `;
            modalComments.appendChild(commentDiv);
        });
    } else {
        modalComments.innerHTML = '<div style="color: #777; text-align: center; padding: 20px;">No comments yet.</div>';
    }
    
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