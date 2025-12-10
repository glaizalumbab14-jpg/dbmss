<?php

require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Please login to view your cart";
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get cart items with product details
$stmt = $pdo->prepare("
    SELECT 
        c.*, 
        p.name, 
        p.price, 
        p.image, 
        p.quantity as stock,
        p.description,
        cat.name as category_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE c.user_id = ? AND p.status = 'active'
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$total_items = 0;
$shipping = 0;
$tax_rate = 0.10; // 10% tax

foreach ($cart_items as $item) {
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    $total_items += $item['quantity'];
}

// Calculate shipping (free over $50, otherwise $5)
$shipping = ($subtotal >= 50) ? 0 : 5;
$tax = $subtotal * $tax_rate;
$total = $subtotal + $shipping + $tax;

// Get cart count for badge
$count_stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$count_stmt->execute([$user_id]);
$cart_count = $count_stmt->fetch()['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
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
        
        .nav-links .cart-btn {
            background: white;
            color: #667eea;
            font-weight: bold;
            position: relative;
        }
        
        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 30px;
            font-size: 14px;
            color: #666;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Cart Header */
        .cart-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .cart-header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .cart-header .icon {
            color: #667eea;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
        }
        
        @media (max-width: 992px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .cart-item {
            display: flex;
            padding: 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
            gap: 20px;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 120px;
            height: 120px;
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
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }
        
        .item-name:hover {
            color: #667eea;
        }
        
        .item-price {
            font-size: 20px;
            color: #28a745;
            font-weight: bold;
        }
        
        .item-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .item-stock {
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .in-stock { color: #28a745; }
        .low-stock { color: #ffc107; }
        .out-of-stock { color: #dc3545; }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            width: 60px;
            height: 30px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .remove-btn {
            color: #dc3545;
            text-decoration: none;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .remove-btn:hover {
            background: #f8d7da;
        }
        
        /* Cart Summary */
        .cart-summary {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .cart-summary h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
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
        
        .free-shipping {
            color: #28a745;
            font-weight: bold;
        }
        
        .shipping-note {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            text-align: center;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .continue-shopping {
            width: 100%;
            padding: 12px;
            background: #f8f9fa;
            color: #333;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .continue-shopping:hover {
            background: #e9ecef;
        }
        
        /* Cart Actions */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 12px 25px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
        }
        
        .empty-icon {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-cart h2 {
            color: #666;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .empty-cart p {
            color: #888;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
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
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
            }
            
            .item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .item-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .cart-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .cart-actions .btn {
                width: 100%;
                text-align: center;
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
                <a href="index.php" class="cart-btn">
                    🛒 Cart
                    <?php if ($cart_count > 0): ?>
                    <span class="badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="../user/dashboard.php">My Account</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">Home</a> / 
            <span>Shopping Cart</span>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Cart Header -->
        <div class="cart-header">
            <h1><span class="icon">🛒</span> Shopping Cart</h1>
            <p><?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?> in your cart</p>
        </div>
        
        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <div class="empty-icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet. Start shopping to fill it up!</p>
                <a href="../products/index.php" class="btn" style="display: inline-block; margin: 0 10px;">Continue Shopping</a>
                
            </div>
        <?php else: ?>
            <form action="update.php" method="POST">
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../assets/uploads/products/<?= htmlspecialchars($item['image']) ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f8f9fa;">📦</div>
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f8f9fa;">📦</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <div class="item-header">
                                    <a href="../products/detail.php?id=<?= $item['product_id'] ?>" class="item-name">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                    <div class="item-price">$<?= number_format($item['price'], 2) ?></div>
                                </div>
                                
                                <?php if (!empty($item['description'])): ?>
                                <div class="item-description">
                                    <?= substr(htmlspecialchars($item['description']), 0, 100) ?>...
                                </div>
                                <?php endif; ?>
                                
                                <div class="item-stock <?= 
                                    ($item['stock'] > 10) ? 'in-stock' : 
                                    (($item['stock'] > 0) ? 'low-stock' : 'out-of-stock')
                                ?>">
                                    <?php if ($item['stock'] > 10): ?>
                                        ✅ In Stock
                                    <?php elseif ($item['stock'] > 0): ?>
                                        ⚠️ Only <?= $item['stock'] ?> left
                                    <?php else: ?>
                                        ❌ Out of Stock
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-actions">
                                    <div class="quantity-control">
                                        <button type="button" class="quantity-btn" 
                                                onclick="updateQuantity(<?= $item['product_id'] ?>, -1)">-</button>
                                        <input type="number" 
                                               name="quantity[<?= $item['product_id'] ?>]" 
                                               value="<?= $item['quantity'] ?>" 
                                               min="0" 
                                               max="<?= $item['stock'] ?>"
                                               class="quantity-input"
                                               onchange="validateQuantity(this, <?= $item['stock'] ?>)">
                                        <button type="button" class="quantity-btn" 
                                                onclick="updateQuantity(<?= $item['product_id'] ?>, 1)">+</button>
                                    </div>
                                    
                                    <a href="remove.php?id=<?= $item['product_id'] ?>" 
                                       onclick="return confirm('Remove this item from cart?')" 
                                       class="remove-btn">
                                        ❌ Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                       <div class="cart-summary">
    <h2>Order Summary</h2>
    
    <div class="summary-row">
        <span class="summary-label">Subtotal (<?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?>)</span>
        <span class="summary-value">$<?= number_format($subtotal, 2) ?></span>
    </div>
    
    <div class="summary-row">
        <span class="summary-label">Shipping</span>
        <span class="summary-value">
            <?php if ($shipping == 0): ?>
                <span class="free-shipping">FREE</span>
            <?php else: ?>
                $<?= number_format($shipping, 2) ?>
            <?php endif; ?>
        </span>
    </div>
    
    <div class="summary-row">
        <span class="summary-label">Tax (<?= ($tax_rate * 100) ?>%)</span>
        <span class="summary-value">$<?= number_format($tax, 2) ?></span>
    </div>
    
    <div class="summary-row total">
        <span class="summary-label">Total</span>
        <span class="summary-value">$<?= number_format($total, 2) ?></span>
    </div>
    
    <?php if ($subtotal < 50): ?>
    <div class="shipping-note">
        ⭐ Add $<?= number_format(50 - $subtotal, 2) ?> more for free shipping!
    </div>
    <?php else: ?>
    <div class="shipping-note">
        ✅ You've got free shipping!
    </div>
    <?php endif; ?>
    
    
    <a href="../checkout/index.php" class="checkout-btn">
        🛍️ Proceed to Checkout ($<?= number_format($total, 2) ?>)
    </a>
    
    
</div>
                <!-- Cart Actions -->
                <div class="cart-actions">
                    <a href="../products/index.php" class="btn" style="background: #6c757d;">
                        ← Continue Shopping
                    </a>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn">
                            🔄 Update Cart
                        </button>
                        <a href="remove.php?clear_all=1" 
                           onclick="return confirm('Clear entire cart?')" 
                           class="btn btn-danger">
                            🗑️ Clear Cart
                        </a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
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
        // Update quantity with buttons
        function updateQuantity(productId, change) {
            const input = document.querySelector(`input[name="quantity[${productId}]"]`);
            let newValue = parseInt(input.value) + change;
            
            // Ensure minimum of 0 and maximum of stock
            newValue = Math.max(0, newValue);
            newValue = Math.min(newValue, parseInt(input.max));
            
            input.value = newValue;
            
            // If quantity becomes 0, show confirm to remove
            if (newValue === 0) {
                if (confirm('Remove this item from cart?')) {
                    window.location.href = `remove.php?id=${productId}`;
                } else {
                    input.value = 1;
                }
            }
        }
        
        // Validate quantity input
        function validateQuantity(input, maxStock) {
            let value = parseInt(input.value);
            
            if (isNaN(value) || value < 0) {
                input.value = 0;
            } else if (value > maxStock) {
                alert(`Only ${maxStock} items available in stock!`);
                input.value = maxStock;
            }
        }
        
        // Auto-update cart when quantity changes (optional)
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value === '0') {
                    if (confirm('Remove this item from cart?')) {
                        const productId = this.name.match(/\[(\d+)\]/)[1];
                        window.location.href = `remove.php?id=${productId}`;
                    } else {
                        this.value = 1;
                    }
                }
            });
        });
    </script>
</body>
</html>