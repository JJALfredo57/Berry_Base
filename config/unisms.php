<?php
return [
    'api_key'   => env('UNISMS_API_KEY', env('PHILSMS_API_KEY', '')),
    'sender_id' => env('UNISMS_SENDER_ID', env('PHILSMS_SENDER_ID', '')),
];
