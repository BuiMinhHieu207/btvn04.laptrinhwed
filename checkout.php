<?php
require __DIR__ . '/includes/init.php';
require_login();

[$items, $total] = get_cart_items($pdo);

if (!$items) {
    set_flash('error', 'Giỏ hàng đang trống.');
    redirect('cart.php');
}

// Lấy thông tin tài khoản để điền sẵn
$stmt = $pdo->prepare('SELECT fullname, phone, address FROM users WHERE id = ?');
$stmt->execute([current_user()['id']]);
$account = $stmt->fetch();

$errors = [];
$fullname = $account['fullname'] ?? '';
$phone = $account['phone'] ?? '';
$address = $account['address'] ?? '';
$note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 100) {
        $errors['fullname'] = 'Họ tên phải có từ 2 đến 100 ký tự.';
    }
    if (!preg_match('/^0[0-9]{9}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại gồm 10 chữ số, bắt đầu bằng số 0.';
    }
    if (mb_strlen($address) > 255) {
        $errors['address'] = 'Địa chỉ không được quá 255 ký tự.';
    }
    if (mb_strlen($note) > 500) {
        $errors['note'] = 'Ghi chú không được quá 500 ký tự.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            
            // Đọc lại giá và kho mới nhất, khoá các dòng bằng FOR UPDATE
            $stmt = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
            $lines = [];
            $orderTotal = 0;
            
            foreach ($_SESSION['cart'] as $productId => $quantity) {
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if (!$product || $product['stock'] < $quantity) {
                    throw new RuntimeException('Một sản phẩm trong giỏ không còn đủ tồn kho.');
                }
                
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity
                ];
                $orderTotal += $product['price'] * $quantity;
            }
            
            // Ghi đơn hàng
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, fullname, phone, address, note, total) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([current_user()['id'], $fullname, $phone, $address, $note, $orderTotal]);
            $orderId = (int) $pdo->lastInsertId();
            
            // Ghi dòng hàng và trừ tồn kho
            $stmtItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)');
            $stmtStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            
            foreach ($lines as $line) {
                $stmtItem->execute([$orderId, $line['product']['id'], $line['product']['name'], $line['product']['price'], $line['quantity']]);
                $stmtStock->execute([$line['quantity'], $line['product']['id']]);
            }
            
            $pdo->commit();
            unset($_SESSION['cart']); // Xoá giỏ hàng
            
            set_flash('success', 'Đặt hàng thành công. Mã đơn hàng của bạn là #' . $orderId);
            redirect('order-detail.php?id=' . $orderId);
            
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $errors['order'] = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['order'] = 'Lỗi hệ thống, vui lòng thử lại.';
        }
    }
}

$pageTitle = 'Đặt hàng';
require __DIR__ . '/includes/header.php';
?>
<h2>Đặt hàng</h2>
<?php if (isset($errors['order'])): ?>
    <p class="alert alert-error"><?= e($errors['order']) ?></p>
<?php endif; ?>

<table class="cart-table">
    <thead>
        <tr>
            <th>Sản phẩm</th>
            <th>Đơn giá</th>
            <th>Số lượng</th>
            <th>Thành tiền</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e($item['name']) ?></td>
            <td><?= format_price($item['price']) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= format_price($item['line_total']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">Tổng cộng</th>
            <th><?= format_price($total) ?></th>
        </tr>
    </tfoot>
</table>

<form action="<?= url('checkout.php') ?>" method="post" class="form">
    <p>
        <label for="fullname">Họ tên nhận hàng</label><br>
        <input type="text" id="fullname" name="fullname" value="<?= e($fullname) ?>" required>
        <span class="error"><?= e($errors['fullname'] ?? '') ?></span>
    </p>
    <p>
        <label for="phone">Số điện thoại</label><br>
        <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" required>
        <span class="error"><?= e($errors['phone'] ?? '') ?></span>
    </p>
    <p>
        <label for="address">Địa chỉ nhận hàng</label><br>
        <textarea id="address" name="address" rows="3" required><?= e($address) ?></textarea>
        <span class="error"><?= e($errors['address'] ?? '') ?></span>
    </p>
    <p>
        <label for="note">Ghi chú (tùy chọn)</label><br>
        <textarea id="note" name="note" rows="3"><?= e($note) ?></textarea>
        <span class="error"><?= e($errors['note'] ?? '') ?></span>
    </p>
    <p>
        <button type="submit" class="btn btn-accent">Xác nhận đặt hàng</button>
        <a href="<?= url('cart.php') ?>">Quay lại giỏ hàng</a>
    </p>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
