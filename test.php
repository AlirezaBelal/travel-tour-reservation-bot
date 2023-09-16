<?php
// Replace with your bot's API token
$apiToken = '6651831731:AAFNCyqXksvssiFLtVY9002_c1yE3XVDp6E';

// Define the base Telegram API URL
$telegramApiUrl = "https://api.telegram.org/bot$apiToken";

// Set the webhook URL
$webhookUrl = "https://jamesbond007.space/test.php";

// Create the request URL
$requestUrl = $telegramApiUrl . "/setWebhook?url=" . urlencode($webhookUrl);

// Send the request to Telegram
$response = file_get_contents($requestUrl);

if ($response === false) {
    echo "Error setting the webhook: " . error_get_last()['message'];
} else {
    echo "Webhook set successfully!";
}
?>
