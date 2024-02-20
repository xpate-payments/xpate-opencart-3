# Changelog OpenCart

** 1.0.0 **

* Initial Version

** 1.0.1 **

* Add Apple Pay
* Fix in the beberlei/assert library
* Fixed bug with field ip address

** 1.0.2 **

* Changed library from ems-php to ginger-php
* Renamed Sofort to Klarna Pay Now
* Renamed Klarna to Klarna Pay Later
* Add American Express, Tikkie Payment Request, WeChat 

** 1.0.3 **

* Fix Captured and shipped functionality

** 1.5.0 **

* Fixed payment URL for Klarna Pay Later

** 1.6.0 ** 

* Added order lines for Klarna Pay Later payment method
* Added order lines for AfterPay payment method
* Fixed incorrect amount value of order
* Klarna-Pay-Later: Remove fields gender and birthday from checkout form and customer object
* Added the ability for AfterPay to be available in the selected countries.
* Added the AfterPay localization for Netherlands, German and French language.
* Replaced locally stored ginger-php library on composer library installer."

** 1.6.1 **

* Removed WebHook option in all payment.
* Updated plugin descriptions.

** 1.6.2 **

* optimized translations
* fixed IP filtering and 'Test API key' functionality for Afterpay and Klarna Pay Later

** 1.6.3 **

* fixed history status updating

** 1.6.4 **

* added refund functionality

** 1.7.0 ** 

* Refactored code to handle GPE solution.
* Unified bank labels to handle GPE solution.
* Added Bank Config class.
* Added Bank Twins for handling custom bank functionality requests.
* Implemented GitHubActions.
* Added AfterMerge & CreateOrder PHPUnit tests.
* Added Sofort, Klarna Direct Debit, Google Pay payment methods
* Implemented multi-currency
* Removed pre-selected iDEAL bank-issuer
* Fixed bugs in refund&capture functionality
* Changed .zip in a release, added .ocmod to the archieve name and deleted unnecessary files (changelog, readme)
* Added installation guide through admin panel to README file

** 1.7.1 **

* Updated default orders statuses for each payment method on the settings page
* Updated the extra field in an order, Refactored PHPUnit tests to correspond the updated extra field 
* Added ApplePay detection
* Added OrderLines in each order

** 1.7.2 **

* Removed unavailable payment methods
* Added caching the array of currency
* Added possibility to skip the intermediate page with terms of condition in AfterPay
* Added Swish, MobilePay, GiroPay