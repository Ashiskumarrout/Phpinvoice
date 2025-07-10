<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Billing Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .admin-photo {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #0d6efd;
      margin-bottom: 10px;
      background: #fff;
    }
  </style>
</head>
<body>
  <div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="border-end bg-white" id="sidebar-wrapper">
      <div class="sidebar-heading border-bottom bg-light text-center py-4">
        <img src="images/Untitled (1200 x 900 px).png" alt="Admin Photo" class="admin-photo" onerror="this.onerror=null;this.src='images/IMG_5672.JPG';">
        <div class="mt-2 fw-bold" style="font-size:1.2rem;"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
      </div>
      <div class="list-group list-group-flush">
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="dashboard.php">
          <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-client.php">
          <i class="fa-solid fa-user-plus"></i> Add Client
        </a>
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="client-list.php">
          <i class="fa-solid fa-users"></i> Client List
        </a>
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="add-bill.php">
          <i class="fa-solid fa-file-invoice-dollar"></i> Add New Bill
        </a>
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="bill-history.php">
          <i class="fa-solid fa-clock-rotate-left"></i> Bill History
        </a>
        <a class="list-group-item list-group-item-action list-group-item-light p-3" href="logout.php">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </div>
    </div>

    <!-- Page content -->
    <div id="page-content-wrapper">
      <!-- Top navbar -->
      <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container-fluid">
          <button class="btn btn-primary" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
          <span class="navbar-text ms-auto"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']); ?></span>
        </div>
      </nav>

      <div class="container-fluid mt-4">
        <h1 class="mt-2">Dashboard</h1>

        <div class="row mt-4">
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                      <i class="fa-solid fa-users"></i> Total Clients
                    </div>
                    <div class="h5 mb-0 fw-bold text-dark">
                      <?php
                      $client_q = $conn->query("SELECT COUNT(*) AS total FROM clients");
                      echo $client_q->fetch_assoc()['total'];
                      ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-users fa-2x text-gray-300"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                      <i class="fa-solid fa-file-invoice"></i> Total Bills
                    </div>
                    <div class="h5 mb-0 fw-bold text-dark">
                      <?php
                      $bill_q = $conn->query("SELECT COUNT(*) AS total FROM bills");
                      echo $bill_q->fetch_assoc()['total'];
                      ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-file-invoice fa-2x text-gray-300"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                      <i class="fa-solid fa-sack-dollar"></i> Total Amount
                    </div>
                    <div class="h5 mb-0 fw-bold text-dark">
                      ₹<?php
                      $sum_q = $conn->query("SELECT IFNULL(SUM(amount),0) AS total FROM bills");
                      echo number_format($sum_q->fetch_assoc()['total'], 2);
                      ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-sack-dollar fa-2x text-gray-300"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                      <i class="fa-solid fa-clock"></i> Pending Bills
                    </div>
                    <div class="h5 mb-0 fw-bold text-dark">
                      <?php
                      $pending_q = $conn->query("SELECT COUNT(*) AS total FROM bills WHERE status='Pending'");
                      echo $pending_q->fetch_assoc()['total'];
                      ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-clock fa-2x text-gray-300"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Add more dashboard sections below if needed -->
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.querySelector("#sidebarToggle").addEventListener("click", function (e) {
      e.preventDefault();
      document.querySelector("#wrapper").classList.toggle("toggled");
    });
  </script>
</body>
</html>
