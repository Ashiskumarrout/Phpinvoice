<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Fetch upcoming payments within next 10 days
$notif_q = $conn->query("
    SELECT b.id, b.invoice_no, b.next_payment_date, b.amount, c.company_name, DATEDIFF(b.next_payment_date, CURDATE()) AS days_left
    FROM bills b
    JOIN clients c ON b.client_id = c.id
    WHERE b.status = 'Pending' 
    AND b.next_payment_date IS NOT NULL
    AND b.next_payment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
    ORDER BY b.next_payment_date ASC
");
$notifications = [];
while ($row = $notif_q->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.admin-photo { width: 90px; height: 90px; object-fit: cover; border-radius: 50%; border: 3px solid #0d6efd; margin-bottom: 10px; background: #fff; }
.notification-bell { position: relative; cursor: pointer; }
.notif-count { position: absolute; top: -5px; right: -8px; background: red; color: white; font-size: 12px; font-weight: bold; padding: 2px 6px; border-radius: 50%; }
.dropdown-menu { max-height: 350px; overflow-y: auto; width: 320px; }
.notif-item { font-size: 14px; padding: 8px; border-bottom: 1px solid #eee; }
.notif-item:last-child { border-bottom: none; }
</style>
</head>
<body>
<div class="d-flex" id="wrapper">
<!-- Sidebar -->
<div class="border-end bg-white" id="sidebar-wrapper">
  <div class="sidebar-heading border-bottom bg-light text-center py-4">
    <img src="companylog1.png" alt="Admin Photo" class="admin-photo" onerror="this.onerror=null;this.src='images/IMG_5672.JPG';">
    <div class="mt-2 fw-bold"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
  </div>
  <style>
    .admin-photo {
    /* width: 120px;          
    height: 120px;         */
    object-fit:contain;     /* Ensures image fills without distortion */
    border-radius: 50%;    /* Makes image circular */
    border: 3px solid #ddd;/* Optional border */
    display: block;        /* Centers image inside parent */
    margin: 0 auto;        /* Center alignment */
    background: #fff;      /* White background if image has transparency */
}
.sidebar-heading {
    text-align: center;    /* Centers everything inside heading */
}

  </style>
  <div class="list-group list-group-flush">
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-client.php"><i class="fa-solid fa-user-plus"></i> Add Client</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="client-list.php"><i class="fa-solid fa-users"></i> Client List</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-bill.php"><i class="fa-solid fa-file-invoice-dollar"></i> Add New Bill</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="bill-history.php"><i class="fa-solid fa-clock-rotate-left"></i> Bill History</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-recurring-bill.php"><i class="fa-solid fa-repeat"></i> Add Recurring Bill</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-one-time-bill.php"><i class="fa-solid fa-file-invoice"></i> One-Time Invoices</a>
    <a class="list-group-item list-group-item-action list-group-item-light p-3" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<!-- Page content -->
<div id="page-content-wrapper">
  <!-- Top navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid">
      <button class="btn btn-primary" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      
      <!-- Notification Bell -->
      <div class="ms-auto me-4 notification-bell dropdown">
        <i class="fa-solid fa-bell fa-2x" data-bs-toggle="dropdown" aria-expanded="false"></i>
        <?php if ($notif_count > 0): ?>
          <span class="notif-count"><?php echo $notif_count; ?></span>
        <?php endif; ?>
        <ul class="dropdown-menu dropdown-menu-end p-2">
          <h6 class="dropdown-header">🔔 Notifications</h6>
          <?php if ($notif_count > 0): ?>
            <?php foreach ($notifications as $n): ?>
              <li class="notif-item" id="notif-<?php echo $n['id']; ?>">
                <strong><?php echo htmlspecialchars($n['company_name']); ?></strong><br>
                Invoice: <?php echo htmlspecialchars($n['invoice_no']); ?><br>
                Amount: ₹<?php echo number_format($n['amount'], 2); ?><br>
                Due: <?php echo date('d M Y', strtotime($n['next_payment_date'])); ?>
                (<?php echo ($n['days_left'] > 0) ? $n['days_left'].' days left' : 'Overdue'; ?>)
                <br>
                <button class="btn btn-sm btn-success mt-1 markPaid" data-id="<?php echo $n['id']; ?>">Mark as Paid</button>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="dropdown-item text-muted">No upcoming renewals</li>
          <?php endif; ?>
        </ul>
      </div>

      <span class="navbar-text"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']); ?></span>
    </div>
  </nav>

  <div class="container-fluid mt-4">
    <h1 class="mt-2">Dashboard</h1>
    <div class="row mt-4">
      <!-- Total Clients -->
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs fw-bold text-primary text-uppercase mb-1"><i class="fa-solid fa-users"></i> Total Clients</div>
                <div class="h5 mb-0 fw-bold text-dark">
                  <?php $client_q = $conn->query("SELECT COUNT(*) AS total FROM clients"); echo $client_q->fetch_assoc()['total']; ?>
                </div>
              </div>
              <i class="fa-solid fa-users fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Bills -->
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs fw-bold text-success text-uppercase mb-1"><i class="fa-solid fa-file-invoice"></i> Total Bills</div>
                <div class="h5 mb-0 fw-bold text-dark">
                  <?php $bill_q = $conn->query("SELECT COUNT(*) AS total FROM bills"); echo $bill_q->fetch_assoc()['total']; ?>
                </div>
              </div>
              <i class="fa-solid fa-file-invoice fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Amount -->
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs fw-bold text-warning text-uppercase mb-1"><i class="fa-solid fa-sack-dollar"></i> Total Amount</div>
                <div class="h5 mb-0 fw-bold text-dark">
                  ₹<?php $sum_q = $conn->query("SELECT IFNULL(SUM(amount),0) AS total FROM bills"); echo number_format($sum_q->fetch_assoc()['total'], 2); ?>
                </div>
              </div>
              <i class="fa-solid fa-sack-dollar fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Bills with View & Clear Button -->
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs fw-bold text-danger text-uppercase mb-1"><i class="fa-solid fa-clock"></i> Pending Bills</div>
                <div class="h5 mb-0 fw-bold text-dark">
                  <?php $pending_q = $conn->query("SELECT COUNT(*) AS total FROM bills WHERE status='Pending'"); echo $pending_q->fetch_assoc()['total']; ?>
                </div>
              </div>
              <i class="fa-solid fa-clock fa-2x text-gray-300"></i>
            </div>
            <button class="btn btn-danger btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#pendingModal">View & Clear</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Pending Bills Modal -->
<div class="modal fade" id="pendingModal" tabindex="-1" aria-labelledby="pendingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pendingModalLabel">Pending Bills</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Client</th>
              <th>Invoice</th>
              <th>Amount</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $pending_list = $conn->query("SELECT b.id, b.invoice_no, b.amount, c.company_name FROM bills b JOIN clients c ON b.client_id = c.id WHERE b.status='Pending'");
            if ($pending_list->num_rows > 0) {
              while ($row = $pending_list->fetch_assoc()) {
                echo "<tr id='row-{$row['id']}'>
                        <td>".htmlspecialchars($row['company_name'])."</td>
                        <td>".htmlspecialchars($row['invoice_no'])."</td>
                        <td>₹".number_format($row['amount'], 2)."</td>
                        <td><button class='btn btn-success btn-sm markPaid' data-id='{$row['id']}'>Clear</button></td>
                      </tr>";
              }
            } else {
              echo "<tr><td colspan='4' class='text-center'>No pending bills</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector("#sidebarToggle").addEventListener("click", function (e) {
  e.preventDefault();
  document.querySelector("#wrapper").classList.toggle("toggled");
});

// Handle Mark as Paid from both dropdown and modal
document.addEventListener("click", function(e) {
  if (e.target.classList.contains("markPaid")) {
    let billId = e.target.getAttribute("data-id");
    if (confirm("Mark this bill as Paid?")) {
      fetch("mark-paid.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + billId
      }).then(res => res.text()).then(data => {
        alert(data);
        document.getElementById("notif-" + billId)?.remove();
        document.getElementById("row-" + billId)?.remove();
      });
    }
  }
});
</script>
</body>
</html>
