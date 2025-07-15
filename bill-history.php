<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$search = $_GET['search'] ?? '';
$search = $conn->real_escape_string($search);



if ($search) {
  $res = $conn->query("
    SELECT bills.*, clients.company_name, clients.gst_number 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE clients.company_name LIKE '%$search%' OR clients.gst_number LIKE '%$search%'
    ORDER BY bills.id DESC
  ");
} else {
  $res = $conn->query("
    SELECT bills.*, clients.company_name 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    ORDER BY bills.id DESC
  ");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Bill History</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin: 0; font-family: Arial; background: #f4f4f4; }
    .sidebar {
      width: 230px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      min-height: 100vh;
      color: white;
      padding: 20px;
      position: fixed;
    }
    .sidebar a {
      color: white;
      display: block;
      padding: 10px 0;
      text-decoration: none;
    }
    .sidebar a:hover {
      background: #512da8;
      border-radius: 5px;
      padding-left: 10px;
    }
    .main-content {
      margin-left: 250px;
      padding: 20px;
    }
    table {
      background: white;
      border-radius: 8px;
      width: 100%;
      overflow-x: auto;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ddd;
      font-size: 14px;
    }
    .logo-img {
      width: 50px;
      height: 50px;
      object-fit: contain;
      border-radius: 6px;
      margin-right: 8px;
    }
    .client-flex {
      display: flex;
      align-items: center;
    }
    .tag {
      font-size: 12px;
      padding: 3px 6px;
      border-radius: 4px;
      color: white;
    }
    .onetime { background: #6a1b9a; }
    .recurring { background: #0288d1; }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 10px;
      }
      .sidebar {
        display: none;
      }
      table, thead, tbody, th, td, tr {
        display: block;
      }
      thead {
        display: none;
      }
      td {
        position: relative;
        padding-left: 50%;
        border: none;
        border-bottom: 1px solid #eee;
      }
      td:before {
        position: absolute;
        top: 10px;
        left: 10px;
        width: 45%;
        white-space: nowrap;
        font-weight: bold;
      }
      td:nth-of-type(1):before { content: "ID"; }
      td:nth-of-type(2):before { content: "Client"; }
      td:nth-of-type(3):before { content: "Project"; }
      td:nth-of-type(4):before { content: "Estimated"; }
      td:nth-of-type(5):before { content: "Remaining"; }
      td:nth-of-type(6):before { content: "Amount"; }
      td:nth-of-type(7):before { content: "GST"; }
      td:nth-of-type(8):before { content: "Total"; }
      td:nth-of-type(9):before { content: "Payment Type"; }
      td:nth-of-type(10):before { content: "Mode"; }
      td:nth-of-type(11):before { content: "Next Payment"; }
      td:nth-of-type(12):before { content: "Bill Date"; }
      td:nth-of-type(13):before { content: "Description"; }
      td:nth-of-type(14):before { content: "PDF"; }
      td:nth-of-type(15):before { content: "Edit"; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-bill.php">🧾 Add New Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="main-content">
    <h2 class="mb-3">📜 Bill History</h2>
    <form method="get" class="mb-3 d-flex flex-wrap gap-2">
      <input type="text" name="search" class="form-control" placeholder="Search by Client or GST" value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-dark">Search</button>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Client</th>
            <th>Project</th>
            <th>Estimated</th>
            <th>Remaining</th>
            <th>Amount</th>
            <th>GST</th>
            <th>Total</th>
            <th>Payment Type</th>
            <th>Mode</th>
            <th>Next Payment</th>
            <th>Bill Date</th>
            <th>Description</th>
            <th>PDF</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
              $gstDisplay = $row['apply_gst'] ? "{$row['gst']}%" : "N/A";
              $tagClass = strtolower($row['project_type']) === 'recurring' ? 'recurring' : 'onetime';
              $logo = (!empty($row['logo']) && file_exists($row['logo'])) ? "<img src='{$row['logo']}' class='logo-img'>" : "";
              $clientDisplay = "<div class='client-flex'>{$logo}<div>{$row['company_name']}</div></div>";
              $nextPayment = $row['next_payment_date'] ?: 'N/A';
              $isRecurring = strtolower($row['project_type']) === 'recurring';

              if ($isRecurring) {
                $estimated = 'N/A';
                $paymentType = 'N/A';
                $remaining = 'N/A';
              } else {
                $estimatedVal = floatval($row['estimated_value']);
                $gstAmt = $row['apply_gst'] ? ($estimatedVal * floatval($row['gst']) / 100) : 0;
                $remaining = number_format(($estimatedVal + $gstAmt) - floatval($row['amount']), 2);
                $estimated = "₹" . number_format($estimatedVal, 2);
                $paymentType = $row['payment_type'] ?: 'N/A';
              }

              echo "<tr>
                <td>{$row['id']}</td>
                <td>{$clientDisplay}</td>
                <td><span class='tag {$tagClass}'>{$row['project_type']}</span></td>
                <td>{$estimated}</td>
                <td>{$remaining}</td>
                <td>₹" . number_format($row['amount'], 2) . "</td>
                <td>{$gstDisplay}</td>
                <td>₹" . number_format($row['total'], 2) . "</td>
                <td>{$paymentType}</td>
                <td>{$row['payment_mode']}</td>
                <td>{$nextPayment}</td>
                <td>{$row['bill_date']}</td>
                <td>{$row['description']}</td>
                <td><a href='download_invoice.php?id={$row['id']}' class='btn btn-sm btn-success'>PDF</a></td>
                <td><a href='edit-bill.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a></td>
              </tr>";
            }
          } else {
            echo "<tr><td colspan='15' class='text-center'>No bills found.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
