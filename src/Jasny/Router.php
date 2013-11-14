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
     * The default instance
     * @var Router
     */
    protected static $instance;

    /**
     * Specific routes
     * @var object 
     */
    protected static $routes = [
        '/**' => ['controller' => '$1|default', 'action' => '$2|index', 'args' => ['$3+']],
    ];

    /**
     * Webroot subdir from DOCUMENT_ROOT.
     * @var string
     */
    protected static $base;

    /**
     * URL to route
     * @var string 
     */
    protected static $url;

    /**
     * Variables from matched route (cached)
     * @var object 
     */
    protected static $route;

    
    /**
     * Get the default router
     * 
     * @deprecated
     */
    public static function i()
    {
        if (!isset(self::$instance)) self::$instance = new static();
        return self::$instance;
    }

    
    /**
     * Set the routes
     * 
     * @param array $routes
     */
    public static function setRoutes($routes)
    {
        static::$routes = (object)$routes;
        static::$route = null;
    }

    /**
     * Get a list of all routes
     * 
     * @return array
     */
    public static function getRoutes()
    {
        if (!is_object(static::$routes)) static::$routes = (object)static::$routes;
        return static::$routes;
    }

    
    /**
     * Set the webroot subdir from DOCUMENT_ROOT.
     * 
     * @param string $dir
     */
    public static function setBase($dir)
    {
        static::$base = rtrim($dir, '/');
        static::$route = null;

        return $this;
    }

    /**
     * Get the webroot subdir from DOCUMENT_ROOT.
     * 
     * @return string
     */
    public static function getBase()
    {
        return static::$base;
    }

    /**
     * Add a base path to the URL if the webroot isn't the same as the webservers document root
     * 
     * @param string $url
     * @return string
     */
    public static function rebase($url)
    {
        return (static::getBase() ?: '/') . ltrim($url, '/');
    }

    /**
     * Set the URL to route
     * 
     * @param string $url
     */
    public static function setUrl($url)
    {
        static::$url = $url;
        static::$route = null;

        return $this;
    }

    /**
     * Get the URL to route
     * 
     * @return string
     */
    public static function getUrl()
    {
        if (!isset(static::$url)) static::$url = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
        return static::$url;
    }

    
    /**
     * Check if the router has been used.
     * 
     * @return boolean
     */
    public static function isUsed()
    {
        return isset(static::$route);
    }

    /**
     * Find a matching route
     * 
     * @param string $url
     * @return string
     */
    protected static function findRoute($url)
    {
        static::getRoutes(); // Make sure the routes are initialised
        
        if ($url !== '/') $url = rtrim($url, '/');

        foreach (array_keys((array)static::$routes) as $route) {
            if ($route !== '/') $route = rtrim($route, '/');
            if (static::fnmatch($route, $url)) return $route;
        }

        return false;
    }

    /**
     * Get a matching route.
     * 
     * @return object
     */
    public static function getRoute()
    {
        if (isset(static::$route)) return static::$route;

        $url = static::getUrl();
        if (static::getBase()) {
            $url = '/' . preg_replace('~^' . preg_quote(trim(static::getBase(), '/'), '~') . '~', '', ltrim($url, '/'));
        }

        $match = static::findRoute($url);

        if ($match) {
            static::$route = static::bind((object)static::$routes->$match, static::splitUrl($url));
            static::$route->route = $match;
        } else {
            static::$route = false;
        }

        return static::$route;
    }

    /**
     * Get a parameter of the matching route.
     * 
     * @param string $name   Parameter name
     * @return mixed
     */
    public static function get($name)
    {
        $route = static::getRoute();
        return @$route->$name;
    }
    
    
    /**
     * Execute the action of the given route.
     * 
     * @param object $route
     * @param object $overwrite
     * @return boolean|mixed  Whatever the controller returns or true on success
     */
    protected static function routeTo($route, $overwrite=[])
    {
        if (!is_object($route)) {
            $match = static::findRoute($route);
            if (!isset($match) || !isset(static::$routes->$match)) return false;
            $route = static::$routes->$match;
        }

        foreach ($overwrite as $key=>$value) {
            $route->$key = $value;
        }
        
        // Route to file
        if (isset($route->file)) {
            $file = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim(static::rebase($route->file), '/');

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

        $class = static::camelcase($route->controller) . 'Controller';
        $method = lcfirst(static::camelcase($route->action)) . 'Action';
        $args = isset($route->args) ? $route->args : [];

        if (!class_exists($class)) return static::notFound();

        $controller = new $class();
        if (!is_callable([$controller, $method])) return static::notFound();

        $ret = call_user_func_array([$controller, $method], $args);
        return isset($ret) ? $ret : true;
    }

    /**
     * Execute the action.
     * 
     * @return mixed  Whatever the controller returns
     */
    public static function execute()
    {
        $route = static::getRoute();
        if ($route) $ret = static::routeTo($route);
        
        if (!isset($ret) || $ret === false) return static::notFound();
        return $ret;
    }

    
    /**
     * Enable router to handle fatal errors.
     */
    public static function handleErrors()
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
    private static function onError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (!(error_reporting() & $errno)) return null;
        
        $args = get_defined_vars();
        return static::error($args);
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
    public static function redirect($url, $http_code=303)
    {
        // Turn relative URL into absolute URL
        if (strpos($url, '://') === false) {
            if ($url == '' || $url[0] != '/') $url = dirname($_SERVER['REQUEST_URI']) . '/' . $url;
            $url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . static::rebase($url);
        }

        header("Location: $url", true, $http_code);
        
        if (!static::routeTo($http_code, ['args'=>[$url, $http_code]])) {
            echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        }
        exit();
    }

    /**
     * Give a 400 Bad Request response and exit
     * 
     * @param string $message
     */
    public static function badRequest($message)
    {
        if (ob_get_level() > 1) ob_end_clean();

        header(static::getProtocol() . ' 400 Bad Request');
        if (!static::routeTo(400, ['args'=>[400, $message]])) echo $message;
        exit();
    }

    /**
     * Give a 404 Not Found response and exit
     * 
     * @param string $message
     */
    public static function notFound($message=null)
    {
        if (ob_get_level() > 1) ob_end_clean();

        if (!isset($message)) $message = "Sorry, this page does not exist";
        
        header(static::getProtocol() . ' 404 Not Found');
        if (!static::routeTo(404, ['args'=>[404, $message]])) echo $message;
        exit();
    }

    /**
     * Give a 500 Internal Server Error response
     * 
     * @param array|\Exception $error
     * @return boolean
     */
    public static function error($error)
    {
        if (ob_get_level() > 1) ob_end_clean();

        header(static::getProtocol() . ' 500 Internal Server Error');
        return (boolean)static::routeTo(500, ['args'=>[500, $error]]);
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
            if (!is_scalar($var)) {
                $var = static::bind($var, $parts);
                continue;
            }

            $options = explode('|', $var);
            $var = null;

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
                    $var = preg_replace('/^([\'"])(.*)\1$/', '$2', $option); // Unquote
                }

                if (!empty($var)) break; // continues if option can't be used
            }

            $pos++;
        }

        return $vars;
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
