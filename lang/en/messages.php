<?php

/**
 * English counterpart of `lang/fr/messages.php`.
 *
 * Every key defined in the French file MUST exist here with the same
 * structure — `LocalizationTest` fails otherwise. A missing key would make
 * Laravel echo the raw key ("messages.payment.failed") to the end user.
 */
return [

    // ---- Authentication / account --------------------------------------
    'auth' => [
        'otp_sent'          => 'Code sent by email',
        'otp_send_failed'   => 'Could not send the email. Please try again.',
        'account_suspended' => 'This account has been suspended. Please contact support.',
        'reset_code_sent'   => 'If an account exists for this email, a reset code has been sent.',
        'code_valid'        => 'Valid code.',
        'password_reset'    => 'Password reset successfully.',
        'logged_out'        => 'Signed out successfully.',
        'invalid_password'  => 'Incorrect password.',
        'account_deleted'   => 'Your account and data have been permanently deleted.',
        'unauthenticated'   => 'Not authenticated.',
        'forbidden_admin'   => 'Access denied: administrator role required.',
        'history_saved'     => 'History saved.',
    ],

    // ---- Catalogue / playback ------------------------------------------
    'content' => [
        'not_found'             => 'Content not found.',
        'subscription_required' => 'An active subscription is required to watch this content.',
        'rubrique_upgrade'      => 'This section requires a higher subscription plan.',
        'rubrique_not_found'    => 'Section not found.',
        'download_unavailable'  => 'This content is not available for download.',
        'download_disabled'     => 'Downloading is not available right now.',
        'download_misconfigured' => 'Downloading is unavailable (incomplete server configuration).',
    ],

    // ---- My list --------------------------------------------------------
    'list' => [
        'added'   => 'Added to your list.',
        'removed' => 'Removed from your list.',
    ],

    // ---- Payments / subscription ----------------------------------------
    'payment' => [
        'succeeded'           => 'Payment completed successfully',
        'failed'              => 'The payment failed.',
        'processing'          => 'Payment is being processed.',
        'crypto_processing'   => 'Crypto payment is being processed.',
        'validate_on_phone'   => 'Please confirm the payment on your phone.',
        'dial_ussd'           => 'Please dial the USSD code shown on your phone',
        'init_failed'         => 'Could not initiate the payment',
        'capture_failed'      => 'Could not capture the payment',
        'confirm_failed'      => 'Could not confirm the payment',
        'status_check_failed' => 'Could not check the payment status',
        'paypal_unavailable'  => 'PayPal payment is unavailable right now. Choose Mobile Money or try again later.',
        'card_unavailable'    => 'Card payment is unavailable right now.',
        'crypto_unavailable'  => 'Crypto payment is unavailable right now.',
        'transaction_not_found' => 'Transaction not found.',
        'subscription_activated' => 'Subscription activated',
        'apple_verify_failed' => 'Apple verification failed',
        'apple_invalid'       => 'Invalid or revoked Apple transaction',
        'apple_plan_missing'  => 'No plan found for this Apple product',
        'stripe_init_failed' => 'Could not initialise Stripe.',
        'stripe_verify_failed' => 'Could not verify the Stripe payment.',
        'kpay_init_failed'   => 'Could not initialise KPay.',
        'kpay_country_unavailable' => 'KPay is not available in your country (:country).',
        'operator_unavailable' => 'This operator is not available for :country.',
        'card_init_failed'   => 'Could not initiate the card payment.',
        'paypal_init_failed' => 'Could not initiate the PayPal payment.',
        'crypto_init_failed' => 'Could not initiate the crypto payment.',
        'crypto_min_amount'  => 'Minimum amount for a crypto payment: :min FCFA. Some cryptocurrencies (BTC, ETH) have high network fees that require a higher minimum.',
        'crypto_amount_too_low' => 'This amount is too low for a crypto payment. The minimum is around :min FCFA (Bitcoin/Ethereum network fees impose a high threshold). Please choose a larger amount.',
        'apple_verify_rejected' => 'Apple verification was rejected.',
        'verify_failed'      => 'Verification failed.',
    ],

    // ---- Cinema reservations --------------------------------------------
    'reservation' => [
        'created'   => 'Reservation created. Complete the payment to confirm it.',
        'confirmed' => 'Reservation confirmed.',
        'cancelled' => 'Reservation cancelled.',
        'not_found' => 'Reservation not found.',
        'quantity_min'      => 'The quantity must be at least 1.',
        'not_enough_seats'  => 'Not enough seats left in this category.',
        'cancelled_cannot_confirm' => 'This reservation was cancelled and cannot be confirmed.',
        'not_enough_to_confirm' => 'Not enough seats left to confirm this reservation.',
    ],

    // ---- Administration (panel) -----------------------------------------
    'admin' => [
        'media_deleted'    => 'Media deleted.',
        'category_deleted' => 'Category deleted.',
        'season_deleted'   => 'Season deleted.',
        'episode_deleted'  => 'Episode deleted.',
    ],

    // ---- Validation (custom messages) ------------------------------------
    'validation' => [
        'name_required'      => 'The name is required.',
        'email_required'     => 'The email address is required.',
        'email_invalid'      => 'The email address is not valid.',
        'email_taken'        => 'This email address is already in use.',
        'password_required'  => 'The password is required.',
        'password_min'       => 'The password must be at least 8 characters long.',
        'password_confirmed' => 'The passwords do not match.',
        'country_required'   => 'Please select your country.',
        'country_invalid'    => 'Invalid country.',
    ],
];
