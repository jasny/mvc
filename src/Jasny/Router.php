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
     * HTTP status codes
     * @var array
     */
    static public $httpStatusCodes = [
        200 => '200 OK',
        201 => '201 Created',
        202 => '202 Accepted',
        204 => '204 No Content',
        205 => '205 Reset Content',
        206 => '206 Partial Content',
        301 => '301 Moved Permanently',
        302 => '302 Found',
        303 => '303 See Other',
        304 => '304 Not Modified',
        307 => '307 Temporary Redirect',
        308 => '308 Permanent Redirect',
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        402 => "402 Payment Required",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        409 => "409 Conflict",
        410 => "410 Gone",
        415 => "415 Unsupported Media Type",
        429 => "429 Too Many Requests",
        500 => "500 Internal server error",
        501 => "501 Not Implemented",
        503 => "503 Service unavailable"
    ];
    
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
     * Output format
     * @var string 
     */
    protected $format;

    /**
     * Variables from matched route (cached)
     * @var object 
     */
    protected $route;

    
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
     * Get the HTTP protocol
     * 
     * @return string;
     */
    protected static function getProtocol()
    {
        return isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
    }
    
    /**
     * Return the request method.
     * 
     * Usually REQUEST_METHOD, but this can be overwritten by $_POST['_method'].
     * Method is alway uppercase.
     * 
     * @return string
     */
    public static function getRequestMethod()
    {
        return strtoupper(!empty($_POST['_method']) ? $_POST['_method'] : $_SERVER['REQUEST_METHOD']);
    }
    
    /**
     * Check if we should output a specific format.
     * Defaults to 'html'.
     * 
     * @return string  'html', 'json', 'xml', 'text', 'js', 'css', 'png', 'gif' or 'jpeg'
     */
    public static function getRequestFormat()
    {
        if (empty($_SERVER['HTTP_ACCEPT'])) return 'html';
        
        if (preg_match('~^application/json\b~', $_SERVER['HTTP_ACCEPT'])) return 'json';
        if (preg_match('~^application/javascript\b~', $_SERVER['HTTP_ACCEPT']))
            return !empty($_GET['callback']) ? 'json' : 'js';
        
        if (preg_match('~^text/xml\b~', $_SERVER['HTTP_ACCEPT'])) return 'xml';
        if (preg_match('~^text/plain\b~', $_SERVER['HTTP_ACCEPT'])) return 'text';
        if (preg_match('~^text/css\b~', $_SERVER['HTTP_ACCEPT'])) return 'css';
        
        if (preg_match('~^image/(\w+)\b~', $_SERVER['HTTP_ACCEPT'], $match)) return $match[1];
        
        return 'html';
    }
    
    /**
     * Check if request is an AJAX request.
     * 
     * @return boolean
     */
    public static function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if requested to wrap JSON as JSONP response.
     * 
     * @return boolean
     */
    public static function isJsonpRequest()
    {
        return preg_match('~^application/javascript\b~', $_SERVER['HTTP_ACCEPT']) && !empty($_GET['callback']);
    }
    
    /**
     * Returns the HTTP referer if it is on the current host.
     * 
     * @return string
     */
    public static function getLocalReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) ==
            $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_REFERER'] : null;
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
        if (!isset($this->method)) {
            $this->method = strtoupper(!empty($_POST['_method']) ? $_POST['_method'] : $_SERVER['REQUEST_METHOD']);
        }
        
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
        if (!isset($this->url)) $this->url = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
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
     * Set the output format for messages.
     * 
     * @param string $format  'html', 'json', 'jsonp', 'xml', 'text', 'js', 'css', 'png', 'gif' or 'jpeg'
     * @return Router
     */
    public function setOutputFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Get the output format for messages.
     * Determines it from ACCEPT header by default.
     * 
     * @return string
     */
    public function getOutputFormat()
    {
        if (!isset($this->format)) $this->format = self::getRequestFormat();
        return $this->format;
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
     * @return string
     */
    protected function findRoute($method, $url)
    {
        $this->getRoutes(); // Make sure the routes are initialised
        
        if ($url !== '/') $url = rtrim($url, '/');
        if (substr($url, 0, 2) == '/:') $url = substr($url, 2);

        foreach (array_keys($this->routes) as $route) {
            if (strpos($route, ' ') !== false && preg_match('/^[A-Z]+\s/', $route)) {
                list($route_method, $route_path) = preg_split('/\s+/', $route, 2);
                if ($route_method !== $method) return;
            } else {
                $route_path = $route;
            }
            
            if ($route_path !== '/') $route_path = rtrim($route_path, '/');
            if ($this->fnmatch($route_path, $url)) return $route;
        }

        return false;
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

        if ($match) {
            $this->route = $this->bind($this->routes[$match], $this->splitUrl($url));
            $this->route->route = $match;
        } else {
            $this->route = false;
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

        if (!class_exists($class)) return $this->notFound();

        $controller = new $class($this);
        if (!is_callable([$controller, $method])) return $this->notFound();

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
        
        if (!isset($ret) || $ret === false) return $this->notFound();
        return $ret;
    }

    
    /**
     * Enable router to handle fatal errors.
     */
    public function handleErrors()
    {
        set_error_handler(array($this, 'onError'), E_RECOVERABLE_ERROR | E_USER_ERROR);
        set_exception_handler(array($this, 'onException'));
    }
    
    /**
     * Error handler callback
     * 
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     * @return boolean
     */
    public function onError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (!(error_reporting() & $errno)) return null;
        
        $args = get_defined_vars();
        return $this->_error($args);
    }

    /**
     * Exception handler callback
     * 
     * @param Exception $exception
     * @return boolean
     */
    public function onException($exception)
    {
        return $this->_error($exception);
    }
    

    /**
     * Redirect to another page and exit
     * 
     * @param string $url 
     * @param int    $http_code  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    public function redirect($url, $http_code=303)
    {
        // Turn relative URL into absolute URL
        if (strpos($url, '://') === false) {
            if ($url == '' || $url[0] != '/') $url = dirname($_SERVER['REQUEST_URI']) . '/' . $url;
            $url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $this->rebase($url);
        }

        if (ob_get_level() > 1) ob_end_clean();
        header("Location: $url", true, $http_code);
        
        echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        exit();
    }

    /**
     * Give a 400 Bad Request response and exit
     * 
     * @param string $message
     * @param int    $http_code  Alternative HTTP status code, eg. 406 (Not Acceptable)
     */
    public function badRequest($message, $http_code=400)
    {
        if (self::getRequestFormat() !== 'html') {
            self::outputError($http_code, $message, $this->getOutputFormat());
            exit();
        }
        
        if (ob_get_level() > 1) ob_end_clean();
        header(self::getProtocol() . ' '. static::$httpStatusCodes[$http_code]);
        
        if (!$this->routeTo(400, ['args'=>[$http_code, $message]])) echo $message;
        exit();
    }

    /**
     * Route to 401, otherwise result in a 403 forbidden.
     * 
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
     * @param int    $http_code  Alternative HTTP status code
     */
    public function forbidden($message=null, $http_code=403)
    {
        if (!isset($message)) $message = "Sorry, you are not allowed to view this page";
        
        if (self::getRequestFormat() !== 'html') {
            self::outputError($http_code, $message, $this->getOutputFormat());
            exit();
        }
        
        if (ob_get_level() > 1) ob_end_clean();
        header(self::getProtocol() . ' ' . static::$httpStatusCodes[$http_code]);
        
        if (!$this->routeTo(403, ['args'=>[$http_code, $message]])) echo $message;
        exit();
    }
    
    /**
     * Give a 404 Not Found response and exit
     * 
     * @param string $message
     * @param int    $http_code  Alternative HTTP status code, eg. 410 (Gone)
     */
    public function notFound($message=null, $http_code=404)
    {
        if (!isset($message)) $message = "Sorry, this page does not exist";
        
        if (self::getRequestFormat() !== 'html') {
            self::outputError($http_code, $message, $this->getOutputFormat());
            exit();
        }
        
        if (ob_get_level() > 1) ob_end_clean();
        header(self::getProtocol() . ' ' . static::$httpStatusCodes[$http_code]);
        
        if (!$this->routeTo(404, ['args'=>[$http_code, $message]])) echo $message;
        exit();
    }

    /**
     * Give a 500 Internal Server Error response and exit
     * 
     * @param string $message
     * @param int    $http_code  Alternative HTTP status code
     * @return boolean
     */
    public function error($message, $http_code=500)
    {
        if (!$this->_error($message, $http_code)) echo $message;
    }
    
    /**
     * Give a 500 Internal Server Error response
     * 
     * @param string|array|\Exception $error
     * @return boolean
     */
    protected function _error($error, $http_code=500)
    {
        if (self::getRequestFormat() !== 'html') {
            self::outputError(500, $error, $this->getOutputFormat());
            return true;
        }
        
        if (ob_get_level() > 1) ob_end_clean();
        header(self::getProtocol() . ' ' . static::$httpStatusCodes[$http_code]);
        return (boolean)$this->routeTo(500, ['args'=>[500, $error]]);
    }
    
    
    /**
     * Output an HTTP error
     * 
     * @param int           $http_code  HTTP status code
     * @param string|object $error
     * @param string        $format     The output format (auto detect by default)
     */
    public static function outputError($http_code, $error, $format=null)
    {
        if (ob_get_level() > 1) ob_end_clean();
        
        if (!isset($format)) $format = static::isJsonpRequest() ? 'jsonp' : static::getRequestFormat();
        
        if ($format !== 'jsonp') header(self::getProtocol() . ' ' . static::$httpStatusCodes[$http_code]);
        
        if ($error instanceof \Exception) {
            $error = (object)[
                'code' => $error->getCode(),
                'message' => $error->getMessage()
            ];
        }
        
        switch ($format) {
            case 'json':
            case 'jsonp':
                return static::outputErrorJson($format, $error);
                
            case 'xml':
                return static::outputErrorXml($error);
            
            case 'png':
            case 'gif':
            case 'jpeg':
                return static::outputErrorImage($format, $error);
        }
        
        echo is_scalar($error) ? $error : json_encode($error, JSON_PRETTY_PRINT);
    }
    
    /**
     * Output error as json
     * 
     * @param string        $format  'json' or 'jsonp'
     * @param string|object $error   Message or object 
     */
    protected static function outputErrorJson($format, $error)
    {
        if (strtolower($format) === 'jsonp') {
            header("Content-Type: application/javascript");
            echo $_GET['callback'] . '(' . json_encode(compact('http_code', 'error')) . ')';
        } else {
            header("Content-Type: application/json");
            echo json_encode($error);
        }
    }
    
    /**
     * Output error as json
     * 
     * @param string        $format  'jpeg', 'png' or 'gif'
     * @param string|object $error   Message or object 
     */
    public static function outputErrorXml($error)
    {
        header('Content-Type: text/xml');
        if (is_scalar($error)) {
            echo '<error>' . htmlspecialchars($error) . '</error>';
        } else {
            echo '<error>';
            foreach ($error as $key => $value) {
                echo "<$key>" . htmlspecialchars($value) . "</$key>";
            }
            echo '</error>';
        }
        return;
    }
    
    /**
     * Output an error image
     * 
     * @param string        $format  'jpeg', 'png' or 'gif'
     * @param string|object $error   Message or object 
     */
    protected static function outputErrorImage($format=null, $error=null)
    {
        if (!isset($format)) $format = $this->getRequestFormat();
        
        $image = imagecreate(100, 100);
        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 255, 0, 0);
        
        imagefill($image, 0, 0, $black);
        imageline($image, 0, 0, 100, 100, $red);
        imageline($image, 0, 100, 100, 0, $red);

        $out = 'image' . $format;
        
        if (is_scalar($error)) {
            header("X-Error: " . str_replace('\n', ' ', $error));
        } elseif (isset($error)) {
            foreach ($error as $key => $value) {
                header("X-Error-" . ucfirst($key) . ": " . str_replace('\n', ' ', $value));
            }
        }
        
        header("Content-Type: application/$format");
        $out($image);
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
        $pos = 0;
        foreach ($vars as $key => &$var) {
            if (!isset($var)) continue;
            
            if (!is_scalar($var)) {
                $var = static::bind($var, $parts);
                continue;
            }

            $options = preg_split('/(?<!\\\\)\|/', $var);
            $var = null;

            $replace_fn = function($match) use ($parts) {
                $i = $match[1];
                $i = $i > 0 ? $i - 1 : count($parts) - $i;
                return $parts[$i];
            };
            
            foreach ($options as $option) {
                if ($option[0] === '$') {
                    $i = (int)substr($option, 1);
                    if ($i > 0) $i--;

                    $slice = array_slice($parts, $i);

                    if (substr($option, -1, 1) != '+') {
                        if (!empty($slice)) $var = array_slice($parts, $i)[0];
                    } elseif (!is_array($vars) || is_string($key)) {
                        trigger_error("Binding multiple parts using \$+, like '$option', "
                                . "are only allow in (numeric) arrays", E_USER_WARING);
                        $var = null;
                    } else {
                        array_splice($vars, $pos, 1, $slice);
                        $pos += count($slice) - 1;
                    }
                } else {
                    $var = preg_replace_callback('/(?<!\\\\)\$(\d+)/', $replace_fn, $option);
                }

                if (!empty($var)) break; // continues if option can't be used
            }

            $pos++;
        }
        unset($var);
        
        return $vars;
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
        return strtr(ucwords(strtr($string, '_-', '  ')), [' '=>'']);
    }    
}
