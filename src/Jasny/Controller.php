<?php
namespace Jasny;

/**
 * Base class for controllers.
 */
abstract class Controller
{
    /**
     * Used router
     * @var Router
     */
    protected $router;

    /**
     * Class constructor
     * 
     * @param Router $router
     */
    public function __construct($router=null)
    {
        $this->router = $router;
    }
    

    /**
     * Return the request method.
     * 
     * Usually $_SERVER['REQUEST_METHOD'], but this can be overwritten by $_POST['_method'].
     * Method is alway uppercase.
     * 
     * @return string
     */
    public function getRequestMethod()
    {
        return Router::getRequestMethod();
    }
    
    /**
     * Check if we should output a specific format.
     * Defaults to html.
     * 
     * @return string  'html', 'json', 'xml', 'text', 'js', 'css', 'png', 'gif' or 'jpeg'
     */
    protected function getRequestFormat()
    {
        return Router::getRequestFormat();
    }

    /**
     * Shortcut for REQUEST_METHOD === 'POST'
     * 
     * @return boolean
     */
    public function isPostRequest()
    {
        return $this->getRequestMethod() === 'POST';
    }
    
    /**
     * Check if request is an AJAX request.
     * 
     * @return boolean
     */
    protected function isAjaxRequest()
    {
        return Router::isAjaxRequest();
    }

    /**
     * Check if requested to wrap JSON as JSONP response.
     * 
     * @return boolean
     */
    protected function isJsonpRequest()
    {
        return Router::isJsonpRequest();
    }
    
    /**
     * Returns the HTTP referer if it is on the current host.
     * 
     * @return string
     */
    protected function getLocalReferer()
    {
        return Router::getLocalReferer();
    }
    
    
    /**
     * Show a view.
     * 
     * @param string $name     Filename of Twig template
     * @param array  $context  Data
     */
    protected function view($name=null, $context=[])
    {
        if (!isset($name) && isset($this->router))
            $name = $this->router()->get('controller') . '/' . $this->router()->get('action');

        View::load($name)
            ->set('current_route', $this->router->getRoute())
            ->display($context);
    }
    
    /**
     * Output result as JSON.
     * Supports JSONP.
     * 
     * @param mixed $result
     * @param int   $options  json_encode options
     */
    protected function outputJSON($result, $options=0)
    {
        if ($this->isJsonpRequest()) {
            header("Content-Type: application/javascript");
            echo $_GET['callback'] . '(' . json_encode($result, $options) . ')';
        } else {
            header("Content-Type: application/json");
            echo json_encode($result, $options);
        }
    }

    
    /**
     * Redirect to previous page.
     * Must be on this website, otherwise redirect to home.
     */
    protected function back()
    {
        $this->redirect(Router::getLocalReferer() ?: '/', 303);
    }
    
    /**
     * Redirect to another page.
     * 
     * @example return $this->redirect("somepage.php");
     * 
     * @param string $url 
     * @param int    $http_code  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    protected function redirect($url, $http_code=303)
    {
        Router::redirect($url, $http_code);
    }
    
    
    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     */
    protected function badRequest($message, $http_code=400)
    {
        if ($this->router) return $this->router->badRequest($message, $http_code);
        
        Router::outputHttpError($http_code, $message);
        exit();
    }

    /**
     * Route to 401, otherwise result in a 403 forbidden.
     * 
     * Note: While the 401 route is used, we don't respond with a 401 http status code.
     */
    public function requireLogin()
    {
        if ($this->router) return $this->router->requireLogin();
        $this->forbidden();
    }
    
    /**
     * Give a 403 Forbidden response
     * 
     * @param string $message
     */
    protected function forbidden($message=null, $http_code=403)
    {
        if ($this->router) return $this->router->forbidden($message, $http_code);
        
        if (!isset($message)) $message = "Sorry, you are not allowed to view this page";
        Router::outputHttpError($http_code, $message);
        exit();
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     */
    protected function notFound($message=null, $http_code=404)
    {
        if ($this->router) return $this->router->notFound($message, $http_code);
        
        if (!isset($message)) $message = "Sorry, this page does not exist";
        Router::outputHttpError($http_code, $message);
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
        if ($this->router) return $this->router->error($message, $http_code);
        
        Router::outputHttpError($http_code, $message);
        exit();
    }
    
    
    /**
     * Set flash message.
     * 
     * @param mixed $type     flash type, eg. 'error', 'notice' or 'success'
     * @param mixed $message  flash message
     */
    public static function setFlash($type, $message)
    {
        $_SESSION['flash'] = ['type'=>$type, 'message'=>$message];
    }
    
    /**
     * Get the flash message.
     */
    public static function getFlash()
    {
        unset($_SESSION['flash']);
    }
    
    /**
     * Clear the flash message.
     */
    public static function clearFlash()
    {
        unset($_SESSION['flash']);
    }
}
