<?php
// ========================================
// FILE: admin/orders/index.php
// ========================================
require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

// Get all orders with customer info
$stmt = $pdo->query("
    SELECT 
        o.*, 
        u.username as customer_name,
        u.email as customer_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll();

// Get order items for each order
$order_items_by_order = [];
foreach ($orders as $order) {
    $items_stmt = $pdo->prepare("
        SELECT oi.*, p.image as product_image 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
        LIMIT 3
    ");
    $items_stmt->execute([$order['id']]);
    $order_items_by_order[$order['id']] = $items_stmt->fetchAll();
}

// Get stats
$total_orders = count($orders);
$total_revenue = 0;
$pending_orders = 0;
$processing_orders = 0;
$shipped_orders = 0;
$completed_orders = 0;

foreach ($orders as $order) {
    $total_revenue += $order['total_amount'] ?? 0;
    switch ($order['order_status']) {
        case 'pending':
            $pending_orders++;
            break;
        case 'processing':
            $processing_orders++;
            break;
        case 'shipped':
            $shipped_orders++;
            break;
        case 'delivered':
            $completed_orders++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .stat-info h3 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #666;
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
            transition: all 0.3s;
        }
        
        .btn:hover { 
            background: #5568d3;
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
        
        .btn-warning { 
            background: #ffc107; 
            color: #212529;
        }
        .btn-warning:hover { 
            background: #e0a800; 
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            transition: background 0.3s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .order-header {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .order-number {
            font-weight: bold;
            color: #667eea;
            font-size: 14px;
        }
        
        .order-date {
            font-size: 12px;
            color: #999;
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .customer-name {
            font-weight: 500;
            color: #333;
        }
        
        .customer-email {
            font-size: 12px;
            color: #666;
        }
        
        .customer-guest {
            font-style: italic;
            color: #999;
            font-size: 11px;
        }
        
        /* Product Items Section */
        .product-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
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
        
        .no-product-image {
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
            min-width: 0; /* Important for text overflow */
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-meta {
            display: flex;
            gap: 8px;
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
        
        .product-quantity {
            background: #667eea;
            color: white;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .product-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .more-items {
            font-size: 11px;
            color: #666;
            text-align: center;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 3px;
            margin-top: 5px;
        }
        
        .amount {
            font-weight: bold;
            color: #28a745;
            font-size: 16px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
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
        
        .payment-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: #f8f9fa;
            color: #666;
            margin-top: 5px;
        }
        
        .payment-cod {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-card {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .payment-paypal {
            background: #cce5ff;
            color: #004085;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group select {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: white;
            min-width: 150px;
        }
        
        .filter-group button {
            padding: 8px 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state .icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .product-item {
                flex-direction: column;
                text-align: center;
            }
            
            .product-details {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📦 Manage Orders</h1>
        <div>
            <a href="../index.php">← Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <span>⚠️</span>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3><?= $total_orders ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3>$<?= number_format($total_revenue, 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3><?= $pending_orders ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <h3><?= $shipped_orders ?></h3>
                    <p>Shipped Orders</p>
                </div>
            </div>
        </div>
        
        <div class="header">
            <h2>All Orders</h2>
            <div class="filter-group">
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="paymentFilter">
                    <option value="">All Payments</option>
                    <option value="cod">Cash on Delivery</option>
                    <option value="card">Credit Card</option>
                    <option value="paypal">PayPal</option>
                </select>
                <button class="btn" onclick="applyFilters()">Apply Filters</button>
                <button class="btn" onclick="resetFilters()">Reset</button>
            </div>
        </div>
        
        <table id="ordersTable">
            <thead>
                <tr>
                    <th>Order Info</th>
                    <th>Customer</th>
                    <th>Products</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    $order_items = $order_items_by_order[$order['id']] ?? [];
                    $total_items_stmt = $pdo->prepare("SELECT COUNT(*) as total_count FROM order_items WHERE order_id = ?");
                    $total_items_stmt->execute([$order['id']]);
                    $total_items = $total_items_stmt->fetch()['total_count'] ?? 0;
                    
                    // Format customer info
                    $customer_name = $order['customer_name'] ?? ($order['shipping_full_name'] ?? 'Guest');
                    $customer_email = $order['customer_email'] ?? ($order['shipping_email'] ?? '');
                    $is_guest = empty($order['user_id']);
                    
                    // Format payment method
                    $payment_method = $order['payment_method'] ?? 'cod';
                    $payment_text = '';
                    $payment_class = '';
                    
                    switch($payment_method) {
                        case 'cod':
                            $payment_text = 'Cash on Delivery';
                            $payment_class = 'payment-cod';
                            break;
                        case 'card':
                            $payment_text = 'Credit Card';
                            $payment_class = 'payment-card';
                            break;
                        case 'paypal':
                            $payment_text = 'PayPal';
                            $payment_class = 'payment-paypal';
                            break;
                        default:
                            $payment_text = ucfirst($payment_method);
                    }
                ?>
                <tr data-status="<?= $order['order_status'] ?>" data-payment="<?= $payment_method ?>">
                    <td>
                        <div class="order-header">
                            <span class="order-number"><?= $order['order_number'] ?></span>
                            <span class="order-date">
                                <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                at <?= date('g:i A', strtotime($order['created_at'])) ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="customer-info">
                            <span class="customer-name"><?= htmlspecialchars($customer_name) ?></span>
                            <?php if (!empty($customer_email)): ?>
                                <span class="customer-email"><?= htmlspecialchars($customer_email) ?></span>
                            <?php endif; ?>
                            <?php if ($is_guest): ?>
                                <span class="customer-guest">Guest Order</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="product-items">
                            <?php if (!empty($order_items)): ?>
                                <?php foreach ($order_items as $item): ?>
                                <div class="product-item">
                                    <div class="product-image">
                                        <?php if (!empty($item['product_image'])): ?>
                                            <img src="../../assets/uploads/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                            <div class="no-product-image" style="display: none;">📦</div>
                                        <?php else: ?>
                                            <div class="no-product-image">📦</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-details">
                                        <div class="product-name" title="<?= htmlspecialchars($item['product_name']) ?>">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </div>
                                        <div class="product-meta">
                                            <span class="product-quantity">x<?= $item['quantity'] ?></span>
                                            <span class="product-price">$<?= number_format($item['unit_price'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if ($total_items > 3): ?>
                                <div class="more-items">
                                    +<?= $total_items - 3 ?> more item<?= ($total_items - 3) > 1 ? 's' : '' ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999; font-size: 13px;">No items</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="amount">$<?= number_format($order['total_amount'] ?? 0, 2) ?></span>
                        <?php if ($total_items > 0): ?>
                            <div style="font-size: 12px; color: #666;">
                                <?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $order['order_status'] ?>">
                            <?= ucfirst($order['order_status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="payment-badge <?= $payment_class ?>">
                            <?= $payment_text ?>
                        </span>
                        <br>
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            <span style="font-size: 11px; color: #28a745;">✅ Paid</span>
                        <?php elseif ($order['payment_status'] === 'pending'): ?>
                            <span style="font-size: 11px; color: #ffc107;">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <!-- Always show View button -->
                            <a href="detail.php?id=<?= $order['id'] ?>" class="btn" title="View Order Details">
                                👁️ View
                            </a>
                            
                            <?php 
                            // Only show Update and Cancel buttons for orders that are NOT delivered or cancelled
                            if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): 
                            ?>
                                <a href="update-status.php?id=<?= $order['id'] ?>" class="btn btn-success" title="Update Order Status">
                                    ✏️ Update
                                </a>
                                
                                <?php if ($order['order_status'] === 'pending'): ?>
                                    <a href="cancel.php?id=<?= $order['id'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to cancel order <?= $order['order_number'] ?>?')"
                                       title="Cancel Order">
                                        ❌ Cancel
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="icon">📦</div>
                            <h3>No Orders Yet</h3>
                            <p>When customers place orders, they'll appear here.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($orders)): ?>
        <div style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">
            Showing <?= count($orders) ?> order<?= count($orders) != 1 ? 's' : '' ?>
            • Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Filter functionality
        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const paymentFilter = document.getElementById('paymentFilter').value;
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowPayment = row.getAttribute('data-payment');
                
                let showRow = true;
                
                if (statusFilter && rowStatus !== statusFilter) {
                    showRow = false;
                }
                
                if (paymentFilter && rowPayment !== paymentFilter) {
                    showRow = false;
                }
                
                if (showRow) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update showing count
            const showingElement = document.querySelector('div[style*="margin-top: 20px"]');
            if (showingElement) {
                showingElement.innerHTML = `Showing ${visibleCount} order${visibleCount !== 1 ? 's' : ''}`;
            }
        }
        
        function resetFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('paymentFilter').value = '';
            
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            // Reset showing count
            const showingElement = document.querySelector('div[style*="margin-top: 20px"]');
            if (showingElement) {
                showingElement.innerHTML = `Showing ${rows.length} order${rows.length !== 1 ? 's' : ''}`;
            }
        }
        
        // Quick status update
        function updateOrderStatus(orderId, newStatus) {
            if (confirm('Are you sure you want to update the order status?')) {
                fetch('update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${orderId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
        
        // Show full product name on hover
        document.addEventListener('DOMContentLoaded', function() {
            const productNames = document.querySelectorAll('.product-name');
            productNames.forEach(name => {
                name.addEventListener('mouseenter', function(e) {
                    const fullName = this.getAttribute('title');
                    if (fullName && this.scrollWidth > this.clientWidth) {
                        // Create tooltip
                        const tooltip = document.createElement('div');
                        tooltip.className = 'product-tooltip';
                        tooltip.textContent = fullName;
                        tooltip.style.cssText = `
                            position: absolute;
                            background: #333;
                            color: white;
                            padding: 5px 10px;
                            border-radius: 3px;
                            font-size: 12px;
                            z-index: 1000;
                            white-space: nowrap;
                        `;
                        document.body.appendChild(tooltip);
                        
                        const rect = this.getBoundingClientRect();
                        tooltip.style.left = rect.left + 'px';
                        tooltip.style.top = (rect.top - 30) + 'px';
                        
                        this.tooltip = tooltip;
                    }
                });
                
                name.addEventListener('mouseleave', function() {
                    if (this.tooltip) {
                        this.tooltip.remove();
                    }
                });
            });
            
            // Make product images clickable to view product
            const productImages = document.querySelectorAll('.product-image');
            productImages.forEach(img => {
                img.style.cursor = 'pointer';
                img.addEventListener('click', function(e) {
                    const imgSrc = this.querySelector('img')?.src;
                    if (imgSrc && !imgSrc.includes('data:image')) {
                        window.open(imgSrc, '_blank');
                    }
                });
            });
        });
    </script>
</body>
</html>