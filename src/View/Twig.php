<?php

namespace Jasny\MVC\View;

use Jasny\MVC\View;
use Jasny\MVC\Flash;

/**
 * View using Twig
 */
class Twig extends View
{
    /** @var \Twig_Template */
    protected $template;
    
    /** @var \Twig_Environment */
    protected $env;
    
    /**
     * The output content type
     * @var string
     */
    public $contentType = 'text/html';

    
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
        
        $this->env = clone self::getEnvironment();
        $this->template = $this->env->loadTemplate($name);
    }

    /**
     * Render the template
     * 
     * @param array $context
     * @return string
     */
    public function render($context)
    {
        return $this->template->render($context);
    }
    
    /**
     * Display the template
     * 
     * @param array $context
     * @return string
     */
    public function display($context)
    {
        header('Content-type: ' . $this->contentType . '; charset=' . $this->env->getCharset());
        $this->template->display($context);
    }

    
    /**
     * Add a global variable to the view.
     * 
     * @param string $name   Variable name
     * @param mixed  $value
     * @return Twig $this
     */
    public function set($name, $value)
    {
        $this->env->addGlobal($name, $value);
        return $this;
    }
    
    /**
     * Expose a function to the view.
     * 
     * @param string $function  Variable name
     * @param mixed  $callback
     * @param string $as        'function' or 'filter'
     * @return Twig $this
     */
    public function expose($function, $callback=null, $as='function')
    {
        if ($as === 'function') {
            $this->env->addFunction(new \Twig_SimpleFunction($function, $callback ?: $function));
        } elseif ($as === 'filter') {
            $this->env->addFilter(new \Twig_SimpleFilter('rot13', 'str_rot13'));
        }
        
        return $this;
    }
    
    
    /**
     * Init Twig environment.
     * Initializing automatically sets Twig to be used by default.
     * 
     * @param string|array $path   Path to the templates 
     * @param string       $cache  The cache directory or false if cache is disabled.
     * @return \Twig_Environment
     */
    public static function init($path=null, $cache=false)
    {
        if (!isset($path)) $path = getcwd();
        
        $loader = new \Twig_Loader_Filesystem($path);

        // Set options like caching or debug http://twig.sensiolabs.org/doc/api.html#environment-options
        $twig = new \Twig_Environment($loader);
        $twig->setCache($cache);
        
        // Add filters and extensions http://twig.sensiolabs.org/doc/api.html#using-extensions
        $twig->addGlobal('flash', new Flash());
        
        if (class_exists('Jasny\Twig\DateExtension')) $twig->addExtension(new \Jasny\Twig\DateExtension());
        if (class_exists('Jasny\Twig\PcreExtension')) $twig->addExtension(new \Jasny\Twig\PcreExtension());
        if (class_exists('Jasny\Twig\TextExtension')) $twig->addExtension(new \Jasny\Twig\TextExtension());
        if (class_exists('Jasny\Twig\ArrayExtension')) $twig->addExtension(new \Jasny\Twig\ArrayExtension());
        
        // Set globals http://twig.sensiolabs.org/doc/advanced.html#globals
        $twig->addGlobal('current_url', rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
        
        self::$environment = $twig;
        return self::$environment;
    }

    /**
     * Get Twig environment
     * 
     * @return \Twig_Environment
     */
    public static function getEnvironment()
    {
        if (!isset(static::$environment)) static::init();
        return static::$environment;
    }
}
