# Woocommerce plugin - Bitcoin forwarding

Requires PHP at least: 5.2

Requires at least WooCommerce: 4.0
Tested up to: 4.9.7
License: GPLv2 or later

## Description

Use Apirone’s plugin to accept bitcoin payments from customers and forward payments to your wallet directly. Bitcoins only, no fiat money. We support Bitcoin SegWit protocol. These transactions have priority and less Bitcoin network fee.

Key features:

* Payments forward directly into your bitcoin wallet (we do not hold your money)
* No KYC/documentation necessary
* Fixed Fee 0.0002 BTC (flat rate for any amount forever, no fee for amounts less than 100,000 Satoshi)
* White label processing (your online shop accept payments directly without redirects, iframes, payment advertisements, etc.)
* Around the world
* TOR network support
* Unlimited count of your requests (generate thousands of bitcoin addresses for thousands of your customers)


## How does it works?

1. The Buyer prepared the order and click to pay via bitcoins.
1. The Store sends bitcoin address and site callback URL to Apirone API Server. The Store receive new bitcoin address, QR code and converted the amount to BTC for payment.
1. Buyer scan QR code and pay for the order. This transaction goes to the blockchain.
* Our Server immediately got it and send a callback to supplied Store URL. Now it's first callback about the unconfirmed transaction. It's too early to pass order from Store to Buyer. We just notify that payment initiated.
* Waiting for payment confirmation on the network. Usually, it will take about ten minutes.
* Got it. After 1 confirmation our Server forward confirmed bitcoins to Store's destination address and do the second callback. Now the Buyer gets the desired order.
* Store finished order and ready for next customers.

Everyone can accept bitcoin payments!



## Installation

This Plugin requires Woocommerce. Please make sure you have Woocommerce installed.


### Installation via WordPress Plugin Manager:

1. Go to WordPress Admin panel > Plugins > Add New in the admin panel.
2. Enter "Apirone Bitcoin Forwarding" in the search box.
3. Click Install Now.
4. Enter your bitcoin address to Apirone Plugin Settings: WooCommerce > Settings > Payments > Apirone.
Turn "On" checkbox in Plugin on the same setting page.
Debug mode saving all responses, debugging messages, errors logs to "apirone-payment.log", but as a best practice do not enable this unless you are having issues with the plugin.
Order's statuses created by default. Change it if needed.
"Minimum confirmations count" is a count of Bitcoin network confirmations. Recommend 3, default 2, minimum 1 conf.

### Installation via WooCommerce FTP Uploader

1. Download https://github.com/Apirone/woocommerce/archive/master.zip
2. Go to WordPress Admin panel » Plugins » Add New in admin panel.
3. Upload zip archive in Upload Plugin page
4. Enter your bitcoin address to Apirone Plugin Settings: WooCommerce > Settings > Payments > Apirone.
Turn "On" checkbox in Plugin on the same setting page.
Debug mode saving all responses, debugging messages, errors logs to "apirone-payment.log", but as a best practice do not enable this unless you are having issues with the plugin.
Order's statuses created by default. Change it if needed.
"Minimum confirmations count" is a count of Bitcoin network confirmations. Recommend 3, default 2, minimum 1 conf.


## Frequently Asked Questions

#### I will get money in USD, EUR, CAD, JPY, RUR...?

No. You will get bitcoins only. Customer sends bitcoins and we forward it to your wallet.
You can enter bitcoin address of your account of any trading platform and convert bitcoins to fiat money at any time.

#### How can The Store cancel order and return bitcoins?

This process is fully manual because you will get all payments to your wallet. And only you control your money.
Contact with the Customer, ask address and finish the deal.

Bitcoin protocol has not refunds, chargebacks or transaction cancellations.

#### Fee

A fixed rate fee 0.0002 BTC per transaction, regardless of the amount and the number of transactions. Accept bitcoins for million dollars and pay the fixed fee.

We do not take the fee from amounts less than 100,000 Satoshi.


## Changelog

= 2.0 =
- Added pre-calculation of amount in Bitcoins.
- Added partial payment ability.
- Formated window for payment.
- Link to transaction(s).
- Status auto-update.
- Total improvement.

= 1.1 =
- Updated exchange rates API. You can use any currency inlcude native bitcoin item price.

= 1.0 =

- Initial Revision. Use Bitcoin mainnet with SegWit support.
RestAPI v1.0 https://apirone.com/docs/bitcoin-forwarding-api



## License

License: GPLv2 or later

License URI: https://www.gnu.org/licenses/gpl-2.0.html
