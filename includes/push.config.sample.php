<?php

/**
 * Web Push VAPID keys — copy to includes/push.config.php and fill in, or run the install wizard.
 *
 * Generate keys (requires composer install):
 *   php -r "require 'vendor/autoload.php'; print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
 */
define('PUSH_VAPID_PUBLIC', '');
define('PUSH_VAPID_PRIVATE', '');
define('PUSH_VAPID_SUBJECT', 'mailto:you@example.com');
