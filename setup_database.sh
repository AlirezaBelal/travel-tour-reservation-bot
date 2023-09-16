#!/bin/bash

# Load environment variables from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Error: .env file not found."
    exit 1
fi

# Step 1: Create the database
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"

# Step 2: Connect to the database and run the SQL script
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_NAME" < "$SQL_FILE"

echo "Database setup completed."
