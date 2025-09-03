<?php
//  Step 1: Your Filestack API key
$filestack_key = "A3xfbwhHOSe25uFyU1V9Lz"; // replace with actual key

$error       = null;
$uploadLink  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error code: " . $_FILES['pdf_file']['error'];
    }
    elseif ($_FILES['pdf_file']['type'] !== 'application/pdf') {
        $error = "❌ Please upload only PDF files.";
    }
    else {
        $tmpPath = $_FILES['pdf_file']['tmp_name'];
        $fileName = $_FILES['pdf_file']['name'];

        // Filestack upload endpoint
        $endpoint = "https://www.filestackapi.com/api/store/S3?key={$filestack_key}&filename=" . urlencode($fileName);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/pdf']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tmpPath));

        $resp = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($resp, true);
        if (isset($json['url'])) {
            $uploadLink = $json['url'];
        } else {
            $error = "Upload failed:<br><pre>" . htmlspecialchars($resp) . "</pre>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload PDF via Filestack</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 30px; }
    form { margin-bottom: 20px; }
    pre { background: #f9f9f9; padding: 10px; }
    a { color: #0066cc; }
  </style>
</head>
<body>
  <h2>🏞 Upload PDF to Filestack</h2>

  <?php if ($error): ?>
    <p style="color: red;"><?= $error ?></p>
  <?php elseif ($uploadLink): ?>
    <p style="color: green;">✅ PDF uploaded successfully!</p>
    <p><strong>File URL:</strong><br>
      <a href="<?= htmlspecialchars($uploadLink) ?>" target="_blank">
        <?= htmlspecialchars($uploadLink) ?>
      </a>
    </p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <label for="pdf_file">Select PDF File:</label><br>
    <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf" required>
    <br><br>
    <button type="submit">Upload PDF</button>
  </form>
</body>
</html>
