<?php

require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Product deleted successfully';
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Failed to delete product';
    }
}

redirect('index.php');
?>