# Mastercard Payment Gateway Services module for PrestaShop

## Installation in PrestaShop
You can obtain the module by downloading the following zip file: [prestashop-mastercard-1.0.0.zip](http://wiki.ontapgroup.com/download/attachments/5473094/prestashop-mastercard-1.0.0.zip?version=1&modificationDate=1561000810602&api=v2) - the module is licensed using  [OSL 3.0](https://opensource.org/licenses/OSL-3.0).

The module has been tested with the PrestaShop 1.7.4.4 and 1.7.5.0, and PHP 7.1 and 7.2.

Please refer to official Prestashop documentation for general installation guidelines  
[https://addons.prestashop.com/en/content/13-installing-modules](https://addons.prestashop.com/en/content/13-installing-modules)

## General settings

Once you have Mastercard Payment Gateway Service module installed you can configure module from admin panel.

Find the relevant Configure button under your Module Manager:

![](https://lh3.googleusercontent.com/FiRDd14ElKzaekb_SDC2FAbBIuz7cj1Zu61NHBm4YlMRvIoaJJ3JQVSyh7hkxDgl_LgNiMOKBMrKWlVmylwLG6JQskRULoRS2WovPj7X-5FfhOjwgEe4jH5cBpBZRvKW62hIxntq)

Firstly, it’s important to configure your Merchant credentials in TEST mode and make sure that everything works.

Note: that if merchant credentials are not configured correctly, you can not enable any of the modules payment methods.

If the credentials are incorrect for any reason, there will be a warning displayed on the top of the configuration page.

![](https://lh4.googleusercontent.com/bJTT2CnpNOy-EQAkGkAl0uXI9p00tJF7y1KUIJgo5qDx0cBk7o4zomLkFMea-6N8ErRg1ZVPoLvA-2ZzZTI0TLzuC6kUQHKjEi1R7Py0J9ICM3jv11hBVoBTw1ya2jWFrQ35S67c)

  
The General Settings view:

![](https://lh6.googleusercontent.com/vBBb75WoewfdFvjMdC2EpBgHizGZF-Q2NrsRKL2gbvwJ5GhhUUO6t3sYrXjesNTR5bGB4QMAElFpOX3Xb8zLBSxl7IsWWXfE1W0HezsBV3e-T8NOiOdBxucri7dfQdn4aRsZYaj5)

| Name | Description |
|--|--|
| Live Mode | Yes/No. Toggles between Test and Live mode. Both modes have their own set of credential fields which you need to fill separately. It gives you the ability to switch between modes without re-entering your credentials every time. |
| API Endpoint | The API endpoint should be selected based on your account region.|
|Send Line Items |Yes/No. This setting allows you to choose if you want shopping cart data to be sent to MasterCard, this includes product information, grand total, etc. |
|Test Merchant ID / Merchant ID |Your merchant ID. |
|Test API Password / API Password | Your merchant API password.|
|Test Webhook Secret / Webhook Secret | If webhook support is enabled, then enter your webhook secret here.|

## Hosted Checkout integration

The Hosted Checkout model allows you to collect payment details from your payer through an interaction hosted and displayed by the Mastercard Payment Gateway. With this model of integration, you never see or handle payment details directly because these are collected by the hosted payment interface and submitted directly from the payer's browser to the Mastercard Payment Gateway.

![](https://lh6.googleusercontent.com/bXaSTtwkM_4lPsItSJWpB-WEx1185kvt-ciXcM15NMT-HonNw55gPCdXQ6OBiqyZR0GJkV89SykKQFDnuYyqhX2Mr3YMauI7IrqEUJInlgY_16-k-prsU_jyg1sGL9X_gFen2Ogo)

If Hosted Checkout is integrated and enabled for Mastercard Payment Gateway module, then once user will enter required card details on popup and click on submit order, then upon successfully authorization of entered card details, funds will be deducted from user’s account and will be automatically transferred to merchant/seller’s account. It may take some time to get funds credited but this process will be automatic.

Below are list of Hosted Checkout method configuration which you will find in admin:

![](https://lh5.googleusercontent.com/kK6rUf8Kry2NYpbCTojQvvptOMAYPpgXe93ULWhu1ExsP0hSIoRYnhcOqrre3Abnu6zvUBxEhV9sz18eWy4QvLwn51t4KfFIcUaop8jWUFRJGiIFhTp5OCEs4dCR8p8bp3vXqA57)

|Name|Description  |
|--|--|
|Enabled | Two Options are available: <br> **YES** - to enable this payment method for Mastercard Payment Gateway Module <br> **NO** - to disable this payment method |
|Title |Text mentioned here will be appear on front-end checkout page / payment method section. |
| Theme|Effect of entered theme name will apply on Mastercard Payment Gateway popup on which user will enter their card details. |
|Google Analytics Tracking ID |If Analytics tracking ID will be included, then all the order placed using MPGS payment option will be tracked and records will be updated under that Google Analytics account. |
|Order Summary display |Select any One option from below: <br>**Hide**  - to not display any order and card details to user before submitting order <br> **Show**  - to display order and entered card details to user before submitting order <br> **Show (without payment details)**  - to display only order details to user before submitting order |

## Hosted Session integration
Choose the Hosted Session model if you want control over the layout and styling of your payment page, while reducing PCI compliance costs. The Hosted Session JavaScript client library enables you to collect sensitive payment details from the payer in payment form fields, sourced from and controlled by Mastercard Payment Gateway. The gateway collects the payment details in a payment session and temporarily stores them for later use. You can then include a payment session in place of payment details in the transaction request to process a payment.

![](https://lh6.googleusercontent.com/y0ewlEgJA5uJQ53uFVWiM0S_7y4XV3niLADhiYCYZQ4MZPqt1aP7B3Ti2TlDnD0rUlK3PcDOXStn-WS_9JCSnruq2ncL2g4528911eWkEegUmgfuFf29DJqfot39xmkmbV28tkZt)

There are two different payment flow methods under Hosted session integration:

1.  **Purchase (Pay)**  
    If Purchase has been selected for Payment Model, then transaction will be done automatically. After user has entered card detail and submit order, amount of total order will be deducted from user’s card and will be automatically transferred to merchant’s account. It may take some time for reflecting amount into merchant’s account, but the process will be automatic.
2.  **Authorize & Capture**  
    If Authorize & Capture has been selected for Payment Model, then merchant will have to manually process transactions and accept payment amount. Manually process of capturing funds can be done via Prestashop Admin as well as Merchant’s Mastercard Payment Gateway account login.

Below is list of all configuration options you will see under Hosted Session payment method for Mastercard Payment Gateway service Module:

![](https://lh5.googleusercontent.com/Clpgk6oQoimTIVSDXERU7FM7ws1cJakR1s-jQzXP0lngbNL_RmBOVbjc9Z8E2gN45LQCCGabN-nSqkFhDwYqOvqgu3xzqGip9CC2BGxN9DyckYmmlrwp1PJeSjihp6-SRR5TGei-)

|Name|Description|
|--|--|
| Enabled | Two Options are available: <br>**YES**  - to enable this payment method for Mastercard Payment Gateway Module <br>**NO**  - to disable this payment method |
|Title|Text mentioned here will be appear on front-end checkout page / payment method section|
|Payment Model|Select any One option from below: <br>**Purchase**  - Fund will be transferred to merchant account as soon as user’s entered card details has been successfully verified and order is placed. <br>**Authorize & Capture**  - 2 stage process; where once order will place, it will only authorize user’s card details. Payment amount need to be captured manually by merchant. |
|3D Secure |Two Options are available: <br> **YES**  - to add extra layer of security for completing order process. After user will enter card details, it will redirect to user's bank payment gateway for verification. <br> **NO**  - After placing order and entered card details has been verified, the order will be placed. No extra layer of security will be there. |

## Back-office Operations
If Hosted Session has been integrated for Mastercard Payment Gateway Service module and Authorize & Capture payment method has been selected, then Funds need to be captured or refund manually.

### To Capture Funds

Capture Payment is used for processing transaction and getting order funds into merchant’s account.

-   Under Order detail page, when clicking on “Capture Payment” button it will process transactions and amount of order will be transferred to merchant's account.
-   After clicking on “Capture Payment”, page will be load and you will get success message Also, Order status will be changed to “Payment Accept”.

![](https://lh6.googleusercontent.com/goDmDIEHGh9j8eWuMQ1Og-fe61_4NAzWxNUyZ-2_nHajaw91p7s7rpnBUaFxnPwtlqkhCHd-kFWFmyp8juO3T6VXXnHyAI5CNBluO3JyOzcqXZyDDLrCmGnhYr6E6D7PIIml9iqC)

### Void Transaction

Void Transaction is used to cancel order if merchant finds any fraud/suspect in that order. By clicking on “Void Transaction” button, order will be cancelled automatically and amount of order will be credited to user’s card (if payment has been captured).

### Refund Payment

Once Payment has been captured by merchant using Capture Payment option, then for that Order, merchant will find option to refund payment.

-   Select Order for which payment has been captured and now amount needs to be refunded to user.
    
-   Goto that order detail page.
    
-   Check Mastercard Payment Action (Online) tab.
    
-   Here, Full Refund button will be find from where merchant can refund full amount captured for that order.  
    ![](https://lh6.googleusercontent.com/UrwQ5_f1YPD3_guWilw-QrnygcDRMZ20oxLVN2Pt92Kbm03NEFKYo-bRQnOFYem8Xu4XzDlGh-_iEH2G9mDgmzEDYDHHeqfBPTFD2q7ttIRUwTZzKYpfLHAUbPQ2oeiqK14Apggg)
    
-   On clicking on Full Refund button, amount will be refunded to user.
    
-   To Restock ordered product, you will need to do process of creating Prestashop Refund on top of Mastercard Payment Gateway module refund process


## Advanced Configurations
Below are list of advanced configurations which you will find under Mastercard Payment Gateway Service Module.

![](https://lh4.googleusercontent.com/2Et3AQgcu7CYi5Qqr2HEpYmYLVso5xi_8OVlpzfniW4vh8JoY-HLJqXHFNZG9GsV9DWMziG4G19g7XGOMehyEx_Z-ZPqJiqQ7YswwrhTc9AT1aX-1o-DOc2xBL-HjOrELYjyb0gL)

|Name|Description  |
|--|--|
| Logging Verbosity | This module logs data into var/logs/mastercard.log - this switch control how much data is being logged, <br> Select any One option from below:<br>**Errors Only**  - this is default option, which only logs when an error happens.<br>**Everything**  - Logs everything related to error when it occurs (Like: API Response/status, errors, warning, etc).<br>**Errors and Warning Only**  - Logs only errors and warnings when error occured.<br>**Disabled**  - by selecting this, nothing will be logged when error will occured. |
|Gateway Order ID Prefix |**Default Option**: Blank<br>In case one Merchant ID is used by multiple installation, then this field can be used to add a prefix to order id-s so that they will not conflict in the gateway. |
|Custom Webhook Endpoint |**Default Option**: Blank<br>This field is mostly only used by development or with some complex web server rules, where the URL is not automatically detected correctly. |

It is suggested to keep these fields with assigned default value. If required, then do required configuration changes based on your need but first consult with Technical team / Mastercard Payment Gateway Module support for these.
