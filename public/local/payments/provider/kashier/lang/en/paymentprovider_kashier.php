<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Kashier Payment Provider';
$string['sandbox_mode'] = 'Sandbox mode';
$string['sandbox_mode_desc'] = 'Enable sandbox/test mode for Kashier transactions.';
$string['merchant_id'] = 'Merchant ID';
$string['merchant_id_desc'] = 'Your Kashier Merchant ID.';
$string['api_key'] = 'API Key';
$string['api_key_desc'] = 'Kashier Payment API Key (used for authentication and webhook signature verification).';
$string['secret_key'] = 'Secret Key';
$string['secret_key_desc'] = 'Kashier Secret Key (used in the Authorization header).';
$string['base_url'] = 'API Base URL';
$string['base_url_desc'] = 'Kashier API base URL. Default: https://api.kashier.io';
$string['refund_base_url'] = 'Refund Base URL';
$string['refund_base_url_desc'] = 'Kashier refund/void API base URL. Default: https://fep.kashier.io';
$string['allowed_methods'] = 'Allowed payment methods';
$string['allowed_methods_desc'] = 'Comma-separated list of allowed payment methods (e.g. card,wallet).';
$string['enable_3ds'] = 'Enable 3D Secure';
$string['enable_3ds_desc'] = 'Require 3D Secure authentication for card payments.';
$string['max_failure_attempts'] = 'Max failure attempts';
$string['max_failure_attempts_desc'] = 'Maximum number of failed payment attempts before the session expires.';
