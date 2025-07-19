<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Get client ID
$client_id = $_GET['id'] ?? 0;
if (!$client_id) {
    header("Location: client-list.php");
    exit;
}

// Fetch client data
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

if (!$client) {
    header("Location: client-list.php?msg=notfound");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $gst_number = trim($_POST['gst_number']);
    $address = trim($_POST['address']);
    
    if (empty($company_name)) {
        $error = "Company name is required.";
    } else {
        // Update client
        $stmt = $conn->prepare("UPDATE clients SET company_name = ?, gst_number = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $company_name, $gst_number, $address, $client_id);
        
        if ($stmt->execute()) {
            header("Location: client-list.php?msg=updated");
            exit;
        } else {
            $error = "Error updating client: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Client</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <style>
    body {
      display: flex;
      min-height: 100vh;
      margin: 0;
      background: #f4f6f9;
      font-family: 'Segoe UI', sans-serif;
    }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white;
      padding: 20px;
      height: 100vh;
      position: fixed;
    }
    .sidebar h3 { margin-bottom: 30px; font-weight: bold; }
    .sidebar a {
      color: white;
      display: block;
      margin: 12px 0;
      text-decoration: none;
      font-weight: 500;
      padding: 10px;
      border-radius: 6px;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: rgba(255,255,255,0.2);
      padding-left: 15px;
    }

    .main {
      flex: 1;
      padding: 40px;
      margin-left: 260px;
      width: calc(100% - 260px);
    }
    .header {
      background: #fff;
      padding: 20px 30px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header h4 {
      margin: 0;
      font-size: 18px;
      color: #333;
    }

    h2 {
      margin-top: 20px;
      font-weight: bold;
      color: #333;
    }

    .alert {
      max-width: 700px;
      margin: 10px auto 20px;
      border-radius: 8px;
      text-align: center;
      font-weight: 600;
    }

    .form-wrapper {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.08);
      max-width: 700px;
    }

    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
    }
    .form-control {
      width: 100%;
      padding: 12px;
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      font-size: 14px;
      transition: 0.3s;
      box-sizing: border-box;
    }
    .form-control:focus {
      border-color: #7F00FF;
      outline: none;
      box-shadow: 0 0 0 3px rgba(127, 0, 255, 0.1);
    }
    textarea.form-control {
      resize: vertical;
      min-height: 100px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      border: none;
      padding: 12px 25px;
      font-weight: bold;
      border-radius: 8px;
      color: white;
      text-decoration: none;
      display: inline-block;
      transition: 0.3s;
      cursor: pointer;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(127, 0, 255, 0.3);
    }

    .btn-secondary {
      background: #6c757d;
      border: none;
      padding: 12px 25px;
      font-weight: bold;
      border-radius: 8px;
      color: white;
      text-decoration: none;
      display: inline-block;
      transition: 0.3s;
      margin-left: 10px;
    }
    .btn-secondary:hover {
      background: #5a6268;
      color: white;
      text-decoration: none;
    }

    .button-group {
      margin-top: 30px;
    }

    @media(max-width: 768px){
      .sidebar { display: none; }
      .main { margin: 0; width: 100%; padding: 20px; }
      .form-wrapper { padding: 20px; }
      .button-group { text-align: center; }
      .btn-secondary { margin-left: 0; margin-top: 10px; }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h3>🧾 Admin Panel</h3>
  <a href="dashboard.php">🏠 Dashboard</a>
  <hr style="border-color: rgba(255,255,255,0.3);">
  <a href="add-client.php">➕ Add Client</a>
  <a href="client-list.php">📄 Client List</a>
  <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
  <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
  <a href="re-nogst.php">🔁 Add Recurring No GST</a>
  <a href="one-nogst.php">🧾 One-Time No GST</a>
  <a href="bill-history.php">📊 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
  <div class="header">
    <h4>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?> 👋</h4>
  </div>

  <!-- Error Message -->
  <?php if (isset($error)): ?>
      <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <h2>✏️ Edit Client</h2>

  <div class="form-wrapper">
    <form method="post">
      <div class="form-group">
        <label for="company_name">Company Name *</label>
        <input type="text" 
               id="company_name" 
               name="company_name" 
               class="form-control" 
               value="<?php echo htmlspecialchars($client['company_name']); ?>" 
               required>
      </div>

      <div class="form-group">
        <label for="gst_number">GST Number</label>
        <input type="text" 
               id="gst_number" 
               name="gst_number" 
               class="form-control" 
               value="<?php echo htmlspecialchars($client['gst_number']); ?>" 
               placeholder="Enter GST number (optional)">
      </div>

      <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" 
                  name="address" 
                  class="form-control" 
                  placeholder="Enter client address"><?php echo htmlspecialchars($client['address']); ?></textarea>
      </div>

      <div class="button-group">
        <button type="submit" class="btn btn-primary">💾 Update Client</button>
        <a href="client-list.php" class="btn btn-secondary">🔙 Back to List</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
