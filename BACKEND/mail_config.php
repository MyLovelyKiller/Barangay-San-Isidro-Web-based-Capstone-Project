
<?php

/*
 * =========================================================
 * MAIL CONFIGURATION
 * =========================================================
 *
 * IMPORTANT:
 * Keep this file outside public access.
 *
 * Configure credentials in the server environment.
 */

return [
    'api_key'    => getenv('BMS_RESEND_API_KEY') ?: '',
    'from_email' => getenv('BMS_RESEND_FROM_EMAIL') ?: '',
    'from_name'  => getenv('BMS_RESEND_FROM_NAME') ?: 'Barangay San Isidro',
];
