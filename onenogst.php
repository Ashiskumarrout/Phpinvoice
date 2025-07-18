<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) exit('Unauthorized access');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Invalid invoice ID.");

// Fetch invoice and client
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

// Invoice info
$invoiceNo = htmlspecialchars($row['invoice_no'] ?: 'INV-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT));
$invoiceDate = date('d/m/Y', strtotime($row['bill_date']));
$clientName = "<strong>" . htmlspecialchars($row['company_name']) . "</strong>"; // Bold client name
$clientAddress = nl2br(htmlspecialchars($row['address']));
$nextPayment = $row['next_payment_date'] ? date('d/m/Y', strtotime($row['next_payment_date'])) : 'N/A';
$paymentMode = htmlspecialchars($row['payment_mode'] ?? 'N/A');
$paymentType = htmlspecialchars($row['payment_type'] ?? 'N/A');

// Values
$estimatedValue = isset($row['estimated_value']) ? floatval($row['estimated_value']) : 0;
$subtotal = 0;
$items = preg_split('/,(?![^\(]*\))/', $row['description']);
$itemRows = '';
foreach ($items as $item) {
    $item = trim($item);
    preg_match('/^(.*)\(([\d,.]+)\)$/', $item, $matches);
    $name = htmlspecialchars(trim($matches[1] ?? $item));
    $price = isset($matches[2]) ? floatval(str_replace(',', '', $matches[2])) : 0;
    $subtotal += $price;
    $itemRows .= "<tr>
                    <td>{$name}</td>
                    <td style='text-align:right;'>" . number_format($price, 2) . "</td>
                  </tr>";
}

$grandTotal = $subtotal;
$remaining = max($estimatedValue - $grandTotal, 0);

// Display values
$estimatedDisplay = number_format($estimatedValue, 2);
$subtotalDisplay = number_format($subtotal, 2);
$grandTotalDisplay = number_format($grandTotal, 2);
$remainingDisplay = number_format($remaining, 2);

// Background image
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';

$html = "
<meta charset='UTF-8'>
<style>
  @page { margin: 25px; }
  body { font-family: Arial, sans-serif; color: #333; padding: 20px;
         background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; }
  .invoice-title { font-size: 30px; color: #0b0b6f; font-weight: bold; }
  .right-header { text-align: right; font-size: 14px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  table.items th, table.items td { border-bottom: 2px solid red; padding: 10px; text-align: left; }
  table.items td:last-child, table.items th:last-child { text-align: right; }
  table.summary { width: 300px; float: right; margin-top: 20px; font-size: 14px; }
  .total-row { font-weight: bold; font-size: 16px; color: #000; }
</style>

<div class='header'>
  <div>
    <div class='invoice-title'>INVOICE</div>
    <p><strong>SOFTECH 18</strong><br>CDA SECTOR 10<br>CUTTACK, ODISHA<br>753014</p>
  </div>
  <div class='right-header'>
    <p><strong>INVOICE #</strong> {$invoiceNo}<br><strong>DATE:</strong> {$invoiceDate}</p>
  </div>
</div>

<div>
  <p><strong>BILL TO:</strong><br>{$clientName}<br>{$clientAddress}</p>
</div>

<div style='margin-top:15px; font-size:14px; font-weight:bold;'>
  Next Renewal Date: {$nextPayment}<br>
  Payment Mode: {$paymentMode} | Payment Type: {$paymentType}
</div>

<table class='items'>
  <thead>
    <tr><th>DESCRIPTION</th><th>AMOUNT</th></tr>
  </thead>
  <tbody>{$itemRows}</tbody>
</table>

<table class='summary'>
  <tr><td>Estimated Project Value:</td><td style='text-align:right;'>{$estimatedDisplay}</td></tr>
  <tr><td>Subtotal:</td><td style='text-align:right;'>{$subtotalDisplay}</td></tr>
  <tr><td>Grand Total:</td><td style='text-align:right;'>{$grandTotalDisplay}</td></tr>
  <tr class='total-row'><td>Remaining Amount:</td><td style='text-align:right;'>{$remainingDisplay}</td></tr>
</table>
";

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice_{$id}.pdf", ["Attachment" => false]);
?>
