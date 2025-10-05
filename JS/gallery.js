let currentUser = { id: null, company_id: null, type: null, profile: null };
let postsData = [];
let currentPostId = null;
let currentImageIndex = {}; // Track current image index for each post

document.addEventListener('DOMContentLoaded', () => {
    fetchPosts();
    
    // Handle comment form submission
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await addComment();
        });
    }
    
    // Add event listeners to modal close buttons
    document.querySelectorAll('.modal-close').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });
});

async function fetchPosts() {
    try {
        // Show improved loading state with skeleton posts
        showLoadingState();
        
        const response = await fetch('PHP/fetch_gallery_posts.php' + window.location.search);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        postsData = data.posts;
        currentUser = data.currentUser;
        
        renderPosts();
        
    } catch (error) {
        console.error('Error fetching posts:', error);
        showMessage('Failed to load posts. ' + error.message, 'error');
        document.getElementById('postsGrid').innerHTML = '<div class="empty-state"><div class="empty-state-icon">❌</div><p>Failed to load posts. Please try again later.</p></div>';
    }
}

function renderPosts() {
    const postsGrid = document.getElementById('postsGrid');
    
    if (!postsData || postsData.length === 0) {
        postsGrid.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-camera"></i></div><h3>Nothing to see here yet</h3><p>New posts will appear here shortly!</p></div>';
        return;
    }
    
    postsGrid.innerHTML = '';
    
    postsData.forEach(post => {
        if (!post.images || post.images.length === 0) return;
        
        const postCard = document.createElement('div');
        postCard.className = 'post-card';
        
        const username = post.user_type === 'user' ? 
            `${post.first_name} ${post.last_name}` : 
            post.company_name;
            
        const profileImage = post.user_type === 'user' ? 
            (post.user_profile || 'default_user.jpg') : 
            (post.company_profile || 'default_company.jpg');
        
        // Format the date
        const postDate = new Date(post.created_at);
        const timeDiff = Math.floor((new Date() - postDate) / (1000 * 60 * 60 * 24));
        let timeText = 'Today';
        
        if (timeDiff > 0) {
            timeText = timeDiff === 1 ? '1 day ago' : `${timeDiff} days ago`;
        }
        
        // Check if the post has multiple images
        const hasMultipleImages = post.images.length > 1;
        
        currentImageIndex[post.id] = 0; // Initialize image index
        
        postCard.innerHTML = `
            <div class="post-image-container">
                <img id="post-image-${post.id}" src="${post.images[0]}" alt="Post image" class="post-image" 
                     onerror="this.src='https://via.placeholder.com/300x400?text=Image+Not+Found'">
                
                ${hasMultipleImages ? `
                    <button class="image-nav-btn nav-btn-prev" onclick="event.stopPropagation(); navigateImage(${post.id}, -1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="image-nav-btn nav-btn-next" onclick="event.stopPropagation(); navigateImage(${post.id}, 1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="image-count" id="image-count-${post.id}">${currentImageIndex[post.id] + 1}/${post.images.length}</div>
                    <div class="post-image-navigation" id="nav-dots-${post.id}">
                        ${post.images.map((_, index) => 
                            `<span class="nav-dot ${index === 0 ? 'active' : ''}" onclick="event.stopPropagation(); navigateImage(${post.id}, ${index}, true)"></span>`
                        ).join('')}
                    </div>
                ` : ''}
            </div>
            <div class="post-content">
                <div class="post-username">${username}</div>
                <div class="post-text">${post.content || 'No description'}</div>
                <div class="post-stats">
                    <span class="stat-item"><i class="far fa-comment"></i> ${post.comments ? post.comments.length : 0}</span>
                    <span>${timeText}</span>
                </div>
            </div>
        `;
        
        // Add click event to open post view
        postCard.addEventListener('click', () => {
            openPostModal(post);
        });
        
        postsGrid.appendChild(postCard);
    });
}

function navigateImage(postId, directionOrIndex, isDot = false) {
    const post = postsData.find(p => p.id === postId);
    if (!post || !post.images) return;
    
    let newIndex;
    if (isDot) {
        newIndex = directionOrIndex;
    } else {
        newIndex = currentImageIndex[postId] + directionOrIndex;
    }
    
    if (newIndex < 0) {
        newIndex = post.images.length - 1;
    } else if (newIndex >= post.images.length) {
        newIndex = 0;
    }
    
    currentImageIndex[postId] = newIndex;
    
    const postImage = document.getElementById(`post-image-${postId}`);
    const navDotsContainer = document.getElementById(`nav-dots-${postId}`);
    const imageCount = document.getElementById(`image-count-${postId}`);
    
    if (postImage) postImage.src = post.images[newIndex];
    if (imageCount) imageCount.textContent = `${newIndex + 1}/${post.images.length}`;
    
    if (navDotsContainer) {
        navDotsContainer.querySelectorAll('.nav-dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === newIndex);
        });
    }
}


let currentModalPost = null;
let currentModalImageIndex = 0;

function openPostModal(post) {
    currentPostId = post.id;
    currentModalPost = post;
    currentModalImageIndex = 0;
    
    const username = post.user_type === 'user' ? 
        `${post.first_name} ${post.last_name}` : 
        post.company_name;
        
    const profileImage = post.user_type === 'user' ? 
        (post.user_profile || 'default_user.jpg') : 
        (post.company_profile || 'default_company.jpg');
    
    // Format the date
    const postDate = new Date(post.created_at);
    const timeDiff = Math.floor((new Date() - postDate) / (1000 * 60 * 60 * 24));
    let timeText = 'Today';
    
    if (timeDiff > 0) {
        timeText = timeDiff === 1 ? '1 day ago' : `${timeDiff} days ago`;
    }
    
    // Set modal image
    const modalImage = document.getElementById('modalImage');
    modalImage.src = post.images[0];
    
    // Set author avatar with error handling
    const modalAvatar = document.getElementById('modalAvatar');
    modalAvatar.src = profileImage;
    modalAvatar.onerror = function() {
        this.src = 'https://via.placeholder.com/48?text=No+Image';
    };
    
    // Set username and time
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('modalPostTime').textContent = timeText;
    document.getElementById('modalText').textContent = post.content || 'No description';
    
    // Handle multiple images
    const hasMultipleImages = post.images && post.images.length > 1;
    const modalNav = document.querySelector('.modal-nav');
    const modalDots = document.getElementById('modalDots');
    const modalImageCount = document.getElementById('modalImageCount');
    
    if (hasMultipleImages) {
        modalNav.style.display = 'flex';
        modalDots.style.display = 'flex';
        modalImageCount.style.display = 'block';
        modalImageCount.textContent = `1 of ${post.images.length}`;
        
        // Create dots
        modalDots.innerHTML = post.images.map((_, index) => 
            `<span class="dot ${index === 0 ? 'active' : ''}" onclick="setModalImage(${index})"></span>`
        ).join('');
    } else {
        modalNav.style.display = 'none';
        modalDots.style.display = 'none';
        modalImageCount.style.display = 'none';
    }
    
    // Set commenter avatar (current user's profile)
    const commentUserAvatar = document.getElementById('commentUserAvatar');
    let commenterProfileUrl = 'https://via.placeholder.com/32?text=You';
    if (currentUser.type === 'user' && currentUser.profile && currentUser.profile.profile_url) {
        commenterProfileUrl = currentUser.profile.profile_url;
    } else if (currentUser.type === 'company' && currentUser.profile && currentUser.profile.profile_photo) {
        commenterProfileUrl = currentUser.profile.profile_photo;
    }
    commentUserAvatar.src = commenterProfileUrl;
    commentUserAvatar.onerror = function() {
        this.src = 'https://via.placeholder.com/32?text=You';
    };
    
    // Set post ID for comment form
    document.getElementById('commentPostId').value = post.id;
    
    // Update comment count
    document.getElementById('commentCount').textContent = `${post.comments ? post.comments.length : 0} Comments`;
    
    // Load comments
    loadModalComments(post);
    
    // Clear comment input
    document.getElementById('commentTextInput').value = '';
    
    // Show modal
    const modal = document.getElementById('imageModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function loadModalComments(post) {
    const modalComments = document.getElementById('modalComments');
    modalComments.innerHTML = '';
    
    if (post.comments && post.comments.length > 0) {
        post.comments.forEach(comment => {
            const commentAuthor = comment.user_type === 'user' ? 
                `${comment.first_name} ${comment.last_name}` : 
                comment.company_name;
            
            const commentAvatar = comment.user_type === 'user' ? 
                (comment.user_profile || 'default_user.jpg') : 
                (comment.company_profile || 'default_company.jpg');
            
            const commentElement = document.createElement('div');
            commentElement.className = 'comment';
            commentElement.innerHTML = `
                <div class="comment-avatar">
                    <img src="${commentAvatar}" alt="${commentAuthor}" onerror="this.src='https://via.placeholder.com/32?text=U'">
                </div>
                <div class="comment-content">
                    <div class="comment-username">${commentAuthor}</div>
                    <div class="comment-text">${comment.comment}</div>
                </div>
            `;
            
            modalComments.appendChild(commentElement);
        });
    }
}



function updateModalImage() {
    if (!currentModalPost || !currentModalPost.images) return;
    
    const modalImage = document.getElementById('modalImage');
    const modalDots = document.getElementById('modalDots');
    const modalImageCount = document.getElementById('modalImageCount');
    
    modalImage.src = currentModalPost.images[currentModalImageIndex];
    
    if (modalImageCount) {
        modalImageCount.textContent = `${currentModalImageIndex + 1} of ${currentModalPost.images.length}`;
    }
    
    if (modalDots) {
        modalDots.querySelectorAll('.dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === currentModalImageIndex);
        });
    }
}

function timeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h ago`;
    
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 30) return `${diffInDays}d ago`;
    
    const diffInMonths = Math.floor(diffInDays / 30);
    if (diffInMonths < 12) return `${diffInMonths}mo ago`;
    
    const diffInYears = Math.floor(diffInMonths / 12);
    return `${diffInYears}y ago`;
}


async function addComment() {
    const commentText = document.getElementById('commentTextInput').value.trim();
    
    if (!commentText) return;
    
    try {
        const formData = new FormData();
        formData.append('add_comment', true);
        formData.append('post_id', currentPostId);
        formData.append('comment_text', commentText);
        
        const response = await fetch('PHP/fetch_gallery_posts.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const post = postsData.find(p => p.id === currentPostId);
            if (post) {
                const newComment = {
                    comment: commentText,
                    created_at: new Date().toISOString(),
                    user_type: currentUser.type,
                    user_id: currentUser.id,
                    company_id: currentUser.company_id,
                    first_name: currentUser.type === 'user' ? (currentUser.profile ? currentUser.profile.first_name : null) : null,
                    last_name: currentUser.type === 'user' ? (currentUser.profile ? currentUser.profile.last_name : null) : null,
                    company_name: currentUser.type === 'company' ? (currentUser.profile ? currentUser.profile.company_name : null) : null,
                    user_profile: currentUser.type === 'user' ? (currentUser.profile ? currentUser.profile.profile_url : null) : null,
                    company_profile: currentUser.type === 'company' ? (currentUser.profile ? currentUser.profile.profile_photo : null) : null,
                };
                
                if (!post.comments) {
                    post.comments = [];
                }
                post.comments.push(newComment);
                
                // Update current modal post
                currentModalPost = post;
                
                // Re-render comments in modal
                loadModalComments(post);
                
                // Update comment count
                document.getElementById('commentCount').textContent = `${post.comments.length} Comments`;
            }
            
            // Clear the input
            document.getElementById('commentTextInput').value = '';
            
            showMessage('Comment added successfully!', 'success');
        } else {
            showMessage('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error adding comment:', error);
        showMessage('Failed to add comment. Please try again.', 'error');
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

function showMessage(text, type) {
    // Create a temporary message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = text;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        document.body.removeChild(messageDiv);
    }, 3000);
}



window.closeModal = function() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    currentPostId = null;
    currentModalPost = null;
    currentModalImageIndex = 0;
}

window.navigateImage = function(direction) {
    if (!currentModalPost || !currentModalPost.images || currentModalPost.images.length <= 1) return;
    
    if (direction === 1) {
        currentModalImageIndex = (currentModalImageIndex + 1) % currentModalPost.images.length;
    } else {
        currentModalImageIndex = currentModalImageIndex === 0 ? currentModalPost.images.length - 1 : currentModalImageIndex - 1;
    }
    
    updateModalImage();
}

window.setModalImage = function(index) {
    if (!currentModalPost || !currentModalPost.images) return;
    currentModalImageIndex = index;
    updateModalImage();
}