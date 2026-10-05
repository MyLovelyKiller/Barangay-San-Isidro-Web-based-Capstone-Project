<?php
session_start();

// 1. Define your available icons (filenames without extension)
$icons = ['star', 'heart', 'car', 'cloud', 'moon', 'sun', 'apple', 'bell'];

// 2. Shuffle and pick 1 correct icon and 3 distractors
shuffle($icons);
$selected_icons = array_slice($icons, 0, 4); 
$correct_icon = $selected_icons[array_rand($selected_icons)];

// 3. Store the CORRECT name in the session for the login backend to check
$_SESSION['captcha_code'] = $correct_icon;

// 4. Return the data as JSON so your captcha_handler.js can build the grid
header('Content-Type: application/json');
echo json_encode([
    'instruction' => "Select the " . strtoupper($correct_icon),
    'icons' => $selected_icons // The JS will use these to set <img> src attributes
]);
?>