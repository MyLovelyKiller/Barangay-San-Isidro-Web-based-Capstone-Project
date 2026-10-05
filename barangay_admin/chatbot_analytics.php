<?php

session_start();

include 'config.php';
include 'session_time-out.php';

date_default_timezone_set('Asia/Manila');


/*
|--------------------------------------------------------------------------
| TOTAL CHATBOT QUESTIONS
|--------------------------------------------------------------------------
*/

$totalQuestions = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM chatbot_logs
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $totalQuestions = (int)$row['total'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| FAILED QUESTIONS
|--------------------------------------------------------------------------
*/

$totalFailed = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM chatbot_logs
    WHERE response_type = 'failed'
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $totalFailed = (int)$row['total'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| AI ANSWERS
|--------------------------------------------------------------------------
*/

$totalAI = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM chatbot_logs
    WHERE response_type = 'ai'
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $totalAI = (int)$row['total'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| FAQ USAGE
|--------------------------------------------------------------------------
*/

$totalFAQ = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM chatbot_logs
    WHERE response_type = 'faq'
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $totalFAQ = (int)$row['total'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| MOST FREQUENT FAQ
|--------------------------------------------------------------------------
*/

$faqStats = false;

$stmt = $conn->prepare("
    SELECT
        f.id,
        f.question,
        f.answer,
        COUNT(cl.id) AS usage_count

    FROM faq f

    LEFT JOIN chatbot_logs cl
        ON cl.matched_faq_id = f.id
        AND cl.response_type = 'faq'

    GROUP BY
        f.id,
        f.question,
        f.answer

    ORDER BY usage_count DESC
");

if ($stmt) {
    $stmt->execute();
    $faqStats = $stmt->get_result();
}


/*
|--------------------------------------------------------------------------
| FAILED QUESTIONS
|--------------------------------------------------------------------------
*/

$failedQuestions = false;

$stmt = $conn->prepare("
    SELECT
        id,
        user_question,
        created_at

    FROM chatbot_logs

    WHERE response_type = 'failed'

    ORDER BY created_at DESC

    LIMIT 100
");

if ($stmt) {
    $stmt->execute();
    $failedQuestions = $stmt->get_result();
}


/*
|--------------------------------------------------------------------------
| TOP FAILED QUESTIONS
|--------------------------------------------------------------------------
*/

$topFailed = false;

$stmt = $conn->prepare("
    SELECT
        user_question,
        COUNT(*) AS total

    FROM chatbot_logs

    WHERE response_type = 'failed'

    GROUP BY user_question

    ORDER BY total DESC

    LIMIT 20
");

if ($stmt) {
    $stmt->execute();
    $topFailed = $stmt->get_result();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Chatbot Analytics</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="style/main.css">
<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/sidebar.css">
<link rel="stylesheet" href="style/chatbot_analytics.css">

</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main-wrapper">

<?php include "header.php"; ?>

<div class="content">

<div class="container-fluid p-4">

    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">
                <i class="bi bi-robot"></i>
                Chatbot Analytics
            </h2>

            <p class="text-muted mb-0">
                Monitor frequently asked questions and chatbot failures.
            </p>

        </div>

    </div>


    <!-- STAT CARDS -->

    <div class="row g-4 mb-4">

        <div class="col-md-3">

            <div class="card stat-card">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">

                        <i class="bi bi-chat-dots"></i>

                    </div>

                    <div>

                        <small class="text-muted">
                            Total Questions
                        </small>

                        <h3 class="mb-0">
                            <?= number_format($totalQuestions) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card stat-card">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">

                        <i class="bi bi-question-circle"></i>

                    </div>

                    <div>

                        <small class="text-muted">
                            FAQ Questions
                        </small>

                        <h3 class="mb-0">
                            <?= number_format($totalFAQ) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card stat-card">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">

                        <i class="bi bi-stars"></i>

                    </div>

                    <div>

                        <small class="text-muted">
                            AI Answers
                        </small>

                        <h3 class="mb-0">
                            <?= number_format($totalAI) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card stat-card">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>

                    <div>

                        <small class="text-muted">
                            Failed Questions
                        </small>

                        <h3 class="mb-0">
                            <?= number_format($totalFailed) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- FAQ SECTION -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-1">

                <i class="bi bi-bar-chart-fill text-primary"></i>

                Frequently Asked Questions

            </h5>

            <small class="text-muted">

                Questions that are most frequently answered using the FAQ database.

            </small>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th width="70">
                            #
                        </th>

                        <th>
                            Frequently Asked Question
                        </th>

                        <th width="150">
                            Times Asked
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php

                    if (
                        $faqStats &&
                        $faqStats->num_rows > 0
                    ):

                        $number = 1;

                        while (
                            $faq = $faqStats->fetch_assoc()
                        ):

                    ?>

                    <tr>

                        <td>
                            <?= $number++ ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $faq['question'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </td>

                        <td>

                            <span class="badge bg-primary">

                                <?= number_format(
                                    $faq['usage_count']
                                ) ?>

                                times

                            </span>

                        </td>

                    </tr>

                    <?php

                        endwhile;

                    else:

                    ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted py-4"
                        >

                            No FAQ usage data yet.

                        </td>

                    </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- FAILED QUESTIONS -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-1">

                <i class="bi bi-exclamation-circle text-danger"></i>

                Questions the Chatbot Failed to Understand

            </h5>

            <small class="text-muted">

                Review these questions to identify new topics that should be added to the FAQ.

            </small>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th width="70">
                            #
                        </th>

                        <th>
                            Resident's Question
                        </th>

                        <th width="200">
                            Date & Time
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php

                    if (
                        $failedQuestions &&
                        $failedQuestions->num_rows > 0
                    ):

                        $number = 1;

                        while (
                            $failed = $failedQuestions->fetch_assoc()
                        ):

                    ?>

                    <tr>

                        <td>
                            <?= $number++ ?>
                        </td>

                        <td class="failed-question">

                            <span class="text-danger">

                                <i class="bi bi-question-circle"></i>

                            </span>

                            <?= htmlspecialchars(
                                $failed['user_question'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>

                        <td>

                            <?= date(
                                'M d, Y h:i A',
                                strtotime(
                                    $failed['created_at']
                                )
                            ) ?>

                        </td>

                    </tr>

                    <?php

                        endwhile;

                    else:

                    ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-success py-4"
                        >

                            <i class="bi bi-check-circle"></i>

                            No failed questions recorded.

                        </td>

                    </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- REPEATED FAILED QUESTIONS -->

    <div class="card dashboard-card">

        <div class="card-header bg-white py-3">

            <h5 class="mb-1">

                <i class="bi bi-repeat text-warning"></i>

                Most Repeated Unrecognized Questions

            </h5>

            <small class="text-muted">

                These questions may indicate that a new FAQ should be created.

            </small>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th width="70">
                            #
                        </th>

                        <th>
                            Question
                        </th>

                        <th width="150">
                            Occurrences
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php

                    if (
                        $topFailed &&
                        $topFailed->num_rows > 0
                    ):

                        $number = 1;

                        while (
                            $failedTop = $topFailed->fetch_assoc()
                        ):

                    ?>

                    <tr>

                        <td>
                            <?= $number++ ?>
                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $failedTop['user_question'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>

                        <td>

                            <span class="badge bg-warning text-dark">

                                <?= number_format(
                                    $failedTop['total']
                                ) ?>

                                occurrence(s)

                            </span>

                        </td>

                    </tr>

                    <?php

                        endwhile;

                    else:

                    ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted py-4"
                        >

                            No repeated failed questions yet.

                        </td>

                    </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>

<?php
$conn->close();
?>