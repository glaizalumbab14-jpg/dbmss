<?php
// ========================================
// FILE: admin/users/view.php
// ========================================
require_once '../../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('index.php');
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    redirect('index.php');
}

// Get user's orders count
$stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
$stmt->execute([$id]);
$order_count = $stmt->fetch()['order_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Admin</title>
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
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        
        .btn-danger { background: #dc3545; }
        
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-header {
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
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
        }
        
        .info-box h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ddd;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .stats-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #f0f0f0;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .last-login {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>👤 User Details</h1>
        <div>
            <a href="index.php">← Back to Users</a>
        </div>
    </div>
    
    <div class="container">
        <div class="user-card">
            <div class="user-header">
                <div class="user-avatar-large">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div>
                    <h1 style="margin-bottom: 5px;"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></h1>
                    <p style="color: #666;">User ID: #<?= $user['id'] ?></p>
                    <div class="last-login">
                        Joined: <?= date('F j, Y', strtotime($user['created_at'])) ?>
                    </div>
                </div>
            </div>
            
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-number">#<?= $user['id'] ?></div>
                    <div class="stat-label">User ID</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $order_count ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?= date('Y', strtotime($user['created_at'])) ?>
                    </div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
            
            <div class="user-info-grid">
                <div class="info-box">
                    <h3>Account Information</h3>
                    <div class="info-row">
                        <span class="info-label">Username:</span>
                        <span class="info-value"><?= htmlspecialchars($user['username']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></span>
                    </div>
                </div>
                
                <div class="info-box">
                    <h3>Personal Information</h3>
                    <div class="info-row">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?= htmlspecialchars($user['name'] ?? 'Not provided') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value">
                            <?= nl2br(htmlspecialchars($user['address'] ?? 'Not provided')) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account Created:</span>
                        <span class="info-value">
                            <?= date('F j, Y \a\t g:i A', strtotime($user['created_at'])) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value">
                            <?= $user['updated_at'] ? date('F j, Y \a\t g:i A', strtotime($user['updated_at'])) : 'Never' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <a href="index.php" class="btn-secondary btn">Back to Users</a>
                <a href="delete.php?id=<?= $user['id'] ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                    Delete User
                </a>
            </div>
        </div>
    </div>
</body>
</html>