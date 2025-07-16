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
  <style>
    body {
      display: flex;
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f4f6f9;
    }

    /* Sidebar */
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
      font-weight: bold;
    }
    .sidebar a {
      color: white;
      display: block;
      padding: 12px 10px;
      text-decoration: none;
      font-weight: 500;
      border-radius: 8px;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: rgba(255, 255, 255, 0.2);
      padding-left: 15px;
    }

    /* Main Content */
    .main-content {
      margin-left: 260px;
      padding: 40px;
      width: calc(100% - 260px);
    }

    /* Alerts */
    .alert {
      max-width: 600px;
      margin: 0 auto 20px auto;
      border-radius: 8px;
      font-weight: 500;
      text-align: center;
    }

    /* Form Container */
    .form-container {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
      max-width: 600px;
      margin: 30px auto;
    }
    .form-container h2 {
      text-align: center;
      margin-bottom: 30px;
      font-weight: bold;
      color: #333;
    }

    /* Form Controls */
    .form-container label {
      font-weight: 600;
      margin-bottom: 8px;
      display: block;
      color: #333;
    }
    .form-control {
      border-radius: 8px;
      padding: 12px 15px;
      border: 1px solid #ccc;
      margin-bottom: 20px;
      transition: all 0.3s;
      font-size: 15px;
    }
    .form-control:focus {
      border-color: #7F00FF;
      box-shadow: 0 0 6px rgba(127, 0, 255, 0.4);
    }

    /* Button */
    .btn-custom {
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white;
      border: none;
      border-radius: 30px;
      padding: 14px;
      font-size: 16px;
      font-weight: bold;
      width: 100%;
      transition: 0.3s;
    }
    .btn-custom:hover {
      opacity: 0.9;
    }

    @media(max-width: 768px){
      .sidebar {
        display: none;
      }
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3>🧾 Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
    <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
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
      <h2>➕ Add New Client</h2>
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
        <button type="submit" class="btn-custom">Add Client</button>
      </form>
    </div>
  </div>
</body>
</html>
