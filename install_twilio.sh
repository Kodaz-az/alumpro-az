#!/bin/bash
# Script to install Twilio SDK via Composer

echo "Installing Twilio PHP SDK..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Composer is not installed. Installing composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Create composer.json if it doesn't exist
if [ ! -f composer.json ]; then
    echo "Creating composer.json..."
    cat > composer.json << EOF
{
    "require": {
        "twilio/sdk": "^7.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
EOF
fi

# Install Twilio SDK
composer require twilio/sdk

echo "Twilio SDK installation completed!"
echo "Don't forget to:"
echo "1. Add your Twilio credentials to config.php"
echo "2. Set up your WhatsApp webhook URL in Twilio Console"
echo "3. Configure auto-replies in the admin panel"