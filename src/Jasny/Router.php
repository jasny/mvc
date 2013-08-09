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
     * @var array 
     */
    protected $routes = [
        '/**' => ['controller'=>'%1|default', 'action'=>'%2|index', 'args'=>['%3+']],
    ];
    
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
     * Get the default router
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
     * @return Router  $this
     */
    public function setRoutes($routes)
    {
        $this->routes = (array)$routes;
        $this->route = null;
        
        return $this;
    }

    /**
     * Get a list of all routes
     * 
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    
    
    /**
     * Set the webroot subdir from DOCUMENT_ROOT.
     * 
     * @param string $dir
     * @return Router  $this
     */
    public function setBase($dir)
    {
        $this->base = $dir;
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
        return (isset($this->base) ? rtrim($this->base, '/') . '/' : '') . $url;
    }
    
    
    /**
     * Set the URL to route
     * 
     * @param string $url
     * @return Router  $this
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
        if (!isset($this->url)) $this->url = $_SERVER['REQUEST_URI'];
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
        $url = rtrim($url, '/');
        
        foreach (array_keys($this->routes) as $route) {
            if (static::fnmatch(rtrim($route, '/'), $url)) return $route;
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
        if ($this->base) {
            $url = '/' . preg_replace('~^' . preg_quote(trim($this->base, '/'), '~') . '~', '', ltrim($url, '/'));
        }
        
        $match = $this->findRoute($url);
        
        if ($match) {
            $this->route = static::bind((object)$this->routes[$match], static::splitUrl($url));
            $this->route->route = $match;
        } else {
            $this->route = false;
        }
        
        return $this->route;
    }
    
    
    /**
     * Execute the action.
     * 
     * @return mixed  Whatever the controller returns
     */
    public function execute()
    {
        $route = $this->getRoute();
        
        // No route
        if (!$route) return $this->notFound();
        
        // Route to file
        if (isset($route->file)) {
            $file = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim(static::rebase($route->file), '/');
            
            if (!file_exists($file)) {
                trigger_error("Failed to route using '{$route->route}': File '$file' doesn't exist."
                    , E_USER_WARNING);
                return $this->notFound();
            }
            
            return include $file; 
        }
        
        // Route to controller
        if (empty($route->controller) || empty($route->action)) {
            trigger_error("Failed to route using '{$route->route}': "
                . (empty($route->controller) ? 'Controller' : 'Action') . " is not set", E_USER_WARNING);
            return $this->notFound();
        }
        
        $class = ucfirst($route->controller) . 'Controller';
        $method = $route->action . 'Action';
        $args = isset($route->args) ? $route->args : [];
 
        if (!class_exists($class)) return $this->notFound();
        
        $controller = new $class();
        if (!is_callable([$controller, $method])) return $this->notFound();
        
        return call_user_func_array([$controller, $method], $args);
    }
    
    
    /**
     * Redirect to another page.
     * 
     * @param string $url 
     * @param int    $http_code  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    public function redirect($url, $http_code = 303)
    {
        // Turn relative URL into absolute URL
        if (strpos($url, '://') === false) {
            if ($url == '' || $url[0] != '/') $url = dirname($_SERVER['REQUEST_URI']) . '/' . $url;
            $url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $this->rebaseUrl($url);
        }

        header("Location: $url", true, $http_code);
        echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     */
    public function notFound($message=null)
    {
       if (ob_get_level() > 1) ob_end_clean();
       
       header('HTTP/1.0 404 Not Found');
       echo $message ?: "Sorry, this page does not exist"; 
    }
    
    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     */
    public function badRequest($message)
    {
       if (ob_get_level() > 1) ob_end_clean();
       
       header('HTTP/1.0 400 Bad Request');
       echo $message;
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
        $regex = strtr($regex, ['\?'=>'[^/]', '\*'=>'[^/]*', '/\*\*'=>'(?:/.+)?', '#'=>'\d+', '\['=>'[', '\]'=>']', '\-'=>'-', '\{'=>'{', '\}'=>'}']);
        $regex = preg_replace_callback('~{[^\}]+}~', function($part) { return '(' . substr(strtr($part[0], ',', '|'), 1, -1) . ')'; }, $regex);
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
        foreach ($vars as $key=>&$var) {
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
}
