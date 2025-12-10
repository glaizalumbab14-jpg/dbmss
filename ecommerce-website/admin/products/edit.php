<?php

require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    redirect('index.php');
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('index.php');
}

// Get categories
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active'");
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $quantity = trim($_POST['quantity'] ?? 0);
    $category_id = trim($_POST['category_id'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Keep existing image
    $imageName = $product['image'];
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../assets/uploads/products/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['image']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            // Delete old image if exists
            if ($product['image'] && file_exists($uploadDir . $product['image'])) {
                unlink($uploadDir . $product['image']);
            }
            
            $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid('product_') . '.' . $fileExtension;
            $uploadPath = $uploadDir . $imageName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $error = 'Failed to upload image';
            }
        } else {
            $error = 'Invalid image type. Only JPG, PNG, GIF, and WEBP allowed';
        }
    }
    
    if (empty($name) || empty($price) || empty($category_id)) {
        $error = 'Name, price, and category are required';
    } elseif (!$error) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, quantity = ?, 
                    category_id = ?, sku = ?, status = ?, featured = ?, image = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $quantity, $category_id, $sku, $status, $featured, $imageName, $id]);
            
            $_SESSION['success'] = 'Product updated successfully';
            redirect('index.php');
        } catch(PDOException $e) {
            $error = 'Failed to update product: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
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
        
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .error-message {
            background: #fee;
            color: #c00;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
        
        .btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        
        .current-image {
            margin: 10px 0;
            text-align: center;
        }
        
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            border: 2px solid #ddd;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            padding: 30px;
            text-align: center;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px auto;
            display: none;
        }
        
        .image-preview img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>✏️ Edit Product</h1>
        <div>
            <a href="index.php">← Back to Products</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2 style="margin-bottom: 30px;">Edit Product #<?= $product['id'] ?></h2>
            
            <?php if ($error): ?>
                <div class="error-message">⚠️ <?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="price">Price *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Stock Quantity</label>
                    <input type="number" id="quantity" name="quantity" value="<?= $product['quantity'] ?>" min="0">
                </div>
                
                <div class="form-group">
                    <label for="sku">SKU (Product Code)</label>
                    <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    
                    <?php if ($product['image']): ?>
                        <div class="current-image">
                            <p style="margin-bottom: 10px; font-size: 14px; color: #666;">Current Image:</p>
                            <img src="../../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="Product">
                        </div>
                    <?php endif; ?>
                    
                    <div class="image-upload-area" style="margin-top: 15px;">
                        <p style="margin-bottom: 10px;">📷 Upload New Image (optional)</p>
                        <p style="font-size: 12px; color: #666; margin-bottom: 15px;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                        <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                        <button type="button" class="btn" onclick="document.getElementById('image').click()">
                            Choose New Image
                        </button>
                        <div class="image-preview" id="imagePreview">
                            <img src="" alt="Preview" id="previewImg">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $product['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="out_of_stock" <?= $product['status'] == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="featured" name="featured" <?= $product['featured'] ? 'checked' : '' ?>>
                    <label for="featured" style="margin-bottom: 0;">Mark as Featured Product</label>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn">Update Product</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>

