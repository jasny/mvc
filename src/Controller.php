<?php

namespace Jasny\MVC;

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
     * @var Request
     */
    protected $request;
    
    /**
     * @var Flash
     */
    protected $flash;
    
    
    /**
     * Class constructor
     * 
     * @param Router $router
     */
    public function __construct($router=null)
    {
        $this->router = $router;
        $this->request = new Request();
        $this->flash = new Flash();
    }
    
    /**
     * Returns the HTTP referer if it is on the current host.
     * 
     * @return string
     */
    protected function getLocalReferer()
    {
        return $this->request->getLocalReferer();
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
     * Get the request input data, decoded based on Content-Type header.
     * 
     * @return mixed
     */
    protected function getInput()
    {
        return $this->request->getInput();
    }
    
    /**
     * Set the headers with HTTP status code and content type.
     * 
     * @param int    $httpCode  HTTP status code (may be omitted)
     * @param string $format    Mime or simple format
     * @return Controller $this
     */
    protected function respondWith($httpCode, $format=null)
    {
        $this->request->respondWith($httpCode, $format);
        return $this;
    }
    
    /**
     * Output data
     * 
     * @param array $data
     */
    protected function output($data)
    {
        $this->request->output($data);
    }
    
    
    /**
     * Redirect to previous page.
     * Must be on this website, otherwise redirect to home.
     */
    protected function back()
    {
        $this->redirect($this->router->getLocalReferer() ?: '/', 303);
    }
    
    /**
     * Redirect to another page.
     * 
     * @param string $url 
     * @param int    $httpCode  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    protected function redirect($url, $httpCode=303)
    {
        if ($this->router) {
            $this->router->redirect($url, $httpCode);
        } else {
            $this->request->respondWith($httpCode, 'html');
            header("Location: $url");
            echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        }
        
        exit();
    }
    
    
    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     */
    protected function badRequest($message, $httpCode=400)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'badRequest'], func_get_args());
        } else {
            $this->request->outputError($httpCode, $message);
        }
        
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
     * @param int    $httpCode  HTTP status code
     */
    protected function forbidden($message=null, $httpCode=403)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'forbidden'], func_get_args());
        } else {
            if (!isset($message)) $message = "Sorry, you are not allowed to view this page";
            $this->request->outputError($httpCode, $message);
        }
        
        exit();
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     */
    protected function notFound($message=null, $httpCode=404)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'notFound'], func_get_args());
        } else {
            if (!isset($message)) $message = "Sorry, this page does not exist";
            $this->request->outputError($httpCode, $message);
        }

        exit();
    }
    
    /**
     * Give a 500 Internal Server Error response and exit
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     * @return boolean
     */
    public function error($message, $httpCode=500)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'error'], func_get_args());
        } else {
            $this->request->outputError($httpCode, $message);
        }
        
        exit();
    }
}
