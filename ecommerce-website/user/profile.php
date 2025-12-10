<?php
// ========================================
// FILE: user/profile.php
// ========================================
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    $errors = [];
    
    // Validate name
    if (empty($name)) {
        $errors[] = 'Full name is required';
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email';
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already taken by another account';
        }
    }
    
    // Validate phone
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{10,}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number';
    }
    
    // Password change validation
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (empty($new_password)) {
            $errors[] = 'New password is required';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update basic info
            $update_sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?";
            $params = [$name, $email, $phone ?: NULL, $address ?: NULL];
            
            // Add password update if needed
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql .= ", password = ?";
                $params[] = $hashed_password;
            }
            
            $update_sql .= " WHERE id = ?";
            $params[] = $user_id;
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute($params);
            
            // Update session email
            $_SESSION['email'] = $email;
            
            $pdo->commit();
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to update profile: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            gap: 20px;
        }
        
        .user-avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group label .optional {
            color: #999;
            font-weight: normal;
            font-size: 12px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .error-message {
            background: #fee;
            color: #c00;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
            font-size: 14px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        
        .section-title {
            color: #333;
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            font-size: 20px;
        }
        
        .password-note {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1976d2;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>✏️ Edit Profile</h1>
        <div>
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="../index.php">← Store</a>
        </div>
    </div>
    
    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="user-avatar-large">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div>
                    <h1 style="margin-bottom: 5px;">Edit Your Profile</h1>
                    <p style="color: #666;">Update your personal information</p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">⚠️ <?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">✓ <?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Personal Information -->
                <h2 class="section-title">Personal Information</h2>
                
                <div class="form-group">
                    <label for="name">Full Name <span style="color: #dc3545">*</span></label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        placeholder="Enter your full name"
                        required
                        value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address <span style="color: #dc3545">*</span></label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email"
                            required
                            value="<?= htmlspecialchars($user['email']) ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="optional"></span></label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="e.g., 0912-345-6789"
                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Shipping Address <span class="optional">\</span></label>
                    <textarea 
                        id="address" 
                        name="address" 
                        placeholder="Enter your complete shipping address"
                    ><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                
                <!-- Password Change -->
                <h2 class="section-title">Change Password</h2>
                
                <div class="password-note">
                    🔒 Leave password fields empty if you don't want to change your password.
                </div>
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        placeholder="Enter your current password"
                    >
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            placeholder="Enter new password (min 6 characters)"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Confirm new password"
                        >
                    </div>
                </div>
                
                <div style="margin-top: 40px;">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>