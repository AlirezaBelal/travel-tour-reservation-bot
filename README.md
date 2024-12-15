# Travel Tour Reservation Bot

### Telegram Bot with PHP and MySQL

This is a simple Telegram bot written in PHP that uses a MySQL database to manage user registrations and send messages
to users. It allows you to send messages to all registered users or to specific users by their user IDs.

---

## **Features**

- **User Registration**: Users can register with the bot using the `/start` command.
- **Admin Messaging**: The admin can send messages to all registered users or specific ones.
- **Database Integration**: All user data is securely stored in a MySQL database.
- **Scalable**: Built to handle a large number of users efficiently.

---

## **Prerequisites**

Before setting up the bot, ensure you have the following:

1. **MySQL Database**:
    - Set up a MySQL database on your server.
    - Update the database credentials in the configuration section of the PHP code.

2. **Telegram Bot API Key**:
    - Create a new Telegram bot using the [BotFather](https://core.telegram.org/bots#botfather).
    - Obtain the unique API key for your bot.

---

## **Configuration**

You need to configure the database and bot settings in the PHP code. Update the following variables:

```php
// Database Configuration
$dbConfig = [
    'host' => 'localhost',         // Database host
    'name' => 'your_database_name', // Name of your database
    'user' => 'your_database_user', // Database username
    'pass' => 'your_database_password', // Database password
];

// Telegram Bot Configuration
const API_KEY = 'YOUR_TELEGRAM_API_KEY'; // Bot API Key from BotFather
$admin = 'YOUR_ADMIN_USER_ID'; // Admin's Telegram User ID
```

---

## **Installation**

1. **Clone the repository**:
   ```bash
   git clone https://github.com/AlirezaBelal/travel-tour-reservation-bot.git
   ```

2. **Set up the database**:
    - Import the provided SQL file (`database.sql`) into your MySQL database.
    - This will create the necessary tables for user data.

3. **Configure your bot**:
    - Update the `API_KEY` and database credentials in the PHP script.

4. **Interact with the bot**:
    - Open Telegram, search for your bot, and send the `/start` command.

---

## **Telegram Bot Commands**

| Command    | Description                               |
|------------|-------------------------------------------|
| `/start`   | Register with the bot and start using it. |
| `/help`    | Get help and see available commands.      |
| `/message` | (Admin only) Send messages to all users.  |

---

## **Usage**

1. **For Users**:
    - Send `/start` to the bot to register.
    - Use the bot to receive updates and messages from the admin.

2. **For Admin**:
    - Identify yourself using the configured `Admin User ID`.
    - Send messages to all users by simply typing your message in the bot.

---

## **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## **Acknowledgments**

- Thanks to the Telegram team for providing the powerful [Telegram Bot API](https://core.telegram.org/bots/api).
- Inspired by the simplicity of integrating PHP with Telegram.

---

## **Need Help?**

If you have any questions or need further assistance, feel free to contact me:

- **Email**: belal.alireza@gmail.com
- **Telegram**: [@alireza_belal](https://t.me/alireza_belal)

---

### Notes

- For scalability, consider moving to a framework (e.g., Laravel) or integrating with a queue system for sending bulk
  messages.
- If you encounter issues, feel free to open an issue in
  the [GitHub Repository](https://github.com/AlirezaBelal/travel-tour-reservation-bot).
