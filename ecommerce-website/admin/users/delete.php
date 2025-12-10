<?php

require_once '../../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('index.php');
}

// Prevent admin from deleting themselves
if ($id == 1) { // Assuming admin user has ID 1
    $_SESSION['error'] = 'Cannot delete the main admin account';
    redirect('index.php');
}

// Get user details for confirmation
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    redirect('index.php');
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if user has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
        $stmt->execute([$id]);
        $order_count = $stmt->fetch()['order_count'];
        
        if ($order_count > 0) {
            // Keep orders but set user_id to NULL
            $stmt = $pdo->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
            $stmt->execute([$id]);
        }
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "User '{$user['username']}' deleted successfully";
        if ($order_count > 0) {
            $_SESSION['success'] .= ". {$order_count} order(s) are now anonymous.";
        }
        
        redirect('index.php');
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
        redirect('index.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User - Admin</title>
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
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .warning-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .warning-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .warning-title {
            color: #721c24;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .warning-text {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            text-align: left;
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
            margin: 0 10px;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-secondary { background: #6c757d; }
        
        .form-group {
            margin-top: 30px;
        }
        
        .confirm-text {
            font-size: 14px;
            color: #666;
            margin: 15px 0;
        }
        
        .confirm-input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            width: 100%;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🗑️ Delete User</h1>
        <div>
            <a href="index.php">← Back to Users</a>
        </div>
    </div>
    
    <div class="container">
        <div class="warning-card">
            <div class="warning-icon">⚠️</div>
            <div class="warning-title">Delete User Account</div>
            
            <div class="user-info">
                <h3 style="margin-bottom: 10px; color: #333;">User to be deleted:</h3>
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>User ID:</strong> #<?= $id ?></p>
            </div>
            
            <div class="danger-box">
                <strong>⚠️ Warning: This action cannot be undone!</strong><br>
                • User account will be permanently deleted<br>
                • User will no longer be able to login<br>
                • User's orders will be kept as anonymous<br>
                • This action is irreversible
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <div class="confirm-text">
                        Type <strong>"DELETE"</strong> to confirm deletion:
                    </div>
                    <input type="text" name="confirm" class="confirm-input" 
                           placeholder="Type DELETE here" required
                           pattern="DELETE">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-danger">
                        Permanently Delete User
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>