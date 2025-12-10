<?php

require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Remove specific item
if ($product_id > 0) {
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $_SESSION['success'] = "Item removed from cart!";
}

// Clear entire cart
if (isset($_GET['clear_all'])) {
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $_SESSION['success'] = "Cart cleared!";
}

// Update cart count
$count_stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$count_stmt->execute([$user_id]);
$cart_count = $count_stmt->fetch()['count'] ?? 0;
$_SESSION['cart_count'] = $cart_count;

redirect('index.php');