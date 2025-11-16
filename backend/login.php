<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "madras_masal";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Please enter a valid email address!'); window.history.back();</script>";
        exit;
    }

    // Validate password is not empty
    if (empty($pass)) {
        echo "<script>alert('Please enter your password!'); window.history.back();</script>";
        exit;
    }

    // Prepare SQL to prevent SQL injection
    $sql = "SELECT id, name, password FROM registration WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($pass, $user['password'])) {
            // Password correct, start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Redirect to home page or dashboard
            header("Location: ../pages/home.html");
            exit();
        } else {
            echo "<script>alert('Invalid password! Please check your credentials and try again.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Email address not found! Please check your email or register first.'); window.history.back();</script>";
    }

    $stmt->close();
}

$conn->close();
?>
