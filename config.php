<?php
/**
 * URL API of payment gateway
 * This is Apirone Bitcoin Forwarding RESTful API query
 * You can read more details at https://apirone.com/docs/bitcoin-forwarding-api
 */
define('ABF_PROD_URL', 'https://apirone.com/api/v1/receive');

define('ABF_TEST_URL', 'https://apirone.com/api/v1/receive');

define('ABF_MAX_CONFIRMATIONS', '30'); // if 0 - max confirmations count is unlimited, -1 - function is disabled

/**
 * Payment Icon
 */

define('ABF_ICON', plugins_url('logo.svg', __FILE__ ));

/**
 * Shop URL
 */

define('ABF_SHOP_URL', site_url()); // take Site URL for callbacks
?>