<?php
/*
Plugin Name: WooCommerce apirone gateway
Plugin URI: http://apirone.com
Description: Bitcoin Bank Gateway for Woocoomerce.
Version: 1.0
Author: Apirone LLC
Author URI: http://www.apirone.com
    Copyright: © 2017 Apirone.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

session_start();
require_once 'config.php';//configuration files
require_once 'woocommerce-payment-name.php'; //payment gateway constants

function logger($var) {
    if ($var) {
        $date = '<---------- '.date('Y-m-d H:i:s')." ---------->\n";
        $result = $var;
        if (is_array($var) || is_object($var)) {
            $result = print_r($var, 1);
        }
        $result .= "\n";
        $path = 'wp-content/plugins/woocommerce-apirone/apirone-payment.log';//defaults wp-content/plugins/woocomerce-apirone/
        error_log($date.$result, 3, $path);
        return true;
    }
    return false;
}

function Zumper_widget_enqueue_script() {   
    wp_enqueue_script( 'my_custom_script', plugin_dir_url( __FILE__ ) . 'apirone.js', array('jquery'), '1.0' );
}
add_action('wp_enqueue_scripts', 'Zumper_widget_enqueue_script');

if (DEBUG) {
	// Display errors
	ini_set('display_errors', 1);
	error_reporting(E_ALL & ~E_NOTICE);
}


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_apironepayment', 0);
function woocommerce_apironepayment(){
		if (!class_exists('WC_Payment_Gateway'))
		return; // if the WC payment gateway class is not available, do nothing
	if(class_exists('WC_APIRONE'))
		return;

class WC_APIRONE extends WC_Payment_Gateway{
	public function __construct(){
		$plugin_dir = plugin_dir_url(__FILE__);

		global $woocommerce;

		$this->id = APIRONEPAYMENT_ID;
		$this->has_fields = false;
		$this->liveurl = PROD_URL;
		$this->testurl = TEST_URL;
		$this->icon = ICON;

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title = $this->get_option('title');
		$this->address = $this->get_option('address');
		$this->testmode = $this->get_option('testmode');
		$this->description = $this->get_option('description');

		// Actions
		add_action('valid-apironepayment-standard-ipn-reques', array($this, 'successful_request') );
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		//Save our GW Options into Woocommerce
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );  

		// Payment listener/API hook
		add_action('woocommerce_api_callback_apirone', 'check_response');
		add_action('woocommerce_api_check_payment', 'ajax_response');

		if (!$this->is_valid_for_use()){
			$this->enabled = false;
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country. Defaults it's USD
	 */
	function is_valid_for_use(){
		if (!in_array(get_option('woocommerce_currency'), array('USD'))){
			return false;
		}
		return true;
	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() {
		?>
		<h3><?php _e(APIRONEPAYMENT_TITLE_1, 'woocommerce'); ?></h3>
		<p><?php _e(APIRONEPAYMENT_TITLE_2, 'woocommerce'); ?></p>

	  <?php if ( $this->is_valid_for_use() ) : ?>

		<table class="form-table">

		<?php
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    ?>
    </table><!--/.form-table-->

    <?php else : ?>
		<div class="inline error"><p><strong><?php _e('Gateway offline', 'woocommerce'); ?></strong>: <?php _e($this->id.' don\'t support your shop currency', 'woocommerce' ); ?></p></div>
		<?php
			endif;

    } // End admin_options()

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields(){
		$this->form_fields = array(
				'enabled' => array(
					'title' => __('On/off', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('On', 'woocommerce'),
					'default' => 'yes'
				),
				'address' => array(
					'title' => __('Destination Bitcoin address', 'woocommerce'),
					'type' => 'text',
					'description' => __('Your Destination Bitcoin address', 'woocommerce'),
					'default' => ''
				),
				'testmode' => array(
					'title' => __('Test Mode', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('On', 'woocommerce'),
					'description' => __('This is test mode without money transfer.', 'woocommerce'),
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Payment name', 'woocommerce'),
					'type' => 'text',
					'description' => __( 'Payment name for backend', 'woocommerce' ),
					'default' => __($this->id, 'woocommerce')
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Description payment method for backend', 'woocommerce' ),
					'default' => 'Payment via '.$this->id
				)
			);
	}

	/**
	 * Generate the dibs button link
	 */
	static function convert_to_btc($currency, $value){
			$apironeConvertTotalCost = curl_init();
			//$apirone_tobtc = $apirone_adr . 'tobtc?currency='.$args['currency'].'&value='.$args['value'];
			$apirone_tobtc = 'https://blockchain.info/tobtc?currency='.$currency.'&value='.$value;
			curl_setopt_array($apironeConvertTotalCost, array(
			    CURLOPT_URL => $apirone_tobtc,
			    CURLOPT_RETURNTRANSFER => 1
			));
			$response_btc = curl_exec($apironeConvertTotalCost);
			curl_close($apironeConvertTotalCost);
			if (DEBUG) {
				logger('Request: ' . $apirone_tobtc . ': Response: ' . $response_btc);
			}
			return $response_btc;
	}

	public function generate_form($order_id){
		global $woocommerce;

		$order = new WC_Order( $order_id );

		if ($this->testmode == 'yes'){
			$apirone_adr = $this->testurl;
		}
		else{
			$apirone_adr = $this->liveurl;
		}

		$_SESSION['testmode'] = $this->testmode;

		for ($i=0; $i++<10;) {

			$response_btc = $this->convert_to_btc('USD', $order->order_total);
			/**
			 * Args for Forward query
			 */
			$args = array(
				'address' => $this->address,
				'callback' => urlencode(SHOP_URL . '?wc-api=callback_apirone&key='.$order->order_key.'&order_id='.$order_id),
			);
			$apirone_create = $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'];

			$apironeCurl = curl_init();
			curl_setopt_array($apironeCurl, array(
			    CURLOPT_URL => $apirone_create,
			    CURLOPT_RETURNTRANSFER => 1
			));
			$response_create = curl_exec($apironeCurl);
			curl_close($apironeCurl);
			$response_create = json_decode($response_create, true);
			//print_r($response_create);
			echo '<div class="woocommerce"><ul class="order_details"><li>Please send exactly <strong>'. $response_btc .' BTC</strong> </li><li>for this address:<strong>'. $response_create['input_address'];
			echo '</strong></li><li><img src="https://bitaps.com/api/qrcode/png/'. urlencode( "bitcoin:".$response_create['input_address']."?amount=".$response_btc."&label=Apirone pay" ) .'"></li><li class="apirone_result"></li></ul></div>' ;

			if (DEBUG) {
				logger('Request: ' . $apirone_create . ': ' . print_r($args,true).'Response: ' . $response);
			}
			if($response_create != '') break;
		}
	}

	/**
	 * Process the payment and return the result
	 */
	function process_payment($order_id){
		$order = new WC_Order($order_id);
		return array(
			'result' => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}

	/**
	 * Receipt page
	 */
	function receipt_page($order){
		echo $this->generate_form($order);
	}
}

function ajax_response(){
	global $woocommerce;
	if (isset($_GET['key']) AND isset($_GET['order'])){
	$order = wc_get_order($_GET['order']);
	if ($order == '') {echo 'Error'; exit;}
	if ($order->status == 'processing') {echo 'Payment accepted. Thank You!';}
	else echo "Waiting for payment...";
	exit;
}
}
	/**
	 * Check response
	 */

	function check_response() {
		global $woocommerce;
		if (isset($_GET['confirmations']) AND isset($_GET['value'])){
			if ($_SESSION['testmode'] == 'yes'){
				$apirone_adr = TEST_URL;
			}
			else{
				$apirone_adr = PROD_URL;
			}
			$apirone_order = array(
					'confirmations' => $_GET['confirmations'],
					'orderId' => $_GET['order_id'], // order id
					'key' => $_GET['key'],
					'value' => $_GET['value'],
					'transaction_hash' => $_GET['transaction_hash'],
					'input_address' => $_GET['input_address'],
				);
			print_r($apirone_order);
			if ($apirone_order['confirmations'] > 1) {
				$order = new WC_Order($apirone_order['orderId']);
				//echo $order->order_key;
				if($apirone_order['key'] == $order->order_key){
				$response_btc = WC_APIRONE::convert_to_btc('USD', $order->order_total);
				$notes  = 'Input Address: '. $apirone_order['input_address'] .', Transaction ID: '.$apirone_order['transaction_hash'];
				if($response_btc > $apirone_order['value']) $notes .= '. User trasfrer not enough money.';
				if($response_btc < $apirone_order['value']) $notes .= '. User trasfrer more money than You need.';
				$order->update_status('processing', __('Payment complete', 'woocommerce'));
				WC()->cart->empty_cart();
				$order->payment_complete();
				//wp_redirect($this->get_return_url( $order ));
				$order -> add_order_note($notes);
				echo "*ok*";
				}
				exit;
			} else {
				exit;
			}
		}
	}

/**
 * Add apirone the gateway to WooCommerce
 */
function add_apirone_gateway($methods){
	$methods[] = 'WC_APIRONE';
	return $methods;
}
//http://demo.dvit.pro?wc-api=callback_apirone&key=wc_order_59e8921bcade7&order_id=22&value=100000000&input_address=1E2VSRsaW3Kb1gDkdRUGDo6knAKfi9iYsb&confirmations=1&transaction_hash=0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098&input_transaction_hash=4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b&destination_address=1LisLsZd3bx8U1NYzpNHqpo8Q6UCXKMJ4z
add_filter('woocommerce_payment_gateways', 'add_apirone_gateway');
}