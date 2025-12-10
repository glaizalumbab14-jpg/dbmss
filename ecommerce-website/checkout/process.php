<?php
// ========================================
// FILE: checkout/process-order.php
// Save as: C:\xampp\htdocs\ecommerce-website\checkout\process-order.php
// This handles the complete order placement and payment processing
// ========================================
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to checkout";
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Check if required data exists
if (!isset($_SESSION['checkout_shipping']) || !isset($_SESSION['checkout_payment'])) {
    $_SESSION['error'] = "Missing checkout information";
    redirect('index.php?step=shipping');
}

$shipping_info = $_SESSION['checkout_shipping'];
$payment_info = $_SESSION['checkout_payment'];

// Get cart items
$stmt = $pdo->prepare("
    SELECT 
        c.*, 
        p.name, 
        p.price, 
        p.image, 
        p.quantity as stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? AND p.status = 'active'
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    $_SESSION['error'] = "Your cart is empty!";
    redirect('../cart/index.php');
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping_cost = ($subtotal >= 50) ? 0 : 5;
$tax = $subtotal * 0.10;
$total = $subtotal + $shipping_cost + $tax;

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate unique order number
    $order_number = 'ORD-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Determine initial payment status based on payment method
    $payment_status = 'pending';
    $order_status = 'pending';
    $transaction_id = null;
    
    // Process payment based on method
    if ($payment_info['method'] === 'cod') {
        // Cash on Delivery - no immediate payment processing
        $payment_status = 'pending';
        $order_status = 'pending';
        
    } elseif ($payment_info['method'] === 'card') {
        // Card Payment - Process via Payment Gateway
        require_once '../payment/stripe-config.php';
        require_once '../payment/payment-api.php';
        
        $paymentAPI = new PaymentAPI();
        
        // For demo purposes - using test mode
        // In production, you would process real card payments here
        $payment_result = $paymentAPI->processStripePayment(
            $total,
            $payment_info['card_number'], // This should be a Stripe token in production
            $order_number
        );
        
        if ($payment_result['success']) {
            $payment_status = 'paid';
            $order_status = 'processing';
            $transaction_id = $payment_result['transaction_id'];
        } else {
            throw new Exception("Payment failed: " . $payment_result['error']);
        }
        
    } elseif ($payment_info['method'] === 'paypal') {
        // PayPal Payment
        require_once '../payment/paypal-config.php';
        require_once '../payment/payment-api.php';
        
        $paymentAPI = new PaymentAPI();
        
        $payment_result = $paymentAPI->processPayPalPayment($total, $order_number);
        
        if ($payment_result['success']) {
            // For PayPal, redirect to approval URL
            $_SESSION['pending_order'] = [
                'order_number' => $order_number,
                'shipping_info' => $shipping_info,
                'cart_items' => $cart_items,
                'totals' => [
                    'subtotal' => $subtotal,
                    'shipping' => $shipping_cost,
                    'tax' => $tax,
                    'total' => $total
                ]
            ];
            
            header('Location: ' . $payment_result['approval_url']);
            exit;
        } else {
            throw new Exception("PayPal payment initialization failed");
        }
    }
    
    // Create order in database
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
            status,
            transaction_id,
            shipping_address,
            shipping_city,
            shipping_state,
            shipping_zip
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $order_stmt->execute([
        $user_id,
        $order_number,
        $subtotal,
        $shipping_cost,
        $shipping_cost,
        $tax,
        $total,
        $total,
        $payment_info['method'],
        $payment_status,
        $status,
        $transaction_id,
        $shipping_info['address'],
        $shipping_info['city'],
        $shipping_info['state'],
        $shipping_info['zip_code']
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Create order items
    $order_item_stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id, 
            product_id, 
            quantity, 
            price, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($cart_items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $order_item_stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item_total
        ]);
        
        // Update product stock
        $update_stock = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
        $update_stock->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Clear cart
    $clear_cart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear_cart->execute([$user_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Store order info for confirmation page
    $_SESSION['last_order'] = [
        'order_id' => $order_id,
        'order_number' => $order_number,
        'total' => $total,
        'payment_method' => $payment_info['method'],
        'payment_status' => $payment_status
    ];
    
    // Clear checkout session
    unset($_SESSION['checkout_shipping']);
    unset($_SESSION['checkout_payment']);
    
    // Redirect to success page
    redirect('success.php');
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    
    error_log("Order processing error: " . $e->getMessage());
    
    $_SESSION['error'] = "Order processing failed: " . $e->getMessage();
    redirect('index.php?step=review');
}
