<?php
/*
Plugin Name: Apirone Bitcoin Forwarding 
Plugin URI: https://github.com/Apirone/woocommerce/
Description: Bitcoin Forwarding Plugin for Woocoomerce by Apirone Processing Provider.
Version: 2.0.2
Author: Apirone LLC
Author URI: https://www.apirone.com
Copyright: © 2018 Apirone.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
require_once 'config.php'; //configuration files
require_once 'woocommerce-payment-name.php'; //payment gateway constants

global $apirone_db_version;
$apirone_db_version = '1.04';

function abf_install()
{
    global $wpdb;
    global $apirone_db_version;
    
    $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
    $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
    $secret_table = $wpdb->prefix . 'woocommerce_apirone_secret'; 
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $sale_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        address text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    $sql .= "CREATE TABLE $transactions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        paid bigint DEFAULT '0' NOT NULL,
        confirmations int DEFAULT '0' NOT NULL,
        thash text NOT NULL,
        input_thash text NOT NULL,
        order_id int DEFAULT '0' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    $sql .= "CREATE TABLE $secret_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        mdkey text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    $sql .= "INSERT IGNORE INTO $secret_table (`id`, `mdkey`) VALUES (1, MD5(NOW()))"; 
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option('apirone_db_version', $apirone_db_version);
}

register_activation_hook(__FILE__, 'abf_install');

function abf_upgrade()
{
    global $wpdb;
    $secret_table = $wpdb->prefix . 'woocommerce_apirone_secret';
    $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';

    $sql = "ALTER TABLE $transactions_table
    ADD input_thash text NOT NULL
    AFTER thash";
    $sql .= "CREATE TABLE $secret_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        mdkey text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    $sql .="INSERT INTO $secret_table (`id`, `mdkey`) VALUES (1, MD5(NOW()))";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function abf_update_db_check()
{
    global $apirone_db_version;
    if (get_site_option('apirone_db_version') != $apirone_db_version) {
        abf_upgrade();
        update_option('apirone_db_version', $apirone_db_version);
    }
}

add_action('plugins_loaded', 'abf_update_db_check');

function abf_enqueue_script()
{
    wp_enqueue_style( 'apirone_style', plugin_dir_url(__FILE__) . 'apirone_style.css' );
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
            $this->method_title = ABF_METHOD_TITLE;
            $this->method_description   = sprintf( 'Start accepting Bitcoins today. No registration/ KYC/ documentation/ keys necessary. Just enter your bitcoin address. Read more <a href="%1$s" target="_blank">how does it work</a>.', 'https://apirone.com/docs/how-it-works' );
            
            // Load the settings from DB
            $this->abf_init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->address = $this->get_option('address');
            $this->enabled = $this->get_option('enabled');
            $this->debug = $this->get_option('debug');
            $this->order_states = $this->get_option('order_states');

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
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this,
                'save_order_states'
            ));
            
            // Payment listener/API hook
            add_action('woocommerce_api_callback_apirone', 'abf_check_response');
            add_action('woocommerce_api_check_payment', 'abf_ajax_response');
            
            if (!$this->abf_is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function generate_order_states_html()
        {
            $this->abf_logger('[Info] Entered generate_order_states_html()...');

            ob_start();
            $apirone_statuses = array('new'=>'New Order', 'partiallypaid'=>'Partially paid or waiting for confirmations', 'complete'=>'Completed');
            $default_statuses = array('new'=>'wc-on-hold', 'partiallypaid'=>'wc-processing', 'complete'=>'wc-completed');

            $wc_statuses = wc_get_order_statuses();

            ?><tr valign="top">
                <th scope="row" class="titledesc">Order States</th>
                <td class="forminp" id="abf_order_states">
                    <table cellspacing="0"><?php
                            foreach ($apirone_statuses as $apirone_state => $apirone_name) {
                            ?>
                            <tr>
                            <th><?php echo $apirone_name; ?></th>
                            <td>
                                <select name="woocommerce_abf_order_states[<?php echo $apirone_state; ?>]"><?php
                                $order_states = get_option('woocommerce_apirone_settings');
                                $order_states = $order_states['order_states'];
                                foreach ($wc_statuses as $wc_state => $wc_name) {
                                    $current_option = $order_states[$apirone_state];
                                    if (true === empty($current_option)) {
                                        $current_option = $default_statuses[$apirone_state];
                                    }
                                    if ($current_option === $wc_state) {
                                        echo "<option value=\"$wc_state\" selected>$wc_name</option>\n";
                                    } else {
                                        echo "<option value=\"$wc_state\">$wc_name</option>\n";
                                    }
                                }
                                ?></select>
                            </td>
                            </tr><?php
                        }
                        ?></table>
                </td>
            </tr><?php

            $this->abf_logger('[Info] Leaving generate_order_states_html()...');
            return ob_get_clean();
        }

        public function abf_logger($message)
        {
            if (true === isset($this->debug) && 'yes' == $this->debug) {
                if (false === isset($this->logger) || true === empty($this->logger)) {
                    $this->logger = new WC_Logger();
                }

                $this->logger->add('apirone', $message);
            }
        }

        /**
         * Save order states
         */
        public function save_order_states()
        {
            $this->abf_logger('[Info] Entered save_order_states()...');

            $apirone_statuses = array('new'=>'New Order', 'partiallypaid'=>'Partially paid or waiting for confirmations', 'complete'=>'Completed');

            $wc_statuses = wc_get_order_statuses();

            if (true === isset($_POST['woocommerce_abf_order_states'])) {

                $abf_settings = get_option('woocommerce_apirone_settings');
                /*print_r($abf_settings);*/
                //$order_states = $abf_settings['order_states'];
                $order_states = array();
                foreach ($apirone_statuses as $apirone_state => $apirone_name) {
                    if (false === isset($_POST['woocommerce_abf_order_states'][ $apirone_state ])) {
                        continue;
                    }

                    $wc_state = $_POST['woocommerce_abf_order_states'][ $apirone_state ];


                    if (true === array_key_exists($wc_state, $wc_statuses)) {
                        //print_r('exists');
                        $this->abf_logger('[Info] Updating order state ' . $apirone_state . ' to ' . $wc_state);
                        $order_states[$apirone_state] = $wc_state;
                    }

                }
                //print_r($order_states);
                $abf_settings['order_states'] = $order_states;
                /*print_r($abf_settings);*/
                update_option('woocommerce_apirone_settings', $abf_settings);
            }

            $this->abf_logger('[Info] Leaving save_order_states()...');
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
?><h3><?php _e(APIRONEPAYMENT_TITLE_1, 'woocommerce');?></h3>
  <p><?php _e(APIRONEPAYMENT_TITLE_2, 'woocommerce');?></p>
<?php if ($this->abf_is_valid_for_use()): ?>

        <table class="form-table">

        <?php $this->generate_settings_html(); ?>
    </table><?php else: ?>
        <div class="inline error"><p><strong><?php
                _e('Gateway offline', 'woocommerce');
?></strong>: <?php _e($this->id . ' don\'t support your shop currency', 'woocommerce'); ?></p></div><?php endif;
            
        } // End admin_options()
        
        public function payment_fields() {
            $total = WC()->cart->total;
            $currency = get_woocommerce_currency();
            try {
                $response_btc = $this->abf_convert_to_btc($currency, $total);
                echo '<p class="pwe-eth-pricing-note"><strong>';
                printf( __( 'Payment of %s BTC will be due.', 'abf' ), $response_btc );
                echo '</p></strong>';
            } catch ( \Exception $e ) {
            echo '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">';
            echo '<ul class="woocommerce-error">';
            echo '<li>';
            _e(
                'Unable to provide an order value in BTC at this time.',
                'abf'
            );
            echo '</li>';
            echo '</ul>';
            echo '</div>';
        }
        }

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
                    'description' => __('All callback responses, debugging messages, errors logs store in "wp-content/wc-logs" but as a best practice do not enable this unless you are having issues with the plugin.', 'woocommerce'),
                    'default' => 'no'
                ),
                'order_states' => array(
                    'type' => 'order_states'
                ),                
                'count_confirmations' => array(
                    'title' => __('Minimum confirmations count', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Minimum confirmations count for accepting payment. Must be an integer.', 'woocommerce'),
                    'default' => '1'
                ),
                'merchant' => array(
                    'title' => __('Merchant name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Shows Merchant name in the payment order. If this field is blank then Site Title from General settings will be shown.', 'woocommerce'),
                    'default' => ''
                ),
            );
        }
        
        /**
         * Generate the dibs button link
         */

        public function abf_convert_to_btc($currency, $value)
        {      
            if ($currency == 'BTC') {
                return $value;
            } else { if ( $currency == 'BTC' || $currency == 'USD' || $currency == 'EUR' || $currency == 'GBP') {
            $response_btc = wp_remote_get('https://apirone.com/api/v1/tobtc?currency=' . $currency . '&value=' . $value);
            return round($response_btc['body'], 8);      
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

        public function abf_getKey($order_id){
            global $wpdb;
            global $woocommerce;
            $order = new WC_Order($order_id);
            $query =
               "SELECT * 
                FROM {$wpdb->prefix}woocommerce_apirone_secret";
            $key = $wpdb->get_results($query); 
            return md5($key->mdkey . $order->order_key);
        }

        //checks that order has sale
        public function abf_getSales($order_id, $address = NULL) {
            global $wpdb;
            if (is_null($address)) {
                $query = $wpdb->prepare(
                   "SELECT * 
                    FROM {$wpdb->prefix}woocommerce_apirone_sale
                    WHERE order_id = %d",
                    $order_id
                );
            } else {
                $query = $wpdb->prepare(
                   "SELECT * 
                    FROM {$wpdb->prefix}woocommerce_apirone_sale
                    WHERE order_id = %d AND address = %s",
                    $order_id,
                    $address
                );
            }
            $sales = $wpdb->get_results($query);      
            return $sales;
        }

        public function abf_getTransactions($order_id){
            global $wpdb;
            $query = $wpdb->prepare(
               "SELECT * 
                FROM {$wpdb->prefix}woocommerce_apirone_transactions
                WHERE order_id = %d",
               $order_id
            );
            $transactions = $wpdb->get_results($query);
            return $transactions;
        } 

        public function abf_addTransaction($order_id, $thash, $input_thash, $paid, $confirmations) {
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
            $wpdb->delete($transactions_table, array(
                'input_thash' => $input_thash
            ));
            return $wpdb->insert($transactions_table, array(
                'time' => current_time('mysql'),
                'confirmations' => $confirmations,
                'paid' => $paid,
                'order_id' => $order_id,
                'thash' => $thash,
                'input_thash' => $input_thash
            ));
        }

        public function abf_addSale($order_id, $address) {
            global $wpdb;
            $sale_table = $wpdb->prefix . 'woocommerce_apirone_sale';
            return $wpdb->insert($sale_table, array(
                'time' => current_time('mysql'),
                'order_id' => $order_id,
                'address' => $address
            ));
        }   

        public function abf_updateTransaction($where_input_thash, $where_paid, $confirmations, $thash = NULL, $where_order_id = NULL, $where_thash = 'empty') {
            global $wpdb;
            
            if (!is_null($thash)) {
                $thash = esc_sql($thash);
            }
            if (!is_null($where_order_id)) {
                $where_order_id = esc_sql($where_order_id);
            }
            $transactions_table = $wpdb->prefix . 'woocommerce_apirone_transactions';
            $where_paid = esc_sql($where_paid);
            $confirmations = esc_sql($confirmations);
            $where_thash = esc_sql($where_thash);
            $where_input_thash = esc_sql($where_input_thash);

            if (is_null($thash) || is_null($where_order_id)) { 
                $update_query = array(
                    'time' => current_time('mysql'),
                    'confirmations' => $confirmations,
                );
                $where = array(
                    'paid' => $where_paid,
                    'thash' => $where_thash,
                    'input_thash' => $where_input_thash
                );
            } else{
                $update_query = array(
                    'time' => current_time('mysql'),
                    'confirmations' => $confirmations
                );
                $where = array(
                    'input_thash' => $where_input_thash,
                    'thash' => $thash
                );
            }
            return $wpdb->update($transactions_table, $update_query, $where);
        }     
        
        public function abf_sale_exists($order_id, $input_address)
        {
            $sales = $this->abf_getSales($order_id, $input_address);
            if ($sales[0]->address == $input_address) {return true;} else {return false;};
        }

        // function that checks what user complete full payment for order
        public function abf_check_remains($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $total = $this->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
            $transactions = $this->abf_getTransactions($order_id);
            $remains = 0;
            $total_paid = 0;
            $total_empty = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->thash == "empty") $total_empty+=$transaction->paid;
                $total_paid+=$transaction->paid;
            }
            $total_paid/=1E8;
            $total_empty/=1E8;
            $remains = $total - $total_paid;
            $remains_wo_empty = $remains + $total_empty;
            if ($remains_wo_empty > 0) {
                return false;
            } else {
                return true;
            };
        }

        public function abf_remains_to_pay($order_id)
        {   
            global $woocommerce;
            $order = new WC_Order($order_id);
            $transactions = $this->abf_getTransactions($order_id);
            $total_paid = 0;
            foreach ($transactions as $transaction) {
                $total_paid+=$transaction->paid;
            }
            $response_btc = $this->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
            $remains = $response_btc - $total_paid/1E8;
            if($remains < 0) $remains = 0;  
            return $remains;
        }

public function abf_check_data($apirone_order){
    $abf_check_code = 100; //No value
    if (!empty($apirone_order['value'])) {
        $abf_check_code = 101; //No input address
        if (!empty($apirone_order['input_address'])) {
            $abf_check_code = 102; //No order_id
            if (!empty($apirone_order['orderId'])) {
                $abf_check_code = 103; //No secret
                if (!empty($apirone_order['secret'])) {
                    $abf_check_code = 104; //No confirmations
                    if ($apirone_order['confirmations']>=0) {
                        $abf_check_code = 105; //No key
                        if (!empty($apirone_order['key'])) {
                            $abf_check_code = 106; //No input transaction hash
                            if (!empty($apirone_order['input_transaction_hash'])) {
                                $abf_check_code = 200; //No transaction hash
                                if (!empty($apirone_order['transaction_hash'])){
                                    $abf_check_code = 201; //All data is ready
                                }                           
                            }
                        }                       
                    }                   
                }
            }
        }
    }
    return $abf_check_code;
}

public function abf_transaction_exists($thash, $order_id){
    $transactions = $this->abf_getTransactions($order_id);
    $flag = false;
        foreach ($transactions as $transaction) {
        if($thash == $transaction->thash){
            $flag = true; // same transaction was in DB
            break;
        }
    }
    return $flag;
}

public function abf_input_transaction_exists($input_thash, $order_id){
    $transactions = $this->abf_getTransactions($order_id);
    $flag = false;
        foreach ($transactions as $transaction) {
        if($input_thash == $transaction->input_thash){
            $flag = true; // same transaction was in DB
            break;
        }
    }
    return $flag;
}

public function secret_is_valid($secret, $order_id){
    $flag = false;
    if($secret == $this->abf_getKey($order_id)){
        $flag = true;
    }
    return $flag;
}

public function key_is_valid($key, $order_id){
    global $woocommerce;
    $order = new WC_Order($order_id);
    $flag = false;
    if($key == $order->order_key){
        $flag = true;
    }
    return $flag;
}

public function confirmations_is_ok($confirmations){
    $flag = false;
    if($confirmations >= ABF_COUNT_CONFIRMATIONS) {
        $flag = true;
    }
    return $flag;
}

public function abf_validate_data($apirone_order){
    $abf_check_code = 300; //No sale exists
    if ($this->abf_sale_exists($apirone_order['orderId'], $apirone_order['input_address'])) {
        $abf_check_code = 301; //key is invalid
        if ($this->key_is_valid($apirone_order['key'], $apirone_order['orderId'])) {
            $abf_check_code = 302; //Secret is invalid
            if ($this->secret_is_valid($apirone_order['secret'], $apirone_order['orderId'])) {
                $abf_check_code = 400; //validate complete
            }
        }
    }
    return $abf_check_code;
}

public function abf_empty_transaction_hash($apirone_order){
    if ($this->abf_input_transaction_exists($apirone_order['input_transaction_hash'],$apirone_order['orderId'])) {
        $this->abf_updateTransaction(
            $apirone_order['input_transaction_hash'],
            $apirone_order['value'],
            $apirone_order['confirmations']
        );
        $abf_check_code = 500; //update existing transaction
    } else {
        $this->abf_addTransaction(
            $apirone_order['orderId'],
            'empty',
            $apirone_order['input_transaction_hash'],
            $apirone_order['value'],
            $apirone_order['confirmations']
        );
        $abf_check_code = 501; //insert new transaction in DB without transaction hash
    }
    return $abf_check_code;
}

public function abf_calculate_payamount($apirone_order){
    $transactions = $this->abf_getTransactions($apirone_order['orderId']);
    $payamount = 0;
    foreach ($transactions as $transaction) {
        if($transaction->thash != 'empty')
            $payamount += $transaction->paid;
    }
    return $payamount;
}

public function abf_skip_transaction($apirone_order){
    $abf_check_code = NULL;
    if(($apirone_order['confirmations'] >= ABF_MAX_CONFIRMATIONS) && (ABF_MAX_CONFIRMATIONS != 0)) {// if callback's confirmations count more than ABF_MAX_CONFIRMATIONS we answer *ok*
        $abf_check_code="*ok*";
        $this->abf_logger('[Info] Skipped transaction: ' .  $apirone_order['transaction_hash'] . ' with confirmations: ' . $apirone_order['confirmations']);
        };
        return $abf_check_code;
}

public function abf_take_notes($apirone_order){
    global $woocommerce;
    $order = new WC_Order($apirone_order['orderId']);
    $response_btc = $this->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);
    $payamount = $this->abf_calculate_payamount($apirone_order);
    $notes  = 'Input Address: ' . $apirone_order['input_address'] . '; Transaction Hash: ' . $apirone_order['transaction_hash'] . '; Payment: ' . number_format($apirone_order['value']/1E8, 8, '.', '') . ' BTC; ';
    $notes .= 'Total paid: '.number_format(($payamount)/1E8, 8, '.', '').' BTC; ';
    if (($payamount)/1E8 < $response_btc)
        $notes .= 'User trasfrer not enough money in your shop currency. Waiting for next payment; ';
    if (($payamount)/1E8 > $response_btc)
        $notes .= 'User trasfrer more money than You need in your shop currency; ';
    $notes .= 'Order total: '.$response_btc . ' BTC; ';
    if ($this->abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment it's not completed 
        $notes .= 'Successfully paid.';
    }
    return $notes;
}

public function abf_filled_transaction_hash($apirone_order){
    global $woocommerce;
    $order = new WC_Order($apirone_order['orderId']);
    $order_states = get_option('woocommerce_apirone_settings');
    $order_states = $order_states['order_states'];  
        if($this->abf_transaction_exists($apirone_order['transaction_hash'],$apirone_order['orderId'])){
            $abf_check_code = 600;//update transaction
            $this->abf_updateTransaction(
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations'],
                $apirone_order['transaction_hash'],
                $apirone_order['orderId']
            ); 
        } else {
            $abf_check_code = 601; //small confirmations count for update tx
            if ($this->confirmations_is_ok($apirone_order['confirmations'])) {
            $this->abf_addTransaction(
                $apirone_order['orderId'],
                $apirone_order['transaction_hash'],
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations']
            );
            $notes = $this->abf_take_notes($apirone_order);
            $order->add_order_note($notes);
            $abf_check_code = '*ok*';//insert new TX with transaction_hash
            if ($this->abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment is not completed
                $complete_order_status = $order_states['complete'];
                $order->update_status($complete_order_status, __('Payment complete', 'woocommerce'));
                WC()->cart->empty_cart();
                $order->payment_complete();
            } else{
                $partiallypaid_order_status = $order_states['partiallypaid'];
                $order->update_status($partiallypaid_order_status, __('Partially paid', 'woocommerce'));
            }
        }
    }
    return $abf_check_code;
}
        
        public function abf_generate_form($order_id)
        {
            global $woocommerce;
            
            $order = new WC_Order($order_id);
            
            if ($this->testmode == 'yes') {
                $apirone_adr = $this->testurl;
            } else {
                $apirone_adr = $this->liveurl;
            }
            
            $_SESSION['testmode'] = $this->testmode;
            if($this->get_option('merchant')){
                $merchant = sanitize_text_field($this->get_option('merchant'));
            } else {
                $merchant = get_bloginfo('name');
            }
            
            $response_btc = $this->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);

            if($this->enabled === 'no'){
                return "Payment method disabled";
            }

            if ($this->abf_is_valid_for_use() && $response_btc > 0) {
                /**
                 * Args for Forward query
                 */

                $sales = $this->abf_getSales($order_id);
                
                if ($sales == null) {
                    $secret = $this->abf_getKey($order_id);
                    $order_states = get_option('woocommerce_apirone_settings');
                    $order_states = $order_states['order_states'];
                    $new_order_status = $order_states['new'];
                    $order->update_status($new_order_status, __('New order generated', 'woocommerce')); 
                    $args = array(
                        'address' => $this->address,
                        'callback' => urlencode(ABF_SHOP_URL . '?wc-api=callback_apirone&key=' . $order->order_key . '&secret=' . $secret . '&order_id=' . $order_id)
                    );
                    $apirone_create = $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'];
                    $response_create = wp_remote_get( $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'] );
                    if(!is_null($response_create['body'])){
                        $response_create = json_decode($response_create['body'], true);
                    } else{
                        echo "No Input Address from Apirone :(";
                    }
                    if ($response_create['input_address'] != null){
                        $this->abf_addSale($order_id, $response_create['input_address']);
                    } else {
                        echo "No Input Address from Apirone :(";
                    }
                } else {
                    $response_create['input_address'] = $sales[0]->address;
                }
                if ($response_create['input_address'] != null){
                echo '<div class="abf-frame">
        <div class="abf-header">
            <div>
                <div class="abf-ash1"><img src="' . esc_url( plugins_url( 'logo.svg', __FILE__ ) ) . '" alt=""></div>
            </div>
            <div style="text-align: center; background-color:#fff;"><span class="abf-qr">
               <img class="abf-img-height" src="https://apirone.com/api/v1/qr?message=' . urlencode("bitcoin:" . $response_create['input_address'] . "?amount=" . $response_btc . "&label=Apirone") . '">
               </span>
            </div> 
        </div>
        <div class="abf-form">
            <div class="abf-ash1">
                Please send <strong><span class="abf-totalbtc">' . $response_btc . '</span></strong> BTC
                to address:
            </div>
            <div class="abf-address abf-topline abf-ash2 abf-input-address">'. $response_create['input_address'] . '</div>
            <div class="abf-data abf-topline">
                <div class="abf-list">
                    <div class="abf-list-item">
                        <div class="abf-label">Merchant:</div>
                        <div class="abf-value">' . $merchant . '</div>
                    </div>
                    <div class="abf-list-item">
                        <div class="abf-label">Amount to pay:</div>
                        <div class="abf-value"><span class="abf-totalbtc">' . $response_btc . '</span> BTC</div>
                    </div>
                    <div class="abf-list-item">
                        <div class="abf-label">Arrived amount:</div>
                        <div class="abf-value"><span class="abf-arrived">0.00000000</span> BTC</div>
                    </div>
                    <div class="abf-list-item">
                        <div class="abf-label">Remains to pay:</div>
                        <div class="abf-value"><b><span class="abf-remains">' . $response_btc . '</span> BTC</b></div>
                    </div>                                                           
                    <div class="abf-list-item">
                        <div class="abf-label">Date:</div>
                        <div class="abf-value">'.date('Y-m-d').'</div>
                    </div>
                    <div class="abf-list-item abf-tx-block">
                        <div class="abf-label">Transaction(s):</div>
                        <div class="abf-value abf-tx">
                            No TX yet
                        </div>
                    </div>
                    <div class="abf-list-item">
                        <div class="abf-label">Status:</div>
                        <div class="abf-value"><b><span class="abf-status">Loading data</span></b><div class="abf-refresh"></div></div>
                    </div>
                </div>
            </div>
            <div class="abf-info">
                <p>If you are unable to complete your payment, you can try again later to place a new order with saved cart.<br>You can pay partially, but please do not close this window before next payment to prevent loss of bitcoin address and invoice number.
                </p>
                <p class="abf-right"><a href="https://apirone.com/" target="_blank"><img src="' . esc_url( plugins_url( 'apirone_logo.svg', __FILE__ ) ) . '"  alt=""></a></p>
                <div class="abf-clear"></div>
            </div>
        </div>
    </div>
    <div class="abf-clear"></div>';
                }
                if ((ABF_DEBUG == "yes") && !is_null($response_create)) {
                    if($response_create['callback_url'])
                        $this->abf_logger('[Info] Address: ' . $response_create['input_address'] . ' Callback: ' . $response_create['callback_url']);
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
        $apirone = new WC_APIRONE;
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
            $order = wc_get_order($safe_order);
            if (!empty($safe_order)) {
                $transactions = $apirone->abf_getTransactions($safe_order);
            }
            $empty = 0;
            $value = 0;
            $paid_value = 0;
            $payamount = 0;
            $innetwotk_pay = 0;
            $confirmed = '';
            foreach ($transactions as $transaction) {
                if ($transaction->thash == "empty"){
                    $empty = 1; // has empty value in thash
                    $value = $transaction->paid;
                    $innetwotk_pay += $transaction->paid;
                } else{
                    $paid_value = $transaction->paid;
                    $confirmed = $transaction->thash;
                    $payamount += $transaction->paid;
                } 
                $alltransactions[] = array('thash' => $transaction->thash, 'input_thash' => $transaction->input_thash, 'confirmations' => $transaction->confirmations);      
            }
            if ($order == '') {
                echo 'Error';
                exit;
            }
            $order_states = get_option('woocommerce_apirone_settings');
            $order_states = $order_states['order_states'];
            $complete_order_status = $order_states['complete'];
            $response_btc = $apirone->abf_convert_to_btc(get_option('woocommerce_currency'), $order->order_total);         
            if ('wc-'.$order->status == $complete_order_status && $apirone->abf_check_remains($safe_order)) {
                $status ="complete";
            } else {
                if($empty){
                $status ="innetwork";
                } else{
                $status ="waiting";
            }
            }
            $payamount = number_format($payamount/1E8, 8, '.', '');
            $innetwotk_pay = number_format($innetwotk_pay/1E8, 8, '.', '');
            $remains_to_pay = number_format($apirone->abf_remains_to_pay($safe_order), 8, '.', '');
            $ouput = array('total_btc' => $response_btc, 'innetwork_amount' => $innetwotk_pay, 'arrived_amount' => $payamount, 'remains_to_pay' => $remains_to_pay, 'transactions' => $alltransactions, 'status' => $status, 'count_confirmations' => ABF_COUNT_CONFIRMATIONS);
            echo json_encode($ouput);
            exit;
        }
    }

    /**
     * Check response
     */
 function abf_check_response(){
    $apirone = new WC_APIRONE();
    global $woocommerce;
    $apirone->abf_logger('[Info] Callback' . $_SERVER['REQUEST_URI']);

    $safe_key = $_GET['key'];
    $safe_secret = sanitize_text_field($_GET['secret']);
    $safe_order_id = intval( $_GET['order_id'] );
    $safe_confirmations = intval( $_GET['confirmations'] );
    $safe_value = intval( $_GET['value'] );
    $safe_input_address = sanitize_text_field($_GET['input_address']);
    $safe_transaction_hash = sanitize_text_field($_GET['transaction_hash']);
    $safe_input_transaction_hash = sanitize_text_field($_GET['input_transaction_hash']);

    sanitize_key( $safe_key );
    if ( ! $safe_key ) {
        $safe_key = '';
    }
    if ( strlen( $safe_secret ) > 32 ) {
        $safe_secret = substr( $safe_secret, 0, 32 );
    }
    if ( strlen( $safe_key ) > 25 ) {
        $safe_key = substr( $safe_key, 0, 25 );
    }
    if ( $safe_order_id == 'undefined' ) {
         $safe_order_id = '';
    }
    if ( strlen( $safe_order_id ) > 25 ) {
        $safe_order_id = substr( $safe_order_id, 0, 25 );
    }
    if ( strlen( $safe_confirmations ) > 5 ) {
        $safe_confirmations = substr( $safe_confirmations, 0, 5 );
    }
    if ( ! $safe_confirmations ) {
        $safe_confirmations = 0;
    }
    if ( strlen( $safe_value ) > 16 ) {
        $safe_value = substr( $safe_value, 0, 16 );
    }
    if ( ! $safe_value ) {
        $safe_value = '';
    }
    if ( strlen( $safe_input_address ) > 64 ) {
        $safe_input_address = substr( $safe_input_address, 0, 64 );
    }
    if ( ! $safe_input_address ) {
        $safe_input_address = '';
    }
    if ( strlen( $safe_transaction_hash ) > 65 ) {
        $safe_transaction_hash = substr( $safe_transaction_hash, 0, 65 );
    }
    if ( ! $safe_transaction_hash ) {
        $safe_transaction_hash = '';
    }
    if ( strlen( $safe_input_transaction_hash ) > 65 ) {
        $safe_input_transaction_hash = substr( $safe_input_transaction_hash, 0, 65 );
    }
    if ( ! $safe_input_transaction_hash ) {
        $safe_input_transaction_hash = '';
    }
    $apirone_order = array(
        'value' => $safe_value,
        'input_address' => $safe_input_address,
        'orderId' => $safe_order_id, // order id
        'secret' => $safe_secret,
        'confirmations' => $safe_confirmations,
        'key' => $safe_key,
        'input_transaction_hash' => $safe_input_transaction_hash,
        'transaction_hash' => $safe_transaction_hash
    );
    $check_data_score = $apirone->abf_check_data($apirone_order);
    $abf_api_output = $check_data_score;
    if( $check_data_score >= 200 ){
        $validate_score += $apirone->abf_validate_data($apirone_order);
        $abf_api_output = $validate_score;
        if ($validate_score == 400) {
            if($check_data_score == 200){
                $data_action_code = $apirone->abf_empty_transaction_hash($apirone_order);
            }
            if($check_data_score == 201){
                $data_action_code = $apirone->abf_filled_transaction_hash($apirone_order);
            }
            $abf_api_output = $data_action_code;
        }
    }
    if(ABF_DEBUG == "yes") {
        print_r($abf_api_output);//global output
    } else {
        if($abf_api_output === '*ok*') {
            echo '*ok*';   
        } else{
            echo $apirone->abf_skip_transaction($apirone_order);
        }
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
