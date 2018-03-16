<?php
/*
Plugin Name: Apirone Bitcoin Forwarding
Plugin URI: https://github.com/Apirone/woocommerce/
Description: Bitcoin Forwarding Plugin for Woocoomerce by Apirone Processing Provider.
Version: 1.1
Author: Apirone LLC
Author URI: http://www.apirone.com
Copyright: © 2018 Apirone.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
require_once 'config.php'; //configuration files
require_once 'woocommerce-payment-name.php'; //payment gateway constants

function abf_logger($var)
{
    if ($var) {
        $date   = '<---------- ' . date('Y-m-d H:i:s') . " ---------->\n";
        $result = $var;
        if (is_array($var) || is_object($var)) {
            $result = print_r($var, 1);
        }
        $result .= "\n";
        $path = plugin_dir_path( __FILE__ ).'/apirone-payment.log'; //defaults wp-content/plugins/apirone-bitcoin/
        error_log($date . $result, 3, $path);
        return true;
    }
    return false;
}

global $apirone_db_version;
$apirone_db_version = '1.01';

function abf_install()
{
    global $wpdb;
    global $apirone_db_version;
    
    $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
    $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $sale_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        address text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql .= "CREATE TABLE $transactions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        paid bigint DEFAULT '0' NOT NULL,
        confirmations int DEFAULT '0' NOT NULL,
        thash text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option('apirone_db_version', $apirone_db_version);
}

register_activation_hook(__FILE__, 'abf_install');

function abf_update_db_check()
{
    global $apirone_db_version;
    if (get_site_option('apirone_db_version') != $apirone_db_version) {
        abf_install();
    }
}

add_action('plugins_loaded', 'abf_update_db_check');

function abf_enqueue_script()
{
    wp_enqueue_script('apirone_script', plugin_dir_url(__FILE__) . 'apirone.js', array(
        'jquery'
    ), '1.0');
}
add_action('wp_enqueue_scripts', 'abf_enqueue_script');

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly


/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_apironepayment', 0);
function woocommerce_apironepayment()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_APIRONE'))
        return;
    
    class WC_APIRONE extends WC_Payment_Gateway
    {
        public function __construct()
        {            
            global $woocommerce;
            $this->id         = APIRONEPAYMENT_ID;
            $this->has_fields = false;
            $this->liveurl    = ABF_PROD_URL;
            $this->testurl    = ABF_TEST_URL;
            $this->icon       = ABF_ICON;
            $this->title       = APIRONEPAYMENT_TITLE_1;
            $this->description = APIRONEPAYMENT_TITLE_2;
            $this->testmode    = 'no';
            
            // Load the settings from DB
            $this->abf_init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->address = $this->get_option('address');

            define('ABF_DEBUG', $this->get_option('debug'));
            define('ABF_COUNT_CONFIRMATIONS', intval($this->get_option('count_confirmations')));// Integer value for count confirmations

            if (ABF_DEBUG == "yes") {
   			 // Display errors
    		ini_set('display_errors', 1);
    		error_reporting(E_ALL & ~E_NOTICE);
			}
            
            // Actions
            add_action('valid-apironepayment-standard-ipn-reques', array(
                $this,
                'successful_request'
            ));
            add_action('woocommerce_receipt_' . $this->id, array(
                $this,
                'receipt_page'
            ));
            
            //Save our GW Options into Woocommerce
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            
            // Payment listener/API hook
            add_action('woocommerce_api_callback_apirone', 'abf_check_response');
            add_action('woocommerce_api_check_payment', 'abf_ajax_response');
            
            if (!$this->abf_is_valid_for_use()) {
                $this->enabled = false;
            }
        }
        
        /**
         * Check if this gateway is enabled and available in the user's country
         */
        function abf_is_valid_for_use()
        {
            if (!in_array(get_option('woocommerce_currency'), array(
                'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BCH', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EEK', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTC', 'LTL', 'LVL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MTL', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'SVC', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT', 'YER', 'ZAR', 'ZMK', 'ZMW', 'ZWL'
            ))) {
                return false;
            }
            return true;
        }
        
        /**
         * Admin Panel Options
         */
        public function admin_options()
        {
?>
        <h3><?php _e(APIRONEPAYMENT_TITLE_1, 'woocommerce');?></h3>
        <p><?php _e(APIRONEPAYMENT_TITLE_2, 'woocommerce');?></p>

      <?php if ($this->abf_is_valid_for_use()): ?>

        <table class="form-table">

        <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html(); ?>
    </table><!--/.form-table-->

    <?php else: ?>
        <div class="inline error"><p><strong><?php
                _e('Gateway offline', 'woocommerce');
?></strong>: <?php _e($this->id . ' don\'t support your shop currency', 'woocommerce'); ?></p></div>
        <?php endif;
            
        } // End admin_options()
        
        /**
         * Initialise Gateway Settings Form Fields
         */
        function abf_init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('On/off', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('On', 'woocommerce'),
                    'default' => 'no'
                ),
                'address' => array(
                    'title' => __('Destination Bitcoin address', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Your Destination Bitcoin address', 'woocommerce'),
                    'default' => ''
                ),
				'debug' => array(
                    'title' => __('Debug Mode', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('On', 'woocommerce'),
                    'description' => __('All callback responses, debugging messages, errors logs are stored in "apirone-payment.log", but as a best practice do not enable this unless you are having issues with the plugin.', 'woocommerce'),
                    'default' => 'no'
                ),
                'count_confirmations' => array(
                    'title' => __('Minimun confirmations count', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Minimun confirmations count for accepting payment. Must be an integer', 'woocommerce'),
                    'default' => '1'
                ),
            );
        }
        
        /**
         * Generate the dibs button link
         */

        static function abf_convert_to_btc($currency, $value)
        {   
            
            if ($currency == 'BTC') {
                return $value;
            } else { if ( $currency == 'BTC' || $currency == 'USD' || $currency == 'EUR' || $currency == 'GBP') {
            $response_btc = wp_remote_get('https://apirone.com/api/v1/tobtc?currency=' . $currency . '&value=' . $value);
            return $response_btc['body'];      
            } else {
            $args = array(
                'headers' => array(
                'User-Agent' => 'Apirone Bitcoin Gateway',
                'CB-VERSION' => '2017-08-07'
                )
            );
            $response_coinbase = wp_remote_request('https://api.coinbase.com/v2/prices/BTC-'. $currency .'/buy', $args);
            $response_coinbase = json_decode($response_coinbase['body'], true);
            $response_coinbase = $response_coinbase['data']['amount'];
            if (is_numeric($response_coinbase)) {
                return round($value / $response_coinbase, 8);
            } else {
                return 0;
            };
            
                
            }     
            }
        }

        //checks that order has sale
        static function abf_sale_exists($order_id, $input_address)
        {
            global $wpdb;
            $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
            $sales = $wpdb->get_results("SELECT * FROM $sale_table WHERE order_id = $order_id AND address = '$input_address'");
            if ($sales[0]->address == $input_address) {return true;} else {return false;};
        }

        // function that checks what user complete full payment for order
        static function abf_check_remains($order_id)
        {
            global $wpdb;
            global $woocommerce;
            $order = new WC_Order($order_id);
            $total = WC_APIRONE::abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
            $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
            $transactions = $wpdb->get_results("SELECT * FROM $transactions_table WHERE order_id = $order_id");
            $remains = 0;
            $total_paid = 0;
            $total_empty = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->thash == "empty") $total_empty+=$transaction->paid;
                $total_paid+=$transaction->paid;
            }
            $total_paid/=100000000;
            $total_empty/=100000000;
            $remains = $total - $total_paid;
            $remains_wo_empty = $remains + $total_empty;
            if ($remains_wo_empty > 0) {
                return false;
            } else {
                return true;
            };
        }

        static function abf_remains_to_pay($order_id)
        {   
            global $woocommerce;
            global $wpdb;
            $order = new WC_Order($order_id);
            $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
            $transactions = $wpdb->get_results("SELECT * FROM $transactions_table WHERE order_id = $order_id");
            $total_paid = 0;
            foreach ($transactions as $transaction) {
                $total_paid+=$transaction->paid;
            }
            $response_btc = WC_APIRONE::abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
            $remains = $response_btc - $total_paid/100000000;
            if($remains < 0) $remains = 0;  
            return $remains;
        }
        
        public function abf_generate_form($order_id)
        {
            global $woocommerce;
            global $wpdb;
            $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
            
            $order = new WC_Order($order_id);
            
            if ($this->testmode == 'yes') {
                $apirone_adr = $this->testurl;
            } else {
                $apirone_adr = $this->liveurl;
            }
            
            $_SESSION['testmode'] = $this->testmode;
            
            $response_btc = $this->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);

            if ($this->abf_is_valid_for_use() && $response_btc > 0) {
                /**
                 * Args for Forward query
                 */
                $args           = array(
                        'address' => $this->address,
                        'callback' => urlencode(ABF_SHOP_URL . '?wc-api=callback_apirone&key=' . $order->order_key . '&order_id=' . $order_id)
                    );            
    
                $sales = $wpdb->get_results("SELECT * FROM $sale_table WHERE order_id = $order_id");
                
                if ($sales == null) {
                    $args           = array(
                        'address' => $this->address,
                        'callback' => urlencode(ABF_SHOP_URL . '?wc-api=callback_apirone&key=' . $order->order_key . '&order_id=' . $order_id)
                    );
                    $apirone_create = $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'];
                    $response_create = wp_remote_get( $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'] );
                    $response_create = json_decode($response_create['body'], true);
                    if ($response_create['input_address'] != null){
                        $wpdb->insert($sale_table, array(
                            'time' => current_time('mysql'),
                            'order_id' => $order_id,
                            'address' => $response_create['input_address']
                         ));
                    } else{
                        echo "No Input Address from Apirone :(";
                    }
                } else {
                    $response_create['input_address'] = $sales[0]->address;
                }
                if ($response_create['input_address'] != null){
                echo '<div class="woocommerce"><ul class="order_details"><li>Please send exactly <strong>' . $response_btc . ' BTC</strong> </li><li>for this address:<strong>' . $response_create['input_address'];
                echo '</strong></li><li><img src="https://apirone.com/api/v1/qr?message=' . urlencode("bitcoin:" . $response_create['input_address'] . "?amount=" . $response_btc . "&label=Apirone") . '"></li><li class="apirone_result"></li></ul></div>';
                }
                if ((ABF_DEBUG == "yes") && !is_null($response_create)) {
                    abf_logger('Request: ' . $apirone_create . ': ' . print_r($args, true) . 'Response: ' . $response);
                }
            } else {
                echo "Apirone couldn't exchange " . get_option('woocommerce_currency') . " to BTC :(";
            }
        }
        
        /**
         * Process the payment and return the result
         */
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        /**
         * Receipt page
         */
    function receipt_page($order)
        {
            echo $this->abf_generate_form($order);
        }
    }
    
    function abf_ajax_response()
    {
        $safe_key = $_GET['key'];
        if ( ! $safe_key ) {
            $safe_key = '';
        }

        if ( strlen( $safe_key ) > 25 ) {
            $safe_key = substr( $safe_key, 0, 25 );
        }
        sanitize_key( $safe_key );

        $safe_order = intval( $_GET['order'] );

        if ( $safe_order == 'undefined') {
             $safe_order = '';
        }

        if ( strlen( $safe_order ) > 25 ) {
            $safe_order = substr( $safe_order, 0, 25 );
        }

        if (!empty($safe_key) && !empty($safe_order)) {
            global $woocommerce;
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
            $order = wc_get_order($safe_order);
            if (!empty($safe_order)) {
                $transactions = $wpdb->get_results("SELECT * FROM $transactions_table WHERE order_id = ".$safe_order);
            }
            $empty = 0;
            $value = 0;
            $paid_value = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->thash == "empty"){
                    $empty = 1; // has empty value in thash
                    $value = $transaction->paid;
                } else{
                    $paid_value = $transaction->paid;
                    $confirmed = $transaction->thash;
                }              
            }
            if ($order == '') {
                echo 'Error';
                exit;
            }
            $response_btc = WC_APIRONE::abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
            if ($order->status == 'processing' && WC_APIRONE::abf_check_remains($safe_order)) {
                echo 'Payment accepted. Thank You!';
            } else {
                if($empty){
                echo "Transaction in progress... <b>Amount</b>: " . number_format($value/100000000, 8, '.', '') . " BTC. <b>Remains to pay</b>:".number_format(WC_APIRONE::abf_remains_to_pay($safe_order), 8, '.', '');
                } else{
                echo "Waiting for payment... ";
                if($paid_value){
                    echo "<b>Last Confirmed</b>: ".  number_format($paid_value/100000000, 8, '.', '') . " BTC, <b>Transaction hash</b>: ". $confirmed ." <b>Remains to pay</b>: ".number_format(WC_APIRONE::abf_remains_to_pay($safe_order), 8, '.', '') . ' BTC';
                }
            }
            }
            exit;
        }
    }

    /**
     * Check response
     */
    function abf_check_response()
    {
        global $woocommerce;
        global $wpdb;

        $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
        $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
        $abf_api_output = 0; //Nothing to do (empty callback, wrong order Id or Input Address)
        if (ABF_DEBUG == "yes") {
                abf_logger('Callback' . $_SERVER['REQUEST_URI']);
        }
        $safe_key = $_GET['key'];
        if ( ! $safe_key ) {
            $safe_key = '';
        }

        if ( strlen( $safe_key ) > 25 ) {
            $safe_key = substr( $safe_key, 0, 25 );
        }
        sanitize_key( $safe_key );

        $safe_order_id = intval( $_GET['order_id'] );

        if ( $safe_order_id == 'undefined') {
             $safe_order_id = '';
        }

        if ( strlen( $safe_order_id ) > 25 ) {
            $safe_order_id = substr( $safe_order_id, 0, 25 );
        }

        $safe_confirmations = intval( $_GET['confirmations'] );
        if ( strlen( $safe_confirmations ) > 5 ) {
            $safe_confirmations = substr( $safe_confirmations, 0, 5 );
        }
        if ( ! $safe_confirmations ) {
            $safe_confirmations = 0;
        }

        $safe_value = intval( $_GET['value'] );
        if ( strlen( $safe_value ) > 16 ) {
            $safe_value = substr( $safe_value, 0, 16 );
        }
        if ( ! $safe_value ) {
            $safe_value = '';
        }

        $safe_input_address = sanitize_text_field($_GET['input_address']);
        if ( strlen( $safe_input_address ) > 64 ) {
            $safe_input_address = substr( $safe_input_address, 0, 64 );
        }
        if ( ! $safe_input_address ) {
            $safe_input_address = '';
        }

        $safe_transaction_hash = sanitize_text_field($_GET['transaction_hash']);
        if ( strlen( $safe_transaction_hash ) > 65 ) {
            $safe_transaction_hash = substr( $safe_transaction_hash, 0, 65 );
        }
        if ( ! $safe_transaction_hash ) {
            $safe_transaction_hash = '';
        }
        $apirone_order = array(
            'confirmations' => $safe_confirmations,
            'orderId' => $safe_order_id, // order id
            'key' => $safe_key,
            'value' => $safe_value,
            'transaction_hash' => $safe_transaction_hash,
            'input_address' => $safe_input_address
        );
        if (($safe_confirmations >= 0) AND !empty($safe_value) AND WC_APIRONE::abf_sale_exists($safe_order_id, $safe_input_address)) {
            $abf_api_output = 1; //transaction exists
            if ($_SESSION['testmode'] == 'yes') {
                $apirone_adr = ABF_TEST_URL;
            } else {
                $apirone_adr = ABF_PROD_URL;
            }
            if (!empty($apirone_order['value']) && !empty($apirone_order['input_address']) && empty($apirone_order['transaction_hash'])) {
                $order = new WC_Order($apirone_order['orderId']);
                if ($apirone_order['key'] == $order->order_key) {
                $transactions = $wpdb->get_results("SELECT * FROM $transactions_table WHERE order_id = ".$apirone_order['orderId']);
                $flag = 1; //no simular transactions
                foreach ($transactions as $transaction) {
                    if(($transaction->thash == 'empty') && ($transaction->paid == $apirone_order['value'])){
                        $flag = 0; //simular transaction detected
                        break;
                    }
                }
                if($flag){
                    $wpdb->insert($transactions_table, array(
                        'time' => current_time('mysql'),
                        'confirmations' => $apirone_order['confirmations'],
                        'paid' => $apirone_order['value'],
                        'order_id' => $apirone_order['orderId'],
                        'thash' => 'empty'
                    ));
                $abf_api_output = 2; //insert new transaction in DB without transaction hash
                } else {
                        $update_query = array(
                            'time' => current_time('mysql'),
                            'confirmations' => $apirone_order['confirmations'],
                        );
                        $where = array('paid' => $apirone_order['value'], 'thash' => 'empty');
                        $wpdb->update($transactions_table, $update_query, $where); 
                $abf_api_output = 3; //update existing transaction
                    }
                }
            }

                if (!empty($apirone_order['value']) && !empty($apirone_order['input_address']) && !empty($apirone_order['transaction_hash'])) {
                $abf_api_output = 4; // callback with transaction_hash
                $transactions  = $wpdb->get_results("SELECT * FROM $transactions_table WHERE order_id = ".$apirone_order['orderId']);
                $sales = $wpdb->get_results("SELECT * FROM $sale_table WHERE order_id = ".$apirone_order['orderId']);
                $order = new WC_Order($apirone_order['orderId']);
                if ($sales == null) $abf_api_output = 5; //no such information about input_address
                $flag = 1; //new transaction
                $empty = 0; //unconfirmed transaction
                   if ($apirone_order['key'] == $order->order_key) {
                        $abf_api_output = 6; //WP key is valid but confirmations smaller that value from config or input_address not equivalent from DB
                        if (($apirone_order['confirmations'] >= ABF_COUNT_CONFIRMATIONS) && ($apirone_order['input_address'] == $sales[0]->address)) {
                            $abf_api_output = 7; //valid transaction
                            foreach ($transactions as $transaction) {
                                $abf_api_output = 8; //finding same transaction in DB
                                if($apirone_order['transaction_hash'] == $transaction->thash){
                                    $abf_api_output = 9; // same transaction was in DB
                                    $flag = 0; // same transaction was in DB
                                    break;
                                }
                                if(($apirone_order['value'] == $transaction->paid) && ($transaction->thash == 'empty')){
                                    $empty = 1; //empty find
                                }
                            }
                        }
                    }
                $response_btc = WC_APIRONE::abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);                 
                if($flag && $apirone_order['confirmations'] >= ABF_COUNT_CONFIRMATIONS && $response_btc > 0){
                    $abf_api_output = 10; //writing into DB, taking notes
                    $notes        = 'Input Address: ' . $apirone_order['input_address'] . ', Transaction hash: ' . $apirone_order['transaction_hash'] . 'Payment in BTC:' . $apirone_order['value']/100000000;
                    if ($response_btc > $apirone_order['value']/100000000)
                        $notes .= '. User trasfrer not enough money in your shop currency. Waiting for next payment.';
                    if ($response_btc < $apirone_order['value']/100000000)
                        $notes .= '. User trasfrer more money than You need in your shop currency.';

                    if($empty){
                    $update_query = array(
                        'time' => current_time('mysql'),
                        'confirmations' => $apirone_order['confirmations'],
                        'thash' => $apirone_order['transaction_hash']
                    );
                    $where = array(
                        'paid' => $apirone_order['value'],
                        'order_id' => $apirone_order['orderId'],
                        'thash' => 'empty'
                    );
                    $wpdb->update($transactions_table, $update_query, $where);
                    } else {

                    $wpdb->insert($transactions_table, array(
                            'time' => current_time('mysql'),
                            'confirmations' => $apirone_order['confirmations'],
                            'paid' => $apirone_order['value'],
                            'order_id' => $apirone_order['orderId'],
                            'thash' => $apirone_order['transaction_hash']
                        ));                        
                    } 
                    if (WC_APIRONE::abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment it's not completed
                    $order->update_status('processing', __('Payment complete', 'woocommerce'));
                    WC()->cart->empty_cart();
                    $order->payment_complete();
                    }
                    $order->add_order_note("Payment accepted: ".  $apirone_order['value']/100000000 . " BTC");
                    $order->add_order_note('Order total: '.$response_btc . ' BTC');

                    $abf_api_output = '*ok*';
                } else {
                    $abf_api_output = '11'; //No currency or small confirmations count or same transaction in DB
                }
            }
        }

        if(($apirone_order['confirmations'] >= ABF_MAX_CONFIRMATIONS) && (ABF_MAX_CONFIRMATIONS != 0)) {// if callback's confirmations count more than ABF_MAX_CONFIRMATIONS we answer *ok*
            $abf_api_output="*ok*";
            if(ABF_DEBUG == "yes") {
                abf_logger('Skipped transaction: ' .  $apirone_order['transaction_hash'] . ' with confirmations: ' . $apirone_order['confirmations']);
            };
        };
        if(ABF_DEBUG == "yes") {
        print_r($abf_api_output);//global output
        } else{
            if($abf_api_output === '*ok*') echo '*ok*';
        }
        exit;
    }
    
    /**
     * Add apirone the gateway to WooCommerce
     */
    function add_apirone_gateway($methods)
    {
        $methods[] = 'WC_APIRONE';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_apirone_gateway');
}