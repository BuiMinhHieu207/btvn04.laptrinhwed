<?php
require __DIR__ . '/includes/init.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order || ($order['user_id'] !== current_user()['id'] && !is_admin())) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy đơn hàng';
    require __DIR__ . '/includes/header.php';
    echo '<p>Không tìm thấy đơn hàng bạn yêu cầu.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ?');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$pageTitle = 'Đơn hàng #' . $order['id'];
require __DIR__ . '/includes/header.php';
?>
<h2>Đơn hàng #<?= $order['id'] ?></h2>

<table class="cart-table">
    <tbody>
        <tr>
            <th>Ngày đặt</th>
            <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
        </tr>
        <tr>
            <th>Trạng thái</th>
            <td><?= e(order_status_label($order['status'])) ?></td>
        </tr>
        <tr>
            <th>Người nhận</th>
            <td><?= e($order['fullname']) ?></td>
        </tr>
        <tr>
            <th>Điện thoại</th>
            <td><?= e($order['phone']) ?></td>
        </tr>
        <tr>
            <th>Địa chỉ</th>
            <td><?= e($order['address']) ?></td>
        </tr>
        <tr>
            <th>Ghi chú</th>
            <td><?= e($order['note']) ?></td>
        </tr>
    </tbody>
</table>

<h3>Sản phẩm</h3>
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
            <td><?= e($item['product_name']) ?></td>
            <td><?= format_price($item['price']) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= format_price($item['price'] * $item['quantity']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">Tổng cộng</th>
            <th><?= format_price($order['total']) ?></th>
        </tr>
    </tfoot>
</table>
<p>
    <?php if (is_admin()): ?>
        <a href="<?= url('admin/orders.php') ?>">&laquo; Danh sách đơn hàng (Admin)</a>
    <?php else: ?>
        <a href="<?= url('orders.php') ?>">&laquo; Quay lại danh sách đơn hàng</a>
    <?php endif; ?>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
