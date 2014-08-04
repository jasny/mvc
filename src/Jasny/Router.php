<?php

namespace Jasny;

/**
 * Route pretty URLs to correct controller
 * 
 * Wildcards:
 *  ?          Single character
 *  #          One or more digits
 *  *          One or more characters
 *  **         Any number of subdirs
 *  [abc]      Match character 'a', 'b' or 'c'
 *  [a-z]      Match character 'a' to 'z'
 *  {png,gif}  Match 'png' or 'gif'
 * 
 * Escape characters using URL encode (so %5B for '[')
 */
class Router
{
    /**
     * Specific routes
     * @var array 
     */
    protected $routes;

    /**
     * Method for route
     * @var string 
     */
    protected $method;

    /**
     * Webroot subdir from DOCUMENT_ROOT.
     * @var string
     */
    protected $base;

    /**
     * URL to route
     * @var string 
     */
    protected $url;

    /**
     * Variables from matched route (cached)
     * @var object 
     */
    protected $route;

    /**
     * The HTTP status to use by not found
     * @var int
     */
    protected $httpStatus;
    
    
    /**
     * The chained error handler
     * @var callback
     */
    protected $prevErrorHandler;
    
    /**
     * The chained exception handler
     * @var callback
     */
    protected $prevExceptionHandler;
    
    
    /**
     * Class constructor
     * 
     * @param array $routes  Array with route objects
     */
    public function __construct($routes=null)
    {
        if (isset($routes)) $this->setRoutes($routes);
    }
    
    /**
     * Set the routes
     * 
     * @param array $routes  Array with route objects
     * @return Router
     */
    public function setRoutes($routes)
    {
        if (is_object($routes)) $routes = get_object_vars($routes);
        
        $this->routes = $routes;
        $this->route = null;
        
        return $this;
    }

    /**
     * Add routes to existing list
     * 
     * @param array  $routes  Array with route objects
     * @param string $root    Specify the root dir for routes
     * @return Router
     */
    public function addRoutes($routes, $root=null)
    {
        if (is_object($routes)) $routes = get_object_vars($routes);
        
        foreach ($routes as $path=>$route) {
            if (!empty($root)) $path = $root . $path;
            
            if (isset($this->routes[$path])) {
                trigger_error("Route $path is already defined.", E_USER_WARNING);
                continue;
            }
            
            $this->routes[$path] = $route;
        }
        
        return $this;
    }
    
    /**
     * Get a list of all routes
     * 
     * @return object
     */
    public function getRoutes()
    {
        if (!isset($this->routes)) $this->setRoutes([
            '/**' => (object)['controller' => '$1|default', 'action' => '$2|index', 'args' => ['$3+']]
        ]);
        
        return $this->routes;
    }

    
    /**
     * Set the method to route
     * 
     * @param string $method
     * @return Router
     */
    public function setMethod($method)
    {
        $this->method = $method;
        $this->route = null;

        return $this;
    }

    /**
     * Get the method to route.
     * Defaults to REQUEST_METHOD, which can be overwritten by $_POST['_method'].
     * 
     * @return string
     */
    public function getMethod()
    {
        if (!isset($this->method)) $this->method = Request::getMethod();
        return $this->method;
    }
    
    
    /**
     * Set the webroot subdir from DOCUMENT_ROOT.
     * 
     * @param string $dir
     * @return Router
     */
    public function setBase($dir)
    {
        $this->base = rtrim($dir, '/');
        $this->route = null;

        return $this;
    }

    /**
     * Get the webroot subdir from DOCUMENT_ROOT.
     * 
     * @return string
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * Add a base path to the URL if the webroot isn't the same as the webservers document root
     * 
     * @param string $url
     * @return string
     */
    public function rebase($url)
    {
        return ($this->getBase() ?: '/') . ltrim($url, '/');
    }

    /**
     * Set the URL to route
     * 
     * @param string $url
     * @return Router
     */
    public function setUrl($url)
    {
        $this->url = $url;
        $this->route = null;

        return $this;
    }

    /**
     * Get the URL to route.
     * Defaults to REQUEST_URI.
     * 
     * @return string
     */
    public function getUrl()
    {
        if (!isset($this->url)) $this->url = urldecode(preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']));
        return $this->url;
    }

    /**
     * Split the URL and return a part
     * 
     * @param int $i  Part number, starts at 1
     * @return string
     */
    public function getUrlPart($i)
    {
        $parts = $this->splitUrl($this->getUrl());
        return $parts[$i - 1];
    }
    
    
    /**
     * Check if the router has been used.
     * 
     * @return boolean
     */
    public function isUsed()
    {
        return isset($this->route);
    }

    /**
     * Find a matching route
     * 
     * @param string $method
     * @param string $url
     * @return string|int   < 0 means no route found
     */
    protected function findRoute($method, $url)
    {
        $this->getRoutes(); // Make sure the routes are initialised
        $ret = -404;
        
        if ($url !== '/') $url = rtrim($url, '/');
        if (substr($url, 0, 2) == '/:') $url = substr($url, 2);

        foreach (array_keys($this->routes) as $route) {
            if (strpos($route, ' ') !== false && preg_match_all('/\s+\+(\w+)\b|\s+\-(\w+)\b/', $route, $matches)) {
                list($path) = preg_split('/\s+/', $route, 2);
                $inc = isset($matches[1]) ? array_filter($matches[1]) : [];
                $excl = isset($matches[2]) ? array_filter($matches[2]) : [];
            } else {
                $path = $route;
                $inc = $excl = [];
            }
            
            if ($path !== '/') $path = rtrim($path, '/');
            if ($this->fnmatch($path, $url)) {
                if ((empty($inc) || in_array($method, $inc)) && !in_array($method, $excl)) return $route;
                $ret = -405;
            }
        }

        return $ret;
    }

    /**
     * Get a matching route.
     * 
     * @return object
     */
    public function getRoute()
    {
        if (isset($this->route)) return $this->route;

        $method = $this->getMethod();
        $url = $this->getUrl();
        
        if ($this->getBase()) {
            $url = '/' . preg_replace('~^' . preg_quote(trim($this->getBase(), '/'), '~') . '~', '', ltrim($url, '/'));
        }

        $match = $this->findRoute($method, $url);

        if (!is_int($match) || $match >= 0) {
            $this->route = $this->bind($this->routes[$match], $this->splitUrl($url));
            $this->route->route = $match;
        } else {
            $this->route = false;
            $this->httpStatus = -1 * $match;
        }

        return $this->route;
    }

    /**
     * Get a property of the matching route.
     * 
     * @param string $prop  Property name
     * @return mixed
     */
    public function get($prop)
    {
        $route = $this->getRoute();
        return isset($route->$prop) ? $route->$prop : null;
    }
    
    
    /**
     * Execute the action of the given route.
     * 
     * @param object $route
     * @param object $overwrite
     * @return boolean|mixed  Whatever the controller returns or true on success
     */
    protected function routeTo($route, $overwrite=[])
    {
        if (!is_object($route)) {
            $match = $this->findRoute(null, $route);
            if (!isset($match) || !isset($this->routes[$match])) return false;
            $route = $this->routes[$match];
        }

        foreach ($overwrite as $key=>$value) {
            $route->$key = $value;
        }
        
        // Route to file
        if (isset($route->file)) {
            $file = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($this->rebase($route->file), '/');

            if (!file_exists($file)) {
                trigger_error("Failed to route using '{$route->route}': File '$file' doesn't exist."
                        , E_USER_WARNING);
                return false;
            }

            return include $file;
        }
        
        // Route to controller
        if (empty($route->controller) || empty($route->action)) {
            trigger_error("Failed to route using '{$route->route}': "
                    . (empty($route->controller) ? 'Controller' : 'Action') . " is not set", E_USER_WARNING);
            return false;
        }

        $class = $this->getControllerClass($route->controller);
        $method = $this->getActionMethod($route->action);
        $args = isset($route->args) ? $route->args : [];

        if (!class_exists($class)) return false;

        $controller = new $class($this);
        if (!is_callable([$controller, $method])) return false;

        $ret = call_user_func_array([$controller, $method], $args);
        return isset($ret) ? $ret : true;
    }

    /**
     * Execute the action.
     * 
     * @return mixed  Whatever the controller returns
     */
    public function execute()
    {
        $route = $this->getRoute();
        if ($route) $ret = $this->routeTo($route);
        
        if (!isset($ret) || $ret === false) return $this->notFound(null, $this->httpStatus ?: 404);
        return $ret;
    }

    
    /**
     * Enable router to handle fatal errors.
     * 
     * @param boolean $callPrevious  Call previous error handler
     */
    public function handleErrors($callPrevious = true)
    {
        $prevErrorHandler = set_error_handler(array($this, 'onError'));
        $prevExceptionHandler = set_exception_handler(array($this, 'onException'));
        
        if ($callPrevious) {
            $this->prevErrorHandler = $prevErrorHandler;
            $this->prevExceptionHandler = $prevExceptionHandler;
        }
    }
    
    /**
     * Error handler callback
     * 
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     * @param array  $context
     * @return boolean
     */
    public function onError($code, $message, $file, $line, $context)
    {
        if ($this->prevErrorHandler) {
            call_user_func($this->prevErrorHandler, $code, $message, $file, $line, $context);
        }
        
        if ($code & (E_RECOVERABLE_ERROR | E_USER_ERROR)) {
            $error = get_defined_vars();
            $this->error(null, 500, $error);
        }
    }

    /**
     * Exception handler callback
     * 
     * @param Exception $exception
     * @return boolean
     */
    public function onException($exception)
    {
        if ($this->prevExceptionHandler) call_user_func($this->prevExceptionHandler, $exception);
        
        $this->error(null, 500, $exception);
    }
    

    /**
     * Redirect to another page and exit
     * 
     * @param string $url 
     * @param int    $httpCode  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    public function redirect($url, $httpCode=303)
    {
        if (ob_get_level() > 1) ob_end_clean();
        
        if ($url[0] === '/' && substr($url, 0, 2) !== '//') $url = $this->rebase($url);
        
        Request::respondWith($httpCode, 'html');
        header("Location: $url");
        
        echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        exit();
    }

    /**
     * Give a 400 Bad Request response and exit
     * 
     * @param string $message
     * @param int    $httpCode  Alternative HTTP status code, eg. 406 (Not Acceptable)
     * @param mixed  $..        Additional arguments are passed to action        
     */
    public function badRequest($message, $httpCode=400)
    {
        $this->respond(400, $message, $httpCode, array_slice(func_get_args(), 2));        
        exit();
    }

    /**
     * Route to 401, otherwise result in a 403 forbidden.
     * Note: While the 401 route is used, we don't respond with a 401 http status code.
     */
    public function requireLogin()
    {
        $this->routeTo(401) || $this->forbidden();
        exit();
    }
    
    /**
     * Give a 403 Forbidden response and exit
     * 
     * @param string $message
     * @param int    $httpCode  Alternative HTTP status code
     * @param mixed  $..        Additional arguments are passed to action        
     */
    public function forbidden($message=null, $httpCode=403)
    {
        if (ob_get_level() > 1) ob_end_clean();
        
        if (!$this->routeTo(403, ['args'=>func_get_args()])) {
            if (!isset($message)) $message = "Sorry, you are not allowed to view this page";
            self::outputError($httpCode, $message, $this->getOutputFormat());
        }
        
        exit();
    }
    
    /**
     * Give a 404 Not Found response and exit
     * 
     * @param string $message
     * @param int    $httpCode  Alternative HTTP status code, eg. 410 (Gone)
     * @param mixed  $..        Additional arguments are passed to action        
     */
    public function notFound($message=null, $httpCode=404)
    {
        if (ob_get_level() > 1) ob_end_clean();

        if (!$this->routeTo(404, ['args'=>func_get_args()])) {
            if (!isset($message)) $message = $httpCode === 405 ?
                "Sorry, this action isn't supported" :
                "Sorry, this page does not exist";
            
            self::outputError($httpCode, $message, $this->getOutputFormat());
        }
        
        exit();
    }

    /**
     * Give a 500 Internal Server Error response and exit
     * 
     * @param string $message
     * @param int    $httpCode  Alternative HTTP status code, eg. 503 (Service unavailable)
     * @param mixed  $..        Additional arguments are passed to action        
     * @return boolean
     */
    protected function error($message=null, $httpCode=500)
    {
        if (ob_get_level() > 1) ob_end_clean();
        
        if (!$this->routeTo(500, ['args'=>func_get_args()])) {
            if (!isset($message)) $message = "Sorry, an unexpected error occured";
            self::outputError($httpCode, $message, $this->getOutputFormat());
        }
        
        exit();
    }
    
    
    /**
     * Get parts of a URL path
     * 
     * @param string $url
     * @return array
     */
    public static function splitUrl($url)
    {
        $url = parse_url(trim($url, '/'), PHP_URL_PATH);
        return $url ? explode('/', $url) : array();
    }

    /**
     * Match path against wildcard pattern.
     * 
     * @param string $pattern
     * @param string $path
     * @return boolean
     */
    public static function fnmatch($pattern, $path)
    {
        $regex = preg_quote($pattern, '~');
        $regex = strtr($regex, ['\?' => '[^/]', '\*' => '[^/]*', '/\*\*' => '(?:/.*)?', '#' => '\d+', '\[' => '[',
            '\]' => ']', '\-' => '-', '\{' => '{', '\}' => '}']);
        $regex = preg_replace_callback('~{[^\}]+}~', function($part) {
                return '(' . substr(strtr($part[0], ',', '|'), 1, -1) . ')';
            }, $regex);
        $regex = rawurldecode($regex);

        return (boolean)preg_match("~^{$regex}$~", $path);
    }

    /**
     * Fill out the routes variables based on the url parts.
     * 
     * @param array|object $vars   Route variables
     * @param array        $parts  URL parts
     * @return array
     */
    protected static function bind($vars, array $parts)
    {
        $values = [];
        $type = is_array($vars) || is_int(reset($vars)) ? 'numeric' : 'assoc';

        foreach ($vars as $key=>$var) {
            if (!isset($var)) continue;
            
            if (!is_scalar($var)) {
                $var = static::bind($var, $parts);
                continue;
            }

            $options = preg_split('/(?<!\\\\)\|/', $var);
            $part = static::bindVar($parts, $options);
            
            if ($type === 'assoc') {
                $values[$key] = $part[0];
            } else {
                $values = array_merge($values, $part);
            }
        }
        
        return $vars;
    }
    
    /**
     * Bind variable
     * 
     * @param string $type     'assoc or 'numeric'
     * @param array  $parts
     * @param array  $options
     * @return array
     */
    protected static function bindVar($type, array $parts, array $options)
    {
        foreach ($options as $option) {
            // Normal string
            if ($option[0] !== '$') return [stripcslashes($option, '$')];
            
            // Super global
            if (preg_match('/^(\$_(GET|POST|COOKIE))\[([^\[]*)\]$/i', $options, $matches)) {
                return [${$matches[1]}[$matches[2]]];
            }

            $i = (int)substr($options, 1);

            // Multiple parts
            if (substr($option, -1, 1) === '+') {
                if ($type === 'assoc') {
                    trigger_error("Binding multiple parts using '$option'"
                        . " is only allowed in numeric arrays", E_USER_WARNING);
                    return [null];
                }

                return array_slice($parts, $i);
            }
            
            // Single part
            $part = array_slice($parts, $i, 1);
            if (!empty($part)) return $part;
        }
        
        return [null];
    }
    
    
    /**
     * Get the class name of the controller
     * 
     * @param string $controller
     * @return string
     */
    protected static function getControllerClass($controller)
    {
        return self::camelcase($controller) . 'Controller';
    }
    
    /**
     * Get the method name of the action
     * 
     * @param string $action
     * @return string
     */
    protected static function getActionMethod($action)
    {
        return lcfirst(self::camelcase($action) . 'Action');
    }
    
    /**
     * CamelCase a word
     * 
     * @param string $string
     * @return string
     */
    protected static function camelcase($string)
    {
        return strtr(ucwords(strtr($string, '_-', '  ')), [' ' => '']);
    }
}
