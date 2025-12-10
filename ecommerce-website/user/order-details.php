
<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    redirect('orders.php');
}

// Get order details with user verification
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.username as customer_name,
        u.email as customer_email,
        u.phone as customer_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Order not found or access denied.';
    redirect('orders.php');
}

// Get order items with product details
$stmt = $pdo->prepare("
    SELECT 
        oi.*,
        p.image as product_image,
        p.description as product_description,
        p.sku as product_sku
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$shipping = $order['shipping'] ?? $order['shipping_amount'] ?? 0;
$tax = $order['tax'] ?? 0;
$total = $order['total'] ?? $order['total_amount'] ?? 0;

foreach ($order_items as $item) {
    $item_price = $item['unit_price'] ?? $item['price'] ?? 0;
    $item_quantity = $item['quantity'] ?? 1;
    $item_total = $item_price * $item_quantity;
    $subtotal += $item_total;
}

// If subtotal is 0 but total exists, use order total
if ($subtotal == 0 && $total > 0) {
    $subtotal = $total - $shipping - $tax;
}

// Get payment method details
$payment_method = $order['payment_method'] ?? 'cod';
$payment_text = '';
$payment_icon = '';

switch($payment_method) {
    case 'cod':
        $payment_text = 'Cash on Delivery';
        $payment_icon = '💵';
        break;
    case 'card':
        $payment_text = 'Credit/Debit Card';
        $payment_icon = '💳';
        break;
    case 'paypal':
        $payment_text = 'PayPal';
        $payment_icon = '🅿️';
        break;
    default:
        $payment_text = ucfirst($payment_method);
        $payment_icon = '💳';
}

// Get status details
$status = $order['order_status'] ?? $order['status'] ?? 'pending';
$status_text = ucfirst($status);
$status_icon = '';
$status_description = '';

switch($status) {
    case 'pending':
        $status_icon = '⏳';
        $status_description = 'Order received, waiting for payment confirmation';
        break;
    case 'processing':
        $status_icon = '🔄';
        $status_description = 'Payment confirmed, preparing your order';
        break;
    case 'shipped':
        $status_icon = '🚚';
        $status_description = 'Order shipped and on its way';
        break;
    case 'delivered':
        $status_icon = '✅';
        $status_description = 'Order delivered successfully';
        break;
    case 'cancelled':
        $status_icon = '❌';
        $status_description = 'Order has been cancelled';
        break;
    default:
        $status_icon = '📦';
        $status_description = 'Order status: ' . $status;
}

// Format shipping address
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?= SITE_NAME ?></title>
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover { 
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover { 
            background: #218838;
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
        
        .order-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .order-items-section {
            margin-top: 30px;
        }
        
        .order-items-section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 20px;
        }
        
        .products-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .products-table thead {
            background: #667eea;
            color: white;
        }
        
        .products-table th {
            padding: 18px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .products-table td {
            padding: 18px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .products-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
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
            font-size: 14px;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .product-sku {
            font-size: 12px;
            color: #666;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        .product-description {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin-top: 5px;
        }
        
        .price {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .quantity {
            color: #666;
            font-weight: 500;
        }
        
        .item-total {
            font-weight: bold;
            color: #28a745;
            font-size: 18px;
        }
        
        .order-summary {
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
        
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 20px 0;
            padding: 15px 0;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 2px;
            background: #eee;
            z-index: 1;
        }
        
        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .timeline-dot {
            width: 20px;
            height: 20px;
            background: #ddd;
            border-radius: 50%;
            margin: 0 auto 8px;
            position: relative;
            z-index: 2;
            border: 3px solid white;
        }
        
        .timeline-step.active .timeline-dot {
            background: #28a745;
            box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
        }
        
        .timeline-step.completed .timeline-dot {
            background: #28a745;
        }
        
        .timeline-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            
            .products-table {
                display: block;
                overflow-x: auto;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .product-image {
                width: 100%;
                height: 150px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📋 Order Details</h1>
        <div>
            <a href="orders.php">← Back to Orders</a>
            <a href="../index.php">← Store</a>
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
                    <span class="order-status-badge status-<?= $status ?>">
                        <?= $status_icon ?> <?= $status_text ?>
                    </span>
                </div>
            </div>
            
            <!-- Status Timeline -->
            <div class="status-timeline">
                <div class="timeline-step <?= in_array($status, ['pending', 'processing', 'shipped', 'delivered']) ? 'completed' : '' ?> <?= $status == 'pending' ? 'active' : '' ?>">
                    <div class="timeline-dot"></div>
                    <div class="timeline-label">Pending</div>
                </div>
                <div class="timeline-step <?= in_array($status, ['processing', 'shipped', 'delivered']) ? 'completed' : '' ?> <?= $status == 'processing' ? 'active' : '' ?>">
                    <div class="timeline-dot"></div>
                    <div class="timeline-label">Processing</div>
                </div>
                <div class="timeline-step <?= in_array($status, ['shipped', 'delivered']) ? 'completed' : '' ?> <?= $status == 'shipped' ? 'active' : '' ?>">
                    <div class="timeline-dot"></div>
                    <div class="timeline-label">Shipped</div>
                </div>
                <div class="timeline-step <?= $status == 'delivered' ? 'completed' : '' ?> <?= $status == 'delivered' ? 'active' : '' ?>">
                    <div class="timeline-dot"></div>
                    <div class="timeline-label">Delivered</div>
                </div>
            </div>
            
            <div class="info-grid">
                <!-- Customer Information -->
                <div class="info-box">
                    <h3>Customer Information</h3>
                    <div class="info-content">
                        <p><strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></p>
                        <?php if (!empty($order['user_id'])): ?>
                            <p><strong>Account:</strong> Registered User</p>
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
                        <?php if (!empty($order['estimated_delivery'])): ?>
                            <p><strong>Est. Delivery:</strong> <?= date('F j, Y', strtotime($order['estimated_delivery'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="info-box">
                    <h3>Payment Information</h3>
                    <div class="info-content">
                        <p>
                            <strong>Method:</strong> <?= $payment_icon ?> <?= $payment_text ?>
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
            <div class="order-items-section">
                <h2>Order Items (<?= count($order_items) ?>)</h2>
                
                <?php if (!empty($order_items)): ?>
                    <table class="products-table">
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
                                
                                $image_path = !empty($item['product_image']) ? "../assets/uploads/products/" . htmlspecialchars($item['product_image']) : '';
                                $image_exists = !empty($image_path) && file_exists($image_path);
                            ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-image">
                                            <?php if ($image_exists): ?>
                                                <img src="<?= $image_path ?>" 
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
                                                <div style="font-size: 12px; color: #999; margin-top: 3px;">
                                                    Product ID: <?= $item['product_id'] ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['product_description'])): ?>
                                                <div class="product-description">
                                                    <?= htmlspecialchars(substr($item['product_description'], 0, 100)) ?>...
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
                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal (<?= count($order_items) ?> items)</span>
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
                            <span class="summary-label">Tax</span>
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
                <a href="orders.php" class="btn btn-secondary">
                    ← Back to Orders
                </a>
                
                <?php if ($status == 'delivered'): ?>
                    <a href="../products/index.php" class="btn btn-success">
                        ⭐ Shop Again
                    </a>
                <?php elseif (($status == 'pending' || $status == 'processing') && $payment_method == 'cod'): ?>
                    <a href="cancel-order.php?id=<?= $order['id'] ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to cancel this order?\n\nNote: You can only cancel orders with Cash on Delivery payment method.\nThis action cannot be undone.')">
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
                
                <button onclick="window.print()" class="btn">
                    🖨️ Print Invoice
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Handle image loading errors
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Print functionality
            const printBtn = document.querySelector('button[onclick*="print"]');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    window.print();
                });
            }
            
            // Cancel order confirmation
            const cancelBtn = document.querySelector('a[href*="cancel-order.php"]');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this order?\n\nNote: You can only cancel orders with Cash on Delivery payment method.\nThis action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
    
    <!-- Print Styles -->
    <style media="print">
        @media print {
            .navbar, .actions, .btn {
                display: none !important;
            }
            
            body {
                background: white;
                font-size: 12px;
            }
            
            .container {
                max-width: 100%;
                margin: 0;
                padding: 20px;
            }
            
            .order-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .info-box, .order-summary {
                border: 1px solid #ddd;
            }
            
            .product-image {
                width: 60px;
                height: 60px;
            }
            
            .summary-total {
                font-size: 18px;
            }
            
            a {
                text-decoration: none;
                color: #333;
            }
        }
    </style>
</body>
</html>