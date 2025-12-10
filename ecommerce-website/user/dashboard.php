<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's orders count
$stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$order_count = $stmt->fetch()['order_count'];

// Get total spent
$stmt = $pdo->prepare("SELECT SUM(total) as total_spent FROM orders WHERE user_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_id]);
$total_spent = $stmt->fetch()['total_spent'] ?? 0;

// Get recent orders with product details
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           oi.product_name,
           oi.image as product_image
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ? 
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// For each order, get more product details (first 2 products)
$order_details = [];
foreach ($recent_orders as $order) {
    $items_stmt = $pdo->prepare("
        SELECT oi.*, p.image as product_image 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ? 
        ORDER BY oi.id ASC 
        LIMIT 2
    ");
    $items_stmt->execute([$order['id']]);
    $order_details[$order['id']] = $items_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar a { 
            color: white; 
            text-decoration: none; 
            margin-left: 20px;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .welcome-text h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .welcome-text p {
            color: #666;
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 16px;
            font-weight: 500;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .section-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px 10px 0 0;
        }
        
        .section-card h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 20px;
        }
        
        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .quick-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        
        .quick-link:hover {
            background: white;
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .quick-link .icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .quick-link-content {
            flex: 1;
        }
        
        .quick-link-content strong {
            display: block;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .quick-link-content p {
            font-size: 12px;
            color: #666;
            margin: 0;
            line-height: 1.4;
        }
        
        /* Product Items in Orders */
        .order-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .order-item:hover {
            background: #f8f9fa;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .order-number {
            font-weight: bold;
            color: #667eea;
            font-size: 14px;
        }
        
        .order-date {
            font-size: 12px;
            color: #999;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .status-processing { 
            background: #d1ecf1; 
            color: #0c5460; 
        }
        .status-shipped { 
            background: #cce5ff; 
            color: #004085; 
        }
        .status-delivered { 
            background: #d4edda; 
            color: #155724; 
        }
        .status-cancelled { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            transition: transform 0.2s;
        }
        
        .product-item:hover {
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            overflow: hidden;
            background: white;
            border: 1px solid #eee;
            flex-shrink: 0;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #999;
            font-size: 12px;
        }
        
        .product-info {
            flex: 1;
            min-width: 0;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        
        .product-meta {
            display: flex;
            gap: 5px;
            font-size: 10px;
            color: #666;
        }
        
        .product-quantity {
            background: #667eea;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        .product-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .more-products {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #666;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 3px;
            margin-top: 10px;
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: bold;
            color: #333;
        }
        
        .amount {
            color: #28a745;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover { 
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
        }
        
        .btn-view-all {
            display: block;
            text-align: center;
            margin-top: 25px;
            background: #6c757d;
        }
        
        .btn-view-all:hover { 
            background: #5a6268;
        }
        
        .user-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 25px;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .no-orders {
            text-align: center;
            padding: 40px 20px;
        }
        
        .no-orders .icon {
            font-size: 64px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .no-orders h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-orders p {
            color: #999;
            margin-bottom: 20px;
        }
        
        .account-info {
            padding: 10px 0;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
            display: block;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .navbar div {
                display: flex;
                gap: 10px;
            }
            
            .navbar a {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>👤 My Account</h1>
        <div>
            <a href="../index.php">← Store</a>
            <a href="../cart/index.php">🛒 Cart</a>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-header">
            <div class="user-avatar-large">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars($user['name'] ?? $user['username']) ?>!</h1>
                <p>Here's what's happening with your account today.</p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-number"><?= $order_count ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$<?= number_format($total_spent, 2) ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-number"><?= date('Y', strtotime($user['created_at'])) ?></div>
                <div class="stat-label">Member Since</div>
            </div>
        </div>
        
        <div class="dashboard-sections">
            <!-- Quick Links -->
            <div class="section-card">
                <h2>Quick Actions</h2>
                <div class="quick-links">
                    <a href="profile.php" class="quick-link">
                        <div class="icon">👤</div>
                        <div class="quick-link-content">
                            <strong>Edit Profile</strong>
                            <p>Update your personal information</p>
                        </div>
                    </a>
                    
                    <a href="orders.php" class="quick-link">
                        <div class="icon">📦</div>
                        <div class="quick-link-content">
                            <strong>My Orders</strong>
                            <p>View and track your orders</p>
                        </div>
                    </a>
                    
                    <a href="../cart/index.php" class="quick-link">
                        <div class="icon">🛒</div>
                        <div class="quick-link-content">
                            <strong>Shopping Cart</strong>
                            <p>Continue shopping</p>
                        </div>
                    </a>
                    
                   
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="section-card">
                <h2>Recent Orders</h2>
                <?php if (empty($recent_orders)): ?>
                    <div class="no-orders">
                        <div class="icon">📦</div>
                        <h3>No Orders Yet</h3>
                        <p>You haven't placed any orders yet.</p>
                        <a href="../products/index.php" class="btn" style="margin-top: 15px;">
                            🛍️ Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): 
                        $order_items = $order_details[$order['id']] ?? [];
                        $item_count = $order['item_count'] ?? 0;
                    ?>
                    <div class="order-item">
                        <div class="order-header">
                            <div>
                                <div class="order-number"><?= $order['order_number'] ?? 'Order #' . $order['id'] ?></div>
                                <div class="order-date">
                                    <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                            <span class="order-status status-<?= $order['order_status'] ?>">
                                <?= ucfirst($order['order_status']) ?>
                            </span>
                        </div>
                        
                        <!-- Product Items -->
                        <?php if (!empty($order_items)): ?>
                        <div class="product-grid">
                            <?php foreach ($order_items as $item): ?>
                            <div class="product-item">
                                <div class="product-image">
                                    <?php if (!empty($item['product_image'])): ?>
                                        <img src="../assets/uploads/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                        <div class="no-image" style="display: none;">📦</div>
                                    <?php else: ?>
                                        <div class="no-image">📦</div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name" title="<?= htmlspecialchars($item['product_name']) ?>">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </div>
                                    <div class="product-meta">
                                        <span class="product-quantity">x<?= $item['quantity'] ?></span>
                                        <span class="product-price">$<?= number_format($item['unit_price'] ?? 0, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if ($item_count > 2): ?>
                            <div class="more-products">
                                +<?= $item_count - 2 ?> more item<?= ($item_count - 2) > 1 ? 's' : '' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="order-total">
                            <span><?= $item_count ?> item<?= $item_count != 1 ? 's' : '' ?></span>
                            <span class="amount">$<?= number_format($order['total'] ?? $order['total_amount'] ?? 0, 2) ?></span>
                        </div>
                      
                    </div>
                    <?php endforeach; ?>
                    
                    <a href="orders.php" class="btn btn-view-all">
                        📋 View All Orders →
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Account Info -->
            <div class="section-card">
                <h2>Account Information</h2>
                <div class="account-info">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    
                    <?php if ($user['phone']): ?>
                    <div class="info-item">
                        <span class="info-label">Phone Number</span>
                        <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['address']): ?>
                    <div class="info-item">
                        <span class="info-label">Shipping Address</span>
                        <div class="info-value"><?= nl2br(htmlspecialchars($user['address'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <a href="profile.php" class="btn" style="width: 100%; margin-top: 20px;">
                    ✏️ Edit Profile Information
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Handle image loading errors
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const noImage = this.nextElementSibling;
                    if (noImage && noImage.classList.contains('no-image')) {
                        noImage.style.display = 'flex';
                    }
                });
            });
            
            // Add hover effect to product items
            document.querySelectorAll('.product-item').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    const productName = this.querySelector('.product-name');
                    if (productName.scrollWidth > productName.clientWidth) {
                        const tooltip = document.createElement('div');
                        tooltip.className = 'product-tooltip';
                        tooltip.textContent = productName.getAttribute('title');
                        tooltip.style.cssText = `
                            position: absolute;
                            background: rgba(0,0,0,0.8);
                            color: white;
                            padding: 5px 10px;
                            border-radius: 3px;
                            font-size: 11px;
                            z-index: 1000;
                            white-space: nowrap;
                            pointer-events: none;
                        `;
                        document.body.appendChild(tooltip);
                        
                        const rect = this.getBoundingClientRect();
                        tooltip.style.left = rect.left + 'px';
                        tooltip.style.top = (rect.top - 30) + 'px';
                        
                        this.tooltip = tooltip;
                    }
                });
                
                item.addEventListener('mouseleave', function() {
                    if (this.tooltip) {
                        this.tooltip.remove();
                    }
                });
            });
        });
    </script>
</body>
</html>