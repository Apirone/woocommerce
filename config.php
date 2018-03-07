<?php
/**
 * URL API of payment gateway
 */
define('PROD_URL', 'https://apirone.com/api/v1/receive');

define('TEST_URL', 'https://apirone.com/api/v1/receive');
define('COUNT_CONFIRMATIONS', '1'); //minimun confirmations count for accepting payment
define('MAX_CONFIRMATIONS', '30'); // if 0 - max confirmations count is unlimited, -1 - function is disabled

/**
 * Logging
 */
define('DEBUG', false);

/**
 * Payment Icon
 */
define('ICON', '/wp-content/plugins/woocommerce-apirone/logo.svg');

/**
 * Shop URL
 */
define('SHOP_URL', 'http://example.com'); // CHANGE THIS DOMAIN TO YOUR'S without slash "/" at the end of line.
?>