<?php

require "../vendor/autoload.php";

use Facteur\Facteur;
use Colis\RequestFactory;
use Colis\ResponseFactory;
use Colis\StreamFactory;

$facteur = new Facteur(new ResponseFactory());

$request = (new RequestFactory())->createRequest("GET","http://www.google.com");

$response = $facteur->send($request);

echo 'Headers: '; 
var_dump($response->getHeaders());

echo "Body: ";
$response->getBody()->rewind();
echo $response->getBody()->getContents();
