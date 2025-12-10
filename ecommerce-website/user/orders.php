<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user's orders
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get order items for each order with product images
$order_items_by_order = [];
foreach ($orders as $order) {
    $items_stmt = $pdo->prepare("
        SELECT oi.*, p.image as product_image 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $items_stmt->execute([$order['id']]);
    $order_items_by_order[$order['id']] = $items_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - <?= SITE_NAME ?></title>
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
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .header p {
            color: #666;
            font-size: 18px;
        }
        
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
        }
        
        /* Status-specific borders */
        .order-pending {
            border-left: 4px solid #ffc107;
        }
        
        .order-processing {
            border-left: 4px solid #17a2b8;
        }
        
        .order-shipped {
            border-left: 4px solid #007bff;
        }
        
        .order-delivered {
            border-left: 4px solid #28a745;
        }
        
        .order-cancelled {
            border-left: 4px solid #dc3545;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .order-info h3 {
            color: #333;
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .order-info p {
            color: #666;
            font-size: 14px;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
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
        
        /* Product Items Display - UPDATED */
        .order-products {
            margin-bottom: 20px;
        }
        
        .order-products-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
            transition: all 0.3s ease;
        }
        
        .product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: white;
        }
        
        .product-image-container {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eee;
            flex-shrink: 0;
            position: relative;
            cursor: pointer;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            flex: 1;
            min-width: 0;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .product-info {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .product-quantity {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .product-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .product-total {
            color: #333;
            font-weight: 500;
        }
        
        /* Order Details */
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-item {
            background: white;
            padding: 12px 15px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        
        .detail-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn:hover { 
            background: #5568d3; 
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
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
        
        .no-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-orders .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .no-orders h3 {
            color: #666;
            margin-bottom: 15px;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .amount {
            font-weight: bold;
            color: #28a745;
            font-size: 18px;
        }
        
        .payment-method {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .payment-icon {
            font-size: 16px;
        }
        
        /* Empty image placeholder */
        .empty-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #ccc;
            background: #f8f9fa;
        }
        
        /* Simple Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            padding: 20px;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 5px;
            background: #f8f9fa;
            margin-bottom: 15px;
        }
        
        .modal-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .modal-info p {
            margin: 5px 0;
        }
        
        .item-quantity {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #667eea;
            color: white;
            font-size: 11px;
            font-weight: bold;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            border: 2px solid white;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
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
        <h1>📦 My Orders</h1>
        <div>
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="../index.php">← Store</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>Order History</h1>
            <p>Track and manage your purchases</p>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <div class="icon">📦</div>
                <h3>You haven't placed any orders yet</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Start shopping to see your orders here.
                </p>
                <a href="../products/index.php" class="btn">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): 
                    $order_items = $order_items_by_order[$order['id']] ?? [];
                    $status = $order['order_status'] ?? $order['status'] ?? 'pending';
                    $status_class = 'status-' . $status;
                    $order_class = 'order-' . $status;
                    
                    // Get payment method
                    $payment_method = $order['payment_method'] ?? '';
                    $payment_icon = '';
                    $payment_text = '';
                    
                    switch($payment_method) {
                        case 'cod':
                            $payment_icon = '💵';
                            $payment_text = 'Cash on Delivery';
                            break;
                        case 'card':
                            $payment_icon = '💳';
                            $payment_text = 'Credit/Debit Card';
                            break;
                        case 'paypal':
                            $payment_icon = '🅿️';
                            $payment_text = 'PayPal';
                            break;
                        default:
                            $payment_icon = '💳';
                            $payment_text = 'Payment';
                    }
                    
                    // Get status display text
                    $status_text = ucfirst($status);
                    $status_icon = '⏳'; // Default icon
                    $status_desc = '';
                    
                    switch($status) {
                        case 'pending':
                            $status_icon = '⏳';
                            $status_desc = 'Order received, waiting for payment confirmation';
                            break;
                        case 'processing':
                            $status_icon = '🔄';
                            $status_desc = 'Payment confirmed, preparing your order';
                            break;
                        case 'shipped':
                            $status_icon = '🚚';
                            $status_desc = 'Order shipped and on its way';
                            break;
                        case 'delivered':
                            $status_icon = '✅';
                            $status_desc = 'Order delivered successfully';
                            break;
                        case 'cancelled':
                            $status_icon = '❌';
                            $status_desc = 'Order has been cancelled';
                            break;
                        default:
                            $status_icon = '📦';
                            $status_desc = 'Order status: ' . $status;
                    }
                ?>
                <div class="order-card <?= $order_class ?>">
                    <div class="order-header">
                        <div class="order-info">
                            <h3><?= $order['order_number'] ?? 'Order #' . $order['id'] ?></h3>
                            <p>
                                Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?>
                                • <?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?>
                            </p>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="status-badge <?= $status_class ?>">
                            <?= $status_icon ?> <?= $status_text ?>
                        </div>
                    </div>
                    
                    <!-- Status Description -->
                    <div style="margin-bottom: 20px; padding: 12px 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px;">
                        <strong>Status:</strong> <?= $status_desc ?>
                    </div>
                    
                    <!-- Order Products - UPDATED -->
                    <div class="order-products">
                        <div class="order-products-title">
                            <span>📦 Ordered Products</span>
                        </div>
                        <div class="products-grid">
                            <?php foreach ($order_items as $item): 
                                $image_path = !empty($item['product_image']) ? "../assets/uploads/products/" . htmlspecialchars($item['product_image']) : '';
                                $image_exists = !empty($image_path) && file_exists($image_path);
                                
                                // Calculate item total
                                $item_price = $item['unit_price'] ?? $item['price'] ?? 0;
                                $item_quantity = $item['quantity'] ?? 1;
                                $item_total = $item_price * $item_quantity;
                                
                                // Escape for JavaScript
                                $product_name_js = htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="product-item">
                                <div class="product-image-container" 
                                     onclick="showProductModal(<?= json_encode($product_name_js) ?>, <?= json_encode($image_exists ? $image_path : '') ?>, <?= floatval($item_price) ?>, <?= intval($item_quantity) ?>)"
                                     title="Click to enlarge: <?= htmlspecialchars($item['product_name']) ?>">
                                    <?php if ($image_exists): ?>
                                        <img src="<?= $image_path ?>" 
                                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="empty-image">📦</div>
                                    <?php endif; ?>
                                    <?php if ($item_quantity > 1): ?>
                                        <div class="item-quantity"><?= $item_quantity ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="product-info">
                                        <span class="product-quantity">Qty: <?= $item_quantity ?></span>
                                        <span class="product-price">$<?= number_format($item_price, 2) ?></span>
                                        <span class="product-total">Total: $<?= number_format($item_total, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Order Details -->
                    <div class="order-details">
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value amount">$<?= number_format($order['total'] ?? $order['total_amount'] ?? 0, 2) ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value payment-method">
                                <?= $payment_icon ?> <?= $payment_text ?>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span style="font-size: 11px; color: #28a745; margin-left: 8px;">✅ Paid</span>
                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span style="font-size: 11px; color: #ffc107; margin-left: 8px;">⏳ Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['tracking_number'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Tracking Number</div>
                            <div class="detail-value">
                                📦 <?= htmlspecialchars($order['tracking_number']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['shipping_address'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Shipping Address</div>
                            <div class="detail-value">
                                🏠 <?= htmlspecialchars(substr($order['shipping_address'], 0, 50)) ?>...
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Order Actions -->
                    <div class="order-actions">
                        <a href="order-details.php?id=<?= $order['id'] ?>" class="btn">
                            📋 View Order Details
                        </a>
                        
                        <?php if ($status == 'delivered'): ?>
                        <a href="../products/index.php" class="btn btn-success">
                            ⭐ Shop Again
                        </a>
                        <?php elseif (($status == 'pending' || $status == 'processing') && $payment_method == 'cod'): ?>
                        <a href="cancel-order.php?id=<?= $order['id'] ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to cancel order <?= $order['order_number'] ?? $order['id'] ?>?\n\nNote: You can only cancel orders with Cash on Delivery payment method.\nThis action cannot be undone.')">
                            ❌ Cancel Order
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Simple Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Product Details</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <img id="modalImage" class="modal-image" src="" alt="">
            <div id="modalEmptyImage" class="empty-image" style="display: none; height: 300px;">📦</div>
            <div class="modal-info">
                <p><strong>Product:</strong> <span id="modalProductName"></span></p>
                <p><strong>Price:</strong> $<span id="modalProductPrice"></span></p>
                <p><strong>Quantity:</strong> <span id="modalProductQuantity"></span></p>
                <p><strong>Total:</strong> $<span id="modalProductTotal"></span></p>
            </div>
        </div>
    </div>
    
    <script>
        // Simple modal functions
        function showProductModal(name, imageSrc, price, quantity) {
            const modal = document.getElementById('productModal');
            const modalImage = document.getElementById('modalImage');
            const modalEmptyImage = document.getElementById('modalEmptyImage');
            const modalProductName = document.getElementById('modalProductName');
            const modalProductPrice = document.getElementById('modalProductPrice');
            const modalProductQuantity = document.getElementById('modalProductQuantity');
            const modalProductTotal = document.getElementById('modalProductTotal');
            
            // Set product info
            modalProductName.textContent = name;
            modalProductPrice.textContent = price.toFixed(2);
            modalProductQuantity.textContent = quantity;
            modalProductTotal.textContent = (price * quantity).toFixed(2);
            
            // Set image
            if (imageSrc) {
                modalImage.src = imageSrc;
                modalImage.style.display = 'block';
                modalEmptyImage.style.display = 'none';
                
                // Handle image loading errors
                modalImage.onerror = function() {
                    modalImage.style.display = 'none';
                    modalEmptyImage.style.display = 'flex';
                };
            } else {
                modalImage.style.display = 'none';
                modalEmptyImage.style.display = 'flex';
            }
            
            // Show modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            const modal = document.getElementById('productModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Cancel order confirmation - only for COD orders
        document.querySelectorAll('.btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to cancel this order?\n\nNote: You can only cancel orders with Cash on Delivery payment method.\nThis action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>