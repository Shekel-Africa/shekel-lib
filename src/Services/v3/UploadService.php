<?php

namespace Shekel\ShekelLib\Services\v3;


class UploadService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('upload');
    }

    public function storeFiles($data) {
        $url = '/';
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function listUploads($data) {
        $url = "/";
        return $this->handleRequest($this->client->get($url, $data));
    }

    public function filesExist($data) {
        $url = "/files/exists";
        return $this->handleRequest($this->client->post($url, $data));
    }
}
