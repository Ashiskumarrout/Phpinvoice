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
$clientName = htmlspecialchars($row['company_name']);
$clientAddress = nl2br(htmlspecialchars($row['address']));
$nextPayment = $row['next_payment_date'] ? date('d/m/Y', strtotime($row['next_payment_date'])) : 'N/A';
$paymentMode = htmlspecialchars($row['payment_mode'] ?? 'N/A');
$paymentType = htmlspecialchars($row['payment_type'] ?? 'N/A');

// Values
$estimatedValue = isset($row['estimated_value']) ? floatval($row['estimated_value']) : 0;
$gstRate = $row['apply_gst'] ? floatval($row['gst']) : 0;

// ✅ Process descriptions safely (split only on commas outside brackets)
$items = preg_split('/,(?![^\(]*\))/', $row['description']);

$subtotal = 0;
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

// Totals
$gstAmount = $gstRate > 0 ? ($subtotal * $gstRate / 100) : 0;
$grandTotal = $subtotal + $gstAmount;
$remaining = max($estimatedValue - $grandTotal, 0);

// Display
$estimatedDisplay = number_format($estimatedValue, 2);
$subtotalDisplay = number_format($subtotal, 2);
$gstDisplay = $gstRate > 0 ? number_format($gstAmount, 2) . " ({$gstRate}%)" : "Not Applied";
$grandTotalDisplay = number_format($grandTotal, 2);
$remainingDisplay = number_format($remaining, 2);

// Background image
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';

$html = "
<meta charset='UTF-8'>
<style>
  @page { margin: 25px; }
  body { font-family: Arial, sans-serif; color: #333; padding: 20px; font-size: 14px;
         background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; }
  .invoice-title { font-size: 30px; color: #0b0b6f; font-weight: bold; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  table.items th, table.items td { border-bottom: 2px solid red; padding: 10px; text-align: left; }
  table.items td:last-child, table.items th:last-child { text-align: right; }
  table.summary { width: 300px; float: right; margin-top: 20px; font-size: 14px; }
  .total-row { font-weight: bold; font-size: 16px; color: #000; }
  .footer-wrapper { clear: both; margin-top: 50px; }
  .thank { font-size: 38px; font-weight: bold; color: #0b0b6f; margin-bottom: 10px; margin-top: 150px; }
  .footer-header { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 5px; font-size: 14px; }
  .footer-header span { font-size: 14px; }
  .footer-header span:first-child { margin-right: 410px; }
  .footer-left { float: left; width: 50%; margin-top: 5px; font-size: 12px; }
  .footer-right { float: right; width: 45%; text-align: right; margin-top: 5px; font-size: 12px; }
  .footer-bottom { clear: both; text-align: center; font-size: 12px; margin-top: 20px; border-top: 2px solid red; padding-top: 8px; font-weight: bold; }
  .right-header { text-align: right; font-size: 14px; }
</style>

<div class='header'>
  <div>
    <div class='invoice-title'>INVOICE</div>
    <p><strong>SOFTECH 18</strong><br>GST - 21BVNPR0777G1ZD<br>CDA SECTOR 10<br>CUTTACK, ODISHA<br>753014</p>
  </div>
  <div class='right-header'>
    <p><strong>INVOICE #</strong> {$invoiceNo}<br><strong>DATE:</strong> {$invoiceDate}</p>
  </div>
</div>

<div>
  <p><strong>BILL TO:</strong><br>{$clientName}<br>{$clientAddress}</p>
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
  <tr><td>GST:</td><td style='text-align:right;'>{$gstDisplay}</td></tr>
  <tr><td>Grand Total:</td><td style='text-align:right;'>{$grandTotalDisplay}</td></tr>
  <tr class='total-row'><td>Remaining Amount:</td><td style='text-align:right;'>{$remainingDisplay}</td></tr>
</table>

<div style='margin-top:20px; font-size:14px; font-weight:bold;'>Next Renewal Date: {$nextPayment}</div>
<div style='margin-top:10px; font-size:14px; font-weight:bold;'>Payment Mode: {$paymentMode} | Payment Type: {$paymentType}</div>

<div class='footer-wrapper'>
  <div class='thank'>Thank You</div>
  <div class='footer-header'>
    <span>TERMS & CONDITIONS</span>
    <span>ACCOUNT DETAILS</span>
  </div>
  <div class='footer-left'>
    Payment is due within 2 days
  </div>
  <div class='footer-right'>
    Softech18<br>
    Ac- 243305002040<br>
    IFSC-ICIC0002433<br>
    ICICI Bank<br>
    College square branch<br>
    Cuttack
  </div>
  <div class='footer-bottom'>
    Contact us: +91 9937857561 | visit us: www.softech18.com
  </div>
</div>
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
