CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       chat_id BIGINT NOT NULL,
                       telegram_id BIGINT NOT NULL,
                       mobile_number VARCHAR(20),
                       name VARCHAR(255),
                       date_of_birth DATE,
                       gender ENUM('Male', 'Female', 'Other')
);
