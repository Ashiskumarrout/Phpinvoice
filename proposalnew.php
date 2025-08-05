<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';

// Fetch clients
$clients = $conn->query("SELECT id, company_name, gst_number, address FROM clients");

// Create proposals table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_address TEXT,
    client_gst VARCHAR(50),
    website_details TEXT,
    website_price DECIMAL(10,2),
    social_details TEXT,
    monthly_price DECIMAL(10,2),
    quarterly_price DECIMAL(10,2),
    half_yearly_price DECIMAL(10,2),
    yearly_price DECIMAL(10,2),
    proposal_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTableQuery);

// Handle PDF download for existing proposal
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $proposalId = intval($_GET['download']);
    $stmt = $conn->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmt->bind_param("i", $proposalId);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    
    if ($proposal) {
        generatePDF($proposal);
        exit;
    }
}

// Handle editing existing proposal
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// Function to generate PDF
function generatePDF($data) {
    $clientName = $data['client_name'];
    $clientAddress = $data['client_address'];
    $clientGST = $data['client_gst'] ?? '';
    $websiteDetails = $data['website_details'];
    $websitePrice = $data['website_price'];
    $socialDetails = $data['social_details'];
    $monthly = $data['monthly_price'];
    $quarterly = $data['quarterly_price'];
    $halfYearly = $data['half_yearly_price'];
    $yearly = $data['yearly_price'];

    // Process website details into list
    $webList = '';
    if (!empty($websiteDetails)) {
        $webItems = explode("\n", $websiteDetails);
        foreach ($webItems as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $webList .= "<li>" . htmlspecialchars($item) . "</li>";
            }
        }
        $webList = "<ul>{$webList}</ul>";
    }

    // Process social media details into list
    $socialList = '';
    if (!empty($socialDetails)) {
        $socialItems = explode("\n", $socialDetails);
        foreach ($socialItems as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $socialList .= "<li>" . htmlspecialchars($item) . "</li>";
            }
        }
        $socialList = "<ul>{$socialList}</ul>";
    }

    // Background image
    $bgPath = 'logo2.jpg';
    $backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';
    $bgStyle = $backgroundImage ? "background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover;" : '';

    // Build HTML for PDF
    $html = "
    <meta charset='UTF-8'>
    <style>
      @page { margin: 25px; }
      body { font-family: Arial, sans-serif; color: #333; padding: 20px; {$bgStyle} }
      .header { display: flex; justify-content: space-between; align-items: flex-start; }
      .company-info { font-size: 18px; }
      .company-info strong { font-size: 20px; }
      .quotation-title { font-size: 36px; color: #0b0b6f; font-weight: bold; }
      .right-header { text-align: right; font-size: 18px; }
      .bill-to { margin-top: 20px; }
      .bill-to strong { font-size: 20px; }
      table.items { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px; 
        font-size: 18px;
        border: 2px solid #333;
      }
      table.items th { 
        border: 1px solid #333; 
        padding: 12px; 
        text-align: center; 
        background-color: #f8f9fa; 
        font-weight: bold; 
        font-size: 20px;
        color: #333;
      }
      table.items td { 
        border: 1px solid #333; 
        padding: 10px; 
        text-align: left; 
        vertical-align: top;
        font-size: 18px;
        line-height: 1.4;
      }
      .service-title { 
        font-weight: bold; 
        color: #7F00FF; 
        font-size: 20px;
        display: block;
        margin-bottom: 8px;
      }
      .price-cell {
        text-align: center !important;
        font-weight: bold;
        font-size: 19px;
        color: #333;
      }
      ul { 
        margin: 8px 0; 
        padding-left: 20px; 
      }
      li { 
        font-size: 17px; 
        margin: 4px 0; 
        line-height: 1.3;
      }
    </style>

    <div class='header' style='margin-top: 130px;'>
      <div class='company-info'>
      </div>
      <div class='right-header'>
        <p>
            <strong>QUOTATION DATE:</strong> " . (isset($data['proposal_date']) ? date('d/m/Y', strtotime($data['proposal_date'])) : date('d/m/Y')) . "<br>
            <strong>VALID UNTIL:</strong> " . (isset($data['valid_until']) ? date('d/m/Y', strtotime($data['valid_until'])) : date('d/m/Y', strtotime('+30 days'))) . "
        </p>
      </div>
    </div>

    <div class='bill-to'>
      <p><strong>QUOTATION TO:</strong><br>
      <strong>" . htmlspecialchars($clientName) . "</strong><br>
      " . nl2br(htmlspecialchars($clientAddress)) . "</p>
    </div>

    <table class='items'>
      <thead>
        <tr>
          <th style='width:70%;'>SERVICE DETAILS</th>
          <th style='width:30%;'>PRICE</th>
        </tr>
      </thead>
      <tbody>";
      
    // Website Design Section (Optional)
    if (!empty($websiteDetails) || !empty($websitePrice)) {
        $html .= "
        <tr>
          <td>
            <span class='service-title'>Website Design</span>
            {$webList}
          </td>
          <td class='price-cell'>Rs – " . htmlspecialchars($websitePrice ?: '0') . "</td>
        </tr>";
    }
    
    // Social Media Marketing Section (Optional)
    if (!empty($socialDetails) || !empty($monthly) || !empty($quarterly) || !empty($halfYearly) || !empty($yearly)) {
        $priceText = '';
        if (!empty($monthly)) $priceText .= "Rs – " . htmlspecialchars($monthly) . " Monthly<br>";
        if (!empty($quarterly)) $priceText .= "Or<br>Rs – " . htmlspecialchars($quarterly) . " Quarterly<br>";
        if (!empty($halfYearly)) $priceText .= "Or<br>Rs – " . htmlspecialchars($halfYearly) . " Half-Yearly<br>";
        if (!empty($yearly)) $priceText .= "Or<br>Rs – " . htmlspecialchars($yearly) . " Yearly<br>";
        
        // If no price is entered, show default message
        if (empty($priceText)) {
            $priceText = "Contact for Pricing";
        }
        
        $html .= "
        <tr>
          <td>
            <span class='service-title'>Social Media Marketing</span>
            {$socialList}
          </td>
          <td class='price-cell'>{$priceText}</td>
        </tr>";
    }
    
    // Background image for second page
    $bgPath2 = 'logo3.jpg';
    $backgroundImage2 = file_exists($bgPath2) ? base64_encode(file_get_contents($bgPath2)) : '';
    $bgStyle2 = $backgroundImage2 ? "background: url('data:image/jpeg;base64,{$backgroundImage2}') no-repeat center center; background-size: cover;" : '';

    $html .= "
      </tbody>
    </table>
    
    <!-- Second Page - Terms and Conditions -->
    <div style='page-break-before: always; padding-top: 20px; {$bgStyle2}'>
      <h2 style='color: #0b0b6f; font-size: 18px; text-align: center; margin-top: 78px; margin-bottom: 15px; font-weight: bold;'>TERMS FOR PAYMENT</h2>
      ";
      
    // Only show Website Design terms if website services are included
    if (!empty($websiteDetails) || !empty($websitePrice)) {
        $html .= "
        <div style='margin-bottom: 15px;'>
          <h3 style='color: #7F00FF; font-size: 17px; margin-bottom: 8px; font-weight: bold;'>Website Design:</h3>
          <p style='font-size: 15px; line-height: 1.3; margin-bottom: 5px;'>60% will be received as advance, 20% after showing the website and the rest 20% after deploying on the domain.</p>
        </div>";
    }
    
    // Only show Social Media Marketing terms if social media services are included
    if (!empty($socialDetails) || !empty($monthly) || !empty($quarterly) || !empty($halfYearly) || !empty($yearly)) {
        $html .= "
        <div style='margin-bottom: 15px;'>
          <h3 style='color: #7F00FF; font-size: 17px; margin-bottom: 8px; font-weight: bold;'>Social Media Marketing:</h3>
          <p style='font-size: 15px; line-height: 1.3; margin-bottom: 5px;'>Payment of 3 months will be received as advance.</p>
        </div>";
    }
    
    // Only show dispute clause if any services are included
    if ((!empty($websiteDetails) || !empty($websitePrice)) || (!empty($socialDetails) || !empty($monthly) || !empty($quarterly) || !empty($halfYearly) || !empty($yearly))) {
        $html .= "
        <div style='margin-bottom: 15px; padding: 8px;'>
          <p style='font-size: 12px; font-style: italic; margin: 0; color: #333;'>*Any dispute regarding the invoice must be raised within 7 days of receipt. No claims will be entertained after this period.</p>
        </div>";
    }
    
    $html .= "
      
      <div style='margin-bottom: 18px;'>
        <h3 style='color: #0b0b6f; font-size: 18px; margin-bottom: 10px; font-weight: bold; text-align: center;'>BANK DETAILS</h3>
        <div style='padding: 10px;'>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>Account Name:</strong> SOFTECH18</p>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>Account Number:</strong> 243305002040</p>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>IFSC Code:</strong> ICIC0002433</p>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>Bank:</strong> ICICI BANK SQUARE BRANCH CUTTACK</p>
        </div>
      </div>
      
      <div style='margin-bottom: 18px;'>
        <h3 style='color: #0b0b6f; font-size: 18px; margin-bottom: 10px; font-weight: bold; text-align: center;'>UPI DETAILS</h3>
        <div style='padding: 10px;'>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>Mobile Number:</strong> 9438801054</p>
          <p style='font-size: 15px; margin: 4px 0; line-height: 1.3;'><strong>UPI ID Name:</strong> Satyaranjan Rout</p>
        </div>
      </div>
      
      <div style='text-align: center; margin-top: 20px;'>
        <h3 style='color: #7F00FF; font-size: 17px; margin-bottom: 15px; font-weight: bold;'>Thank You</h3>
        
        <div style='margin-top: 20px;'>
          <p style='font-size: 15px; margin: 3px 0; font-weight: bold;'>With Regards</p>
          <p style='font-size: 16px; margin: 6px 0; font-weight: bold; color: #0b0b6f;'>Anil Kumar Jena</p>
          <p style='font-size: 14px; margin: 3px 0;'>Manager Softech18</p>
          <p style='font-size: 14px; margin: 3px 0; font-weight: bold;'>7008826091</p>
        </div>
        
        <div style='margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;'>
          <h4 style='color: #0b0b6f; font-size: 15px; margin-bottom: 12px; font-weight: bold;'>Follow Us</h4>
          <div style='text-align: center;'>
            <a href='https://www.facebook.com/supportsoftech18/' style='text-decoration: none; margin: 0 6px; color: #1877F2; font-size: 13px; font-weight: bold; display: inline-block; padding: 5px 8px; border: 1px solid #1877F2; border-radius: 4px;'>Facebook</a>
            <a href='https://www.instagram.com/softech18/' style='text-decoration: none; margin: 0 6px; color: #E4405F; font-size: 13px; font-weight: bold; display: inline-block; padding: 5px 8px; border: 1px solid #E4405F; border-radius: 4px;'>Instagram</a>
            <a href='https://www.linkedin.com/company/softech-18/posts/?feedView=all' style='text-decoration: none; margin: 0 6px; color: #0A66C2; font-size: 13px; font-weight: bold; display: inline-block; padding: 5px 8px; border: 1px solid #0A66C2; border-radius: 4px;'>LinkedIn</a>
            <a href='https://in.pinterest.com/softechone8/softech-18/' style='text-decoration: none; margin: 0 6px; color: #BD081C; font-size: 13px; font-weight: bold; display: inline-block; padding: 5px 8px; border: 1px solid #BD081C; border-radius: 4px;'>Pinterest</a>
          </div>
        </div>
      </div>
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
    $filename = "proposal_" . (isset($data['id']) ? $data['id'] . "_" : "") . date('Y-m-d') . ".pdf";
    $dompdf->stream($filename, ["Attachment" => false]);
}

// Handle PDF generation and saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf'])) {
    $clientName = $_POST['client_name'];
    $clientAddress = $_POST['client_address'];
    $clientGST = $_POST['client_gst'] ?? '';
    $websiteDetails = $_POST['website_details'];
    $websitePrice = $_POST['website_price'];
    $socialDetails = $_POST['social_details'];
    $monthly = $_POST['monthly'];
    $quarterly = $_POST['quarterly'];
    $halfYearly = $_POST['half_yearly'];
    $yearly = $_POST['yearly'];
    $clientId = $_POST['client_id'];

    // Save to database
    $proposalDate = date('Y-m-d');
    $validUntil = date('Y-m-d', strtotime('+30 days'));
    
    if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
        // Update existing proposal
        $editId = intval($_POST['edit_id']);
        $stmt = $conn->prepare("UPDATE proposals SET client_id=?, client_name=?, client_address=?, client_gst=?, website_details=?, website_price=?, social_details=?, monthly_price=?, quarterly_price=?, half_yearly_price=?, yearly_price=? WHERE id=?");
        $stmt->bind_param("issssdsddddi", $clientId, $clientName, $clientAddress, $clientGST, $websiteDetails, $websitePrice, $socialDetails, $monthly, $quarterly, $halfYearly, $yearly, $editId);
        $stmt->execute();
        $proposalId = $editId;
    } else {
        // Insert new proposal
        $stmt = $conn->prepare("INSERT INTO proposals (client_id, client_name, client_address, client_gst, website_details, website_price, social_details, monthly_price, quarterly_price, half_yearly_price, yearly_price, proposal_date, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssdsddddss", $clientId, $clientName, $clientAddress, $clientGST, $websiteDetails, $websitePrice, $socialDetails, $monthly, $quarterly, $halfYearly, $yearly, $proposalDate, $validUntil);
        $stmt->execute();
        $proposalId = $conn->insert_id;
    }

    // Generate PDF with the form data
    $pdfData = [
        'id' => $proposalId,
        'client_name' => $clientName,
        'client_address' => $clientAddress,
        'client_gst' => $clientGST,
        'website_details' => $websiteDetails,
        'website_price' => $websitePrice,
        'social_details' => $socialDetails,
        'monthly_price' => $monthly,
        'quarterly_price' => $quarterly,
        'half_yearly_price' => $halfYearly,
        'yearly_price' => $yearly,
        'proposal_date' => $proposalDate,
        'valid_until' => $validUntil
    ];
    
    generatePDF($pdfData);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $editData ? 'Edit Proposal' : 'Quotation Generator' ?></title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      background: #f0f2f5;
      padding: 20px;
      font-size: 14px;
    }
    
    .form-container {
      background: white;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
      max-width: 900px;
      margin: auto;
    }
    
    h2 {
      text-align: center;
      color: #7F00FF;
      font-weight: bold;
      margin-bottom: 30px;
    }
    
    label {
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
      display: block;
      font-size: 16px;
    }
    
    input, textarea, select {
      width: 100%;
      padding: 12px;
      margin: 8px 0 15px 0;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 16px;
      transition: 0.3s;
      box-sizing: border-box;
    }
    
    input:focus, textarea:focus, select:focus {
      border-color: #7F00FF;
      box-shadow: 0 0 8px rgba(127, 0, 255, 0.2);
      outline: none;
    }
    
    .btn {
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white;
      border: none;
      padding: 12px 20px;
      font-weight: bold;
      border-radius: 8px;
      margin: 10px 5px;
      font-size: 14px;
      cursor: pointer;
      transition: 0.3s;
      width: 48%;
      display: inline-block;
      text-decoration: none;
      text-align: center;
    }
    
    .btn:hover {
      transform: scale(1.02);
      box-shadow: 0 4px 15px rgba(127, 0, 255, 0.3);
    }
    
    .btn-secondary {
      background: linear-gradient(135deg, #6c757d, #495057);
    }
    
    .service-section {
      background: #fafafa;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
      margin: 15px 0;
    }
    
    .service-section h3 {
      color: #7F00FF;
      margin-bottom: 10px;
    }
    
    #bill-to-box {
      background: #fafafa;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    #bill-to-box strong {
      display: block;
      margin-bottom: 10px;
      font-size: 18px;
      color: #333;
    }
    
    #bill-to-box p {
      font-size: 16px;
      margin: 8px 0;
    }
    
    .button-group {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
  </style>
</head>
<body>

<div class="form-container">
  <h2><?= $editData ? '✏️ Edit Proposal' : '📋 Quotation Generator' ?></h2>

  <form method="post">
    <?php if ($editData): ?>
      <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
    <?php endif; ?>
    
    <div class="service-section">
      <label>Client:</label>
      <select id="client_id" name="client_id" onchange="updateBillTo()" required>
        <option value="">Select Client</option>
        <?php
        $clients = $conn->query("SELECT id, company_name, gst_number, address FROM clients");
        while($row = $clients->fetch_assoc()) {
            $selected = ($editData && $editData['client_id'] == $row['id']) ? 'selected' : '';
            echo "<option value='{$row['id']}' data-company='{$row['company_name']}' data-gst='{$row['gst_number']}' data-address='{$row['address']}' {$selected}>{$row['company_name']}</option>";
        }
        ?>
      </select>
      
      <div id="bill-to-box">
        <strong>QUOTATION TO:</strong>
        <p id="bill-company"><?= $editData ? htmlspecialchars($editData['client_name']) : '--' ?></p>
        <p><strong>GST:</strong> <span id="bill-gst"><?= $editData ? htmlspecialchars($editData['client_gst'] ?: '--') : '--' ?></span></p>
        <p id="bill-address"><?= $editData ? nl2br(htmlspecialchars($editData['client_address'])) : '--' ?></p>
      </div>
      
      <input type="hidden" id="clientName" name="client_name" value="<?= $editData ? htmlspecialchars($editData['client_name']) : '' ?>">
      <input type="hidden" id="clientAddress" name="client_address" value="<?= $editData ? htmlspecialchars($editData['client_address']) : '' ?>">
      <input type="hidden" id="clientGST" name="client_gst" value="<?= $editData ? htmlspecialchars($editData['client_gst']) : '' ?>">
    </div>

    <div class="service-section">
      <h3>🌐 Website Design (Optional)</h3>
      <label>Website Service Details:</label>
      <textarea name="website_details" rows="3" placeholder="List each service in a new line (e.g. Up to 7 pages web design...)"><?= $editData ? htmlspecialchars($editData['website_details']) : '' ?></textarea>
      
      <label>Website Design Price (₹):</label>
      <input type="text" name="website_price" placeholder="11000" value="<?= $editData ? $editData['website_price'] : '' ?>">
    </div>

    <div class="service-section">
      <h3>📱 Social Media Marketing (Optional)</h3>
      <label>Social Media Details:</label>
      <textarea name="social_details" rows="3" placeholder="List each point in a new line (e.g. Weekly 3 videos...)"><?= $editData ? htmlspecialchars($editData['social_details']) : '' ?></textarea>

      <label>Price (Monthly):</label>
      <input type="text" name="monthly" placeholder="3500" value="<?= $editData ? $editData['monthly_price'] : '' ?>">

      <label>Price (Quarterly):</label>
      <input type="text" name="quarterly" placeholder="9500" value="<?= $editData ? $editData['quarterly_price'] : '' ?>">

      <label>Price (Half-Yearly):</label>
      <input type="text" name="half_yearly" placeholder="18000" value="<?= $editData ? $editData['half_yearly_price'] : '' ?>">

      <label>Price (Yearly):</label>
      <input type="text" name="yearly" placeholder="34000" value="<?= $editData ? $editData['yearly_price'] : '' ?>">
    </div>

    <div class="button-group">
      <button type="submit" name="generate_pdf" class="btn">💾 <?= $editData ? 'Update & Download PDF' : 'Generate & Download PDF' ?></button>
      <a href="proposal-dashboard.php" class="btn btn-secondary">📊 View Dashboard</a>
    </div>
  </form>
</div>

  <script>
    function updateBillTo() {
      let select = document.getElementById('client_id');
      let company = select.options[select.selectedIndex].getAttribute('data-company') || '--';
      let gst = select.options[select.selectedIndex].getAttribute('data-gst') || '--';
      let address = select.options[select.selectedIndex].getAttribute('data-address') || '--';
      
      document.getElementById('bill-company').innerText = company;
      document.getElementById('bill-gst').innerText = gst;
      document.getElementById('bill-address').innerText = address;
      
      // Auto-fill the hidden form fields when client is selected
      if (company !== '--') {
        document.getElementById('clientName').value = company;
        document.getElementById('clientAddress').value = address;
        document.getElementById('clientGST').value = gst;
      }
    }
    
    // Initialize form if editing
    <?php if ($editData): ?>
    document.addEventListener('DOMContentLoaded', function() {
      updateBillTo();
    });
    <?php endif; ?>
  </script>

</body>
</html>
