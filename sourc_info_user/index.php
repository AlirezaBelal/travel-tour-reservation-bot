<?php
require("pdo.php");

// Database Configuration
$dbHost = 'localhost';
$dbName = 'jamesbon_hiva';
$dbUser = 'jamesbon_admin';
$dbPass = 'OI3j}0ro7#I?';

$DB = new Db($dbHost, $dbName, $dbUser, $dbPass);

// Telegram Bot Configuration
define('API_KEY', 'YOUR_TELEGRAM_API_KEY');
$admin = '70532057';

$telegram = json_decode(file_get_contents('php://input'), true);
$data = $telegram['message']['chat'];
$text = $telegram['message']['text'];

if ($text == "/start") {
    $exist = $DB->query("SELECT id FROM users WHERE user_id=" . $data["id"]);

    if (count($exist) !== 1) {
        $DB->query("INSERT INTO users (first_name, last_name, username, user_id) VALUES (?, ?, ?, ?)",
            [$data["first_name"], $data["last_name"], $data["username"], $data["id"]]);
    }

    $message = (count($exist) === 0) ? "اطلاعات شما در دیتابیس ذخیره شد." : "اطلاعات شما در دیتابیس موجود می باشد.";

    bot('sendMessage', [
        'chat_id' => $data['id'],
        'text' => $message,
    ]);
} else {
    if ($data["id"] == $admin) {
        $allUser = $DB->query("SELECT user_id FROM users");

        foreach ($allUser as $user) {
            bot('sendMessage', [
                'chat_id' => $user['user_id'],
                'text' => $text,
            ]);
        }
    }
}

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);

    $res = curl_exec($ch);

    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}
