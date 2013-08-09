<?php
namespace Jasny;

/**
 * Base class for controllers.
 */
abstract class Controller
{
    /**
     * Show a view.
     * 
     * @param string $name     Filename of Twig template
     * @param array  $context  Data
     */
    protected function view($name, $context = array())
    {
        header('Content-type: text/html; charset=utf-8');
        View::load($name)->display($context);
    }
    
    
    /**
     * Redirect to another page.
     * 
     * @example return $this->redirect("somepage.php");
     * 
     * @param string $url 
     * @param int    $http_code  301 (Moved Permanently), 303 (See Other) or 307 (Temporary Redirect)
     */
    protected function redirect($url, $http_code = 303)
    {
        return Router::redirect($url, $http_code);
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     */
    protected function notFound($message=null)
    {
        return Router::notFound($message);
    }

    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     */
    protected function badRequest($message)
    {
        return Router::badRequest($message);
    }
    
    
    /**
     * Set flash message.
     * 
     * @access protected
     * @param mixed $type     flash type, error, notice, success
     * @param mixed $message  flash message
     * @return void
     */
    protected function setFlash($type, $message)
    {
        $_SESSION['flash'] = ['type'=>$type, 'message'=>$message];
    }
}
