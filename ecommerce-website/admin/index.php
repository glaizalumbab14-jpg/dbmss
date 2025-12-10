<?php

require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
$totalProducts = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$totalOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
$totalRevenue = $stmt->fetch()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card.revenue .number {
            color: #28a745;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
        }
        
        .menu-card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .menu-card h3 {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🛠️ Admin Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <strong><?= $_SESSION['admin_username'] ?? 'Admin' ?></strong></span>
             
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                ✓ <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <h2 style="margin-bottom: 20px;">Dashboard Overview</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="number"><?= $totalProducts ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?= $totalUsers ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="number"><?= $totalOrders ?></div>
            </div>
            
            <div class="stat-card revenue">
                <h3>Total Revenue</h3>
                <div class="number">$<?= number_format($totalRevenue, 2) ?></div>
            </div>
        </div>
        
        <h2 style="margin: 30px 0 20px 0;">Quick Actions</h2>
        
        <div class="menu-grid">
            <a href="products/index.php" class="menu-card">
                <div class="icon">📦</div>
                <h3>Manage Products</h3>
            </a>
            
            <a href="categories/index.php" class="menu-card">
                <div class="icon">📂</div>
                <h3>Manage Categories</h3>
            </a>
            
            <a href="orders/index.php" class="menu-card">
                <div class="icon">🛒</div>
                <h3>View Orders</h3>
            </a>
            
            <a href="users/index.php" class="menu-card">
                <div class="icon">👥</div>
                <h3>Manage Users</h3>
            </a>
        </div>
    </div>
</body>
</html>s