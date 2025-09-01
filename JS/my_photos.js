 
        let currentUser = { id: null, company_id: null, type: null, profile: null };
        let postsData = [];
        let currentPostId = null;
        let currentImageIndex = {}; // Track current image index for each post

        document.addEventListener('DOMContentLoaded', () => {
            fetchUserPosts();
            
            // Add event listener for comment input
            document.getElementById('commentInput').addEventListener('input', function() {
                document.getElementById('submitComment').disabled = this.value.trim() === '';
            });
            
            document.getElementById('submitComment').addEventListener('click', addComment);
            
            // Add event listeners to modal close buttons
            document.querySelectorAll('.modal-close').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            });
            
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    // In a real implementation, you would filter posts here
                });
            });
        });

        async function fetchUserPosts() {
            try {
                // Show loading state
                document.getElementById('postsGrid').innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-spinner fa-spin"></i></div><p>Loading your posts...</p></div>';
                
                const response = await fetch('PHP/fetch_user_posts.php');
                
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
                console.error('Error fetching user posts:', error);
                showMessage('Failed to load your posts. ' + error.message, 'error');
                document.getElementById('postsGrid').innerHTML = '<div class="empty-state"><div class="empty-state-icon">❌</div><p>Failed to load your posts. Please try again later.</p></div>';
            }
        }

        function renderPosts() {
            const postsGrid = document.getElementById('postsGrid');
            
            if (!postsData || postsData.length === 0) {
                postsGrid.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-camera"></i></div><h3>You haven\'t posted anything yet</h3><p>Share your first post to get started!</p></div>';
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

                        <div class="post-actions">
                            <button class="action-btn" onclick="event.stopPropagation(); editPost(${post.id})"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" onclick="event.stopPropagation(); openDeleteModal(${post.id})"><i class="fas fa-trash"></i></button>
                        </div>
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


        function openPostModal(post) {
            currentPostId = post.id;
            
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
            
            // Set modal content
            const viewModalImage = document.getElementById('viewModalImage');
            const viewModalPrevBtn = document.getElementById('viewModalPrevBtn');
            const viewModalNextBtn = document.getElementById('viewModalNextBtn');
            const viewModalImageCount = document.getElementById('viewModalImageCount');
            const viewModalNavDots = document.getElementById('viewModalNavDots');

            // Initialize or update current image index for modal view
            currentImageIndex.modal = 0;
            const hasMultipleImages = post.images && post.images.length > 1;

            if (hasMultipleImages) {
                viewModalPrevBtn.style.display = 'flex';
                viewModalNextBtn.style.display = 'flex';
                viewModalImageCount.style.display = 'block';
                viewModalNavDots.style.display = 'flex';

                viewModalImageCount.textContent = `${currentImageIndex.modal + 1}/${post.images.length}`;
                viewModalNavDots.innerHTML = post.images.map((_, index) => 
                    `<span class="modal-nav-dot ${index === 0 ? 'active' : ''}" onclick="navigateModalImage(${index})"></span>`
                ).join('');

                viewModalPrevBtn.onclick = () => navigateModalImage(-1);
                viewModalNextBtn.onclick = () => navigateModalImage(1);
            } else {
                viewModalPrevBtn.style.display = 'none';
                viewModalNextBtn.style.display = 'none';
                viewModalImageCount.style.display = 'none';
                viewModalNavDots.style.display = 'none';
            }

            // Function to navigate modal images
            function navigateModalImage(directionOrIndex) {
                const images = post.images;
                if (!images) return;

                let newIndex;
                if (typeof directionOrIndex === 'number' && directionOrIndex >= 0) {
                    newIndex = directionOrIndex;
                } else {
                    newIndex = currentImageIndex.modal + directionOrIndex;
                }
                
                if (newIndex < 0) {
                    newIndex = images.length - 1;
                } else if (newIndex >= images.length) {
                    newIndex = 0;
                }
                
                currentImageIndex.modal = newIndex;
                viewModalImage.src = images[newIndex];
                viewModalImageCount.textContent = `${newIndex + 1}/${images.length}`;
                
                viewModalNavDots.querySelectorAll('.modal-nav-dot').forEach((dot, index) => {
                    dot.classList.toggle('active', index === newIndex);
                });
            }

            viewModalImage.src = post.images[0];
            
            // Set author avatar with error handling
            const authorAvatar = document.getElementById('viewModalAvatar');
            authorAvatar.src = profileImage;
            authorAvatar.onerror = function() {
                this.src = 'https://via.placeholder.com/56?text=No+Image';
            };
            
            document.getElementById('viewModalAuthor').textContent = username;
            document.getElementById('viewModalTitle').textContent = post.user_type === 'user' ? 'Professional' : 'Company';
            document.getElementById('viewModalTime').textContent = timeText;
            document.getElementById('viewModalText').textContent = post.content || 'No description';
            document.getElementById('viewModalComments').textContent = `${post.comments ? post.comments.length : 0} comments`;
            
            // Set commenter avatar (current user's profile)
            const commenterAvatar = document.getElementById('commenterAvatar');
            let commenterProfileUrl = 'https://via.placeholder.com/40?text=You';
            if (currentUser.type === 'user' && currentUser.profile && currentUser.profile.profile_url) {
                commenterProfileUrl = currentUser.profile.profile_url;
            } else if (currentUser.type === 'company' && currentUser.profile && currentUser.profile.profile_photo) {
                commenterProfileUrl = currentUser.profile.profile_photo;
            }
            commenterAvatar.src = commenterProfileUrl;
            commenterAvatar.onerror = function() {
                this.src = 'https://via.placeholder.com/40?text=You';
            };
            
            // Load comments
            const commentsSection = document.getElementById('viewModalCommentsSection');
            commentsSection.innerHTML = '<h3 class="comments-title">Comments</h3>';
            
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
                            <img src="${commentAvatar}" alt="${commentAuthor}" class="profile-image" onerror="this.src='https://via.placeholder.com/40?text=U'">
                        </div>
                        <div class="comment-content">
                            <div class="comment-username">${commentAuthor}</div>
                            <div class="comment-text">${comment.comment}</div>
                            <div class="comment-actions">
                                
                                <span class="comment-action">${timeAgo(new Date(comment.created_at))}</span>
                            </div>
                        </div>
                    `;
                    
                    commentsSection.appendChild(commentElement);
                });
            } else {
                commentsSection.innerHTML += '<p>No comments yet. Be the first to comment!</p>';
            }
            
            // Clear comment input
            document.getElementById('commentInput').value = '';
            document.getElementById('submitComment').disabled = true;
            
            openModal('viewModal');
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

        function editPost(postId) {
            const post = postsData.find(p => p.id === postId);
            if (!post) return;
            
            currentPostId = postId;
            
            const editModalImage = document.getElementById('editModalImage');
            const editModalText = document.getElementById('editModalText');
            const editModalPrevBtn = document.getElementById('editModalPrevBtn');
            const editModalNextBtn = document.getElementById('editModalNextBtn');
            const editModalImageCount = document.getElementById('editModalImageCount');
            const editModalNavDots = document.getElementById('editModalNavDots');

            // Handle multiple images in edit modal
            currentImageIndex.edit = 0;
            const hasMultipleImages = post.images && post.images.length > 1;

            if (hasMultipleImages) {
                editModalPrevBtn.style.display = 'flex';
                editModalNextBtn.style.display = 'flex';
                editModalImageCount.style.display = 'block';
                editModalNavDots.style.display = 'flex';

                editModalImageCount.textContent = `${currentImageIndex.edit + 1}/${post.images.length}`;
                editModalNavDots.innerHTML = post.images.map((_, index) => 
                    `<span class="modal-nav-dot ${index === 0 ? 'active' : ''}" onclick="navigateEditModalImage(${index})"></span>`
                ).join('');
                
                editModalPrevBtn.onclick = () => navigateEditModalImage(-1);
                editModalNextBtn.onclick = () => navigateEditModalImage(1);
            } else {
                editModalPrevBtn.style.display = 'none';
                editModalNextBtn.style.display = 'none';
                editModalImageCount.style.display = 'none';
                editModalNavDots.style.display = 'none';
            }

            // Function to navigate images in edit modal
            function navigateEditModalImage(directionOrIndex) {
                const images = post.images;
                if (!images) return;
                
                let newIndex;
                if (typeof directionOrIndex === 'number' && directionOrIndex >= 0) {
                    newIndex = directionOrIndex;
                } else {
                    newIndex = currentImageIndex.edit + directionOrIndex;
                }

                if (newIndex < 0) {
                    newIndex = images.length - 1;
                } else if (newIndex >= images.length) {
                    newIndex = 0;
                }

                currentImageIndex.edit = newIndex;
                editModalImage.src = images[newIndex];
                editModalImageCount.textContent = `${newIndex + 1}/${images.length}`;
                
                editModalNavDots.querySelectorAll('.modal-nav-dot').forEach((dot, index) => {
                    dot.classList.toggle('active', index === newIndex);
                });
            }

            editModalImage.src = post.images[0];
            editModalText.value = post.content || '';
            
            openModal('editModal');
        }

        function openDeleteModal(postId) {
            currentPostId = postId;
            openModal('deleteModal');
        }

        async function savePostChanges() {
            const newText = document.getElementById('editModalText').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'edit_post');
                formData.append('post_id', currentPostId);
                formData.append('content', newText);
                
                const response = await fetch('PHP/post_operations.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update locally
                    const postIndex = postsData.findIndex(p => p.id === currentPostId);
                    if (postIndex !== -1) {
                        postsData[postIndex].content = newText;
                        renderPosts();
                    }
                    
                    closeModal('editModal');
                    showMessage(data.message, 'success');
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error updating post:', error);
                showMessage('Failed to update post. Please try again.', 'error');
            }
        }

        async function confirmDelete() {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_post');
                formData.append('post_id', currentPostId);
                
                const response = await fetch('PHP/post_operations.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove locally
                    postsData = postsData.filter(p => p.id !== currentPostId);
                    renderPosts();
                    
                    closeModal('deleteModal');
                    showMessage(data.message, 'success');
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error deleting post:', error);
                showMessage('Failed to delete post. Please try again.', 'error');
            }
        }

        async function addComment() {
            const commentText = document.getElementById('commentInput').value.trim();
            
            if (!commentText) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_comment');
                formData.append('post_id', currentPostId);
                formData.append('comment_text', commentText);
                
                const response = await fetch('PHP/post_operations.php', {
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
                            first_name: currentUser.type === 'user' ? currentUser.profile.first_name : null,
                            last_name: currentUser.type === 'user' ? currentUser.profile.last_name : null,
                            company_name: currentUser.type === 'company' ? currentUser.profile.company_name : null,
                            user_profile: currentUser.type === 'user' ? currentUser.profile.profile_url : null,
                            company_profile: currentUser.type === 'company' ? currentUser.profile.profile_photo : null,
                        };
                        
                        if (!post.comments) {
                            post.comments = [];
                        }
                        post.comments.push(newComment);
                        
                        // Render the new comment on the modal instantly
                        const commentsSection = document.getElementById('viewModalCommentsSection');
                        const commentsTitle = commentsSection.querySelector('.comments-title');
                        const emptyMessage = commentsSection.querySelector('p');
                        
                        if (emptyMessage) {
                            emptyMessage.remove();
                        }
                        
                        const commentElement = document.createElement('div');
                        commentElement.className = 'comment';
                        
                        let commenterName = currentUser.type === 'user' ? 
                            `${currentUser.profile.first_name} ${currentUser.profile.last_name}` : 
                            currentUser.profile.company_name;

                        let commenterAvatar = currentUser.type === 'user' ? 
                            (currentUser.profile.profile_url || 'default_user.jpg') : 
                            (currentUser.profile.profile_photo || 'default_company.jpg');
                        
                        commentElement.innerHTML = `
                            <div class="comment-avatar">
                                <img src="${commenterAvatar}" alt="${commenterName}" class="profile-image">
                            </div>
                            <div class="comment-content">
                                <div class="comment-username">${commenterName}</div>
                                <div class="comment-text">${commentText}</div>
                                <div class="comment-actions">
                                   
                                    <span class="comment-action">Just now</span>
                                </div>
                            </div>
                        `;
                        
                        commentsSection.insertBefore(commentElement, commentsTitle.nextSibling);
                        
                        // Update comment count
                        const commentCount = document.getElementById('viewModalComments');
                        commentCount.textContent = `${post.comments.length} comments`;
                    }
                    
                    // Clear the input
                    document.getElementById('commentInput').value = '';
                    document.getElementById('submitComment').disabled = true;
                    
                    showMessage('Comment added successfully!', 'success');
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error adding comment:', error);
                showMessage('Failed to add comment. Please try again.', 'error');
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

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            currentPostId = null;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    modals[i].style.display = 'none';
                }
                document.body.style.overflow = 'auto';
                currentPostId = null;
            }
        };