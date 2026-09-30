<?php

namespace Pils36\Rexpay\Helpers;

use \Closure;
use \Pils36\Rexpay\Contracts\RouteInterface;
use \Pils36\Rexpay\Exception\ValidationException;

class Router
{
    private $route;
    private $route_class;
    private $methods;
    public static $ROUTES = [
        'customer', 'page', 'plan', 'subscription', 'transaction', 'subaccount',
        'balance', 'bank', 'decision', 'integration', 'settlement',
        'transfer', 'transferrecipient', 'invoice'
    ];
    public static $ROUTE_SINGULAR_LOOKUP = [
        'customers'=>'customer',
        'invoices'=>'invoice',
        'pages'=>'page',
        'plans'=>'plan',
        'subscriptions'=>'subscription',
        'transactions'=>'transaction',
        'banks'=>'bank',
        'settlements'=>'settlement',
        'transfers'=>'transfer',
        'transferrecipients'=>'transferrecipient',
    ];

    const ID_KEY = 'id';
    const REXPAY_API_ROOT = 'https://pgs-sandbox.globalaccelerex.com/api/pgs';
    const REXPAY_API_ROOT_LIVE = 'https://pgs-sandbox.globalaccelerex.com/api/pgs';
    const REXPAY_API_ROOT_VERIFY_TRANSACTION = 'https://pgs-sandbox.globalaccelerex.com/api/cps/v1';


    public function __call($methd, $sentargs)
    {
        $method = ($methd === 'list' ? 'getList' : $methd );
        if (array_key_exists($method, $this->methods) && is_callable($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $sentargs);
        } else {
            throw new \Exception('Function "' . $method . '" does not exist for "' . $this->route . '".');
        }
    }

    public static function singularFor($method)
    {
        return (
            array_key_exists($method, Router::$ROUTE_SINGULAR_LOOKUP) ?
                Router::$ROUTE_SINGULAR_LOOKUP[$method] :
                null
            );
    }

    public function __construct($route, $rexpayObj)
    {
        $routes = $this->getAllRoutes($rexpayObj);

        if (!in_array($route, $routes)) {
            throw new ValidationException(
                "Route '{$route}' does not exist."
            );
        }

        $this->route = strtolower($route);
        if (in_array($this->route, ['customer', 'plan', 'subscription', 'subaccount', 'bank', 'settlement', 'transfer', 'transferrecipient', 'decision', 'integration', 'invoice'])) {
            @trigger_error("Route '{$route}' is a legacy Paystack endpoint not supported by RexPay.", E_USER_DEPRECATED);
        }
        $this->route_class = $this->getRouteClass($rexpayObj);

        $mets = get_class_methods($this->route_class);
        if (empty($mets)) {
            throw new \InvalidArgumentException('Class "' . $this->route . '" does not exist.');
        }
        // add methods to this object per method, except root
        foreach ($mets as $mtd) {
            if ($mtd === 'root') {
                continue;
            }
            $mtdFunc = function (
                array $params = [ ],
                array $sentargs = [ ]
            ) use (
                $mtd,
                $rexpayObj
            ) {
                $interface = call_user_func($this->route_class . '::' . $mtd);
                // TODO: validate params and sentargs against definitions
                $caller = new Caller($rexpayObj);
                return $caller->callEndpoint($interface, $params, $sentargs);
            };
            $this->methods[$mtd] = \Closure::bind($mtdFunc, $this, get_class());
        }
    }

    private function getAllRoutes($rexpayObj)
    {
        return array_merge(static::$ROUTES, array_keys($rexpayObj->custom_routes));
    }

    private function getRouteClass($rexpayObj)
    {
        if (isset($rexpayObj->custom_routes[$this->route])) {
            return $rexpayObj->custom_routes[$this->route];
        }

        return 'Pils36\\Rexpay\\Routes\\' . ucwords($this->route);
    }
}
