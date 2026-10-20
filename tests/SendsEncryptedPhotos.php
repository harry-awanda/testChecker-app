<?php
namespace Tests;
trait SendsEncryptedPhotos {
    protected function postPhoto(string $uri,array $payload) {
        $key=random_bytes(32);$iv=random_bytes(12);$tag='';
        $ciphertext=openssl_encrypt(json_encode($payload,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        return $this->call('POST',$uri,[],[],[],[
            'CONTENT_TYPE'=>'application/octet-stream','HTTP_ACCEPT'=>'application/json','HTTP_X_PHOTO_ENCRYPTED'=>'v1',
            'HTTP_X_PHOTO_KEY'=>base64_encode($key),'HTTP_X_PHOTO_IV'=>base64_encode($iv),
        ],$ciphertext.$tag);
    }
}
