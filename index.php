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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $user;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid login!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .login-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }
    .login-left {
      flex: 1;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 30px;
      color: #fff;
    }
    .login-left h1 {
      font-size: 2.5rem;
      font-weight: bold;
    }
    .login-left p {
      font-size: 1rem;
      max-width: 400px;
      margin-top: 10px;
    }
    .login-right {
      flex: 1;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px;
    }
    .login-box {
      width: 100%;
      max-width: 350px;
    }
    .login-box input {
      border-radius: 50px;
      padding: 10px 20px;
      margin-bottom: 15px;
    }
    .login-box button {
      border-radius: 50px;
      padding: 10px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      border: none;
    }
    .remember-forgot {
      display: flex;
      justify-content: space-between;
      font-size: 0.9rem;
      margin-bottom: 10px;
    }
    @media (max-width: 768px) {
      .login-container {
        flex-direction: column;
      }
      .login-left, .login-right {
        flex: none;
        width: 100%;
        height: 50vh;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <h1>Welcome to website</h1>
      <p>Create and send invoices as a PDF attachment using over 100 professional invoice templates. Email invoices directly, get paid by card. Fast & Secure!</p>
    </div>
    <div class="login-right">
      <form method="post" class="login-box">
        <h5 class="text-center mb-4">USER LOGIN</h5>
        <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <input type="text" name="username" class="form-control" placeholder="&#xf007; Username" required style="font-family:Arial, FontAwesome">
        <input type="password" name="password" class="form-control" placeholder="&#xf023; Password" required style="font-family:Arial, FontAwesome">
        <div class="remember-forgot">
          <label><input type="checkbox"> Remember</label>
          <a href="#">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100 text-white">LOGIN</button>
      </form>
    </div>
  </div>
</body>
</html>
