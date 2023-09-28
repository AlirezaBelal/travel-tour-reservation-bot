<?php
require("pdo.php");

// Database Configuration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'jamesbon_hiva',
    'user' => 'jamesbon_admin',
    'pass' => 'OI3j}0ro7#I?',
];
$DB = new Db($dbConfig['host'], $dbConfig['name'], $dbConfig['user'], $dbConfig['pass']);

// Telegram Bot Configuration
const API_KEY = '6540963935:AAFwkyBqvFMXs6p4GDuXWZKn96uC30LzFLU';
$admin = '6540963935';

$telegram = json_decode(file_get_contents('php://input'), true);

// Check if 'message' key exists in the $telegram array
if (isset($telegram['message'])) {
    $messageData = $telegram['message'];

    // Check if 'chat' and 'text' keys exist in the $messageData array
    if (isset($messageData['chat'], $messageData['text'])) {
        $data = $messageData['chat'];
        $text = $messageData['text'];

        if ($text == "/start") {
            $exist = $DB->query("SELECT id FROM users WHERE user_id=?", [$data["id"]]);

            if (count($exist) !== 1) {
                $DB->query("INSERT INTO users (first_name, last_name, username, user_id) VALUES (?, ?, ?, ?)",
                    [$data["first_name"] ?? '', $data["last_name"] ?? '', $data["username"] ?? '', $data["id"]]);
            }

            $message = (count($exist) === 0) ? "اطلاعات شما در دیتابیس ذخیره شد." : "اطلاعات شما در دیتابیس موجود می باشد.";

            sendMessage($data['id'], $message);
        } else {
            if ($data["id"] == $admin) {
                $allUser = $DB->query("SELECT user_id FROM users");

                foreach ($allUser as $user) {
                    sendMessage($user['user_id'], $text);
                }
            }
        }
    }
}

function sendMessage($chatId, $message)
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $res = curl_exec($ch);

    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}
