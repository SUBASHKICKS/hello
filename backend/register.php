<?php
// Database connection
$servername = "localhost";
$username = "root";  // default in XAMPP
$password = "";      // default in XAMPP
$dbname = "madras_masal"; // your database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $pass = $_POST['password'];
    $cpass = $_POST['confirm_password'];

    // Address fields
    $addressLine1 = $_POST['address_line1'];
    $addressLine2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'];
    $state = $_POST['state'];
    $pincode = $_POST['pincode'];
    $landmark = $_POST['landmark'] ?? '';

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Please enter a valid email address!'); window.history.back();</script>";
        exit;
    }

    // Check if password and confirm password match
    if ($pass !== $cpass) {
        echo "<script>alert('Passwords do not match! Please try again.'); window.history.back();</script>";
        exit;
    }

    // Validate password strength
    if (strlen($pass) < 6) {
        echo "<script>alert('Password must be at least 6 characters long!'); window.history.back();</script>";
        exit;
    }

    // Validate phone number
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo "<script>alert('Please enter a valid 10-digit phone number!'); window.history.back();</script>";
        exit;
    }

    // Validate pincode
    if (!preg_match('/^[0-9]{6}$/', $pincode)) {
        echo "<script>alert('Please enter a valid 6-digit pincode!'); window.history.back();</script>";
        exit;
    }

    // Hash the password for security
    $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert user
        $sql = "INSERT INTO registration (name, email, phone, password) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);

        if (!$stmt->execute()) {
            throw new Exception("User registration failed: " . $stmt->error);
        }

        $userId = $conn->insert_id;
        $stmt->close();

        // Insert default address
        $addressSql = "INSERT INTO user_addresses (user_id, address_type, full_name, phone, address_line1, address_line2, city, state, pincode, landmark, is_default)
                      VALUES (?, 'home', ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $addressStmt = $conn->prepare($addressSql);
        $addressStmt->bind_param("issssssss", $userId, $name, $phone, $addressLine1, $addressLine2, $city, $state, $pincode, $landmark);

        if (!$addressStmt->execute()) {
            throw new Exception("Address creation failed: " . $addressStmt->error);
        }

        $addressStmt->close();

        // Commit transaction
        $conn->commit();

        echo "<script>alert('Registration successful! Your address has been saved. Please login.'); window.location.href='../pages/login.html';</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "Registration failed: " . $e->getMessage();
    }
}
$conn->close();
?>
