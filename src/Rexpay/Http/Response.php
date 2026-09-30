<?php

namespace Pils36\Rexpay\Http;

use \Pils36\Rexpay\Exception\ApiException;

class Response
{
    public $okay;
    public $body;
    public $forApi;
    public $messages = [];

    private $requestObject;

    public function setRequestObject($requestObject)
    {
        $this->requestObject = $requestObject;
    }

    public function getRequestObject()
    {
        return $this->requestObject;
    }

    private function parseRexpayResponse()
    {
        $resp = \json_decode($this->body);

        if ($resp === null || !is_object($resp)) {
            throw new ApiException(
                "Rexpay Request failed with response: '" .
                $this->messageFromApiJson(null) . "'",
                $resp,
                $this->requestObject
            );
        }

        return $resp;
    }

    private function messageFromApiJson($resp)
    {
        $message = $this->body;
        if ($resp !== null) {
            if (property_exists($resp, 'message')) {
                $message = $resp->message;
            }
            if (property_exists($resp, 'errors') && ($resp->errors instanceof \stdClass)) {
                $message .= "\nErrors:\n";
                foreach ($resp->errors as $field => $errors) {
                    $message .= "\t" . $field . ":\n";
                    foreach ($errors as $_unused => $error) {
                        $message .= "\t\t" . $error->rule . ": ";
                        $message .= $error->message . "\n";
                    }
                }
            }
        }
        return $message;
    }

    private function implodedMessages()
    {
        return implode("\n\n", $this->messages);
    }

    public function wrapUp()
    {
        if ($this->okay && $this->forApi) {
            return $this->parseRexpayResponse();
        }
        if (!$this->okay && $this->forApi) {
            throw new \Exception($this->implodedMessages());
        }
        if ($this->okay) {
            return $this->body;
        }
        error_log($this->implodedMessages());
        return false;
    }
}
