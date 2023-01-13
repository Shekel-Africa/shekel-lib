<?php 

namespace Shekel\ShekelLib\Services;

use Illuminate\Support\Arr;
use Shekel\ShekelLib\Services\ShekelBaseService;

class UploadService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'upload');
    }

    public function storeFiles($data) {
        $url = '/';
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function listUploads($data) {
        $url = "/";
        return $this->handleRequest($this->client->get($url, $data));
    }
}