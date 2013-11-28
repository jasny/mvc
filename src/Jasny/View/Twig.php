<?php

namespace Jasny\View;

use \Jasny\Router;

/**
 * View using Twig
 */
class Twig extends \Jasny\View
{
    /** @var \Twig_Template */
    protected $template;
    
    
    /**
     * Cached flash message
     * @var array
     */
    protected static $flash;
    
    /** @var \Twig_Environment */
    protected static $environment;
    
    
    /**
     * Class constructor
     * 
     * @param string $name  Template filename
     */
    public function __construct($name)
    {
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.html.twig';
        $this->template = self::getEnvironment()->loadTemplate($name);
    }

    /**
     * Render the template
     * 
     * @param array $context
     * @return string
     */
    public function render($context)
    {
        $this->template->render($context);
    }
    
    /**
     * Display the template
     * 
     * @param array $context
     * @return string
     */
    public function display($context)
    {
        $this->template->display($context);
    }
    
    
    /**
     * Init Twig environment
     * 
     * @param string $path   Path to the templates 
     * @param string $cache  The cache directory or false if cache is disabled.
     * @return \Twig_Environment
     */
    public static function init($path, $cache=false)
    {
        if (!isset(self::$default)) self::$default = '\\' . get_called_class();
        
        $loader = new \Twig_Loader_Filesystem($path);

        // Set options like caching or debug http://twig.sensiolabs.org/doc/api.html#environment-options
        $twig = new \Twig_Environment($loader);
        $twig->setCache($cache);
        
        // Add filters and extensions http://twig.sensiolabs.org/doc/api.html#using-extensions
        $twig->addFunction(new \Twig_SimpleFunction('flash', [__CLASS__, 'getFlash']));
        
        if (class_exists('Jasny\Twig\DateExtension')) $twig->addExtension(new \Jasny\Twig\DateExtension());
        if (class_exists('Jasny\Twig\PcreExtension')) $twig->addExtension(new \Jasny\Twig\PcreExtension());
        if (class_exists('Jasny\Twig\TextExtension')) $twig->addExtension(new \Jasny\Twig\TextExtension());
        if (class_exists('Jasny\Twig\ArrayExtension')) $twig->addExtension(new \Jasny\Twig\ArrayExtension());
        
        // Set globals http://twig.sensiolabs.org/doc/advanced.html#globals
        $twig->addGlobal('current_url', rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
        if (Router::isUsed()) $twig->addGlobal('current_route', Router::getRoute());
        
        self::$environment = $twig;
        return self::$environment;
    }

    /**
     * Get Twig environment
     */
    public static function getEnvironment()
    {
        if (!isset(static::$environment)) static::init((defined('BASE_PATH') ? BASE_PATH : getcwd()) . '/views');
        return static::$environment;
    }
}
