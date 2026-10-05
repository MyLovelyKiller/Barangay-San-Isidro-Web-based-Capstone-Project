<?php
session_start();
include(__DIR__ . '/../includes/db_connect.php');

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

/* SAFE GET ID + PREPARED STATEMENT (SQL Injection Defense) */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$case = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $case = $res->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Summons<?php echo $case ? ' - ' . htmlspecialchars($case['case_no']) : ''; ?></title>

<style>

/* ============================================
   SCREEN VIEW — simulates a printed sheet of
   paper sitting on a neutral preview backdrop
============================================ */

* {
    box-sizing: border-box;
}

html, body {
    height: 100%;
}

body {
    font-family: "Times New Roman", Times, serif;
    margin: 0;
    background: #e9ecf1;
    color: #1a1a1a;
    padding: 40px 20px 60px;
}

.preview-toolbar {
    max-width: 850px;
    margin: 0 auto 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: Arial, Helvetica, sans-serif;
}

.preview-toolbar .label {
    font-size: 13px;
    color: #5a6472;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.print-btn-style,
.close-btn-style {
    font-family: Arial, Helvetica, sans-serif;
    padding: 10px 22px;
    cursor: pointer;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    letter-spacing: 0.3px;
    transition: all 0.2s ease;
}

.print-btn-style {
    background-color: #0a2351;
    color: white;
}

.print-btn-style:hover {
    background-color: #16305f;
    box-shadow: 0 4px 10px rgba(10, 35, 81, 0.3);
}

.close-btn-style {
    background-color: #ffffff;
    color: #0a2351;
    border: 1px solid #c7cede;
}

.close-btn-style:hover {
    background-color: #f1f3f8;
}

/* THE "PAPER" ITSELF */
.page {
    max-width: 850px;
    min-height: 1100px;
    margin: 0 auto;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), 0 2px 6px rgba(0, 0, 0, 0.08);
    border-radius: 2px;
    position: relative;
}

.document {
    width: 100%;
    padding: 70px 80px;
}

.header {
    text-align: center;
    line-height: 1.4;
}

.header p {
    margin: 2px 0;
    font-size: 15px;
}

.header h3 {
    margin: 4px 0;
    font-size: 17px;
    letter-spacing: 1px;
}

.header h4 {
    margin: 10px 0 0;
    font-size: 15px;
    font-weight: normal;
}

.case-info {
    display: flex;
    justify-content: space-between;
    margin-top: 45px;
    font-size: 15px;
}

.case-info .against {
    text-align: center;
    margin: 6px 0;
    font-style: italic;
}

.title {
    text-align: center;
    margin-top: 45px;
    letter-spacing: 6px;
    font-size: 20px;
    font-weight: bold;
    border-top: 1px solid #ccc;
    border-bottom: 1px solid #ccc;
    padding: 14px 0;
}

p {
    line-height: 1.7;
    font-size: 15px;
    text-align: justify;
}

.signature {
    margin-top: 70px;
    text-align: center;
    font-size: 15px;
}

.signature .sig-line {
    display: inline-block;
    border-top: 1px solid #333;
    padding-top: 6px;
    margin-top: 60px;
    min-width: 260px;
}

.watermark-tag {
    position: absolute;
    top: 30px;
    right: 40px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #b5bbc7;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* ============================================
   PRINT VIEW — strip away every "screen only"
   affordance so the physical printout is clean
============================================ */
@media print {
    body {
        background: #ffffff;
        padding: 0;
        margin: 0;
    }

    .preview-toolbar,
    .watermark-tag {
        display: none !important;
    }

    .page {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
        min-height: 0;
        margin: 0;
    }

    .document {
        padding: 0.5in 0.75in;
    }

    @page {
        size: letter;
        margin: 0.5in;
    }
}

</style>

</head>

<body>

<?php if (!$case): ?>

    <div class="preview-toolbar">
        <span class="label">Print Preview</span>
    </div>
    <div class="page">
        <div class="document">
            <h3 style="color:#c0392b; text-align:center;">Case not found.</h3>
        </div>
    </div>

<?php else: ?>

<div class="preview-toolbar">
    <span class="label">Print Preview &middot; Summons <?php echo htmlspecialchars($case['case_no']); ?></span>
    <div class="action-buttons">
        <button type="button" class="close-btn-style" onclick="window.close()">Close</button>
        <button type="button" class="print-btn-style" onclick="window.print()">Print Summons</button>
    </div>
</div>

<div class="page">
<div class="watermark-tag">Preview Only</div>
<div class="document">

<div class="header">

<p>Republic of the Philippines</p>
<h3>MUNICIPALITY OF CAINTA</h3>
<p>Province of Rizal</p>
<h3>BARANGAY SAN ISIDRO</h3>
<h4><u>OFFICE OF THE LUPONG TAGAPAMAYAPA</u></h4>

</div>


<div class="case-info">

<div>

<b><?php echo htmlspecialchars($case['complainant_name']); ?></b><br>
<i>Complainant/s</i>

<p class="against">- Against -</p>

<b><?php echo htmlspecialchars($case['respondent_name']); ?></b><br>
<i>Respondent/s</i>

</div>

<div style="text-align:right">

Barangay Case No. <b><?php echo htmlspecialchars($case['case_no']); ?></b><br>

For: <b><?php echo htmlspecialchars($case['case_type']); ?></b>

</div>

</div>


<h2 class="title">SUMMONS</h2>


<p>

TO: <b><?php echo htmlspecialchars($case['respondent_name']); ?></b><br>
Respondent/s

</p>


<p>

You are hereby summoned to appear before me in person, together with your witnesses, on

<b>
<?php
if (!empty($case['schedule_date']) && $case['schedule_date'] !== '0000-00-00') {
    echo date("jS \\of F, Y", strtotime($case['schedule_date']));
} else {
    echo "a date to be announced";
}
?>
</b>

at <b>9:00 o'clock</b> in the morning, then and there to answer to a complaint made before me, copy of which is attached hereto, for mediation/conciliation of your dispute with complainant/s.

</p>


<p>

You are hereby warned that if you refuse or willfully fail to appear in obedience to this summons, you may be barred from filing any counterclaim arising from said complaint.

<b>FAIL NOT</b> or else faces punishment as for contempt of court.

</p>


<p>

This <b><?php echo date("jS \\of F, Y"); ?></b>.

</p>


<div class="signature">

<div class="sig-line">
Punong Barangay / Pangkat Chairman
</div>

</div>

</div>
</div>

<?php endif; ?>

</body>
</html>
