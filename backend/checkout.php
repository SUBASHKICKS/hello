<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.html");
    exit();
}

// Include configuration
require_once 'config.php';

// Include Razorpay SDK
require_once 'vendor/autoload.php';

// Database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Razorpay payment creation
if (isset($_GET['action']) && $_GET['action'] == 'create_order') {
    $cartData = json_decode($_POST['cart'], true);
    $userId = $_SESSION['user_id'];

    if (empty($cartData)) {
        echo json_encode(['error' => 'Cart is empty']);
        exit();
    }

    // Calculate total in paisa (Razorpay requires amount in paisa)
    $total = 0;
    foreach ($cartData as $item) {
        $total += $item['price'] * $item['qty'];
    }
    $amountInPaisa = $total * 100; // Convert to paisa

    // Insert order first
    $sql = "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $userId, $total);

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;

        // Insert order items
        $itemSql = "INSERT INTO order_items (order_id, item_name, quantity, price, total) VALUES (?, ?, ?, ?, ?)";
        $itemStmt = $conn->prepare($itemSql);

        foreach ($cartData as $item) {
            $itemTotal = $item['price'] * $item['qty'];
            $itemStmt->bind_param("isidd", $orderId, $item['name'], $item['qty'], $item['price'], $itemTotal);
            $itemStmt->execute();
        }
        $itemStmt->close();

        // Create Razorpay order
        $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

        $razorpayOrder = $api->order->create([
            'receipt' => 'order_' . $orderId,
            'amount' => $amountInPaisa,
            'currency' => CURRENCY,
            'notes' => [
                'order_id' => $orderId,
                'user_id' => $userId
            ]
        ]);

        echo json_encode([
            'order_id' => $razorpayOrder['id'],
            'amount' => $amountInPaisa,
            'currency' => CURRENCY,
            'key' => RAZORPAY_KEY_ID,
            'db_order_id' => $orderId
        ]);
    } else {
        echo json_encode(['error' => 'Failed to create order']);
    }

    $stmt->close();
    exit();
}

// Handle payment verification
if (isset($_POST['razorpay_payment_id'])) {
    $paymentId = $_POST['razorpay_payment_id'];
    $orderId = $_POST['razorpay_order_id'];
    $signature = $_POST['razorpay_signature'];
    $dbOrderId = $_POST['db_order_id'];

    try {
        $api = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

        // Verify payment signature
        $attributes = [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature
        ];

        $api->utility->verifyPaymentSignature($attributes);

        // Update order status to confirmed
        $updateSql = "UPDATE orders SET status = 'confirmed' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $dbOrderId);
        $updateStmt->execute();
        $updateStmt->close();

        echo "<script>alert('Payment successful! Your order has been confirmed.'); localStorage.removeItem('cart'); window.location.href='../pages/home.html';</script>";

    } catch (Exception $e) {
        // Payment verification failed
        echo "<script>alert('Payment verification failed. Please contact support.'); window.location.href='../pages/cart.html';</script>";
    }
    exit();
}

// Legacy checkout (without payment) - kept for backward compatibility
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_GET['action'])) {
    $cartData = json_decode($_POST['cart'], true);
    $userId = $_SESSION['user_id'];

    if (empty($cartData)) {
        echo "Cart is empty!";
        exit();
    }

    // Calculate total
    $total = 0;
    foreach ($cartData as $item) {
        $total += $item['price'] * $item['qty'];
    }

    // Insert order
    $sql = "INSERT INTO orders (user_id, total_amount) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $userId, $total);

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;

        // Insert order items
        $itemSql = "INSERT INTO order_items (order_id, item_name, quantity, price, total) VALUES (?, ?, ?, ?, ?)";
        $itemStmt = $conn->prepare($itemSql);

        foreach ($cartData as $item) {
            $itemTotal = $item['price'] * $item['qty'];
            $itemStmt->bind_param("isidd", $orderId, $item['name'], $item['qty'], $item['price'], $itemTotal);
            $itemStmt->execute();
        }

        $itemStmt->close();

        // Clear cart (you might want to do this on frontend too)
        echo "<script>alert('Order placed successfully! Order ID: $orderId'); localStorage.removeItem('cart'); window.location.href='../pages/home.html';</script>";
    } else {
        echo "Error placing order: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
