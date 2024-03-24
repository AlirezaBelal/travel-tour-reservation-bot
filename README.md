# travel tour reservation bot

# Telegram Bot with PHP and MySQL

This is a simple Telegram bot written in PHP that uses a MySQL database to store user information and send messages to users. The bot allows you to send messages to all registered users or to specific users by their user IDs.

## Prerequisites

Before you can use this Telegram bot, you need to set up the following:

1. **MySQL Database**: Create a MySQL database and update the database configuration in the code.

2. **Telegram Bot API Key**: Obtain a Telegram Bot API key by talking to the [BotFather](https://core.telegram.org/bots#botfather) on Telegram.

## Configuration

In the PHP code, you'll find a section for configuring the database and the Telegram bot API key. Update the following variables with your own values:

```php
// Database Configuration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'your_database_name',
    'user' => 'your_database_user',
    'pass' => 'your_database_password',
];

// Telegram Bot Configuration
const API_KEY = 'YOUR_TELEGRAM_API_KEY';
$admin = 'YOUR_ADMIN_USER_ID';
```

## Usage

1. Start the bot by running the PHP script on your server.

2. Users can interact with the bot by sending `/start` to initiate registration.

3. The bot's admin (identified by the user ID) can send messages to all registered users by simply sending a message to the bot.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- This bot was created as a simple example of using PHP and a MySQL database with the Telegram API.
- Special thanks to the Telegram team for providing the API.

Feel free to modify and enhance this code according to your needs. If you have any questions or encounter any issues, please don't hesitate to reach out.



Replace the placeholders with your actual database credentials and Telegram API key. You can also add more sections or details to the README as needed. Once you've created this README.md file, place it in the same directory as your PHP code.
