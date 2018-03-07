=== Bitcoin forwarding - Apirone.com gateway ===
Contributors: apirone
Donate link: https://apirone.com
Tags: accept bitcoin, bitcoins, btc, forwarding, payment, processing, acquiring, receive bitcoins, pay via cryptocurrency, crypto, bitcoin wallet
Stable tag: 5.6
Requires PHP: 5.6
Requires at least: 5.2
Tested up to: 7.1.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoins and forward payments to your wallet directly. No account /KYC /registration necessary. Bitcoins only, no fiat money. Apirone.com Gateway for Woocommerce.

== Description ==

Use Apirone’s plugin to accept bitcoin payments from customers around the World. We support Bitcoin SegWit protocol. These transactions have priority and less Bitcoin network fee.

Key features:

* Payments forward directly into your bitcoin wallet (we do not hold your money)
* No KYC/documentation necessary
* Fixed Fee 0.0002 Btc (flat rate for any amount forever, no fee for amounts less than 100.000 Satoshi)
* White label processing (your online shop accept payments directly without redirects, iframes, payment advertisement, etc.)
* Around the world
* TOR network support
* Unlimited count of your requests (generate thousands of bitcoin addresses for thousands of your customers)


How it works?

1. The Buyer prepared the order and click to pay via bitcoins.
1. The Store sends bitcoin address and site callback URL to Apirone API Server. The Store receive new bitcoin address, QR code and converted amount to BTC for payment.
1. Buyer scan QR code and pay for the order. This transaction goes to the blockchain.
* Our Server immediately got it and send a callback to supplied Store URL. Now it's first callback about the unconfirmed transaction. It's too early to pass order from Store to Buyer. We just notify that payment initiated.
* Waiting for payment confirmation on the network. Usually, it will take about ten minutes.
* Got it. After 1 confirmation our Server forward confirmed bitcoins to Store's destination address and do the second callback. Now the Buyer gets the desired order.
* Store finished order and ready for next customers.

Everyone can accept bitcoin payments!

== Installation ==

This Plugin requires Woocommerce. Please make sure you have Woocommerce installed.


Installation via WordPress Plugin Manager:

1. Go to WordPress Admin panel » Plugins » Add New in admin panel.
1. Enter "Bitcoin forwarding - Apirone.com gateway" in search box.
1. Click Install Now.
1. Open menu: Plugins » find WooCommerce Apirone gateway > cleck edit > choose file "woocommerce-apirone/config.php"
change example.com ("define('SHOP_URL', 'http://example.com');") to your site link. HTTP and HTTPS is important. Click update file.
1. Enter your bitcoin address to Apirone Plugin Settings: Admin » WooCommerce > settings > Checkout tab > Apirone.
Turn "On" checkbox in Plugin on same setting page.



== Frequently Asked Questions ==

= I will get money in USD, EUR, CAD, JPY, RUR... =

No. You will get money in bitcoins only. Customer send bitcoins and we forward it to your wallet.
You can enter bitcoin address of your account of any trade platform and convert bitcoins to fiat money at any time.

= What is Segwit? =

SegWit is the protocol of process by which blocks on a blockchain are made smaller by removing signature data from Bitcoin transactions. These transactions have high priority and less network fee.
You can use as destination address any bitcoin wallet starts by 1, 3 or bc1.

= How can The Store cancell order and return bitcoins? =

This process is fully manual, because you will get all payments to your wallet. And only you cantrol your money.
Contact with customer, ask address and finish deal.
Bitcoin protocol have not refunds, chargebacks or transaction cancellations.

= Fee ? =

A fixed rate fee 0.0002 BTC per transaction, regardless of the amount and the number of transactions. Accept bitcoins for million dollars and pay fixed fee.
We do not take the fee from amounts less than 100000 Satoshi.


== Screenshots ==

1. Config.php file to enter your site URL
2. Apirone bitcoin plugin settings page. Enter your bitcoin address and turn on.
3. Customer choose payment method.
4. Integrated payment details onto page. White label processing. Status of payment in real-time.

== Changelog ==

= 1.0 =
- Initial Revision. Using Bitcoin mainnet with SegWit support.
RestAPI v1.0 https://apirone.com/docs/bitcoin-forwarding-api

