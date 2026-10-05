<?php

require_once __DIR__ . '/config.php';

$conn = new mysqli("localhost", "root", "", "barangay_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Manila');

$originalMessage = trim($_POST['message'] ?? '');
$userMessage = strtolower($originalMessage);

/* CLEAN TEXT */
$userMessage = preg_replace('/[^\w\s]/', '', $userMessage);

/*
|--------------------------------------------------------------------------
| CHATBOT LOG FUNCTION
|--------------------------------------------------------------------------
*/
function logChatbot(
    $conn,
    $question,
    $faqId = null,
    $responseType = 'failed'
) {
    $stmt = $conn->prepare("
        INSERT INTO chatbot_logs
        (
            user_question,
            matched_faq_id,
            response_type
        )
        VALUES (?, ?, ?)
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "sis",
        $question,
        $faqId,
        $responseType
    );

    $stmt->execute();
    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| GREETINGS
|--------------------------------------------------------------------------
*/

$greetings = ["hello", "hi", "hey"];

if (in_array($userMessage, $greetings)) {

    $response = "Hello! I am the Barangay Assistant. How can I help you today?";

    logChatbot(
        $conn,
        $originalMessage,
        null,
        'ai'
    );

    echo $response;

    $conn->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| VAGUE QUESTIONS
|--------------------------------------------------------------------------
*/

$vagueMessages = [
    "why",
    "how",
    "what",
    "where",
    "when",
    "who",
    "help",
    "huh",
    "ha",
    "ok"
];

if (in_array($userMessage, $vagueMessages)) {

    $response =
        "I'd be happy to help. Could you please tell me more about your question?";

    logChatbot(
        $conn,
        $originalMessage,
        null,
        'failed'
    );

    echo $response;

    $conn->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| VERY SHORT MESSAGE
|--------------------------------------------------------------------------
*/

if (strlen($userMessage) < 4) {

    $response = "Could you please clarify your question?";

    logChatbot(
        $conn,
        $originalMessage,
        null,
        'failed'
    );

    echo $response;

    $conn->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| COMMON TYPO CORRECTION
|--------------------------------------------------------------------------
*/

$typoMap = [

    "clearnce" => "clearance",
    "clrance" => "clearance",
    "clearence" => "clearance",

    "certficate" => "certificate",
    "certifcate" => "certificate",

    "offce" => "office",
    "offic" => "office",

    "houres" => "hours",

    "emergncy" => "emergency",

    "hotine" => "hotline",

    "contct" => "contact",

    "locaton" => "location",

    "servces" => "services",

    "incdent" => "incident",

    "complan" => "complaint",

    "schlarship" => "scholarship",

    "financial" => "financial",

    "medcal" => "medical"
];

$words = explode(" ", $userMessage);

$correctedWords = [];

foreach ($words as $word) {

    if (isset($typoMap[$word])) {

        $correctedWords[] = $typoMap[$word];

    } else {

        $correctedWords[] = $word;
    }
}

$userMessage = implode(" ", $correctedWords);


$response = "";
$bestMatch = 0;
$bestAnswer = "";
$bestFaqId = null;


/*
|--------------------------------------------------------------------------
| INTENT KEYWORDS
|--------------------------------------------------------------------------
*/

$intents = [

    "certificate fee" => [
        "fee for certificate",
        "certificate fee",
        "how much",
        "price",
        "cost",
        "fee",
        "pay"
    ],

    "clearance requirements" => [
        "requirements for barangay clearance",
        "documents needed for barangay clearance",
        "clearance requirements",
        "valid ids"
    ],

    "clearance validity" => [
        "validity",
        "clearance valid",
        "expiration",
        "how long is barangay clearance valid"
    ],

    "clearance behalf" => [
        "on my behalf",
        "behalf",
        "someone request clearance",
        "another person request"
    ],

    "hours" => [
        "hours",
        "time",
        "open",
        "schedule",
        "office hours"
    ],

    "incident" => [
        "incident",
        "report",
        "complaint",
        "complain"
    ],

    "contact" => [
        "contact",
        "phone",
        "number",
        "email",
        "call"
    ],

    "steps" => [
        "steps",
        "procedure",
        "process",
        "how to"
    ],

    "bot identity" => [
        "who are you",
        "what are you",
        "chatbot",
        "assistant",
        "barangay assistant",
        "who made you"
    ],

    "mission" => [
        "mission",
        "barangay mission",
        "what is the mission"
    ],

    "vision" => [
        "vision",
        "barangay vision",
        "future goal"
    ],

    "history" => [
        "history",
        "background",
        "barangay history",
        "history of barangay san isidro"
    ],

    "medical assistance" => [
        "medical assistance",
        "medical",
        "medicine",
        "prescription",
        "medical records"
    ],

    "financial assistance" => [
        "financial assistance",
        "financial",
        "cash assistance",
        "income",
        "assistance"
    ],

    "scholarship" => [
        "scholarship",
        "scholar",
        "student aid",
        "educational assistance"
    ],

    "processing time" => [
        "processing time",
        "how long",
        "duration",
        "processing",
        "24 to 48 hours"
    ],

    "services" => [
        "services",
        "offer",
        "provide",
        "barangay services"
    ],

    "track request" => [
        "track request",
        "track",
        "request status",
        "status of request"
    ],

    "emergency hotline" => [
        "emergency",
        "hotline",
        "tanod",
        "911",
        "mdrrmo"
    ],

    "location" => [
        "location",
        "located",
        "address",
        "where is the barangay office",
        "barangay hall"
    ],

    "complaint requirements" => [
        "filing a complaint",
        "file complaint",
        "complaint requirements",
        "complaint information"
    ],

    "clearance" => [
        "clearance",
        "barangay clearance"
    ]
];


/*
|--------------------------------------------------------------------------
| INTENT DETECTION
|--------------------------------------------------------------------------
*/

foreach ($intents as $intent => $words) {

    foreach ($words as $word) {

        if (strpos($userMessage, $word) !== false) {

            $safeIntent =
                $conn->real_escape_string($intent);

            $sql = "
                SELECT id, answer
                FROM faq
                WHERE question LIKE '%$safeIntent%'
                LIMIT 1
            ";

            $result = $conn->query($sql);

            if ($result && $row = $result->fetch_assoc()) {

                $response = $row['answer'];

                $bestFaqId = (int)$row['id'];

                break 2;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SIMILARITY MATCHING
|--------------------------------------------------------------------------
*/

if ($response === "") {

    $sql = "
        SELECT id, question, answer
        FROM faq
    ";

    $result = $conn->query($sql);

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $question =
                strtolower($row['question']);

            similar_text(
                $userMessage,
                $question,
                $percent
            );

            if ($percent > $bestMatch) {

                $bestMatch = $percent;

                $bestAnswer =
                    $row['answer'];

                $bestFaqId =
                    (int)$row['id'];
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| AI FALLBACK
|--------------------------------------------------------------------------
*/

if (
    $response === "" &&
    defined('GROQ_API_KEY') &&
    GROQ_API_KEY !== ''
) {

    $context =
        ($bestMatch >= 20)
        ? strip_tags($bestAnswer)
        : null;

    $aiAnswer =
        askGroq(
            $userMessage,
            $context
        );

    if ($aiAnswer !== null) {

        logChatbot(
            $conn,
            $originalMessage,
            null,
            'ai'
        );

        echo safeFormat($aiAnswer);

        $conn->close();

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FINAL FALLBACK
|--------------------------------------------------------------------------
*/

if ($response === "") {

    if ($bestMatch >= 55) {

        $response = $bestAnswer;

        logChatbot(
            $conn,
            $originalMessage,
            $bestFaqId,
            'faq'
        );

    } else {

        $response =
            "Sorry, I couldn't understand your question. Please contact the barangay office.";

        /*
        |-----------------------------------------------
        | THIS IS THE IMPORTANT PART
        |-----------------------------------------------
        | The question is saved as FAILED so the admin
        | can review it later.
        */

        logChatbot(
            $conn,
            $originalMessage,
            null,
            'failed'
        );
    }
}


echo $response;

$conn->close();


/*
|--------------------------------------------------------------------------
| GROQ FUNCTION
|--------------------------------------------------------------------------
*/

function askGroq(
    $userMessage,
    $context = null
) {

    $systemPrompt = <<<PROMPT
You are the AI Assistant embedded in the official website of Barangay San Isidro, Cainta, Rizal, Philippines.

Rules:
- If "official context" is provided below, treat it as the authoritative source for anything specific to Barangay San Isidro.
- Do not invent barangay-specific fees, hours, contact numbers, requirements, or procedures.
- For general knowledge questions, civic/government process questions, greetings, or small talk, you may answer normally.
- Reply in the same language the resident used: English, Filipino, or natural Taglish.
- Keep replies short and conversational, around 2-4 sentences.
- Do not use markdown, bullet symbols, or emojis.
PROMPT;

    $userContent = $context
        ? "Official context:\n{$context}\n\nResident's question: {$userMessage}"
        : "Resident's question: {$userMessage}";

    $payload = [

        "model" => GROQ_MODEL,

        "messages" => [

            [
                "role" => "system",
                "content" => $systemPrompt
            ],

            [
                "role" => "user",
                "content" => $userContent
            ]
        ],

        "temperature" => 0.6,

        "max_tokens" => 220
    ];

    $ch = curl_init(
        "https://api.groq.com/openai/v1/chat/completions"
    );

    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_HTTPHEADER => [

                "Content-Type: application/json",

                "Authorization: Bearer "
                . GROQ_API_KEY
            ],

            CURLOPT_POSTFIELDS =>
                json_encode($payload),

            CURLOPT_TIMEOUT => 8,

            CURLOPT_CONNECTTIMEOUT => 4
        ]
    );

    $raw = curl_exec($ch);

    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    $curlErr =
        curl_error($ch);

    curl_close($ch);

    if (
        $curlErr ||
        $httpCode !== 200 ||
        !$raw
    ) {

        error_log(
            "Groq API call failed (HTTP "
            . $httpCode
            . "): "
            . $curlErr
        );

        return null;
    }

    $data =
        json_decode(
            $raw,
            true
        );

    $text =
        $data['choices'][0]['message']['content']
        ?? null;

    return $text
        ? trim($text)
        : null;
}


/*
|--------------------------------------------------------------------------
| SAFE FORMAT
|--------------------------------------------------------------------------
*/

function safeFormat($text) {

    return nl2br(
        htmlspecialchars(
            $text,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}