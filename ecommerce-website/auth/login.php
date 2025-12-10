<?php
require_once '../config.php';

// If already logged in as user, redirect
if (isUserLoggedIn()) {
    redirect('../index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's login or registration
    $form_type = $_POST['form_type'] ?? 'login';
    
    if ($form_type === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter username and password';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if (userLogin($user['id'], $user['username'], $user['email'])) {
                        redirect('../index.php');
                    } else {
                        $error = 'Login failed. Please try again.';
                    }
                } else {
                    $error = 'Invalid username or password';
                }
            } catch(PDOException $e) {
                $error = 'Login failed. Please try again.';
            }
        }
    } else if ($form_type === 'register') {
        // Handle registration
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = trim($_POST['reg_password'] ?? '');
        $confirm_password = trim($_POST['reg_confirm_password'] ?? '');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            try {
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check_stmt->execute([$username, $email]);
                
                if ($check_stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
                    
                    if ($stmt->execute([$username, $email, $hashed_password])) {
                        // Auto-login after registration
                        $user_id = $pdo->lastInsertId();
                        if (userLogin($user_id, $username, $email)) {
                            $_SESSION['success'] = 'Registration successful! Welcome!';
                            redirect('../index.php');
                        }
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            } catch(PDOException $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Login / Register</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Subtle background pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(102, 126, 234, 0.02) 0%, transparent 50%);
            z-index: -1;
        }
        
        .form-container {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.06),
                0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 480px;
            min-height: 580px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .form-wrapper {
            width: 200%;
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }
        
        .form-wrapper.show-register {
            transform: translateX(-50%);
        }
        
        .form-panel {
            width: 50%;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 48px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        
        .form-header h1 {
            color: #1a202c;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        
        .form-header p {
            color: #718096;
            font-size: 16px;
            line-height: 1.5;
            max-width: 300px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.2px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 16px 20px 16px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            background: #ffffff;
            color: #2d3748;
            font-weight: 500;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        
        .input-wrapper input::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
            pointer-events: none;
            transition: color 0.2s;
        }
        
        .input-wrapper input:focus + .input-icon {
            color: #667eea;
        }
        
        .error-message {
            background: #fff5f5;
            color: #c53030;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #fed7d7;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }
        
        .error-message::before {
            content: '⚠️';
            font-size: 16px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 8px 25px rgba(102, 126, 234, 0.3),
                0 4px 10px rgba(102, 126, 234, 0.2);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn-submit:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 15px;
            color: #718096;
            line-height: 1.5;
        }
        
        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            padding: 2px 4px;
            margin: 0 2px;
            border-radius: 4px;
        }
        
        .form-footer a:hover {
            color: #764ba2;
            background: rgba(102, 126, 234, 0.08);
        }
        
        .form-footer a:focus {
            outline: none;
            background: rgba(102, 126, 234, 0.12);
        }
        
        .back-to-home {
            position: absolute;
            top: 24px;
            left: 24px;
            color: #718096;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            padding: 8px 12px;
            border-radius: 8px;
            z-index: 10;
        }
        
        .back-to-home:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.08);
        }
        
        .back-to-home:focus {
            outline: none;
            background: rgba(102, 126, 234, 0.12);
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background-color: #fc8181; }
        .strength-medium { background-color: #f6ad55; }
        .strength-strong { background-color: #68d391; }
        
        /* Loading spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }
        
        .loading .spinner {
            display: block;
        }
        
        .loading .btn-submit span {
            display: none;
        }
        
        /* Form toggle indicator */
        .form-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        
        .indicator-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s;
        }
        
        .indicator-dot.active {
            background: #667eea;
            transform: scale(1.2);
        }
        
        /* Terms checkbox for registration */
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 16px;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .terms-checkbox input {
            margin-top: 3px;
        }
        
        .terms-checkbox label {
            font-size: 13px;
            color: #718096;
            line-height: 1.4;
        }
        
        .terms-checkbox a {
            color: #667eea;
            text-decoration: none;
        }
        
        .terms-checkbox a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .form-container {
                max-width: 100%;
                border-radius: 20px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            }
            
            .form-panel {
                padding: 48px 28px;
            }
            
            .form-header h1 {
                font-size: 28px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .back-to-home {
                top: 16px;
                left: 16px;
            }
        }
        
        /* Focus styles */
        *:focus {
            outline: none;
        }
        
        .input-wrapper input:focus,
        .btn-submit:focus,
        .back-to-home:focus,
        .form-footer a:focus {
            outline: 2px solid rgba(102, 126, 234, 0.3);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="../index.php" class="back-to-home">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Home
        </a>
        
        <div class="form-wrapper" id="formWrapper">
            <!-- Login Form -->
            <div class="form-panel" id="loginPanel">
                <div class="form-header">
                    <div class="logo"><?= SITE_NAME ?></div>
                    <h1>Welcome Back</h1>
                    <p>Sign in to continue to your account</p>
                </div>
                
                <?php if ($error && (!isset($_POST['form_type']) || $_POST['form_type'] === 'login')): ?>
                    <div class="error-message" id="loginError">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm" onsubmit="handleLoginSubmit(event)">
                    <input type="hidden" name="form_type" value="login">
                    
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="Enter your username or email"
                                required
                                value="<?= isset($_POST['form_type']) && $_POST['form_type'] === 'login' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required
                            >
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="loginBtn">
                        <span>Sign In</span>
                        <div class="spinner"></div>
                    </button>
                </form>
                
                <div class="form-footer">
                    Don't have an account? <a onclick="showRegister()">Create Account</a>
                </div>
                
                <div class="form-indicator">
                    <div class="indicator-dot active"></div>
                    <div class="indicator-dot"></div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="form-panel" id="registerPanel">
                <div class="form-header">
                    <div class="logo"><?= SITE_NAME ?></div>
                    <h1>Create Account</h1>
                    <p>Join us and start your shopping journey</p>
                </div>
                
                <?php if ($error && isset($_POST['form_type']) && $_POST['form_type'] === 'register'): ?>
                    <div class="error-message" id="registerError">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm" onsubmit="handleRegisterSubmit(event)">
                    <input type="hidden" name="form_type" value="register">
                    
                    <div class="form-group">
                        <label for="reg_username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input 
                                type="text" 
                                id="reg_username" 
                                name="reg_username" 
                                placeholder="Choose a username"
                                required
                                value="<?= isset($_POST['form_type']) && $_POST['form_type'] === 'register' ? htmlspecialchars($_POST['reg_username'] ?? '') : '' ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_email">Email Address</label>
                        <div class="input-wrapper">
                            <span class="input-icon">📧</span>
                            <input 
                                type="email" 
                                id="reg_email" 
                                name="reg_email" 
                                placeholder="Enter your email address"
                                required
                                value="<?= isset($_POST['form_type']) && $_POST['form_type'] === 'register' ? htmlspecialchars($_POST['reg_email'] ?? '') : '' ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                id="reg_password" 
                                name="reg_password" 
                                placeholder="Create a password (min. 6 characters)"
                                required
                                minlength="6"
                                oninput="updatePasswordStrength()"
                            >
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                id="reg_confirm_password" 
                                name="reg_confirm_password" 
                                placeholder="Confirm your password"
                                required
                                minlength="6"
                            >
                        </div>
                    </div>
                    
                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="registerBtn">
                        <span>Create Account</span>
                        <div class="spinner"></div>
                    </button>
                </form>
                
                <div class="form-footer">
                    Already have an account? <a onclick="showLogin()">Sign In</a>
                </div>
                
                <div class="form-indicator">
                    <div class="indicator-dot"></div>
                    <div class="indicator-dot active"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const formWrapper = document.getElementById('formWrapper');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const strengthBar = document.getElementById('strengthBar');
        
        // Show registration form
        function showRegister() {
            formWrapper.classList.add('show-register');
            clearErrors();
            updateIndicators('register');
        }
        
        // Show login form
        function showLogin() {
            formWrapper.classList.remove('show-register');
            clearErrors();
            updateIndicators('login');
        }
        
        // Update indicator dots
        function updateIndicators(formType) {
            const dots = document.querySelectorAll('.indicator-dot');
            dots.forEach((dot, index) => {
                if (formType === 'login') {
                    dot.classList.toggle('active', index === 0);
                } else {
                    dot.classList.toggle('active', index === 1);
                }
            });
        }
        
        // Clear error messages
        function clearErrors() {
            const errors = document.querySelectorAll('.error-message');
            errors.forEach(error => {
                error.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => error.remove(), 300);
            });
        }
        
        // Password strength indicator
        function updatePasswordStrength() {
            const password = document.getElementById('reg_password').value;
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.className = 'strength-bar strength-weak';
            } else if (strength < 75) {
                strengthBar.className = 'strength-bar strength-medium';
            } else {
                strengthBar.className = 'strength-bar strength-strong';
            }
        }
        
        // Handle form submissions
        function handleLoginSubmit(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                showError('login', 'Please fill in all fields');
                return;
            }
            
            loginBtn.classList.add('loading');
            loginForm.submit();
        }
        
        function handleRegisterSubmit(e) {
            e.preventDefault();
            
            const username = document.getElementById('reg_username').value.trim();
            const email = document.getElementById('reg_email').value.trim();
            const password = document.getElementById('reg_password').value.trim();
            const confirmPassword = document.getElementById('reg_confirm_password').value.trim();
            const terms = document.getElementById('terms').checked;
            
            // Validation
            if (!username || !email || !password || !confirmPassword) {
                showError('register', 'All fields are required');
                return;
            }
            
            if (!validateEmail(email)) {
                showError('register', 'Please enter a valid email address');
                return;
            }
            
            if (password.length < 6) {
                showError('register', 'Password must be at least 6 characters');
                return;
            }
            
            if (password !== confirmPassword) {
                showError('register', 'Passwords do not match');
                return;
            }
            
            if (!terms) {
                showError('register', 'Please agree to the Terms of Service');
                return;
            }
            
            registerBtn.classList.add('loading');
            registerForm.submit();
        }
        
        function showError(formType, message) {
            clearErrors();
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = message;
            
            if (formType === 'login') {
                document.getElementById('loginForm').prepend(errorDiv);
            } else {
                document.getElementById('registerForm').prepend(errorDiv);
            }
            
            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Auto-show register form if there are register errors
        <?php if (isset($_POST['form_type']) && $_POST['form_type'] === 'register' && $error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {
                    showRegister();
                }, 100);
            });
        <?php endif; ?>
        
        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_POST['form_type']) && $_POST['form_type'] === 'register'): ?>
                setTimeout(() => {
                    const input = document.getElementById('reg_username');
                    if (input) input.focus();
                }, 500);
            <?php else: ?>
                const input = document.getElementById('username');
                if (input) input.focus();
            <?php endif; ?>
        });
        
        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-10px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>