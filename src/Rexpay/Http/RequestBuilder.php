<?php

namespace Pils36\Rexpay\Http;

use \Pils36\Rexpay\Contracts\RouteInterface;
use \Pils36\Rexpay\Helpers\Router;
use \Pils36\Rexpay;

class RequestBuilder
{
    protected $rexpayObj;
    protected $interface;
    protected $request;

    public $payload = [ ];
    public $sentargs = [ ];

    public function __construct($rexpayObj, $interface, array $payload = [ ], array $sentargs = [ ])
    {

        $this->request = new Request($rexpayObj);
        $this->rexpayObj = $rexpayObj;
        $this->interface = $interface;
        $this->payload = $payload;
        $this->sentargs = $sentargs;
        $this->autoInjectCredentials();
    }

    private function autoInjectCredentials()
    {
        if (is_object($this->rexpayObj)) {
            if (!empty($this->rexpayObj->auth_token) && empty($this->payload['authToken'])) {
                $this->payload['authToken'] = $this->rexpayObj->auth_token;
            }
            if (!empty($this->rexpayObj->mode) && empty($this->payload['mode'])) {
                $this->payload['mode'] = $this->rexpayObj->mode;
            }
        }
    }

    public function build()
    {


        $this->request->headers["User-Agent"] = "Rexpay/v1 PhpBindings/" . Rexpay::VERSION;
        $this->request->endpoint = ($this->interface[RouteInterface::ENDPOINT_KEY] === "/getTransactionStatus" ? Router::REXPAY_API_ROOT_VERIFY_TRANSACTION : Router::REXPAY_API_ROOT) . $this->interface[RouteInterface::ENDPOINT_KEY];
        $this->request->method = $this->interface[RouteInterface::METHOD_KEY];
        $this->moveArgsToSentargs();
        $this->putArgsIntoEndpoint($this->request->endpoint);
        $this->packagePayload();
        $this->request->prepare();
        return $this->request;
    }

    public function packagePayload()
    {
        if (is_array($this->payload) && count($this->payload)) {
            if ($this->request->method === RouteInterface::GET_METHOD) {
                $this->request->endpoint = $this->request->endpoint . '?' . http_build_query($this->payload);
            } else {
                $this->request->body = json_encode($this->payload);
            }
        }
    }

    public function putArgsIntoEndpoint(&$endpoint)
    {
        foreach ($this->sentargs as $key => $value) {
            $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
        }
    }

    public function moveArgsToSentargs()
    {
        if (!array_key_exists(RouteInterface::ARGS_KEY, $this->interface)) {
            return;
        }
        $args = $this->interface[RouteInterface::ARGS_KEY];
        foreach ($this->payload as $key => $value) {
            if (in_array($key, $args)) {
                $this->sentargs[$key] = $value;
                unset($this->payload[$key]);
            }
        }
    }

    public function array_search_partial($arr, $keyword)
    {
        foreach ($arr as $index => $string) {
            if (strpos($string, $keyword) !== FALSE)
                return $index;
        }
    }

}
