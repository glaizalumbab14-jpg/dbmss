<?php

require_once '../config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full Name is required';
    } elseif (strlen($full_name) < 2) {
        $errors[] = 'Full Name must be at least 2 characters';
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{10,}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number';
    }
    
    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                // Hash the password (SECURE!)
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with all fields
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, name, phone, address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $username, 
                    $email, 
                    $hashedPassword, 
                    $full_name, 
                    $phone ?: NULL, 
                    $address ?: NULL
                ]);
                
                // Auto-login after registration (optional)
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                unset($_SESSION['admin_id'], $_SESSION['admin_username']);
                
                $success = 'Registration successful! Welcome to ' . SITE_NAME;
                redirect('../index.php');
            }
        } catch(PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
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
    <title>Register - <?= SITE_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
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
            min-height: 80px;
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
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .password-strength.weak { color: #dc3545; }
        .password-strength.fair { color: #ffc107; }
        .password-strength.good { color: #28a745; }
        
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
    <div class="register-container">
        <div class="register-header">
            <h1>📝 Create Account</h1>
            <p>Join us today and start shopping!</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                ✓ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <!-- Personal Information -->
            <div class="form-group">
                <label for="full_name">Full Name <span style="color: #dc3545">*</span></label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    placeholder="Enter your full name"
                    required
                    value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                >
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username <span style="color: #dc3545">*</span></label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Choose a username"
                        required
                        value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                    >
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Letters, numbers, and underscores only
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span style="color: #dc3545">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your email"
                        required
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number <span class="optional"></span></label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    placeholder="e.g., 0912-345-6789"
                    value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>"
                >
            </div>
            
            <!-- Address -->
            <div class="form-group">
                <label for="address">Shipping Address <span class="optional"></span></label>
                <textarea 
                    id="address" 
                    name="address" 
                    placeholder="Enter your complete shipping address (you can add this later)"
                ><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
            </div>
            
            <!-- Password -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span style="color: #dc3545">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Create a password"
                        required
                        onkeyup="checkPasswordStrength()"
                    >
                    <div id="password-strength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #dc3545">*</span></label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Confirm your password"
                        required
                        onkeyup="checkPasswordMatch()"
                    >
                    <div id="password-match" class="password-strength"></div>
                </div>
            </div>
            
            <button type="submit" class="btn-register">
                Create Account
            </button>
        </form>
        
        <div class="links">
            Already have an account? <a href="login.php">Login here</a><br>
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthText = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthText.textContent = '';
                strengthText.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            let text = '';
            let className = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                text = 'Weak password';
                className = 'password-strength weak';
            } else if (strength <= 4) {
                text = 'Fair password';
                className = 'password-strength fair';
            } else {
                text = 'Strong password';
                className = 'password-strength good';
            }
            
            strengthText.textContent = text;
            strengthText.className = className;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'password-strength';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'password-strength good';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'password-strength weak';
            }
        }
        
        // Username validation
        document.getElementById('username').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    </script>
</body>
</html>