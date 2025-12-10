<?php

require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    redirect('../auth/login.php');
}

// Check if order was just placed
if (!isset($_SESSION['last_order'])) {
    redirect('../products/index.php');
}

$order_info = $_SESSION['last_order'];
$user_id = $_SESSION['user_id'];

// Get order details from database
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.total_price) as subtotal
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ? AND o.user_id = ?
    GROUP BY o.id
");
$stmt->execute([$order_info['order_id'], $user_id]);
$order = $stmt->fetch();

// Get order items
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.image 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$items_stmt->execute([$order_info['order_id']]);
$order_items = $items_stmt->fetchAll();

// Clear session order info after display
unset($_SESSION['last_order']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Container */
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Success Card */
        .success-card {
            background: white;
            padding: 50px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 40px;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 30px;
        }
        
        .success-title {
            color: #333;
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .success-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .order-number {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .order-number h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .order-number-code {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 1px;
        }
        
        /* Order Details */
        .order-details {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .order-details h2 {
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
            display: block;
        }
        
        .detail-value {
            color: #333;
            margin-top: 5px;
        }
        
        /* Order Items */
        .order-items {
            margin-top: 30px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .item-quantity {
            color: #666;
            font-size: 14px;
        }
        
        /* Order Summary */
        .order-summary {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .summary-row.total {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f8f9fa;
        }
        
        .summary-label {
            color: #666;
        }
        
        .summary-value {
            font-weight: 500;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 40px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #218838 0%, #17a2b8 100%);
        }
        
        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        /* Shipping Note */
        .shipping-note {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
            border-left: 4px solid #2196f3;
        }
        
        .shipping-note h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .shipping-note p {
            color: #555;
            margin-bottom: 5px;
        }
        
        /* Footer */
        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .success-card {
                padding: 30px;
            }
            
            .success-title {
                font-size: 28px;
            }
            
            .order-details {
                padding: 25px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🛒 <?= SITE_NAME ?></a>
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="../products/index.php">Products</a>
                <a href="../user/dashboard.php">My Account</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <!-- Success Message -->
        <div class="success-card">
            <div class="success-icon">✅</div>
            <h1 class="success-title">Order Confirmed!</h1>
            <p class="success-message">
                Thank you for your order! We've received your order and will begin processing it right away.<br>
                You'll receive an order confirmation email shortly.
            </p>
            
            <div class="order-number">
                <h3>Your Order Number</h3>
                <div class="order-number-code"><?= htmlspecialchars($order_info['order_number']) ?></div>
                <p style="color: #666; margin-top: 10px;">Keep this number for your records</p>
            </div>
            
            <div class="action-buttons">
                <a href="../user/orders.php" class="btn btn-primary">
                    📦 View My Orders
                </a>
                <a href="../products/index.php" class="btn btn-outline">
                    🛍️ Continue Shopping
                </a>
            </div>
        </div>
        
        <!-- Order Details -->
        <div class="order-details">
            <h2>Order Details</h2>
            
            <div class="details-grid">
                <div class="detail-section">
                    <h3>Shipping Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Name</span>
                        <div class="detail-value"><?= htmlspecialchars($order['shipping_name']) ?></div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <div class="detail-value"><?= htmlspecialchars($order['shipping_email']) ?></div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <div class="detail-value"><?= htmlspecialchars($order['shipping_phone']) ?></div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Address</span>
                        <div class="detail-value">
                            <?= htmlspecialchars($order['shipping_address']) ?><br>
                            <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_zip']) ?><br>
                            <?= htmlspecialchars($order['shipping_country']) ?>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Order Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Order Date</span>
                        <div class="detail-value"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Order Status</span>
                        <div class="detail-value">
                            <span style="background: #ffc107; color: #212529; padding: 3px 8px; border-radius: 3px; font-weight: bold;">
                                Processing
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Method</span>
                        <div class="detail-value">Cash on Delivery</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Status</span>
                        <div class="detail-value">
                            <span style="background: #17a2b8; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;">
                                Pending
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="order-items">
                <h3 style="margin-bottom: 20px;">Order Items (<?= count($order_items) ?>)</h3>
                <?php foreach ($order_items as $item): ?>
                <div class="order-item">
                    <div class="item-image">
                        <?php if (!empty($item['image'])): ?>
                            <img src="../assets/uploads/products/<?= htmlspecialchars($item['image']) ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 24px; color: #ccc; background: #f8f9fa;">📦</div>
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #ccc; background: #f8f9fa;">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="item-details">
                        <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="item-price">$<?= number_format($item['unit_price'], 2) ?> × <?= $item['quantity'] ?></div>
                        <div class="item-quantity">Total: $<?= number_format($item['total_price'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="order-summary">
            <h2>Order Summary</h2>
            
            <div class="summary-row">
                <span class="summary-label">Subtotal</span>
                <span class="summary-value">$<?= number_format($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount'], 2) ?></span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Shipping</span>
                <span class="summary-value">
                    <?php if ($order['shipping_amount'] == 0): ?>
                        <span style="color: #28a745; font-weight: bold;">FREE</span>
                    <?php else: ?>
                        $<?= number_format($order['shipping_amount'], 2) ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Tax (10%)</span>
                <span class="summary-value">$<?= number_format($order['tax_amount'], 2) ?></span>
            </div>
            
            <div class="summary-row total">
                <span class="summary-label">Total Amount</span>
                <span class="summary-value">$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>
        
        <!-- Shipping Note -->
        <div class="shipping-note">
            <h3>⏱️ Estimated Delivery Time</h3>
            <p><strong>3-5 business days</strong> for standard shipping</p>
            <p>You'll receive tracking information once your order ships</p>
            <p>For any questions, contact our customer support</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons" style="margin-top: 40px;">
            <a href="../user/dashboard.php" class="btn">
                👤 Go to Dashboard
            </a>
            <a href="../products/index.php" class="btn btn-outline">
                🛒 Shop More Products
            </a>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <p>&copy; 2024 <?= SITE_NAME ?>. All rights reserved.</p>
        <p style="margin-top: 10px; color: #aaa;">
            <a href="../index.php" style="color: #aaa;">Home</a> • 
            <a href="../products/index.php" style="color: #aaa;">Products</a> • 
            <a href="../contact.php" style="color: #aaa;">Contact</a>
        </p>
    </footer>
    
    <script>
        // Print order confirmation
        function printOrder() {
            window.print();
        }
        
        // Redirect to home after 30 seconds
        setTimeout(function() {
            // Optional: Redirect to home
            // window.location.href = '../index.php';
        }, 30000);
    </script>
</body>
</html>