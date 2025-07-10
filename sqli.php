<?php
$host = "localhost";
$user = "root";
$pass = "password";
$dbname = "sys";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // ❌ Insecure query (VULNERABLE to SQL injection)
    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION["user"] = $username;
        echo "<h2>✅ Login successful!</h2>";
    } else {
        echo "<h2>❌ Invalid login!</h2>";
    }
}
?>

<form method="post">
    <label>👤 Username:</label><br>
    <input type="text" name="username"><br><br>

    <label>🔒 Password:</label><br>
    <input type="password" name="password"><br><br>

    <input type="submit" value="Login">
</form>
