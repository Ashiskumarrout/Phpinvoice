<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) exit('Unauthorized access');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Invalid invoice ID.");

// Fetch data
$stmt = $conn->prepare("
    SELECT bills.*, clients.company_name, clients.address 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE bills.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) exit("Invoice not found.");

// Prepare fields
$invoiceNo = htmlspecialchars($row['invoice_no'] ?: 'INV-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT));
$clientName = htmlspecialchars($row['company_name']);
$clientAddress = nl2br(htmlspecialchars($row['address']));
$date = htmlspecialchars($row['bill_date']);
$description = htmlspecialchars($row['description']);
$remarks = !empty($row['remarks']) ? nl2br(htmlspecialchars($row['remarks'])) : 'None';
$projectType = htmlspecialchars($row['project_type']);
$paymentType = htmlspecialchars($row['payment_type'] ?? '');
$paymentMode = htmlspecialchars($row['payment_mode'] ?? '');
$nextPayment = $row['next_payment_date'] ? htmlspecialchars($row['next_payment_date']) : 'N/A';

// Financial details
$amountPaid = floatval($row['amount']);
$estimatedValue = isset($row['estimated_value']) ? floatval($row['estimated_value']) : 0;
$gstRate = $row['apply_gst'] ? floatval($row['gst']) : 0;
$gstAmount = $gstRate > 0 ? ($estimatedValue * $gstRate / 100) : 0;
$totalPayable = $estimatedValue + $gstAmount;
$remaining = max($totalPayable - $amountPaid, 0);

// Format for display
$amountPaidDisplay = "₹" . number_format($amountPaid, 2);
$estimatedDisplay = "₹" . number_format($estimatedValue, 2);
$gstDisplay = $gstRate > 0 ? "₹" . number_format($gstAmount, 2) . " ({$gstRate}%)" : "Not Applied";
$totalPayableDisplay = "₹" . number_format($totalPayable, 2);
$remainingDisplay = "₹" . number_format($remaining, 2);

// Background Logo
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';
$bgStyle = $backgroundImage ? "background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover; opacity: 0.05;" : '';

// Header Logo
$logo = '';
if (!empty($row['logo']) && file_exists($row['logo'])) {
    $imageData = base64_encode(file_get_contents($row['logo']));
    $logo = '<img src="data:image/png;base64,' . $imageData . '" style="width:120px; height:auto; border-radius:8px;">';
}

// Split description into items
$itemRows = '';
if (!empty($description)) {
    $items = explode(",", $description);
    foreach ($items as $item) {
        $itemRows .= "<tr><td style='padding:8px;'>" . htmlspecialchars(trim($item)) . "</td></tr>";
    }
}

// Build HTML
$html = "
<meta charset='UTF-8'>
<style>
  @page { margin: 20px; }
  body { font-family: Arial, sans-serif; color: #333; padding: 20px; font-size: 14px; }
  .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .header h2 { text-align: center; color: #4A148C; font-size: 26px; margin: 0; flex: 1; }
  .client-info { background: #f9f9f9; padding: 10px; border-radius: 8px; margin-top: 10px; }
  .client-info p { margin: 4px 0; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
  th { background-color: #f0f0f0; }
  .total-box { margin-top: 15px; text-align: right; font-size: 16px; font-weight: bold; }
  .remarks { margin-top: 20px; font-size: 14px; }
</style>

<div class='header'>
  <div>{$logo}</div>
  <h2>INVOICE</h2>
  <div style='width:120px;'></div>
</div>

<div class='client-info'>
  <p><strong>Invoice No:</strong> {$invoiceNo}</p>
  <p><strong>Client:</strong> {$clientName}</p>
  <p><strong>Address:</strong> {$clientAddress}</p>
  <p><strong>Date:</strong> {$date}</p>
  <p><strong>Project Type:</strong> {$projectType}</p>
  <p><strong>Estimated Value:</strong> {$estimatedDisplay}</p>
  <p><strong>Payment Type:</strong> {$paymentType}</p>
  <p><strong>Payment Mode:</strong> {$paymentMode}</p>
  <p><strong>Next Payment:</strong> {$nextPayment}</p>
</div>

<h3 style='margin-top:20px;'>Project Details</h3>
<table>
  <tr><th>Description</th></tr>
  {$itemRows}
</table>

<h3 style='margin-top:20px;'>Billing Summary</h3>
<table>
  <tr><th>Estimated Value</th><td>{$estimatedDisplay}</td></tr>
  <tr><th>GST</th><td>{$gstDisplay}</td></tr>
  <tr><th>Total Payable</th><td><strong>{$totalPayableDisplay}</strong></td></tr>
  <tr><th>Amount Paid</th><td>{$amountPaidDisplay}</td></tr>
  <tr><th>Remaining Amount</th><td><strong>{$remainingDisplay}</strong></td></tr>
</table>

<div class='total-box'>Grand Total: {$totalPayableDisplay}</div>

<div class='remarks'>
  <strong>Remarks:</strong><br>{$remarks}
</div>
";

// Generate PDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice_{$id}.pdf", ["Attachment" => false]);
?>
