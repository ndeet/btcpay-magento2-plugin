#BTCPay Server integration for Magento 2

## Requirements
- Magento 2.3 installation (tested on Community Edition 2.3.2 with PHP 7.2)
- Magento < 2.3 should also work, but is untested.

## Goal
The goal of this module is to allow Bitcoin, Lightcoin and other crypto payments in Magento 2 without any other 3rd party.
This module is also designed to be robust, low-maintenance and a solid foundation for future customization, should your business need it.

## How to install
Just like any other Magento 2 module. Nothing special.

## How to configure
After installation, a new Payment Method will be visible in Stores > Configuration > Payment Methods. Configure the fields there.

You will need to get a pairing code from BTCPay Server and enter that.

## How does it work?
- When an order is placed in Magento and BTCPay was selected as a payment method, the customer is redirected to the payment page on your BTCPay Server.
- The customer can pay there, or he can cancel his order.
- When he cancels, the unpaid order is canceled freeing up reserved stock and the customer is sent back to the shopping cart page. This module will restore the contents of the shopping cart, so the customer does not need to start from scratch.
- When the customer pays, BTCPay Server will be notified of the payment and will signal Magento on the changed transaction status.
- BTCPay Server pushes payment status changes to Magento, but Magento can also poll for transaction changes on it's own. We've built this as a safety net in case BTCPay Server cannot connect to Magento (i.e. during developement, behind a firewall).
- Transaction updates from BTCPay Server to Magento are instant.
- Magento polls BTCPay Server for updates every 5 minutes.
 
## Which payment methods are supported?
This depends on your configuration of BTCPay Server. All payment methods you have activated on BTCPay Server, will be available to the customer.

## Who has created this module?
This module was created by Storefront, a small Magento integrator from Belgium with over 10 years experience. Visit our website at www.storefront.be to learn more about us.

This module does NOT contain any advertising and is 100% open source and free to use.

## Why did you create this module?
- Existing modules had very poor code quality, did not follow Magento 2 best-practises
- Was little supported (in combination with BTCPay Server)
- Was confusing to set up since the previous modules are basically designed for BitPay
- We now have a module dedicated to BTCPay, so both BTCPay Server and this module can innovate freely without having to consider BitPay compatibility
- Higher code quality means less maintenance and easier compatibility with future Magento versions

## Roadmap
- As this is a first release, we want to learn more from actual day-to-day use and work on stability first.
- We hope to bring you easier automated testing, but for this we need changes in BTCPay Server too: https://github.com/btcpayserver/btcpayserver/issues/917
- Nothing else is required really, as this module does what it needs to do in a robust and dependable way.

## What can I do if my BTCPay Server or Magento was offline for some time and transaction updates may not have synchronized?
Magento polls BTCPay Serer every 5 minutes for updates to non-completed transactions, so basically you don't need to do anything.
If you don't want to wait 5 minutes or prefer to see what is happening, we have prepared a console command to run the transaction sync manually:

```
bin/magento btcpay:transaction:update
```


## What if I need help?
Just like with any other open source software, you can get help anywhere from the community, or just open an issue here on Github.

You can talk to Wouter Samaey on the BTCPay Server Slack #development channel

If you prefer professional paid support, you can contact Storefront at info@storefront.be.

If this module powers your business, consider getting paid support (we built this module for free) and also donate to the development of BTCPay Server at https://btcpayserver.org/#makeADonation 