<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use mysqli;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Retrieve values from the loaded environment variables
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'];
$dbHost = $_ENV['DB_HOST'];
$dbUser = $_ENV['DB_USER'];
$dbPassword = $_ENV['DB_PASSWORD'];
$dbName = $_ENV['DB_NAME'];

try {
    // Initialize the Telegram API
    $telegram = new Api($botToken);

    // Connect to the database
    $dbConnection = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

    if ($dbConnection->connect_error) {
        die('Database connection failed: ' . $dbConnection->connect_error);
    }

    // Handle incoming updates (messages)
    $updates = $telegram->getWebhookUpdates();

    if ($updates->getMessage()) {
        $chatId = $updates->getMessage()->getChat()->getId();
        $messageText = $updates->getMessage()->getText();

        // Assuming the message is a mobile number
        $mobileNumber = $messageText;

        // Fetch user details from the database using the chat_id
        $userId = $updates->getMessage()->getFrom()->getId();
        $query = "SELECT name, date_of_birth, gender FROM users WHERE chat_id = $userId";
        $result = $dbConnection->query($query);

        if ($result) {
            $user = $result->fetch_assoc();

            // Send user details and mobile number back to the user
            $response = "Name: {$user['name']}\nDate of Birth: {$user['date_of_birth']}\nGender: {$user['gender']}\nMobile Number: $mobileNumber";
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => $response]);
        } else {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => 'User not found in the database']);
        }
    }
} catch (TelegramSDKException $e) {
    echo 'Telegram SDK Exception: ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage();
}
