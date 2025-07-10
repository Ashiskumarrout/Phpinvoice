<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) exit('Unauthorized access');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Invalid invoice ID.");

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

$clientName = htmlspecialchars($row['company_name']);
$clientAddress = nl2br(htmlspecialchars($row['address']));
$date = htmlspecialchars($row['bill_date']);
$description = nl2br(htmlspecialchars($row['description']));
$amount = number_format($row['amount'], 2);
$gst = $row['apply_gst'] ? number_format($row['gst'], 2) : "0.00";
$gstAmount = $row['apply_gst'] ? number_format($row['amount'] * $row['gst'] / 100, 2) : "0.00";
$total = number_format($row['total'], 2);
$projectType = htmlspecialchars($row['project_type']);
$paymentType = htmlspecialchars($row['payment_type'] ?? '');
$paymentMode = htmlspecialchars($row['payment_mode'] ?? '');
$nextPayment = $row['next_payment_date'] ? htmlspecialchars($row['next_payment_date']) : 'N/A';

$logo = '';
if (!empty($row['logo']) && file_exists($row['logo'])) {
    $imageData = base64_encode(file_get_contents($row['logo']));
    $logo = '<img src="data:image/png;base64,' . $imageData . '" style="width:120px; height:auto; border-radius:8px;">';
}

$html = "
<style>
  body { font-family: Arial, sans-serif; color: #333; padding: 20px; }
  .header { display: flex; justify-content: space-between; align-items: center; }
  .header h2 { flex: 1; text-align: center; color: #4A148C; margin: 0; }
  .client-info { margin-top: 20px; }
  .client-info p { margin: 5px 0; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  th, td { border: 1px solid #ddd; padding: 10px; }
  th { background-color: #f0f0f0; text-align: left; }
  .total { font-size: 16px; font-weight: bold; text-align: right; margin-top: 20px; }
</style>

<div class='header'>
  <div class='logo'>{$logo}</div>
  <h2>Invoice</h2>
  <div style='width:120px;'></div>
</div>

<div class='client-info'>
  <p><strong>Client:</strong> {$clientName}</p>
  <p><strong>Address:</strong> {$clientAddress}</p>
  <p><strong>Bill Date:</strong> {$date}</p>
  <p><strong>Project Type:</strong> {$projectType}</p>
  <p><strong>Payment Type:</strong> {$paymentType}</p>
  <p><strong>Payment Mode:</strong> {$paymentMode}</p>
  <p><strong>Next Payment Date:</strong> {$nextPayment}</p>
</div>

<table>
  <tr><th>Description</th><td>{$description}</td></tr>
  <tr><th>Base Amount</th><td>₹{$amount}</td></tr>
  <tr><th>GST</th><td>" . ($row['apply_gst'] ? "₹{$gstAmount} ({$gst}%)" : "Not Applied") . "</td></tr>
  <tr><th>Total Amount</th><td><strong>₹{$total}</strong></td></tr>
</table>

<p class='total'>Grand Total: ₹{$total}</p>
";

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true); // for image
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("invoice_{$id}.pdf", ["Attachment" => false]);
?>
