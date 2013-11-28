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
        if ($this->router) return $this->router->redirect($url, $http_code);
        
        // Turn relative URL into absolute URL
        if (strpos($url, '://') === false) {
            if ($url == '' || $url[0] != '/') $url = dirname($_SERVER['REQUEST_URI']) . '/' . $url;
            $url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $this->rebase($url);
        }

        header("Location: $url", true, $http_code);
        echo 'You are being redirected to <a href="' . $url . '">' . $url . '</a>';
        
        exit();
    }

    /**
     * Give a 404 Not Found response
     * 
     * @param string $message
     */
    protected function notFound($message=null)
    {
        if ($this->router) return $this->router->notFound($message);
        
        header((@$_SERVER['SERVER_PROTOCOL'] ?: 'HTTP/1.1') . ' 404 Not Found');
        echo $message ?: "Sorry, this page does not exist";
        exit();
    }

    /**
     * Give a 400 Bad Request response
     * 
     * @param string $message
     */
    protected function badRequest($message)
    {
        if ($this->router) return $this->router->badRequest($message);
        
        if (ob_get_level() > 1) ob_end_clean();

        header((@$_SERVER['SERVER_PROTOCOL'] ?: 'HTTP/1.1') . ' 400 Bad Request');
        echo $message;
        exit();
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
