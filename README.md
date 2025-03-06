# Telegram Error Notifier

A WordPress plugin to send Telegram notifications for fatal errors on your site. This plugin alerts you via Telegram whenever a fatal error occurs and provides a reminder if the error persists for more than 24 hours.

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Description

The Telegram Error Notifier plugin helps you stay informed about critical errors on your WordPress site. When a fatal error occurs, a notification is sent to a specified Telegram chat. Additionally, if the error persists for more than a day, a reminder is sent daily at 08:00 AM CET.

## Features

- Sends instant Telegram notifications for fatal errors.
- Reminds you daily if a fatal error persists.
- Notifies on WooCommerce events: add to cart, order received, payment completed.
- Notifies on user activities: registration, login.
- Notifies on plugin events: activation, deactivation, deletion, installation and update.
- Easy configuration via the WordPress admin panel.
- Scheduled checks to avoid disturbing clients during off hours.

## Installation

1. **Download the Plugin:**

   - Download the plugin zip file from the [releases](https://github.com/yourusername/telegram-error-notifier/releases) section.

2. **Install via WordPress Admin:**

   - Go to `Plugins > Add New` in your WordPress admin dashboard.
   - Click `Upload Plugin` and choose the downloaded zip file.
   - Click `Install Now` and then `Activate`.

3. **Install via FTP:**
   - Extract the zip file.
   - Upload the extracted folder to the `/wp-content/plugins/` directory on your server.
   - Activate the plugin through the `Plugins` menu in WordPress.

## Usage

1. **Configure Telegram Settings:**

   - Go to `Tools > Telegram Error Notifier` in the WordPress admin panel.
   - Enter your Telegram Bot Token and Chat ID.
   - Save your settings.

2. **Monitor Events:**
   - The plugin will automatically start monitoring for the specified events.
   - In case of a fatal error, you will receive a Telegram notification with the error details.
   - If the error persists, you will receive a daily reminder at 08:00 AM CET.
   - Notifications for WooCommerce, user activities, and plugin events will also be sent as configured.

## Configuration

### Telegram Bot Setup

1. **Create a Telegram Bot:**

   - Open Telegram and search for `@BotFather`.
   - Start a chat and use the `/newbot` command to create a new bot.
   - Follow the instructions to get your Bot Token.

2. **Get Your Chat ID:**

   - Start a chat with your bot or add it to a group.
   - Send a message to the bot or in the group.
   - Open the following URL in your browser to get updates: `https://api.telegram.org/bot<YourBotToken>/getUpdates`.
   - Find the `chat` object in the response to get your Chat ID.

3. **Configure the Plugin:**
   - Go to `Tools > Telegram Error Notifier` in your WordPress admin panel.
   - Enter your Bot Token and Chat ID.
   - Select the events you want to be notified for.
   - Save your settings.

## Contributing

We welcome contributions to improve this plugin. Here’s how you can help:

1. Fork the repository.
2. Create a new branch: `git checkout -b feature-branch-name`.
3. Make your changes and commit them: `git commit -m 'Add some feature'`.
4. Push to the branch: `git push origin feature-branch-name`.
5. Open a pull request.

Please ensure your code follows the existing coding standards and includes appropriate tests.

## License

This plugin is licensed under the GPL-2.0 License. See the [LICENSE](LICENSE) file for more information.
# telegram-error-notifier
