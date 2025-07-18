<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    // Secure query
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $user;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid Username or Password!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Softech18</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', sans-serif;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #7F00FF, #E100FF);
}
.center-box {
    background: rgba(255, 255, 255, 0.15);
    padding: 40px;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    text-align: center;
    color: #fff;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
}
.center-box img {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    border: 3px solid #fff;
    margin-bottom: 15px;
     object-fit: contain;
   
}
.center-box h1 {
    font-size: 2rem;
    margin-bottom: 10px;
}
.center-box p {
    font-size: 1rem;
    margin-bottom: 20px;
}
.login-box input {
    width: 96%;
    border-radius: 50px;
    padding: 14px 18px;
    margin-bottom: 15px;
    border: none;
    font-size: 16px;
    outline: none;
}
.password-wrapper {
    position: relative;
}
.password-wrapper input {
    padding-right: 50px;
}
.password-wrapper .toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #333;
    cursor: pointer;
}
#password{width: 87% !important;}
.login-box button {
    width: 100%;
    border-radius: 50px;
    padding: 14px;
    background: linear-gradient(135deg, #7F00FF, #E100FF);
    border: none;
    color: #fff;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.4s;
}
.login-box button:hover {
    background: linear-gradient(135deg, #E100FF, #7F00FF);
}
.error-msg {
    background: rgba(255, 0, 0, 0.8);
    color: #fff;
    padding: 8px;
    border-radius: 8px;
    margin-bottom: 15px;
}
</style>
</head>
<body>
<div class="center-box">
    <img src="companylog1.png" alt="Logo" onerror="this.src='https://via.placeholder.com/110'">
    <h1>Welcome to Softech18</h1>
    <p>Secure login to manage your billing system</p>

    <form method="post" class="login-box">
        <?php if (!empty($error)) echo "<div class='error-msg'>$error</div>"; ?>
        <input type="text" name="username" placeholder="Username" required>
        
        <div class="password-wrapper">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword()"></i>
        </div>
        
        <button type="submit">LOGIN</button>
    </form>
</div>

<script>
function togglePassword() {
    const passField = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password');
    if (passField.type === 'password') {
        passField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
