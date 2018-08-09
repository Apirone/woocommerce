# Woocommerce plugin - Bitcoin forwarding

Requires PHP at least: 5.2

Requires at least WooCommerce: 4.0
Tested up to: 4.9.7
License: GPLv2 or later

## Description

Use the Apirone plugin to receive payments in bitcoin from all around the world. We support Bitcoin SegWit protocol which has priority on the network and lower fees.

Key features:

* We transfer your payment directly into your bitcoin wallet ( we do not hold client money)
* You do not need to complete a KYC/Documentation to start using our plugin. No third-party accounts during the process, use your own wallet.
* We will charge a fixed fee (0.0002 BTC/transaction) which does not depend on the amount of the order. No fee for amounts less than 100,000 Satoshi.
* White label processing (your online store accepts payments without redirects, iframes, advertisements, logo, etc.)
* There is no restriction on the customer's country of residence. This plugins works well all over the world.
* We support the Tor network
* You can create unlimited number of requests. 



## How does it works?

1. The Buyer prepares the order and click on the “pay with bitcoins” button.
1. The store sends its bitcoin address and the callback URL of its site to the Apirone API Server. He will receive immediately a new bitcoin, a QR code and also the amount of the order converted into Bitcoin.
1. Then, the buyer scans the QR code and pays for the order. This transaction goes to blockchain

* Our server immediately intercepts it and sends a callback to the URL address provided by the store. This is only the first callback regarding this unconfirmed transaction. It is too early to deliver the order. We notify the store that a payment order has been initiated.

* Now we look forward to the confirmation on the network. Usually, it will take about ten minutes. Usually, it takes about 10 minutes.

* After the first confirmation, our server transfers the bitcoin to the destination address provided by the store and makes a second callback. The buyer can now receive his products.

* The store completes the transaction.


The plugin work with our own RESTful API – Bitcoin Forwarding. For more details on how does it works, visit the Apirone website, in the section “How does it work” https://apirone.com/docs/how-it-works
Multilingual interface available in German, English, French and Russian.

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
