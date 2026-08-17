<?php

return [
    'api_key'   => env('PHILSMS_API_KEY', env('UNISMS_API_KEY', '')),
    'sender_id' => env('PHILSMS_SENDER_ID', env('UNISMS_SENDER_ID', '')),
    'endpoint'  => env('PHILSMS_ENDPOINT', 'https://dashboard.philsms.com/api/v3/sms/send'),
];
