<?php
session_start();
require_once('config/db.php');

$error = "";

// Form handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    // Jointure users + roles pour récupérer le nom du rôle
    $stmt = $conn->prepare("
        SELECT u.*, r.name AS role
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.username = :username
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];
        header("Location: dashboard_princip.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AAAW MotorSport Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('img/login.png') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            min-height: 100vh;
            padding-left: 150px;
            padding-top: 150px;
        }

        .form-container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 350px;
        }

        .btn-orange {
            background-color: #cc5a00;
            border: none;
            color: white;
            font-weight: bold;
        }

        .btn-orange:hover {
            background-color: #a34700;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2 class="text-start">Login</h2>

    <form action="index.php" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-orange w-100">Log In</button>
    </form>
</div>

</body>