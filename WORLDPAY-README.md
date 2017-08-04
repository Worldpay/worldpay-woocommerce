Worldpay Online Payments WooCommerce
==================

Worldpay Online Payments Magento WooCommerce - Version 2.0.0

#### Issues
Please see our [support contact information]( https://developer.worldpay.com/jsonapi/faq/articles/how-can-i-contact-you-for-support) to raise an issue.

Features
================
Template form
Card on file
3DS
Authorize only or capture option
PayPal
Giropay
Webhooks

Tested versions..

WooCommerce 2.4

How To use
================

Upload and install plugin zip.
Go to Plugins -> Installed Plugins and Activate 'WooCommerce Worldpay Gateway - Worldpay Online Payments'

Access the Worldpay Settings, at WooCommerce -> Settings -> Checkout -> Worldpay

Add your keys, which you can find in your Worldpay dashboard (Settings -> API keys).

In your Worldpay dashboard, (Settings -> Webhooks) add a webhook to your WooCommerce URL. (Displayed in WooCommerce)

Access the Worldpay Settings, at WooCommerce -> Settings -> Checkout -> Worldpay

Configuration options
================

Testing
=====
When enable will use test keys

Payment Action
=====
Setting to Authorize; will require you to enable authorisations in your Worldpay online dashboard.
You will then be able to capture the payment from your Worldpay Online dashboard.
You can only capture once, of any amount up to the total of the order.

Card-on-file Payment
=====
A reusable token will be generated for the customer which will then be stored. This will allow the customer to reuse cards they've used in the past. They simply need to re-enter their CVC to verify.

Settlement Currency
=====
Choose the settlement currency that you have setup in the Worldpay online dashboard.


Changelog
================
2.0.0
3DS
Authorize Only
Template Form
APMS PayPal and Giropay Added
Settlement Currency

1.0.0
Initial Release
