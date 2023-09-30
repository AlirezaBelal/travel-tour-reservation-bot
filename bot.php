<?php

// Define your Telegram Bot API token and URL
$botToken = '6540963935:AAFtYGFtmLjWG1bdcuoYtFaxFhkXh9Yms_A';
$telegramApiUrl = "https://api.telegram.org/bot$botToken/sendMessage";

// Get the incoming JSON data
$update = file_get_contents("php://input");

if (!$update) {
    exit('No data provided');
}

// Decode the JSON data
$updateData = json_decode($update, true);

if (!isset($updateData["message"])) {
    exit('No message data found');
}

// Extract message text and chat ID
$message = $updateData["message"];
$text = $message["text"];
$chat_id = $message["chat"]["id"];

// Construct the reply message
$reply = "پیام شما: $text";

// Send the reply message
$response = send_reply($telegramApiUrl, ['chat_id' => $chat_id, 'text' => $reply]);

// Check for errors
if (!$response) {
    exit('Error sending message');
}

// Handle the response (You can add more error handling here if needed)

function send_reply($url, $post_params)
{
    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_POSTFIELDS, $post_params);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($cu);

    if ($result === false) {
        // Handle cURL error
        return false;
    }

    $httpCode = curl_getinfo($cu, CURLINFO_HTTP_CODE);
    curl_close($cu);

    if ($httpCode != 200) {
        // Handle HTTP error
        return false;
    }

    return true;
}
