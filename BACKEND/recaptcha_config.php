<?php

/*
 * reCAPTCHA Enterprise configuration
 *
 * IMPORTANT:
 * Never put this API key in JavaScript,
 * HTML, GitHub, or any public repository.
 */

return [
    'project_id' => getenv('BMS_RECAPTCHA_PROJECT_ID') ?: '',
    'api_key' => getenv('BMS_RECAPTCHA_API_KEY') ?: '',
    'site_key' => '6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4',
    /*
     * This must match the action used by your login page.
     */
    'expected_action' => 'login',

    /*
     * Your current threshold.
     */
    'minimum_score' => 0.3
];
