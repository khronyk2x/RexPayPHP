<?php

namespace Pils36;

use \Pils36\Rexpay\Helpers\Router;
use \Pils36\Rexpay\Contracts\RouteInterface;

class Rexpay
{
    public $secret_key;
    public $username;
    public $auth_token;
    public $mode = 'test';
    public $use_guzzle = false;
    public $custom_routes = [];
    public static $fallback_to_file_get_contents = true;
    const VERSION = "2.3.0";

    /**
     * Rexpay Client Constructor.
     * Can be initialized empty (classic) or with credentials for automatic auth injection.
     *
     * @param string|null $username RexPay Username or Basic Auth Token
     * @param string|null $secret_key RexPay Secret Key (Password)
     * @param string $mode 'test' or 'production'
     */
    public function __construct($username = null, $secret_key = null, $mode = 'test')
    {
        if ($username !== null && $secret_key !== null) {
            $this->username = $username;
            $this->secret_key = $secret_key;
            $this->auth_token = base64_encode("{$username}:{$secret_key}");
        } elseif ($username !== null && $secret_key === null) {
            $this->auth_token = $username;
        }
        $this->mode = $mode ?? 'test';
    }

    /**
     * Shortcut to initialize a transaction.
     *
     * @param array $params
     * @return mixed
     */
    public function initializePayment(array $params)
    {
        return $this->transaction->initialize($params);
    }

    /**
     * Shortcut to verify a transaction by reference.
     *
     * @param string $reference
     * @param array $additionalParams
     * @return mixed
     */
    public function verifyTransaction($reference, array $additionalParams = [])
    {
        $params = array_merge(['transactionReference' => $reference], $additionalParams);
        return $this->transaction->verify($params);
    }

    /**
     * Helper to check if API response indicates success (responseCode '00').
     *
     * @param mixed $response
     * @return bool
     */
    public static function isSuccessful($response)
    {
        return is_object($response) && isset($response->responseCode) && $response->responseCode === '00';
    }

    /**
     * Helper to check if API response indicates a pending transaction (responseCode '02').
     *
     * @param mixed $response
     * @return bool
     */
    public static function isPending($response)
    {
        return is_object($response) && isset($response->responseCode) && $response->responseCode === '02';
    }

    public function useGuzzle()
    {
        $this->use_guzzle = true;
    }

    public function useRoutes(array $routes)
    {
        foreach ($routes as $route => $class) {
            if (! is_string($route)) {
                throw new \InvalidArgumentException(
                    'Custom routes should map to a route class'
                );
            }

            if (in_array($route, Router::$ROUTES)) {
                throw new \InvalidArgumentException(
                    $route . ' is already an existing defined route'
                );
            }

            if (! in_array(RouteInterface::class, class_implements($class))) {
                throw new \InvalidArgumentException(
                    'Custom route class ' . $class . 'should implement ' . RouteInterface::class
                );
            }
        }

        $this->custom_routes = $routes;
    }

    public static function disableFileGetContentsFallback()
    {
        Rexpay::$fallback_to_file_get_contents = false;
    }

    public static function enableFileGetContentsFallback()
    {
        Rexpay::$fallback_to_file_get_contents = true;
    }

    public function __call($method, $args)
    {
        if ($singular_form = Router::singularFor($method)) {
            return $this->handlePlural($singular_form, $method, $args);
        }
        return $this->handleSingular($method, $args);
    }

    private function handlePlural($singular_form, $method, $args)
    {
        if ((count($args) === 1 && is_array($args[0]))||(count($args) === 0)) {
            return $this->{$singular_form}->__call('getList', $args);
        }
        throw new \InvalidArgumentException(
            'Route "' . $method . '" can only accept an optional array of filters and '
            .'paging arguments (perPage, page).'
        );
    }

    private function handleSingular($method, $args)
    {
        if (count($args) === 1) {
            $args = [[], [ Router::ID_KEY => $args[0] ] ];
            return $this->{$method}->__call('fetch', $args);
        }
        throw new \InvalidArgumentException(
            'Route "' . $method . '" can only accept an id or code.'
        );
    }

    /**
     * @deprecated
     */
    public static function registerAutoloader()
    {
        trigger_error('Include "src/autoload.php" instead', E_DEPRECATED | E_USER_NOTICE);
        require_once(__DIR__ . '/../src/autoload.php');
    }

    public function __get($name)
    {
        return new Router($name, $this);
    }

    public function transactionVerificationStatusUrl()
    {
        return 'https://pgs-sandbox.globalaccelerex.com/api/cps/v1/getTransactionStatus';
    }
}
