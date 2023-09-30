<?php
// Database Configuration
$hostname = "localhost";
$db = "jamesbon_hiva";
$user = "jamesbon_admin";
$pass = "OI3j}0ro7#I?";

// Function to establish a database connection
function connectToDatabase($hostname, $user, $pass, $db)
{
    $dbconn = mysqli_connect($hostname, $user, $pass, $db) or die(mysqli_error($dbconn));
    mysqli_set_charset($dbconn, 'utf8');
    return $dbconn;
}

// Function to send a message via the Telegram Bot API
function sendMessage($chat_id, $text, $token)
{
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        // Handle error here
        return false;
    } else {
        return json_decode($result, true);
    }
}

// Function to process the incoming message
function processMessage($message, $dbconn, $token)
{
    $arrayMessage = json_decode($message, true);
    $chat_id = $arrayMessage['message']['from']['id'];
    $command = $arrayMessage['message']['text'];

    $query = mysqli_query($dbconn, "SELECT * FROM `user` WHERE `userID` = '$chat_id' LIMIT 1");
    $chekUser = mysqli_num_rows($query);

    if ($chekUser > 0) {
        $row = mysqli_fetch_array($query);
        $level = $row['level'];
    }

    if ($command == '/start') {
        if ($chekUser < 1) {
            $add = mysqli_query($dbconn, "INSERT INTO `user` VALUES ('', '$chat_id', '', 'A')");
        }
        $text = "سلام، به ربات ما خوش آمدید، لطفاً نام خود را وارد کنید";
        sendMessage($chat_id, $text, $token);
    }

    if ($level == 'A') {
        $edit = mysqli_query($dbconn, "UPDATE `user` SET `name` = ?, `level` = 'B' WHERE `userID` = ? LIMIT 1");
        if ($edit) {
            $text = $command . ' عزیز، نام شما دریافت شد. لطفاً شماره تماس خود را وارد کنید.';
            sendMessage($chat_id, $text, $token);
        }
    }

    if ($level == 'B') {
        $edit = mysqli_query($dbconn, "UPDATE `user` SET `mobile` = ?, `level` = 'C' WHERE `userID` = ? LIMIT 1");
        if ($edit) {
            $text = 'با تشکر از شما';
            sendMessage($chat_id, $text, $token);
        }
    }
}

// Main code
$message = file_get_contents("php://input");
$token = "6540963935:AAFwkyBqvFMXs6p4GDuXWZKn96uC30LzFLU"; // Replace with your actual bot token
$dbconn = connectToDatabase($hostname, $user, $pass, $db);
processMessage($message, $dbconn, $token);
