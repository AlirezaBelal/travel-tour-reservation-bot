<?php
require_once __DIR__ . '/vendor/autoload.php'; // Include the Composer autoloader

use Dotenv\Dotenv;

// Load environment variables from .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Telegram Bot API token
$botToken = getenv('TELEGRAM_BOT_TOKEN');

// Create a function to get the chat ID
function getChatId($botToken)
{
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        $errorMessage = "Error: " . curl_error($ch);
        curl_close($ch);
        return $errorMessage;
    }

    curl_close($ch);

    $data = json_decode($response, true);

    // Check if there are updates and get the chat ID of the most recent one
    if (!empty($data['result'])) {
        $chatId = $data['result'][0]['message']['chat']['id'];
        return "Chat ID: " . $chatId;
    } else {
        return "No recent messages found.";
    }
}

// Get the Chat ID or error message
$result = getChatId($botToken);

// Log the result to a file
$logFileName = 'log.txt';
$logMessage = date('Y-m-d H:i:s') . " - " . $result . PHP_EOL;
file_put_contents($logFileName, $logMessage, FILE_APPEND);

// Output the result to the browser
echo $result;
