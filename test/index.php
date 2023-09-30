<?php
$token = '6540963935:AAFwkyBqvFMXs6p4GDuXWZKn96uC30LzFLU';
$url = "https://api.telegram.org/bot" . $token;
$method = "/getupdates";
$update = file_get_contents($url . $method);

// Decode the JSON data
$decodedData = json_decode($update);

// Get the total number of messages
$totalMessages = count($decodedData->result);

// Check if there are messages
if ($totalMessages > 0) {
    // Get the chat_id of the most recent message (last added)
    $lastMessageIndex = $totalMessages - 1;
    $chatId = $decodedData->result[$lastMessageIndex]->message->chat->id;
    echo "Chat ID of the most recent message: $chatId";
} else {
    echo "No messages found in the JSON data.";
}
$userMessage = "hello is tets";
$method = "/sendmessage";
file_get_contents($url . $method . "?chat_id=" . $chatId . "&text=" . $userMessage);


//in url : http://jamesbond007.space/test/