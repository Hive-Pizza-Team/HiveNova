<?php

/**
 * Web Push VAPID keys — copy to includes/push.config.php and fill in, or run the install wizard.
 * Never commit includes/push.config.php (private key).
 *
 * Used for building-complete alerts and existing fleet / expedition / directive pushes.
 *
 * Generate keys (requires composer install):
 *   php -r "require 'vendor/autoload.php'; print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
 *
 * iOS: Web Push only works for a Home Screen PWA on iOS 16.4+, after an explicit
 * notification permission grant. Android Chrome works in the browser tab or installed PWA.
 * HTTPS (or localhost) is required.
 */
define('PUSH_VAPID_PUBLIC', '');
define('PUSH_VAPID_PRIVATE', '');
define('PUSH_VAPID_SUBJECT', 'mailto:you@example.com');
