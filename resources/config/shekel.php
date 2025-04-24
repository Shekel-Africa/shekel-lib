<?php

return [

    'auth' => getenv('AUTH_SERVICE'),
    'auth-secret' => getenv('AUTH_SERVICE_SECRET'),
    'car' => getenv('CAR_SERVICE'),
    'car-secret' => getenv('CAR_SERVICE_SECRET'),
    'loan' => getenv('LOAN_SERVICE'),
    'loan-secret' => getenv('LOAN_SERVICE_SECRET'),
    'store' => getenv('STORE_SERVICE'),
    'store-secret' => getenv('LOAN_SERVICE_SECRET'),
    'messaging' => getenv('MESSAGING_SERVICE'),
    'messaging-secret' => getenv('MESSAGING_SERVICE_SECRET'),
    'upload' => getenv('UPLOAD_SERVICE'),
    'upload-secret' => getenv('UPLOAD_SERVICE_SECRET'),
    'transaction' => getenv('TRANSACTION_SERVICE'),
    'transaction-secret' => getenv('TRANSACTION_SERVICE_SECRET'),
    'service_name' => getenv('SERVICE_NAME'),
    'slack_webhook' => getenv('SLACK_WEBHOOK'),
];
