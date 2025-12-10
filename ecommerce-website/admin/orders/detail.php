<?php
require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('index.php');
}

// Get order with customer information
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        u.username as customer_name,
        u.email as customer_email,
        u.phone as customer_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('index.php');
}

// Get order items with actual prices
$stmt = $pdo->prepare("
    SELECT oi.*, p.price as actual_price, p.image as product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$id]);
$order_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$shipping = $order['shipping'] ?? 0;
$tax = $order['tax'] ?? 0;
$total = $order['total'] ?? 0;

foreach ($order_items as $item) {
    // Use the price from order_items (this is what customer paid)
    $item_price = $item['unit_price'] ?? $item['price'] ?? 0;
    $item_quantity = $item['quantity'] ?? 1;
    $item_total = $item_price * $item_quantity;
    $subtotal += $item_total;
}

// If subtotal is 0 but total exists, use order total
if ($subtotal == 0 && $total > 0) {
    $subtotal = $total - $shipping - $tax;
}

// Get customer info with fallbacks
$customer_name = $order['customer_name'] ?? 
                $order['shipping_full_name'] ?? 
                $order['billing_name'] ?? 
                'Guest';

$customer_email = $order['customer_email'] ?? 
                 $order['shipping_email'] ?? 
                 $order['billing_email'] ?? 
                 'N/A';

$customer_phone = $order['customer_phone'] ?? 
                 $order['shipping_phone'] ?? 
                 $order['billing_phone'] ?? 
                 'N/A';

$shipping_address = '';
if (!empty($order['shipping_address'])) {
    $shipping_address = $order['shipping_address'];
    if (!empty($order['shipping_city'])) {
        $shipping_address .= ', ' . $order['shipping_city'];
    }
    if (!empty($order['shipping_state'])) {
        $shipping_address .= ', ' . $order['shipping_state'];
    }
    if (!empty($order['shipping_zip'])) {
        $shipping_address .= ' ' . $order['shipping_zip'];
    }
}

// Get payment method display text
$payment_method = $order['payment_method'] ?? 'cod';
$payment_text = '';
switch($payment_method) {
    case 'cod':
        $payment_text = 'Cash on Delivery';
        break;
    case 'card':
        $payment_text = 'Credit/Debit Card';
        break;
    case 'paypal':
        $payment_text = 'PayPal';
        break;
    default:
        $payment_text = ucfirst($payment_method);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin</title>
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
        }
        
        .btn:hover { 
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover { 
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover { 
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover { 
            background: #c82333;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .order-info h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .order-meta {
            color: #666;
            font-size: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-box h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-content p {
            margin-bottom: 8px;
            color: #555;
        }
        
        .info-content strong {
            color: #333;
            min-width: 100px;
            display: inline-block;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .payment-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-items {
            margin-top: 30px;
        }
        
        .order-items h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        
        thead {
            background: #667eea;
            color: white;
        }
        
        th {
            padding: 18px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 18px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            overflow: hidden;
            background: #fff;
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
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .product-sku {
            font-size: 12px;
            color: #666;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }
        
        .price {
            font-weight: bold;
            color: #333;
        }
        
        .quantity {
            color: #666;
        }
        
        .item-total {
            font-weight: bold;
            color: #28a745;
            font-size: 16px;
        }
        
        .summary {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .summary-label {
            color: #666;
        }
        
        .summary-value {
            font-weight: 500;
        }
        
        .summary-divider {
            height: 1px;
            background: #dee2e6;
            margin: 20px 0;
        }
        
        .summary-total {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            padding-top: 15px;
            border-top: 2px solid #667eea;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        
        .empty-items {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-items .icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .order-card {
                padding: 25px;
            }
            
            .order-header {
                flex-direction: column;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📋 Order Details</h1>
        <div>
            <a href="index.php">← Back to Orders</a>
            <a href="../index.php">← Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✓ <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                ⚠️ <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="order-card">
            <div class="order-header">
                <div class="order-info">
                    <h1>Order #<?= $order['order_number'] ?? $order['id'] ?></h1>
                    <div class="order-meta">
                        <span><strong>Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                        <span><strong>Time:</strong> <?= date('g:i A', strtotime($order['created_at'])) ?></span>
                        <span><strong>Order ID:</strong> #<?= $order['id'] ?></span>
                    </div>
                </div>
                <div>
                    <span class="order-status status-<?= $order['order_status'] ?>">
                        <?= ucfirst($order['order_status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="info-grid">
                <!-- Customer Information -->
                <div class="info-box">
                    <h3>Customer Information</h3>
                    <div class="info-content">
                        <p><strong>Name:</strong> <?= htmlspecialchars($customer_name) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($customer_email) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($customer_phone) ?></p>
                        <?php if (!empty($order['user_id'])): ?>
                            <p><strong>Account:</strong> Registered User (ID: <?= $order['user_id'] ?>)</p>
                        <?php else: ?>
                            <p><strong>Account:</strong> Guest Checkout</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="info-box">
                    <h3>Shipping Information</h3>
                    <div class="info-content">
                        <?php if (!empty($shipping_address)): ?>
                            <p><strong>Address:</strong> <?= htmlspecialchars($shipping_address) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['shipping_method'])): ?>
                            <p><strong>Method:</strong> <?= htmlspecialchars($order['shipping_method']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['tracking_number'])): ?>
                            <p><strong>Tracking:</strong> <?= htmlspecialchars($order['tracking_number']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['shipping_notes'])): ?>
                            <p><strong>Notes:</strong> <?= htmlspecialchars($order['shipping_notes']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="info-box">
                    <h3>Payment Information</h3>
                    <div class="info-content">
                        <p>
                            <strong>Method:</strong> <?= $payment_text ?>
                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <span class="payment-badge payment-paid">✅ Paid</span>
                            <?php elseif ($order['payment_status'] === 'pending'): ?>
                                <span class="payment-badge payment-pending">⏳ Pending</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($order['payment_intent_id'])): ?>
                            <p><strong>Payment ID:</strong> <?= htmlspecialchars($order['payment_intent_id']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['paypal_order_id'])): ?>
                            <p><strong>PayPal ID:</strong> <?= htmlspecialchars($order['paypal_order_id']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['transaction_id'])): ?>
                            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($order['transaction_id']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="order-items">
                <h2>Order Items (<?= count($order_items) ?>)</h2>
                
                <?php if (!empty($order_items)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal_calc = 0;
                            foreach ($order_items as $item): 
                                $item_price = $item['unit_price'] ?? $item['price'] ?? 0;
                                $item_quantity = $item['quantity'] ?? 1;
                                $item_total = $item_price * $item_quantity;
                                $subtotal_calc += $item_total;
                            ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-image">
                                            <?php if (!empty($item['product_image'])): ?>
                                                <img src="../../assets/uploads/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                                <div class="no-image" style="display: none;">📦</div>
                                            <?php else: ?>
                                                <div class="no-image">📦</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-details">
                                            <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <?php if (!empty($item['product_sku'])): ?>
                                                <div class="product-sku">SKU: <?= htmlspecialchars($item['product_sku']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['product_id'])): ?>
                                                <div style="font-size: 11px; color: #999; margin-top: 3px;">
                                                    Product ID: <?= $item['product_id'] ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="price">$<?= number_format($item_price, 2) ?></td>
                                <td class="quantity"><?= $item_quantity ?></td>
                                <td class="item-total">$<?= number_format($item_total, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Order Summary -->
                    <div class="summary">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value">$<?= number_format($subtotal_calc, 2) ?></span>
                        </div>
                        
                        <?php if ($shipping > 0): ?>
                        <div class="summary-row">
                            <span class="summary-label">Shipping</span>
                            <span class="summary-value">$<?= number_format($shipping, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tax > 0): ?>
                        <div class="summary-row">
                            <span class="summary-label">Tax (<?= number_format(($tax / $subtotal_calc) * 100, 1) ?>%)</span>
                            <span class="summary-value">$<?= number_format($tax, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="summary-row summary-total">
                            <span class="summary-label">Total Amount</span>
                            <span class="summary-value">$<?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-items">
                        <div class="icon">📦</div>
                        <h3>No items found for this order</h3>
                        <p>The order items may have been deleted or there was an issue.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Notes -->
            <?php if (!empty($order['order_notes'])): ?>
            <div class="info-box" style="margin-top: 30px;">
                <h3>Order Notes</h3>
                <div class="info-content">
                    <p><?= nl2br(htmlspecialchars($order['order_notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="actions">
                <a href="index.php" class="btn btn-secondary">
                    ← Back to Orders
                </a>
                
                <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                    <a href="update-status.php?id=<?= $order['id'] ?>" class="btn btn-success">
                        📝 Update Order Status
                    </a>
                <?php endif; ?>
                
                <?php if ($order['order_status'] === 'pending'): ?>
                    <a href="cancel.php?id=<?= $order['id'] ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to cancel order <?= $order['order_number'] ?? $order['id'] ?>? This action cannot be undone.')">
                        ❌ Cancel Order
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($order['tracking_number'])): ?>
                    <a href="https://tracking.com/track/<?= urlencode($order['tracking_number']) ?>" 
                       target="_blank" 
                       class="btn">
                        🚚 Track Shipment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Confirm actions
        document.addEventListener('DOMContentLoaded', function() {
            // Confirm cancellation
            const cancelBtn = document.querySelector('a[href*="cancel.php"]');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
            
            // Image error handling
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const noImage = this.nextElementSibling;
                    if (noImage && noImage.classList.contains('no-image')) {
                        noImage.style.display = 'flex';
                    }
                });
            });
        });
    </script>
</body>
</html>