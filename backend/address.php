<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.html");
    exit();
}

// Include configuration
require_once 'config.php';

// Database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle address operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';

    if ($action == 'add_address') {
        // Add new address
        $addressType = $_POST['address_type'];
        $fullName = $_POST['full_name'];
        $phone = $_POST['phone'];
        $addressLine1 = $_POST['address_line1'];
        $addressLine2 = $_POST['address_line2'] ?? '';
        $city = $_POST['city'];
        $state = $_POST['state'];
        $pincode = $_POST['pincode'];
        $landmark = $_POST['landmark'] ?? '';
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        // If setting as default, unset other defaults
        if ($isDefault) {
            $conn->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = $userId");
        }

        $sql = "INSERT INTO user_addresses (user_id, address_type, full_name, phone, address_line1, address_line2, city, state, pincode, landmark, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssss", $userId, $addressType, $fullName, $phone, $addressLine1, $addressLine2, $city, $state, $pincode, $landmark, $isDefault);

        if ($stmt->execute()) {
            echo "<script>alert('Address added successfully!'); window.location.href='../pages/profile.html';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();

    } elseif ($action == 'update_address') {
        // Update existing address
        $addressId = $_POST['address_id'];
        $addressType = $_POST['address_type'];
        $fullName = $_POST['full_name'];
        $phone = $_POST['phone'];
        $addressLine1 = $_POST['address_line1'];
        $addressLine2 = $_POST['address_line2'] ?? '';
        $city = $_POST['city'];
        $state = $_POST['state'];
        $pincode = $_POST['pincode'];
        $landmark = $_POST['landmark'] ?? '';
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        // If setting as default, unset other defaults
        if ($isDefault) {
            $conn->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = $userId");
        }

        $sql = "UPDATE user_addresses SET address_type=?, full_name=?, phone=?, address_line1=?, address_line2=?, city=?, state=?, pincode=?, landmark=?, is_default=? WHERE id=? AND user_id=?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssiii", $addressType, $fullName, $phone, $addressLine1, $addressLine2, $city, $state, $pincode, $landmark, $isDefault, $addressId, $userId);

        if ($stmt->execute()) {
            echo "<script>alert('Address updated successfully!'); window.location.href='../pages/profile.html';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();

    } elseif ($action == 'delete_address') {
        // Delete address
        $addressId = $_POST['address_id'];

        $sql = "DELETE FROM user_addresses WHERE id=? AND user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $addressId, $userId);

        if ($stmt->execute()) {
            echo "<script>alert('Address deleted successfully!'); window.location.href='../pages/profile.html';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle GET requests for fetching addresses
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
    $userId = $_SESSION['user_id'];

    if ($_GET['action'] == 'get_addresses') {
        // Return addresses as JSON
        $sql = "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }

        echo json_encode($addresses);
        $stmt->close();
        exit();
    }
}

$conn->close();
?>
