```php
<?php

return [
    'project_id' => getenv('BMS_RECAPTCHA_PROJECT_ID') ?: '',
    'api_key' => getenv('BMS_RECAPTCHA_API_KEY') ?: '',
    'site_key' => '6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4',
    'expected_action' => 'login',
    'minimum_score' => 0.3,
];
