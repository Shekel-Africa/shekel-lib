<?php

namespace Shekel\ShekelLib\Services\v3;


class UploadService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('upload');
    }

    /**
     * @param array{uploadable:array{id:string,type:string},files:array,category:array} $data
     */
    public function storeFiles(array $data) {
        $url = '/';
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function listUploads($data) {
        $url = "/";
        return $this->handleRequest($this->client->get($url, $data));
    }

    public function filesExist($data) {
        $url = "/files/exist";
        return $this->handleRequest($this->client->post($url, $data));
    }
}
