<?php

$abaEnvironment = strtolower((string) env('ABA_ENV', 'sandbox'));
$abaDefaultBaseUrl = $abaEnvironment === 'production'
    ? 'https://checkout.payway.com.kh'
    : 'https://checkout-sandbox.payway.com.kh';

$resolveAbaUrl = static function (string $envKey, string $path, ?string $fallback = null) use ($abaDefaultBaseUrl): string {
    $rawValue = trim((string) env($envKey, ''));

    if ($rawValue === '') {
        $base = rtrim($fallback ?: $abaDefaultBaseUrl, '/');
        return $base . $path;
    }

    if (preg_match('/^https?:\/\//i', $rawValue) === 1) {
        return rtrim($rawValue, '/');
    }

    if (str_starts_with($rawValue, '/')) {
        $base = rtrim($fallback ?: $abaDefaultBaseUrl, '/');
        return $base . $rawValue;
    }

    $base = rtrim($fallback ?: $abaDefaultBaseUrl, '/');

    return $base . '/' . ltrim($rawValue, '/');
};

$abaBaseUrl = rtrim(trim((string) env('ABA_BASE_URL', '')), '/');
if ($abaBaseUrl === '') {
    $abaBaseUrl = $abaDefaultBaseUrl;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'bakong' => [
        'api_url' => env('BAKONG_API_URL', 'https://api-bakong.nbc.gov.kh'),
        'token' => env('BAKONG_TOKEN'),
        'currency' => env('BAKONG_CURRENCY', 'USD'),
        'static_qr_image' => env('BAKONG_STATIC_QR_IMAGE', ''),
        'static_qr_string' => env('BAKONG_STATIC_QR_STRING', ''),
        'static_qr_deeplink' => env('BAKONG_STATIC_QR_DEEPLINK', ''),
        'static_qr_name' => env('BAKONG_STATIC_QR_NAME', 'Bakong KHQR'),
        'merchant' => [
            'bakong_id' => env('BAKONG_MERCHANT_ID') ?: env('MERCHANT_BAKONG_ID', 'demo_merchant_id_123'),
            'name' => env('BAKONG_MERCHANT_NAME') ?: env('MERCHANT_NAME', 'Chomnuoy System'),
            'city' => env('BAKONG_MERCHANT_CITY') ?: env('MERCHANT_CITY', 'Phnom Penh'),
            'acquiring_bank' => env('BAKONG_ACQUIRING_BANK') ?: env('ACQUIRING_BANK', 'Bakong'),
        ],
    ],

    'aba_payway' => [
        'environment' => $abaEnvironment,
        'base_url' => $abaBaseUrl,

        // ✅ REQUIRED
        'merchant_id' => env('ABA_MERCHANT_ID'),
        'api_key' => env('ABA_API_KEY'), // 🔥 IMPORTANT (ADD THIS)

        // ❌ NOT USED FOR HASH (optional)
        'public_key' => env('ABA_PUBLIC_KEY'),

        // ✅ API URLs
        'purchase_url' => $resolveAbaUrl(
            'ABA_API_URL',
            '/api/payment-gateway/v1/payments/purchase',
            $abaBaseUrl
        ),

        'generate_qr_url' => $resolveAbaUrl(
            'ABA_GENERATE_QR_URL',
            '/api/payment-gateway/v1/payments/generate-qr',
            $abaBaseUrl
        ),

        'check_transaction_url' => $resolveAbaUrl(
            'ABA_CHECK_TRANSACTION_URL',
            '/api/payment-gateway/v1/payments/check-transaction',
            $abaBaseUrl // ✅ FIX (remove -2)
        ),

        'transaction_detail_url' => $resolveAbaUrl(
            'ABA_TRANSACTION_DETAIL_URL',
            '/api/payment-gateway/v1/payments/transaction-detail',
            $abaBaseUrl
        ),

        // ✅ DEFAULT SETTINGS
        'payment_option' => env('ABA_PAYMENT_OPTION', 'abapay_khqr'),
        'currency' => env('ABA_CURRENCY', 'USD'),

        // ✅ URLS
        'return_url' => env('ABA_RETURN_URL'),
        'callback_url' => env('ABA_CALLBACK_URL'),
        'cancel_url' => env('ABA_CANCEL_URL'),
        'continue_success_url' => env('ABA_CONTINUE_SUCCESS_URL'),
        'static_qr_image' => env('ABA_STATIC_QR_IMAGE'),
        'static_qr_string' => env('ABA_STATIC_QR_STRING'),
        'static_qr_deeplink' => env('ABA_STATIC_QR_DEEPLINK'),
        'static_qr_name' => env('ABA_STATIC_QR_NAME', 'Bakong KHQR'),
    ],

];
