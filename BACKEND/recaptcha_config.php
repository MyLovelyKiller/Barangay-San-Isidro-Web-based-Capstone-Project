```php
<?php

/*
 * reCAPTCHA Enterprise configuration
 *
 * IMPORTANT:
 * Never put this API key in JavaScript,
 * HTML, GitHub, or any public repository.
 */

return [
    'project_id' => 'barangay-captcha',

    /*
     * Paste your NEW API key between the quotes below.
     *
     * DO NOT send this value to ChatGPT.
     */
    

    /*
     * This must match the action used by your login page.
     */
    'expected_action' => 'login',

    /*
     * Your current threshold.
     */
    'minimum_score' => 0.3
];
