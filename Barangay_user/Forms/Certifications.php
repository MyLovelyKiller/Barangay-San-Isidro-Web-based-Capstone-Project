<?php
require_once __DIR__ . '/../../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

/* ============================================================
   CSRF TOKEN
   ============================================================ */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

include "../../BACKEND/db_connect.php";

/* ============================================================
   CHECK IF USER IS LOGGED IN
   ============================================================ */

if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* ============================================================
   GET USER INFO
   ============================================================ */

if (isset($_SESSION['resident_id'])) {

    $resident_id = $_SESSION['resident_id'];

} else {

    $username = $_SESSION['username'];

    $stmt = $conn->prepare("
        SELECT resident_id
        FROM residents
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Resident account not found.");
    }

    $row = $result->fetch_assoc();

    $resident_id = $row['resident_id'];

    $stmt->close();
}

/* ============================================================
   GET RESIDENT INFORMATION
   ============================================================ */

$stmt = $conn->prepare("
    SELECT *
    FROM residents
    WHERE resident_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$resident_result = $stmt->get_result();

if ($resident_result->num_rows === 0) {
    die("Resident information not found.");
}

$resident = $resident_result->fetch_assoc();

$stmt->close();

/* ============================================================
   GET CERTIFICATION DOCUMENT TYPES
   ============================================================ */

$category = "Certification";

$stmt = $conn->prepare("
    SELECT *
    FROM document_types
    WHERE category = ?
    ORDER BY document_name ASC
");

$stmt->bind_param("s", $category);
$stmt->execute();

$document_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Certifications</title>

    <!-- ========================================================
         GOOGLE reCAPTCHA ENTERPRISE
         ======================================================== -->

    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>

    <!-- ========================================================
         YOUR EXISTING CSS / JS FILES
         ======================================================== -->

</head>

<body>

<form
    action="../Backend/submit_request.php"
    method="POST"
    enctype="multipart/form-data"
    id="documentRequestForm"
>

    <!-- ============================================================
         EXISTING HIDDEN FIELDS
         ============================================================ -->

    <input
        type="hidden"
        name="g-recaptcha-response"
        id="g-recaptcha-response"
    >

    <!-- ============================================================
         CSRF PROTECTION
         ============================================================ -->

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
    >

    <input
        type="hidden"
        name="form_file"
        value="Certifications.php"
    >

    <input
        type="hidden"
        name="resident_id"
        value="<?php echo htmlspecialchars($resident_id); ?>"
    >


    <!-- ============================================================
         DOCUMENT TYPE
         ============================================================ -->

    <label for="document_type_id">
        Certification Type
    </label>

    <select
        name="document_type_id"
        id="document_type_id"
        required
    >

        <option value="">
            -- Select Certification --
        </option>

        <?php while ($document = $document_result->fetch_assoc()): ?>

            <option
                value="<?php echo htmlspecialchars($document['document_type_id']); ?>"
            >
                <?php echo htmlspecialchars($document['document_name']); ?>
            </option>

        <?php endwhile; ?>

    </select>


    <!-- ============================================================
         PURPOSE
         ============================================================ -->

    <label for="purpose">
        Purpose
    </label>

    <textarea
        name="purpose"
        id="purpose"
        required
    ></textarea>


    <!-- ============================================================
         PHONE
         ============================================================ -->

    <label for="phone">
        Phone Number
    </label>

    <input
        type="text"
        name="phone"
        id="phone"
        value="<?php echo htmlspecialchars($resident['contact_number'] ?? ''); ?>"
        required
    >


    <!-- ============================================================
         EMAIL
         ============================================================ -->

    <label for="email">
        Email
    </label>

    <input
        type="email"
        name="email"
        id="email"
        value="<?php echo htmlspecialchars($resident['email'] ?? ''); ?>"
        required
    >


    <!-- ============================================================
         ADDRESS
         ============================================================ -->

    <label for="address">
        Address
    </label>

    <textarea
        name="address"
        id="address"
        required
    ><?php echo htmlspecialchars($resident['address'] ?? ''); ?></textarea>


    <!-- ============================================================
         ATTACHMENTS
         ============================================================ -->

    <label for="attachment">
        Supporting Document / Attachment
    </label>

    <input
        type="file"
        name="attachment[]"
        id="attachment"
        multiple
        accept=".png,.jpg,.jpeg,.pdf,.doc,.docx"
    >


    <!-- ============================================================
         PAYMENT METHOD
         ============================================================ -->

    <h3>
        Payment Method
    </h3>

    <label>

        <input
            type="radio"
            name="payment_method"
            value="GCash"
            id="paymentGCash"
        >

        GCash

    </label>

    <label>

        <input
            type="radio"
            name="payment_method"
            value="Walk-in"
            id="paymentWalkin"
            checked
        >

        Walk-in

    </label>


    <!-- ============================================================
         GCASH PAYMENT DETAILS
         ============================================================ -->

    <div
        id="gcashPaymentSection"
        style="display:none;"
    >

        <label for="gcash_receipt">
            GCash Receipt
        </label>

        <input
            type="file"
            name="gcash_receipt"
            id="gcash_receipt"
            accept=".png,.jpeg,.jpg,.doc,.docx,.pdf"
        >

        <label for="gcash_reference">
            GCash Reference Number
        </label>

        <input
            type="text"
            name="gcash_reference"
            id="gcash_reference"
        >

    </div>


    <!-- ============================================================
         DIGITAL SIGNATURE
         ============================================================ -->

    <h3>
        Digital Signature
    </h3>

    <canvas
        id="signatureCanvas"
        width="500"
        height="200"
        style="border:1px solid #000;"
    ></canvas>

    <input
        type="hidden"
        name="signature_data"
        id="signature_data"
    >

    <br>

    <label for="signature_file">
        Or Upload Signature
    </label>

    <input
        type="file"
        name="signature_file"
        id="signature_file"
        accept=".png,.jpg,.jpeg"
    >


    <!-- ============================================================
         SUBMIT BUTTON
         ============================================================ -->

    <button
        type="submit"
        id="submitRequestButton"
    >
        Submit Request
    </button>

</form>

</body>

</html>