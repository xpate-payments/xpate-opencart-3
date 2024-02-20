<?php

/**
 * General Admin Settings Page
 */
$_['heading_title'] = 'Xpate: Google Pay';
$_['text_ginger_googlepay'] = '<img src="view/image/payment/ginger_googlepay.png" alt="Xpate" title="Xpate" />';
$_['text_extension'] = 'Extensions';

/**
 * Entry points
 */
$_['entry_status'] = 'Status:';
$_['entry_order_completed'] = 'Order Completed:';
$_['entry_order_new'] = 'Order New:';
$_['entry_order_expired'] = 'Order Expired:';
$_['entry_order_cancelled'] = 'Order Cancelled:';
$_['entry_order_processing'] = 'Order Processing:';
$_['entry_order_error'] = 'Order Error:';
$_['entry_sort_order'] = 'Sort Order:';
$_['entry_ginger_api_key'] = 'Xpate API Key:';
$_['entry_ginger_total'] = 'Minimum order amount:';
$_['entry_cacert'] = 'Bundle cURL ca.cert:';
$_['entry_order_captured'] = 'Order Captured:';

/**
 * Text strings
 */
$_['text_button_save'] = 'Save';
$_['text_button_cancel'] = 'Cancel';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_payments'] = 'Payments';
$_['text_issuer_id'] = 'SWIFT/BIC';
$_['text_settings_saved'] = 'Xpate: Google Pay settings updated!';
$_['text_edit_ginger'] = 'Edit Xpate Payments: Google Pay Settings';
$_['text_yes'] = 'Yes';
$_['text_no'] = 'No';

/**
 * Error messages
 */
$_['error_missing_api_key'] = 'Xpate API Key is required!';

/**
 * Information text
 */
$_['info_help_api_key'] = 'Get your Xpate API key from Payments merchant portal.';
$_['info_help_total'] = 'The payment method will only be presented if the total checkout amount of an order is higher than this threshold.';
$_['info_plugin_not_configured'] = 'Xpate: Google Pay  plugin is not configured.';

/**
 * Refund text
 */
$_['empty_price'] = 'The price is empty in the refund product information.';
$_['wrong_order_status'] = 'Only completed orders can be refunded.';
$_['refund_not_completed'] = 'Refund order is not completed.';