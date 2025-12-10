<?php

require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Please login to add items to cart";
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;

// Validate product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = "Product not found!";
    redirect('../products/index.php');
}

// Check stock availability
if ($product['quantity'] < $quantity) {
    $_SESSION['error'] = "Only {$product['quantity']} items available in stock!";
    redirect('../products/detail.php?id=' . $product_id);
}

// Check if product is already in cart
$stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user_id, $product_id]);
$cart_item = $stmt->fetch();

if ($cart_item) {
    // Update quantity if already in cart
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    // Check stock limit
    if ($new_quantity > $product['quantity']) {
        $_SESSION['error'] = "Cannot add more than available stock!";
        redirect('../products/detail.php?id=' . $product_id);
    }
    
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$new_quantity, $user_id, $product_id]);
    
    $_SESSION['success'] = "Item quantity updated in cart!";
} else {
    // Add new item to cart
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $product_id, $quantity]);
    
    $_SESSION['success'] = "Item added to cart!";
}

// Get cart count for badge
$count_stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$count_stmt->execute([$user_id]);
$cart_count = $count_stmt->fetch()['count'] ?? 0;
$_SESSION['cart_count'] = $cart_count;

// Redirect back to previous page or cart
if (isset($_SERVER['HTTP_REFERER'])) {
    redirect($_SERVER['HTTP_REFERER']);
} else {
    redirect('index.php');
}