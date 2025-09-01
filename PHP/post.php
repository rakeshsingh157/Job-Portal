<?php
session_start();
// Database connection
define('DB_SERVER', 'photostore.ct0go6um6tj0.ap-south-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin'); 
define('DB_PASSWORD', 'DBpicshot'); 
define('DB_NAME', 'jobp_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// IMGBB API Key
define('IMGBB_API_KEY', '8f23d9f5d1b5960647ba5942af8a1523');

// Check if user is logged in using the correct session variables
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$company_id = isset($_SESSION['cuser_id']) ? $_SESSION['cuser_id'] : null;
$user_type = null;
$user_name = '';
$profile_photo = '';

// Determine user type based on which session variable is set
if ($user_id) {
    $user_type = 'user';
    $sql = "SELECT first_name, last_name, profile_url FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_name = $row['first_name'] . ' ' . $row['last_name'];
        $profile_photo = $row['profile_url'];
    }
} elseif ($company_id) {
    $user_type = 'company';
    $sql = "SELECT company_name, profile_photo FROM cuser WHERE id = '$company_id'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_name = $row['company_name'];
        $profile_photo = $row['profile_photo'];
    }
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    if (!$user_id && !$company_id) {
        $message = 'You must be logged in to create a post.';
        $message_type = 'error';
    } else {
        $content = $conn->real_escape_string($_POST['content']);
        
        // Insert post into database with user_type to distinguish
        if ($user_type == 'user') {
            $sql = "INSERT INTO posts (user_id, user_type, content) VALUES ('$user_id', 'user', '$content')";
        } else {
            $sql = "INSERT INTO posts (company_id, user_type, content) VALUES ('$company_id', 'company', '$content')";
        }
        
        if ($conn->query($sql) === TRUE) {
            $post_id = $conn->insert_id;
            $upload_success = true;
            $uploaded_images = 0;
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $image_urls = [];
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        // Check file size (limit to 5MB per image)
                        if ($_FILES['images']['size'][$key] > 5 * 1024 * 1024) {
                            $message = 'One or more images exceed the 5MB size limit.';
                            $message_type = 'error';
                            continue;
                        }
                        
                        $image_data = file_get_contents($tmp_name);
                        $base64_image = base64_encode($image_data);
                        
                        // Upload to IMGBB
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://api.imgbb.com/1/upload?key=" . IMGBB_API_KEY);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                            'image' => $base64_image
                        ]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code === 200) {
                            $response_data = json_decode($response, true);
                            
                            if ($response_data && $response_data['success']) {
                                $image_url = $conn->real_escape_string($response_data['data']['url']);
                                
                                // Insert image URL into database
                                $img_sql = "INSERT INTO post_images (post_id, image_url) VALUES ('$post_id', '$image_url')";
                                if ($conn->query($img_sql)) {
                                    $uploaded_images++;
                                } else {
                                    error_log("Image insert failed: " . $conn->error);
                                }
                            } else {
                                error_log("IMGBB upload failed: " . $response);
                            }
                        } else {
                            error_log("IMGBB API error. HTTP code: " . $http_code);
                        }
                    }
                }
            }
            
            if ($uploaded_images > 0 || empty($_FILES['images']['name'][0])) {
                $message = 'Post created successfully!' . 
                          ($uploaded_images > 0 ? " $uploaded_images image(s) uploaded." : "");
                $message_type = 'success';
                
                // Clear form
                echo '<script>
                    document.getElementById("post-form").reset();
                    document.getElementById("image-preview").innerHTML = "";
                    document.getElementById("image-count").textContent = "No images selected";
                </script>';
            } else {
                $message = 'Post created but no images were uploaded.';
                $message_type = 'warning';
            }
        } else {
            $message = 'Error creating post: ' . $conn->error;
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post with Photos</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            color: #1c1e21;
            line-height: 1.5;
            padding: 20px;
            overflow-x:hidden;
        }
        
        .container {
            max-width: 680px;
            margin: 80px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e4e6eb;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #1877f2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 12px;
            overflow: hidden;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .company-avatar {
            background-color: #42b883;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-type {
            font-size: 14px;
            color: #65676b;
        }
        
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #1c1e21;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #dddfe2;
            border-radius: 8px;
            resize: vertical;
            font-size: 16px;
        }
        
        textarea:focus {
            outline: none;
            border-color: #1877f2;
            box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.2);
        }
        
        .upload-area {
            border: 2px dashed #ccd0d5;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
            background-color: #f7f8fa;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: #1877f2;
            background-color: #ebf5ff;
        }
        
        .upload-area p {
            margin-bottom: 15px;
            color: #65676b;
        }
        
        .upload-btn {
            display: inline-block;
            background-color: #e7f3ff;
            color: #1877f2;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .upload-btn:hover {
            background-color: #dbe7f2;
        }
        
        #file-input {
            display: none;
        }
        
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .preview-item {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(255, 255, 255, 0.8);
            color: #ff0000;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
        }
        
        .submit-btn {
            background-color: #1877f2;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #166fe5;
        }
        
        .submit-btn:disabled {
            background-color: #e4e6eb;
            cursor: not-allowed;
        }
        
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background-color: #e7f9ed;
            color: #0a6634;
            border: 1px solid #a3e9bb;
        }
        
        .error {
            background-color: #feeaea;
            color: #d03c3c;
            border: 1px solid #f8c9c9;
        }
        
        .warning {
            background-color: #fff4e6;
            color: #e67700;
            border: 1px solid #ffd8a8;
        }
        
        .login-prompt {
            text-align: center;
            padding: 30px;
        }
        
        .login-btn {
            display: inline-block;
            background-color: #1877f2;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 15px;
        }
        
        .file-size-warning {
            font-size: 14px;
            color: #65676b;
            margin-top: 10px;
        }
        
        .image-count {
            font-size: 14px;
            color: #65676b;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .upload-area {
                padding: 20px;
            }
            
            .preview-item {
                width: 100px;
                height: 100px;
            }
        }
   
  
        
.navbar {
    margin: -40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--card-background);
    padding: 15px 40px;
    box-shadow: 0 2px 4px var(--shadow-light);
    position: sticky;
    top: 0;
    z-index: 1000;
    background-color: white;
}

.navbar-left .logo {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-blue);
}

.navbar-center ul {
    list-style: none;
    display: flex;
    gap: 30px;
}

.navbar-center ul li a {
    text-decoration: none;
    color: var(--text-color-light);
    font-weight: 500;
    transition: color 0.3s ease;
}

.navbar-center ul li a:hover,
.navbar-center ul li a.active {
    color: var(--primary-blue);
}

.navbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.navbar-right .location {
    display: flex;
    align-items: center;
    color: var(--text-color-light);
    font-size: 14px;
}

.navbar-right .location i {
    margin-right: 5px;
    color: var(--primary-blue);
}

.navbar-right .profile-avatar img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

.navbar-right .menu-icon {
    color: var(--text-color-light);
    font-size: 18px;
    cursor: pointer;
}


.profile-container {
    display: flex;
    gap: 30px;
 
    max-width: 100%;
    margin: 30px ;
    
    align-items: flex-start;
}

.create-post-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #e60023;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    z-index: 10;
}


    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar-left">
            <h1 class="logo">JOBconnect.</h1>
        </div>
        <nav class="navbar-center">
            <ul>
                <li><a href="#" class="active">Jobs</a></li>
                <li><a href="#">Messages</a></li>
                <li><a href="#">Notifications</a></li>
            </ul>
        </nav>
        <div class="navbar-right">
            <div class="location">
                
                <span id="navbarLocation">City, District </span> ㅤ<i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="profile-avatar">
                <img src="https://placehold.co/150x150/png?text=P" alt="Profile Picture" id="navbarProfilePicture">
            </div>
        </div>
    </header>
    <div class="container">
        <h1>Create a New Post</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($user_id || $company_id): ?>
            <div class="user-info">
                <div class="avatar <?php echo $user_type == 'company' ? 'company-avatar' : ''; ?>">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-type"><?php echo $user_type == 'user' ? 'Personal Account' : 'Company Account'; ?></div>
                </div>
            </div>
            
            <form action="" method="post" enctype="multipart/form-data" id="post-form">
                <div class="form-group">
                    <textarea name="content" placeholder="What's on your mind?" required></textarea>
                </div>
                
                <div class="upload-area" id="upload-area">
                    <p>Drag & drop photos here or</p>
                    <label for="file-input" class="upload-btn">Select Photos</label>
                    <input type="file" id="file-input" name="images[]" multiple accept="image/*">
                    <div class="file-size-warning">Maximum 5MB per image</div>
                    <div class="image-count" id="image-count">No images selected</div>
                </div>
                
                <div class="image-preview" id="image-preview"></div>
                
                <button type="submit" name="create_post" class="submit-btn">Post</button>
            </form>
        <?php else: ?>
            <div class="login-prompt">
                <p>You need to be logged in to create a post.</p>
                <a href="login.php" class="login-btn">Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('file-input');
            const uploadArea = document.getElementById('upload-area');
            const imagePreview = document.getElementById('image-preview');
            const imageCount = document.getElementById('image-count');
            const form = document.getElementById('post-form');
            let files = [];
            
            if (!fileInput || !uploadArea || !imagePreview || !form) {
                return; // Elements don't exist if user is not logged in
            }
            
            // Update image count display
            function updateImageCount() {
                const count = files.length;
                imageCount.textContent = count === 0 ? 'No images selected' : 
                                       count === 1 ? '1 image selected' : 
                                       `${count} images selected`;
            }
            
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function() {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                if (e.dataTransfer.files.length) {
                    handleFiles(e.dataTransfer.files);
                }
            });
            
            // File input change
            fileInput.addEventListener('change', function() {
                if (this.files.length) {
                    handleFiles(this.files);
                }
            });
            
            // Click on upload area to open file dialog
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            // Handle selected files
            function handleFiles(newFiles) {
                for (let i = 0; i < newFiles.length; i++) {
                    if (newFiles[i].type.startsWith('image/')) {
                        // Check file size (client-side validation)
                        if (newFiles[i].size > 5 * 1024 * 1024) {
                            alert('File "' + newFiles[i].name + '" exceeds the 5MB size limit.');
                            continue;
                        }
                        
                        files.push(newFiles[i]);
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-item';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            previewItem.appendChild(img);
                            
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'remove-btn';
                            removeBtn.innerHTML = '×';
                            removeBtn.addEventListener('click', function() {
                                const index = Array.from(imagePreview.children).indexOf(previewItem);
                                files.splice(index, 1);
                                previewItem.remove();
                                updateFileInput();
                                updateImageCount();
                            });
                            
                            previewItem.appendChild(removeBtn);
                            imagePreview.appendChild(previewItem);
                        };
                        
                        reader.readAsDataURL(newFiles[i]);
                    }
                }
                updateFileInput();
                updateImageCount();
            }
            
            // Update the file input with selected files
            function updateFileInput() {
                const dataTransfer = new DataTransfer();
                files.forEach(file => dataTransfer.items.add(file));
                fileInput.files = dataTransfer.files;
            }
            
            // Initialize image count
            updateImageCount();
            
            // Form submission validation
            form.addEventListener('submit', function() {
                // You could add additional validation here if needed
            });
        });
    </script>
</body>
</html>