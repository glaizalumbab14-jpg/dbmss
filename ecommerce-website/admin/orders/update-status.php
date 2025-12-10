<?php

require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('index.php');
}

// Get order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['order_status'] ?? '';
    
    // Validate status
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['error'] = 'Invalid order status selected';
        redirect("update-status.php?id=$id");
    }
    
    try {
        
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $_SESSION['success'] = 'Order status updated successfully';
        redirect('index.php');
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Failed to update order status: ' . $e->getMessage();
        redirect("update-status.php?id=$id");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Order Status - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover { 
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        
        .btn-secondary:hover { 
            background: #5a6268;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }
        
        .order-info p {
            margin: 5px 0;
            color: #555;
        }
        
        .order-info strong {
            color: #333;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .current-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            margin-top: 5px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Update Order Status</h1>
        <div>
            <a href="detail.php?id=<?= $id ?>">← Back to Order</a>
            <a href="index.php">← Orders List</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                ⚠️ <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="form-container">
            <h2>Update Order Status</h2>
            
            <div class="order-info">
                <p><strong>Order Number:</strong> #<?= $order['order_number'] ?? $order['id'] ?></p>
                <p><strong>Customer:</strong> 
                    <?= htmlspecialchars($order['customer_name'] ?? 
                                        $order['shipping_full_name'] ?? 
                                        $order['billing_name'] ?? 
                                        'Guest') ?>
                </p>
                <p><strong>Current Status:</strong> 
                    <span class="current-status status-<?= $order['order_status'] ?>">
                        <?= ucfirst($order['order_status']) ?>
                    </span>
                </p>
                <p><strong>Order Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="order_status">Select New Status</label>
                    <select id="order_status" name="order_status" required>
                        <option value="">-- Select Status --</option>
                        <option value="pending" <?= $order['order_status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $order['order_status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $order['order_status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $order['order_status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['order_status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn">Update Status</button>
                    <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('order_status');
            const currentStatus = '<?= $order['order_status'] ?>';
            
            // Warn before changing to certain statuses
            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                
                if (newStatus === 'cancelled') {
                    if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                        this.value = currentStatus;
                        return;
                    }
                }
                
                if (newStatus === 'delivered' && currentStatus !== 'delivered') {
                    if (!confirm('Mark this order as delivered? This will complete the order.')) {
                        this.value = currentStatus;
                        return;
                    }
                }
            });
            
            // Form submission confirmation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const newStatus = statusSelect.value;
                
                if (!newStatus) {
                    e.preventDefault();
                    alert('Please select a status');
                    return;
                }
                
                if (newStatus === currentStatus) {
                    e.preventDefault();
                    alert('Please select a different status from the current one');
                    return;
                }
                
                return confirm(`Change order status from "${currentStatus}" to "${newStatus}"?`);
            });
        });
    </script>
</body>
</html>