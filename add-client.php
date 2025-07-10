<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $name = $_POST['company_name'];
  $gst = $_POST['gst'];
  $address = $_POST['address'];

  $stmt = $conn->prepare("INSERT INTO clients (company_name, gst_number, address) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $name, $gst, $address);

  if ($stmt->execute()) {
    $success = true;
  } else {
    $error = "Failed to add client.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Client</title>
  <link rel="stylesheet" href="bootstrap.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    body {
      display: flex;
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
    }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      min-height: 100vh;
      color: white;
      padding: 20px;
      position: fixed;
    }
    .sidebar h3 {
      margin-bottom: 30px;
    }
    .sidebar a {
      color: white;
      display: block;
      padding: 10px 0;
      text-decoration: none;
    }
    .sidebar a:hover {
      background: #34495e;
      border-radius: 5px;
      padding-left: 10px;
    }
    .main-content {
      margin-left: 250px;
      padding: 40px;
      width: 100%;
    }
    .form-container {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      max-width: 600px;
      margin: auto;
    }
    .form-container h2 {
      text-align: center;
      margin-bottom: 35px;
      font-weight: bold;
      color: #333;
    }
    .form-container label {
      font-weight: 500;
      margin-bottom: 8px;
    }
    .form-control {
      border-radius: 25px;
      padding: 12px 20px;
      border: 1px solid #ccc;
      margin-bottom: 25px;
      transition: 0.3s;
    }
    .form-control:focus {
      border-color: #7F00FF;
      box-shadow: 0 0 5px rgba(127, 0, 255, 0.5);
    }
    .btn-custom {
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white;
      border: none;
      border-radius: 30px;
      padding: 12px 20px;
      font-weight: bold;
      transition: 0.3s;
    }
    .btn-custom:hover {
      background: linear-gradient(135deg, #5e00cc, #cc00cc);
    }
    .alert {
      max-width: 600px;
      margin: 20px auto;
      border-radius: 10px;
      padding: 15px;
      font-weight: bold;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="dashboard.php">← Back to Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    
    <a href="add-bill.php">🧾 Add New Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="main-content">
    <?php if ($success): ?>
      <div class="alert alert-success">✅ Client added successfully!</div>
    <?php elseif (!empty($error)): ?>
      <div class="alert alert-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <div class="form-container">
      <h2>Add New Client</h2>
      <form method="post">
        <div class="mb-3">
          <label for="company_name">Company Name</label>
          <input type="text" name="company_name" id="company_name" class="form-control" required />
        </div>
        <div class="mb-3">
          <label for="gst">GST Number</label>
          <input type="text" name="gst" id="gst" class="form-control" required />
        </div>
        <div class="mb-3">
          <label for="address">Address</label>
          <textarea name="address" id="address" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-custom w-100">Add Client</button>
      </form>
    </div>
  </div>
</body>
</html>
