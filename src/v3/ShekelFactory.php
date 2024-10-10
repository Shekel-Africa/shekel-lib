<?php

namespace Shekel\ShekelLib\v3;

use Shekel\ShekelLib\Services\v3\{AuthService, CarService, LoanService, MessagingService, TransactionService,UploadService};

class ShekelFactory {
    private $token;

    public function __construct(
        private AuthService $authService,
        private CarService $carService,
        private LoanService $loanService,
        private UploadService $uploadService,
        private TransactionService $transactionService,
        private MessagingService $messagingService
    )
    {
    }

    public function setToken($token) {
        $this->token = $token;
    }

    public function getService($name) {
        switch ($name) {
            case 'auth':
                $this->authService->setToken($this->token);
                return $this->authService;
                break;

            case 'cars':
                $this->carService->setToken($this->token);
                return $this->carService;
                break;

            case 'loans':
                $this->loanService->setToken($this->token);
                return $this->loanService;
                break;

            case 'uploads':
                $this->uploadService->setToken($this->token);
                return $this->uploadService;
                break;

            case 'transactions':
                $this->transactionService->setToken($this->token);
                return $this->transactionService;
                break;

            case 'messaging':
                $this->messagingService->setToken($this->token);
                return $this->messagingService;
                break;

            default:
                # code...
                break;
        }
    }
}
