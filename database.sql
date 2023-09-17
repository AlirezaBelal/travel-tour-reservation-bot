CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       chat_id BIGINT NOT NULL,
                       telegram_id BIGINT NOT NULL,
                       mobile_number VARCHAR(20),
                       melli_code VARCHAR(10),
                       name VARCHAR(255),
                       date_of_birth DATE,
                       gender ENUM('Male', 'Female', 'Other')
);

-- Table Explanation:
-- `id`: Unique identifier for each user, auto-incremented.
-- `chat_id`: Unique identifier for the user's chat.
-- `telegram_id`: Unique identifier for the user's Telegram account.
-- `mobile_number`: User's mobile number (up to 20 characters, optional).
-- `melli_code`: User's national ID or other unique identifier (up to 10 characters, optional).
-- `name`: User's name (up to 255 characters).
-- `date_of_birth`: User's date of birth.
-- `gender`: User's gender, with options 'Male', 'Female', or 'Other'.
