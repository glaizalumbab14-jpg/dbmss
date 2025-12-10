<?php
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Please login to checkout";
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
        p.description
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? AND p.status = 'active'
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Check if cart is empty
if (empty($cart_items)) {
    $_SESSION['error'] = "Your cart is empty!";
    redirect('../cart/index.php');
}

// Check stock availability
foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        $_SESSION['error'] = "Product '{$item['name']}' has only {$item['stock']} items in stock!";
        redirect('../cart/index.php');
    }
}

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

// Get user details
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Get user addresses if they exist
$address_stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$address_stmt->execute([$user_id]);
$addresses = $address_stmt->fetchAll();

// Set default shipping address
$default_address = null;
foreach ($addresses as $address) {
    if ($address['is_default']) {
        $default_address = $address;
        break;
    }
}
if (!$default_address && !empty($addresses)) {
    $default_address = $addresses[0];
}

// Determine current step
$current_step = $_GET['step'] ?? 'shipping';
$allowed_steps = ['shipping', 'payment', 'review', 'confirmation'];
if (!in_array($current_step, $allowed_steps)) {
    $current_step = 'shipping';
}

// Handle form submissions for each step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        switch ($_POST['step']) {
            case 'shipping':
                // Save shipping info
                $shipping_address = [
                    'full_name' => trim($_POST['full_name']),
                    'email' => trim($_POST['email']),
                    'phone' => trim($_POST['phone']),
                    'address' => trim($_POST['address']),
                    'city' => trim($_POST['city']),
                    'state' => trim($_POST['state']),
                    'zip_code' => trim($_POST['zip_code']),
                    'save_address' => isset($_POST['save_address'])
                ];
                
                // Validate required fields
                $required_fields = ['full_name', 'email', 'phone', 'address', 'city', 'state', 'zip_code'];
                $errors = [];
                
                foreach ($required_fields as $field) {
                    if (empty($shipping_address[$field])) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
                    }
                }
                
                if (!filter_var($shipping_address['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email address";
                }
                
                if (empty($errors)) {
                    // Save address if requested
                    if ($shipping_address['save_address'] && !$default_address) {
                        $save_stmt = $pdo->prepare("
                            INSERT INTO user_addresses 
                            (user_id, full_name, email, phone, address, city, state, zip_code, is_default) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $save_stmt->execute([
                            $user_id,
                            $shipping_address['full_name'],
                            $shipping_address['email'],
                            $shipping_address['phone'],
                            $shipping_address['address'],
                            $shipping_address['city'],
                            $shipping_address['state'],
                            $shipping_address['zip_code']
                        ]);
                    }
                    
                    // Store shipping info in session
                    $_SESSION['checkout_shipping'] = $shipping_address;
                    redirect('index.php?step=payment');
                }
                break;
                
            case 'payment':
                // Save payment info
                $payment_method = $_POST['payment_method'] ?? 'cod';
                $card_name = $_POST['card_name'] ?? '';
                $card_number = $_POST['card_number'] ?? '';
                $card_expiry = $_POST['card_expiry'] ?? '';
                $card_cvv = $_POST['card_cvv'] ?? '';
                
                // Store in session
                $_SESSION['checkout_payment'] = [
                    'method' => $payment_method,
                    'card_name' => $card_name,
                    'card_number' => $card_number,
                    'card_expiry' => $card_expiry,
                    'card_cvv' => $card_cvv
                ];
                redirect('index.php?step=review');
                break;
                
            case 'review':
                // Place the order
                if (!isset($_SESSION['checkout_shipping']) || !isset($_SESSION['checkout_payment'])) {
                    redirect('index.php?step=shipping');
                }
                
                $shipping_address = $_SESSION['checkout_shipping'];
                $payment_info = $_SESSION['checkout_payment'];
                
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Create order
                    $order_stmt = $pdo->prepare("
                        INSERT INTO orders (
                            user_id, 
                            order_number, 
                            subtotal, 
                            shipping, 
                            shipping_amount, 
                            tax, 
                            total, 
                            total_amount, 
                            payment_method, 
                            payment_status, 
                            order_status,
                            shipping_address,
                            shipping_city,
                            shipping_state,
                            shipping_zip
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?)
                    ");
                    
                    // Generate unique order number
                    $order_number = 'ORD-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    $order_stmt->execute([
                        $user_id,
                        $order_number,
                        $subtotal,
                        $shipping,
                        $shipping,
                        $tax,
                        $total,
                        $total,
                        $payment_info['method'],
                        $shipping_address['address'],
                        $shipping_address['city'],
                        $shipping_address['state'],
                        $shipping_address['zip_code']
                    ]);
                    
                    $order_id = $pdo->lastInsertId();
                    
                   // Check if order_items table exists and create order items
$check_order_items = $pdo->prepare("SHOW TABLES LIKE 'order_items'");
$check_order_items->execute();

if ($check_order_items->rowCount() > 0) {
    // First, check the structure of the order_items table
    $check_columns = $pdo->prepare("DESCRIBE order_items");
    $check_columns->execute();
    $columns = $check_columns->fetchAll();
    
    // Based on your code, you're trying to insert image but not including it in values
    // Let's check if image column exists
    $has_image_column = false;
    foreach ($columns as $col) {
        if ($col['Field'] == 'image') {
            $has_image_column = true;
            break;
        }
    }
    
    if ($has_image_column) {
        $order_item_stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, 
                product_id, 
                product_name, 
                unit_price, 
                quantity, 
                total_price,
                image
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cart_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $order_item_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['name'],
                $item['price'],
                $item['quantity'],
                $item_total,
                $item['image'] // Add the image here
            ]);
        }
    } else {
        // If image column doesn't exist, insert without it
        $order_item_stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, 
                product_id, 
                product_name, 
                unit_price, 
                quantity, 
                total_price
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cart_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $order_item_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['name'],
                $item['price'],
                $item['quantity'],
                $item_total
            ]);
        }
    }
}
                    
                    // Update product stock
                    $update_stock_stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    
                    // Delete cart items
                    $delete_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                    
                    foreach ($cart_items as $item) {
                        $update_stock_stmt->execute([$item['quantity'], $item['product_id']]);
                    }
                    
                    $delete_cart_stmt->execute([$user_id]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Store order info in session for confirmation page
                    $_SESSION['order_confirmation'] = [
                        'order_id' => $order_id,
                        'order_number' => $order_number,
                        'total' => $total,
                        'shipping_address' => $shipping_address,
                        'payment_method' => $payment_info['method']
                    ];
                    
                    // Clear checkout session data
                    unset($_SESSION['checkout_shipping']);
                    unset($_SESSION['checkout_payment']);
                    
                    redirect('index.php?step=confirmation&order_id=' . $order_id);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Order processing failed: " . $e->getMessage();
                    redirect('index.php?step=review');
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= SITE_NAME ?></title>
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
        
        /* Checkout Steps */
        .checkout-steps {
            max-width: 1200px;
            margin: 30px auto 40px;
            padding: 0 20px;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .step.completed .step-number {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: #667eea;
            font-weight: bold;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }
        
        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
        }
        
        @media (max-width: 992px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Checkout Form */
        .checkout-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .checkout-form h2 {
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        /* Order Summary */
        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .order-summary h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .order-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 10px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-item-price {
            font-size: 14px;
            color: #28a745;
            font-weight: bold;
        }
        
        .order-item-quantity {
            font-size: 12px;
            color: #666;
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
        
        /* Payment Options */
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .payment-option {
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-option:hover {
            border-color: #667eea;
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .payment-option-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .payment-option-icon {
            font-size: 24px;
        }
        
        .payment-option-name {
            font-weight: 500;
            color: #333;
        }
        
        .card-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: none;
        }
        
        .card-details.show {
            display: block;
        }
        
        /* Review Section */
        .review-section {
            margin-bottom: 30px;
        }
        
        .review-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .review-item h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .review-content p {
            margin-bottom: 5px;
            color: #666;
        }
        
        .edit-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            float: right;
        }
        
        .edit-link:hover {
            text-decoration: underline;
        }
        
        /* Confirmation Section */
        .confirmation-section {
            text-align: center;
            padding: 40px 20px;
        }
        
        .confirmation-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .confirmation-section h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .confirmation-section p {
            color: #666;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .order-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
        }
        
        /* Buttons */
        .checkout-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f8f9fa;
        }
        
        .btn {
            padding: 15px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back {
            background: #6c757d;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .btn-continue {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 15px 50px;
        }
        
        .btn-continue:hover {
            background: linear-gradient(135deg, #218838 0%, #17a2b8 100%);
        }
        
        .btn-full {
            width: 100%;
            text-align: center;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .checkout-form {
                padding: 25px;
            }
            
            .checkout-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .checkout-actions .btn {
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
                <a href="../cart/index.php" class="cart-btn">
                    🛒 Cart
                    <span class="badge"><?= $total_items ?></span>
                </a>
                <a href="../user/dashboard.php">My Account</a>
            </div>
        </div>
    </nav>
    
    <!-- Checkout Steps -->
    <div class="checkout-steps">
        <div class="steps">
            <a href="../cart/index.php" class="step completed">
                <div class="step-number">1</div>
                <div class="step-label">Cart</div>
            </a>
            <div class="step <?= $current_step == 'shipping' ? 'active' : ($current_step == 'payment' || $current_step == 'review' || $current_step == 'confirmation' ? 'completed' : '') ?>"
                 onclick="goToStep('shipping')">
                <div class="step-number">2</div>
                <div class="step-label">Shipping</div>
            </div>
            <div class="step <?= $current_step == 'payment' ? 'active' : ($current_step == 'review' || $current_step == 'confirmation' ? 'completed' : '') ?>"
                 onclick="goToStep('payment')">
                <div class="step-number">3</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="step <?= $current_step == 'review' ? 'active' : ($current_step == 'confirmation' ? 'completed' : '') ?>"
                 onclick="goToStep('review')">
                <div class="step-number">4</div>
                <div class="step-label">Review</div>
            </div>
            <div class="step <?= $current_step == 'confirmation' ? 'active' : '' ?>">
                <div class="step-number">✓</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-layout">
            <!-- Main Content Area -->
            <div class="checkout-form">
                <?php if ($current_step == 'shipping'): ?>
                    <!-- Step 1: Shipping Information -->
                    <h2>Shipping Information</h2>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Saved Addresses -->
                    <?php if (!empty($addresses)): ?>
                    <div class="saved-addresses">
                        <h3>Saved Addresses</h3>
                        <div class="address-options" id="savedAddresses">
                            <?php foreach ($addresses as $address): ?>
                            <div class="address-option" onclick="fillAddress(<?= htmlspecialchars(json_encode($address)) ?>)">
                                <h4><?= htmlspecialchars($address['full_name']) ?></h4>
                                <p><?= htmlspecialchars($address['address']) ?></p>
                                <p><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['zip_code']) ?></p>
                                <?php if ($address['is_default']): ?>
                                <p style="color: #28a745; font-weight: bold;">✓ Default Address</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="color: #666; margin-top: 10px;">Click an address to fill the form</p>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="shipping">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['full_name'] : ($user['username'] ?? '')) ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['email'] : ($user['email'] ?? '')) ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['phone'] : '') ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Street Address *</label>
                            <input type="text" 
                                   id="address" 
                                   name="address" 
                                   placeholder="123 Main St, Apt 4B"
                                   value="<?= htmlspecialchars($default_address ? $default_address['address'] : '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" 
                                       id="city" 
                                       name="city" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['city'] : '') ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="state">State/Province *</label>
                                <input type="text" 
                                       id="state" 
                                       name="state" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['state'] : '') ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="zip_code">ZIP/Postal Code *</label>
                                <input type="text" 
                                       id="zip_code" 
                                       name="zip_code" 
                                       value="<?= htmlspecialchars($default_address ? $default_address['zip_code'] : '') ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="save_address" 
                                   name="save_address" 
                                   value="1"
                                   <?= (!$default_address) ? 'checked' : '' ?>>
                            <label for="save_address">Save this address for future orders</label>
                        </div>
                        
                        <div class="checkout-actions">
                            <a href="../cart/index.php" class="btn btn-back">
                                ← Back to Cart
                            </a>
                            <button type="submit" class="btn btn-continue">
                                Continue to Payment →
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($current_step == 'payment'): ?>
                    <!-- Step 2: Payment Information -->
                    <h2>Payment Method</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="payment">
                        
                        <div class="payment-options">
                            <div class="payment-option selected" onclick="selectPayment('cod')">
                                <div class="payment-option-header">
                                    <div class="payment-option-icon">💵</div>
                                    <div class="payment-option-name">Cash on Delivery</div>
                                </div>
                                <p style="font-size: 14px; color: #666;">Pay with cash when your order is delivered</p>
                            </div>
                            
                            <div class="payment-option" onclick="selectPayment('card')">
                                <div class="payment-option-header">
                                    <div class="payment-option-icon">💳</div>
                                    <div class="payment-option-name">Credit/Debit Card</div>
                                </div>
                                <p style="font-size: 14px; color: #666;">Pay securely with your credit or debit card</p>
                                <div class="card-details" id="cardDetails">
                                    <div class="form-group">
                                        <label for="card_name">Name on Card *</label>
                                        <input type="text" id="card_name" name="card_name" placeholder="John Doe">
                                    </div>
                                    <div class="form-group">
                                        <label for="card_number">Card Number *</label>
                                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="card_expiry">Expiry Date *</label>
                                            <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY">
                                        </div>
                                        <div class="form-group">
                                            <label for="card_cvv">CVV *</label>
                                            <input type="text" id="card_cvv" name="card_cvv" placeholder="123">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-option" onclick="selectPayment('paypal')">
                                <div class="payment-option-header">
                                    <div class="payment-option-icon">🅿️</div>
                                    <div class="payment-option-name">PayPal</div>
                                </div>
                                <p style="font-size: 14px; color: #666;">Fast and secure online payments</p>
                            </div>
                        </div>
                        
                        <input type="hidden" name="payment_method" id="payment_method" value="cod">
                        
                        <div class="checkout-actions">
                            <a href="index.php?step=shipping" class="btn btn-back">
                                ← Back to Shipping
                            </a>
                            <button type="submit" class="btn btn-continue">
                                Review Order →
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($current_step == 'review'): ?>
                    <!-- Step 3: Review Order -->
                    <h2>Review Your Order</h2>
                    
                    <?php 
                    $shipping_address = $_SESSION['checkout_shipping'] ?? [];
                    $payment_info = $_SESSION['checkout_payment'] ?? ['method' => 'cod'];
                    ?>
                    
                    <div class="review-section">
                        <div class="review-item">
                            <h3>Shipping Information <a href="index.php?step=shipping" class="edit-link">Edit</a></h3>
                            <div class="review-content">
                                <p><strong>Name:</strong> <?= htmlspecialchars($shipping_address['full_name'] ?? '') ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($shipping_address['email'] ?? '') ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($shipping_address['phone'] ?? '') ?></p>
                                <p><strong>Address:</strong> <?= htmlspecialchars($shipping_address['address'] ?? '') ?></p>
                                <p><strong>City:</strong> <?= htmlspecialchars($shipping_address['city'] ?? '') ?>, 
                                   <?= htmlspecialchars($shipping_address['state'] ?? '') ?> 
                                   <?= htmlspecialchars($shipping_address['zip_code'] ?? '') ?></p>
                            </div>
                        </div>
                        
                        <div class="review-item">
                            <h3>Payment Method <a href="index.php?step=payment" class="edit-link">Edit</a></h3>
                            <div class="review-content">
                                <p>
                                    <strong>Method:</strong> 
                                    <?= $payment_info['method'] == 'cod' ? 'Cash on Delivery' : 
                                      ($payment_info['method'] == 'card' ? 'Credit/Debit Card' : 'PayPal') ?>
                                </p>
                                <?php if ($payment_info['method'] == 'card'): ?>
                                <p><strong>Card:</strong> **** **** **** <?= substr($payment_info['card_number'] ?? '', -4) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="review">
                        
                        <div class="form-group">
                            <label for="order_notes">Order Notes (Optional)</label>
                            <textarea id="order_notes" name="order_notes" rows="3" placeholder="Add special instructions for your order..."></textarea>
                        </div>
                        
                        <div class="checkout-actions">
                            <a href="index.php?step=payment" class="btn btn-back">
                                ← Back to Payment
                            </a>
                            <button type="submit" class="btn btn-continue">
                                Place Order →
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($current_step == 'confirmation'): ?>
                    <!-- Step 4: Order Confirmation -->
                    <div class="confirmation-section">
                        <div class="confirmation-icon">✓</div>
                        <h2>Order Confirmed!</h2>
                        <p>Thank you for your purchase.</p>
                        
                        <?php if (isset($_SESSION['order_confirmation'])): 
                            $order = $_SESSION['order_confirmation']; ?>
                            <div class="order-number">Order #<?= $order['order_number'] ?></div>
                            
                            <p>A confirmation email has been sent to <strong><?= htmlspecialchars($order['shipping_address']['email']) ?></strong></p>
                            <p><strong>Total Amount:</strong> $<?= number_format($order['total'], 2) ?></p>
                            <p><strong>Payment Method:</strong> 
                                <?= $order['payment_method'] == 'cod' ? 'Cash on Delivery' : 
                                  ($order['payment_method'] == 'card' ? 'Credit/Debit Card' : 'PayPal') ?>
                            </p>
                            
                            <div style="margin-top: 40px;">
                                <a href="../user/order-details.php?id=<?= $order['order_id'] ?>" class="btn" style="margin-right: 10px;">
                                    View Order Details
                                </a>
                                <a href="../products/index.php" class="btn btn-back">
                                    Continue Shopping
                                </a>
                            </div>
                            
                            <?php unset($_SESSION['order_confirmation']); ?>
                        <?php else: ?>
                            <p>Your order has been successfully placed!</p>
                            <div style="margin-top: 40px;">
                                <a href="../user/orders.php" class="btn" style="margin-right: 10px;">
                                    View My Orders
                                </a>
                                <a href="../products/index.php" class="btn btn-back">
                                    Continue Shopping
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Summary Sidebar -->
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="order-items">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <div class="order-item-image">
                            <?php if (!empty($item['image'])): ?>
                                <img src="../assets/uploads/products/<?= htmlspecialchars($item['image']) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 24px; color: #ccc; background: #f8f9fa;">📦</div>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #ccc; background: #f8f9fa;">📦</div>
                            <?php endif; ?>
                        </div>
                        <div class="order-item-details">
                            <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="order-item-price">$<?= number_format($item['price'], 2) ?></div>
                            <div class="order-item-quantity">Quantity: <?= $item['quantity'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Subtotal (<?= $total_items ?> items)</span>
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
                    <span class="summary-label">Tax (10%)</span>
                    <span class="summary-value">$<?= number_format($tax, 2) ?></span>
                </div>
                
                <div class="summary-row total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">$<?= number_format($total, 2) ?></span>
                </div>
                
                <?php if ($shipping == 0): ?>
                <div style="color: #28a745; font-weight: bold; text-align: center; margin-top: 15px;">
                    ✅ Free Shipping Applied!
                </div>
                <?php else: ?>
                <div style="color: #666; text-align: center; margin-top: 15px; font-size: 14px;">
                    Add $<?= number_format(50 - $subtotal, 2) ?> more for free shipping!
                </div>
                <?php endif; ?>
            </div>
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
        // Fill address form with saved address
        function fillAddress(address) {
            document.getElementById('full_name').value = address.full_name || '';
            document.getElementById('email').value = address.email || '';
            document.getElementById('phone').value = address.phone || '';
            document.getElementById('address').value = address.address || '';
            document.getElementById('city').value = address.city || '';
            document.getElementById('state').value = address.state || '';
            document.getElementById('zip_code').value = address.zip_code || '';
            
            // Highlight selected address
            document.querySelectorAll('.address-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        // Select payment method
        function selectPayment(method) {
            document.getElementById('payment_method').value = method;
            
            // Highlight selected payment
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Show/hide card details
            const cardDetails = document.getElementById('cardDetails');
            if (method === 'card') {
                cardDetails.classList.add('show');
            } else {
                cardDetails.classList.remove('show');
            }
        }
        
        // Navigate to step
        function goToStep(step) {
            // Check if step is allowed based on completed steps
            const currentStep = '<?= $current_step ?>';
            const stepsOrder = ['shipping', 'payment', 'review', 'confirmation'];
            const currentIndex = stepsOrder.indexOf(currentStep);
            const targetIndex = stepsOrder.indexOf(step);
            
            // Only allow navigation to previous steps or current step
            if (targetIndex <= currentIndex) {
                window.location.href = 'index.php?step=' + step;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize payment selection
            selectPayment('<?= $_SESSION['checkout_payment']['method'] ?? 'cod' ?>');
            
            // Add click handlers to payment options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.addEventListener('click', function() {
                    const method = this.getAttribute('onclick').match(/selectPayment\('(\w+)'\)/)[1];
                    selectPayment(method);
                });
            });
            
            // Add click handlers to address options
            document.querySelectorAll('.address-option').forEach(option => {
                option.addEventListener('click', function() {
                    const addressData = this.getAttribute('onclick').match(/fillAddress\((.*)\)/)[1];
                    try {
                        const address = JSON.parse(addressData);
                        fillAddress(address);
                    } catch (e) {
                        console.error('Error parsing address:', e);
                    }
                });
            });
        });
    </script>
</body>
</html>