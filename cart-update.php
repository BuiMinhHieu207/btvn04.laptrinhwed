<?php
require __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

if (isset($_POST['clear'])) {
    // Nút "Xóa giỏ hàng"
    unset($_SESSION['cart']);
} elseif (isset($_POST['remove'])) {
    // Nút "Xóa" một mặt hàng
    $productId = key($_POST['remove']);
    unset($_SESSION['cart'][$productId]);
} else {
    // Nút "Cập nhật", qty là mảng {mã sản phẩm => số lượng}
    $quantities = $_POST['qty'] ?? [];
    if (is_array($quantities)) {
        foreach ($quantities as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = (int) $quantity;
            if (!isset($_SESSION['cart'][$productId])) {
                continue;
            }
            if ($quantity >= 1) {
                $_SESSION['cart'][$productId] = min($quantity, 99);
            } else {
                unset($_SESSION['cart'][$productId]);
            }
        }
    }
}

set_flash('success', 'Đã cập nhật giỏ hàng.');
redirect('cart.php');
