<?php

require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn() || isset($_SESSION['admin_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Process bulk update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = false;
    
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity = max(0, (int)$quantity);
        
        if ($quantity == 0) {
            // Remove item if quantity is 0
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $updated = true;
        } else {
            // Check stock availability
            $stock_stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
            $stock_stmt->execute([$product_id]);
            $stock = $stock_stmt->fetch()['quantity'] ?? 0;
            
            if ($quantity > $stock) {
                $_SESSION['error'] = "Cannot update quantity for product #{$product_id}. Only {$stock} items available!";
                redirect('index.php');
            }
            
            // Update quantity
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $user_id, $product_id]);
            $updated = true;
        }
    }
    
    if ($updated) {
        $_SESSION['success'] = "Cart updated successfully!";
        
        // Update cart count
        $count_stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
        $count_stmt->execute([$user_id]);
        $cart_count = $count_stmt->fetch()['count'] ?? 0;
        $_SESSION['cart_count'] = $cart_count;
    }
}

redirect('index.php');