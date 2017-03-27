<?php

namespace Facteur;

use Interop\Http\Factory\ResponseFactoryInterface;
use Psr\Http\Message\RequestInterface;

class Facteur
{
	protected $curl;
	protected $resfac;
	protected $options = [
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 2,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => "Facteur"
		//CURLOPT_CONNECTTIMEOUT
		//CURLOPT_REFERER
	];

	public function __construct(ResponseFactoryInterface $resfac, array $options = [])
	{
		$this->options = ($options + $this->options);
		$this->resfac = $resfac;
	}

	public function send(RequestInterface $request)
	{
		//Setup
        if (is_resource($this->curl)) {
            curl_reset($this->curl);
        } else {
            $this->curl = curl_init();
		}

		$options = [];

		//Parse Request
		//Uri
        switch ($request->getProtocolVersion()) {
            case '1.0':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
                break;
            case '1.1':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
                break;
            case '2.0':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2;
                break;
            default:
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_NONE;
                break;
        }

		if ($request->getMethod() === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        } elseif ($request->getMethod() !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        $options[CURLOPT_URL] = (string)$request->getUri();

		if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

		//Body
        if (in_array($request->getMethod(), ['PATCH', 'POST', 'PUT'])) {
            if ($request->getBody() !== null) {
                $size = $request->getBody()->getSize();
                if (is_null($size) || ($size > 1024 * 1024)) {
                    $options[CURLOPT_UPLOAD] = true;
                    if (is_int($size)) {
                        $options[CURLOPT_INFILESIZE] = $size;
                    }
                    $options[CURLOPT_READFUNCTION] = function ($c, $f, $length) use ($request) {
                        return $request->getBody()->read($length);
                    };
                } else {
                    $options[CURLOPT_POSTFIELDS] = (string)$body;
                }
            }
        }

		//Headers
        $headers = [];
		foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

		$options = $options + $this->options;

		curl_setopt_array($this->curl, $options);
        
		$data = curl_exec($this->curl);

		if(curl_errno($this->curl) != CURLE_OK)
		{
			throw new \RuntimeException(curl_error($this->curl));
		}

		$response = $this->resfac->createResponse(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));

		if($options[CURLOPT_HEADER])
		{
			$header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
			$head = substr($data, 0, $header_size);
			foreach(preg_split("/((\r?\n)|(\r\n?))/", $head) as $line){
				$parts = explode(':', $line, 2);
				if(isset($parts[1])){
					$response = $response->withHeader($parts[0], $parts[1]);
				}
			}
			$data = substr($data, $header_size);
		}
		$response->getBody()->write($data);
		
        return $response;
	}

	public function __destruct()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }
}