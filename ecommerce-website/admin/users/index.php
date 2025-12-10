<?php

require_once '../../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    redirect('../login.php');
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Handle search
$search = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE username LIKE ? OR email LIKE ? OR name LIKE ?
        ORDER BY created_at DESC
    ");
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $users = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
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
            display: inline-block;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-warning { background: #ffc107; color: #212529; }
        
        table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 200px;
        }
        
        .stat-icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-info h3 {
            font-size: 24px;
            margin: 0;
            color: #333;
        }
        
        .stat-info p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>👥 Manage Users</h1>
        <div>
            <a href="../index.php">← Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <span>⚠️</span>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Statistics -->
        <?php
        $total_users = count($users);
        $today_users = 0;
        
        // Count users created today
        foreach ($users as $user) {
            if (isset($user['created_at']) && date('Y-m-d', strtotime($user['created_at'])) == date('Y-m-d')) {
                $today_users++;
            }
        }
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?= $total_users ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <h3><?= $today_users ?></h3>
                    <p>New Today</p>
                </div>
            </div>
        </div>
        
        <!-- Search Box -->
        <div class="search-box">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search users by name, username, or email..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn">Search</button>
                <?php if ($search): ?>
                    <a href="index.php" class="btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="header">
            <h2>User List (<?= count($users) ?>)</h2>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">
                                <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($user['name'] ?? 'No Name') ?></strong>
                                <div style="font-size: 12px; color: #666;">
                                    ID: #<?= $user['id'] ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                    <td>
                        <?php if (isset($user['created_at'])): ?>
                            <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="view.php?id=<?= $user['id'] ?>" class="btn" title="View Details">
                                View
                            </a>
                            <a href="delete.php?id=<?= $user['id'] ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete user: <?= addslashes($user['username']) ?>?')"
                               title="Delete User">
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <div style="font-size: 18px; color: #666; margin-bottom: 15px;">
                            👥 No users found
                        </div>
                        <?php if ($search): ?>
                            <p style="color: #999;">No users match your search criteria.</p>
                        <?php else: ?>
                            <p style="color: #999;">No users in the system yet.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($users)): ?>
        <div style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">
            Showing <?= count($users) ?> user<?= count($users) != 1 ? 's' : '' ?>
            • <?= $today_users ?> new today • Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>