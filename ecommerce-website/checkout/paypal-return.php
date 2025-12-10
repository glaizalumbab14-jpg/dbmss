

<?php
require_once '../config.php';

$status = $_GET['status'] ?? '';
$paypalOrderId = $_GET['token'] ?? '';

if ($status === 'success' && isset($_SESSION['pending_order'])) {
    $pending = $_SESSION['pending_order'];
    
    try {
        $pdo->beginTransaction();
        
        // Create order in database
        $order_stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, order_number, subtotal, shipping, shipping_amount,
                tax, total, total_amount, payment_method, payment_status,
                order_status, transaction_id, shipping_address, shipping_city,
                shipping_state, shipping_zip
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paypal', 'paid', 'processing', ?, ?, ?, ?, ?)
        ");
        
        $totals = $pending['totals'];
        $shipping_info = $pending['shipping_info'];
        
        $order_stmt->execute([
            $_SESSION['user_id'],
            $pending['order_number'],
            $totals['subtotal'],
            $totals['shipping'],
            $totals['shipping'],
            $totals['tax'],
            $totals['total'],
            $totals['total'],
            $paypalOrderId,
            $shipping_info['address'],
            $shipping_info['city'],
            $shipping_info['state'],
            $shipping_info['zip_code']
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Create order items
        $order_item_stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($pending['cart_items'] as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $order_item_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item_total
            ]);
            
            // Update stock
            $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?")
                ->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")
            ->execute([$_SESSION['user_id']]);
        
        $pdo->commit();
        
        $_SESSION['last_order'] = [
            'order_id' => $order_id,
            'order_number' => $pending['order_number'],
            'total' => $totals['total'],
            'payment_method' => 'paypal',
            'payment_status' => 'paid'
        ];
        
        unset($_SESSION['pending_order']);
        redirect('success.php');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Payment completed but order creation failed";
        redirect('index.php');
    }
} else {
    $_SESSION['error'] = "Payment was cancelled or failed";
    redirect('index.php');
}
?>