<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$search = $_GET['search'] ?? '';
$search = $conn->real_escape_string($search);

$query = "
  SELECT bills.*, clients.company_name, clients.gst_number 
  FROM bills 
  JOIN clients ON bills.client_id = clients.id 
";

if ($search) {
  $query .= " WHERE clients.company_name LIKE '%$search%' OR clients.gst_number LIKE '%$search%'";
}

$query .= " ORDER BY bills.id DESC";
$res = $conn->query($query);
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
    .sidebar a { color: white; display: block; padding: 10px 0; text-decoration: none; }
    .sidebar a:hover { background: #512da8; border-radius: 5px; padding-left: 10px; }
    .main-content { margin-left: 250px; padding: 20px; }
    table { background: white; border-radius: 8px; width: 100%; overflow-x: auto; }
    th, td { padding: 10px; border: 1px solid #ddd; font-size: 14px; }
    .tag { font-size: 12px; padding: 3px 6px; border-radius: 4px; color: white; }
    .onetime { background: #6a1b9a; }
    .recurring { background: #0288d1; }
    .status { font-weight: bold; }
    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 10px; }
      .sidebar { display: none; }
      table, thead, tbody, th, td, tr { display: block; }
      thead { display: none; }
      td { position: relative; padding-left: 50%; border: none; border-bottom: 1px solid #eee; }
      td:before { position: absolute; top: 10px; left: 10px; width: 45%; white-space: nowrap; font-weight: bold; }
      td:nth-of-type(1):before { content: "Invoice #"; }
      td:nth-of-type(2):before { content: "Client"; }
      td:nth-of-type(3):before { content: "Project"; }
      td:nth-of-type(4):before { content: "Estimated"; }
      td:nth-of-type(5):before { content: "Remaining"; }
      td:nth-of-type(6):before { content: "Amount"; }
      td:nth-of-type(7):before { content: "GST"; }
      td:nth-of-type(8):before { content: "Total"; }
      td:nth-of-type(9):before { content: "Currency"; }
      td:nth-of-type(10):before { content: "Status"; }
      td:nth-of-type(11):before { content: "Payment Type"; }
      td:nth-of-type(12):before { content: "Mode"; }
      td:nth-of-type(13):before { content: "Next Payment"; }
      td:nth-of-type(14):before { content: "Bill Date"; }
      td:nth-of-type(15):before { content: "Updated"; }
      td:nth-of-type(16):before { content: "Remarks"; }
      td:nth-of-type(17):before { content: "PDF"; }
      td:nth-of-type(18):before { content: "Edit"; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
    <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
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
            <th>Invoice #</th>
            <th>Client</th>
            <th>Project</th>
            <th>Estimated</th>
            <th>Remaining</th>
            <th>Amount</th>
            <th>GST</th>
            <th>Total</th>
            <th>Currency</th>
            <th>Status</th>
            <th>Payment Type</th>
            <th>Mode</th>
            <th>Next Payment</th>
            <th>Bill Date</th>
            <th>Updated</th>
            <th>Remarks</th>
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
              $nextPayment = $row['next_payment_date'] ?: 'N/A';
              $status = ucfirst($row['status'] ?? 'Pending');

              // Calculate for one-time
              if (strtolower($row['project_type']) === 'recurring') {
                $estimated = 'N/A';
                $remaining = 'N/A';
                $paymentType = 'N/A';
              } else {
                $estimatedVal = floatval($row['estimated_value']);
                $gstAmt = $row['apply_gst'] ? ($estimatedVal * floatval($row['gst']) / 100) : 0;
                $remaining = number_format(($estimatedVal + $gstAmt) - floatval($row['amount']), 2);
                $estimated = "₹" . number_format($estimatedVal, 2);
                $paymentType = $row['payment_type'] ?: 'N/A';
              }

              // Dynamic PDF link
              $pdfLink = strtolower($row['project_type']) === 'onetime'
                ? "invoice-onetime.php?id={$row['id']}"
                : "invoice-recurring.php?id={$row['id']}";

              echo "<tr>
                <td>" . ($row['invoice_no'] ?: $row['id']) . "</td>
                <td>{$row['company_name']}</td>
                <td><span class='tag {$tagClass}'>{$row['project_type']}</span></td>
                <td>{$estimated}</td>
                <td>{$remaining}</td>
                <td>₹" . number_format($row['amount'], 2) . "</td>
                <td>{$gstDisplay}</td>
                <td>₹" . number_format($row['total'], 2) . "</td>
                <td>{$row['currency']}</td>
                <td class='status'>{$status}</td>
                <td>{$paymentType}</td>
                <td>{$row['payment_mode']}</td>
                <td>{$nextPayment}</td>
                <td>{$row['bill_date']}</td>
                <td>{$row['updated_at']}</td>
                <td>{$row['remarks']}</td>
                <td><a href='{$pdfLink}' class='btn btn-sm btn-success'>PDF</a></td>
                <td><a href='edit-bill.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a></td>
              </tr>";
            }
          } else {
            echo "<tr><td colspan='18' class='text-center'>No bills found.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
