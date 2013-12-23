<?php

namespace Jasny;

/**
 * Route pretty URLs to correct controller
 * 
 * Wildcards:
 *  ?          Single character
 *  #          One or more digits (custom extension)
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
     * Get the URL to route
     * 
     * @return string
     */
    public function getUrl()
    {
        if (!isset($this->url)) $this->url = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
        return $this->url;
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
     * @param string $url
     * @return string
     */
    protected function findRoute($url)
    {
        $this->getRoutes(); // Make sure the routes are initialised
        
        if ($url !== '/') $url = rtrim($url, '/');
        if (substr($url, 0, 2) == '/:') $url = substr($url, 2);

        foreach (array_keys($this->routes) as $route) {
            if ($route !== '/') $route = rtrim($route, '/');
            if ($this->fnmatch($route, $url)) return $route;
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

        $url = $this->getUrl();
        if ($this->getBase()) {
            $url = '/' . preg_replace('~^' . preg_quote(trim($this->getBase(), '/'), '~') . '~', '', ltrim($url, '/'));
        }

        $match = $this->findRoute($url);

        if ($match) {
            $this->route = $this->bind($this->routes[$match], $this->splitUrl($url));
            $this->route->route = $match;
        } else {
            $this->route = false;
        }

        return $this->route;
    }

    /**
     * Get a parameter of the matching route.
     * 
     * @param string $name   Parameter name
     * @return mixed
     */
    public function get($name)
    {
        $route = $this->getRoute();
        return @$route->$name;
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
            $match = $this->findRoute($route);
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

        $class = $this->camelcase($route->controller) . 'Controller';
        $method = lcfirst($this->camelcase($route->action)) . 'Action';
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
        set_error_handler(array(get_called_class(), 'onError'), E_RECOVERABLE_ERROR | E_USER_ERROR);
        set_exception_handler(array(get_called_class(), 'error'));
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
    protected function onError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (!(error_reporting() & $errno)) return null;
        
        $args = get_defined_vars();
        return $this->error($args);
    }
    

    /**
     * Get the HTTP protocol
     * 
     * @return string;
     */
    protected static function getProtocol()
    {
        return @$_SERVER['SERVER_PROTOCOL'] ?: 'HTTP/1.1';
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

        header("Location: $url", true, $http_code);
        
        if (!$this->routeTo($http_code, ['args'=>[$url, $http_code]])) {
            echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        }
        exit();
    }

    /**
     * Give a 400 Bad Request response and exit
     * 
     * @param string $message
     */
    public function badRequest($message)
    {
        if (ob_get_level() > 1) ob_end_clean();

        header(self::getProtocol() . ' 400 Bad Request');
        if (!$this->routeTo(400, ['args'=>[400, $message]])) echo $message;
        exit();
    }

    /**
     * Give a 404 Not Found response and exit
     * 
     * @param string $message
     */
    public function notFound($message=null)
    {
        if (ob_get_level() > 1) ob_end_clean();

        if (!isset($message)) $message = "Sorry, this page does not exist";
        
        header(self::getProtocol() . ' 404 Not Found');
        if (!$this->routeTo(404, ['args'=>[404, $message]])) echo $message;
        exit();
    }

    /**
     * Give a 500 Internal Server Error response
     * 
     * @param array|\Exception $error
     * @return boolean
     */
    public function error($error)
    {
        if (ob_get_level() > 1) ob_end_clean();

        header(self::getProtocol() . ' 500 Internal Server Error');
        return (boolean)$this->routeTo(500, ['args'=>[500, $error]]);
    }
    
    
    /**
     * Get parts of a URL path
     * 
     * @param string $url
     * @return array
     */
    public function splitUrl($url)
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
    public function fnmatch($pattern, $path)
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
    protected function bind($vars, array $parts)
    {
        $pos = 0;
        foreach ($vars as $key => &$var) {
            if (!is_scalar($var)) {
                $var = $this->bind($var, $parts);
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
     * CamelCase a word
     * 
     * @param string $string
     * @return string
     */
    protected function camelcase($string)
    {
        return strtr(ucwords(strtr($string, '_-', '  ')), [' '=>'']);
    }
}
