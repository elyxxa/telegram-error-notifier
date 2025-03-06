<?php

namespace Webkonsulenterne\TelegramErrorNotifier;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class UpdateChecker
{
    private $update_checker;
    private $settings;

    public function __construct($plugin_file)
    {
        $this->settings = Settings::get_instance();
        $this->init_update_checker($plugin_file);
    }

    private function init_update_checker($plugin_file)
    {
        // GitHub repository information
        $github_org = 'webkonsulenterne'; // Your GitHub organization
        $github_repo = 'telegram-error-notifier'; // Your repository name

        // Initialize the update checker
        $this->update_checker = PucFactory::buildUpdateChecker(
            "https://github.com/{$github_org}/{$github_repo}/",
            $plugin_file,
            'telegram-error-notifier'
        );

        // Set the branch that contains the stable release
        $this->update_checker->setBranch('main'); // or 'master', or whatever your stable branch is

        // Set authentication for private repository
        // You can store this in wp-config.php for security
        if (defined('GITHUB_ACCESS_TOKEN') && GITHUB_ACCESS_TOKEN) {
            $this->update_checker->setAuthentication(GITHUB_ACCESS_TOKEN);
        } else {
            $this->update_checker->setAuthentication("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");

        }

        // Enable release assets if you're using GitHub releases
        $this->update_checker->getVcsApi()->enableReleaseAssets();
    }
}
