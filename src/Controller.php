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
        
        // Static classes are instantiated to make it easier to use custom versions
        $this->request = new Request();
    }
    
    /**
     * Set the flash message and/or return the flash object.
     * 
     * @param mixed $type     flash type, eg. 'error', 'notice' or 'success'
     * @param mixed $message  flash message
     * @return Flash
     */
    protected function flash($type = null, $message = null)
    {
        if (!isset($this->flash)) $this->flash = new Flash();
        
        if (isset($type) && isset($message)) $this->flash->set($type, $message);
        return $this->flash;
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
     * Get the request input data, decoded based on Content-Type header.
     * 
     * @param string|array $supportedFormats  Supported input formats (mime or short format)
     * @return mixed
     */
    protected function getInput($supportedFormats = null)
    {
        if (isset($supportedFormats)) {
            $failed = null;
            
            if ($this->router) $failed = function($message) {
                $this->router->badRequest($message, 415);
                exit();
            };
            
            $this->request->supportInputFormat($supportedFormats, $failed);
        }
        
        return $this->request->getInput();
    }
    
    
    /**
     * Set the headers with HTTP status code and content type.
     * 
     * @param int    $httpCode  HTTP status code (may be omitted)
     * @param string $format    Mime or simple format
     * @return $this
     */
    protected function respondWith($httpCode, $format=null)
    {
        $this->request->respondWith($httpCode, $format);
        return $this;
    }
    
    /**
     * Output data
     * 
     * @param array  $data
     * @param string $format    Mime or content format
     * @return $this
     */
    protected function output($data, $format = null)
    {
        $this->request->output($data, $format);
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
     * Respond with 200 Ok.
     * This is the default state, so you usually don't have to set it explicitly.
     * 
     * @return $this;
     */
    protected function ok()
    {
        $this->request->respondWith(200);
    }
    
    /**
     * Respond with 201 Created
     * 
     * @param string $location  Location of the created resource
     * @return $this;
     */
    protected function created($location = null)
    {
        $this->request->respondWith(201);
        if (isset($location)) header("Location: $location");
    }
    
    /**
     * Respond with 204 No Content
     * 
     * @return $this;
     */
    protected function noContent()
    {
        $this->request->respondWith(204);
    }
    
    
    /**
     * Redirect to previous page.
     * Must be on this website, otherwise redirect to home.
     * 
     * @return $this;
     */
    protected function back()
    {
        $this->redirect($this->getLocalReferer() ?: '/', 303);
    }
    
    /**
     * Redirect to another page.
     * 
     * @param string $url 
     * @param int    $httpCode  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    protected function redirect($url, $httpCode = 303)
    {
        if ($this->router) {
            $this->router->redirect($url, $httpCode);
        } else {
            $this->request->respondWith($httpCode, 'html');
            header("Location: $url");
            echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        }
    }
    
    
    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     */
    protected function badRequest($message, $httpCode = 400)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'badRequest'], func_get_args() + [1 => $httpCode]);
        } else {
            $this->request->outputError($httpCode, $message);
        }
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
    protected function forbidden($message=null, $httpCode = 403)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'forbidden'], func_get_args() + [1 => $httpCode]);
        } else {
            if (!isset($message)) $message = "Sorry, you are not allowed to view this page";
            $this->request->outputError($httpCode, $message);
        }
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     */
    protected function notFound($message=null, $httpCode = 404)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'notFound'], func_get_args() + [1 => $httpCode]);
        } else {
            if (!isset($message)) $message = "Sorry, this page does not exist";
            $this->request->outputError($httpCode, $message);
        }
    }
    
    /**
     * Give a 409 Conflict response
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     */
    protected function conflict($message, $httpCode = 409)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'badRequest'], func_get_args() + [1 => $httpCode]);
        } else {
            $this->request->outputError($httpCode, $message);
        }
    }
    
    /**
     * Give a 429 Too Many Requests response when the rate limit is exceded.
     * 
     * @param type $message
     * @param type $httpCode
     */
    protected function tooManyRequests($message, $httpCode = 429)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'badRequest'], func_get_args() + [1 => $httpCode]);
        } else {
            $this->request->outputError($httpCode, $message);
        }
    }
    
    /**
     * Give a 500 Internal Server Error response and exit
     * 
     * @param string $message
     * @param int    $httpCode  HTTP status code
     * @return boolean
     */
    public function error($message, $httpCode = 500)
    {
        if ($this->router) {
            call_user_func_array([$this->router, 'error'], func_get_args() + [1 => $httpCode]);
        } else {
            $this->request->outputError($httpCode, $message);
        }
    }
    
    
    /**
     * Check if request has POST method
     * 
     * @return boolean
     */
    protected function isGetRequest()
    {
        return $this->request->getMethod() === 'GET';
    }
    
    /**
     * Check if request has POST method
     * 
     * @return boolean
     */
    protected function isPostRequest()
    {
        return $this->request->getMethod() === 'POST';
    }
    
    /**
     * Check if request has POST method
     * 
     * @return boolean
     */
    protected function isPutRequest()
    {
        return $this->request->getMethod() === 'PUT';
    }
    
    /**
     * Check if request has POST method
     * 
     * @return boolean
     */
    protected function isDeleteRequest()
    {
        return $this->request->getMethod() === 'DELETE';
    }
    
    
    /**
     * Check if response is 2xx succesful
     * 
     * @return boolean
     */
    protected function isSuccessful()
    {
        $code = http_response_code();
        return $code >= 200 && $code < 300;
    }
    
    /**
     * Check if response is a 3xx redirect
     * 
     * @return boolean
     */
    protected function isRedirection()
    {
        $code = http_response_code();
        return $code >= 300 && $code < 400;
    }
    
    /**
     * Check if response is a 4xx client error
     * 
     * @return boolean
     */
    protected function isClientError()
    {
        $code = http_response_code();
        return $code >= 400 && $code < 500;
    }
    
    /**
     * Check if response is a 5xx redirect
     * 
     * @return boolean
     */
    protected function isServerError()
    {
        $code = http_response_code();
        return $code >= 500;
    }
}
