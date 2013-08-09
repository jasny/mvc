<?php

namespace Jasny;

/**
 * View using Twig
 */
class View
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
     * @param string $path  Path to the templates 
     * @param string $path  The cache directory or false if cache is disabled.
     */
    public static function init($path, $cache=false)
    {
        $loader = new \Twig_Loader_Filesystem($path);

        $options = array();
        // Set options like caching or debug http://twig.sensiolabs.org/doc/api.html#environment-options
        
        $twig = new \Twig_Environment($loader, $options);
        $twig->setCache($cache);
        
        // Add filters and extensions http://twig.sensiolabs.org/doc/api.html#using-extensions
        $twig->addFunction(new \Twig_SimpleFunction('flash', [__CLASS__, 'getFlash']));
        $twig->addFilter(new \Twig_SimpleFilter('as_url', [__CLASS__, 'asUrl']));
        $twig->addFilter(new \Twig_SimpleFilter('as_thumb', [__CLASS__, 'asThumb']));
        
        $twig->addExtension(new Jasny\Twig\DateExtension());
        $twig->addExtension(new Jasny\Twig\PcreExtension());
        $twig->addExtension(new Jasny\Twig\TextExtension());
        $twig->addExtension(new Jasny\Twig\ArrayExtension());
        
        // Set globals http://twig.sensiolabs.org/doc/advanced.html#globals
        $current_url = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $twig->addGlobal('current_url', $current_url);
        $twig->addGlobal('current_item', preg_replace('~/.*~', '', $current_url));
        $twig->addGlobal('menu', self::getMenuItems());
        
        self::$environment = $twig;
        return self::$environment;
    }

    /**
     * Get Twig environment
     */
    public static function getEnvironment()
    {
        if (!isset(static::$environment)) static::init(getcwd() . '/views');
        return static::$environment;
    }

    
    /**
     * Load a view.
     * 
     * @param string $name  Template filename
     * @return View
     */
    public static function load($name)
    {
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.html.twig';
        return new static($name);
    }
    
    /**
     * Check if a view exists.
     * 
     * @param string $name  Template filename
     * @return boolean
     */
    public static function exists($name)
    {
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.html.twig';
        return self::getEnvironment()->getLoader()->exists($name);
    }
    
    
    /**
     * Get menu items
     * 
     * @return array
     */
    protected static function getMenuItems()
    {
        $profiles = DB::conn()->fetchAll("SELECT reference, name, type FROM profile");
        $events = DB::conn()->fetchAll("SELECT reference, name, 'event' AS type FROM event");
        
        return array_merge($profiles, $events);
    }
    
    
    /**
     * Use the flash message
     * 
     * @return array
     */
    public static function getFlash()
    {
        if (isset($_SESSION['flash'])) {
            self::$flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
        }
        
        return self::$flash;
    }
    
    /**
     * Change upload path to upload url.
     * 
     * @param string $file
     * @return string
     */
    public static function asUrl($file)
    {
        if (!isset($file)) return null;
        
        return substr_replace($file, 'http://usr.' . DOMAIN, 0, strlen(UPLOAD_PATH));
    }
    
    /**
     * Prefix image filename with thumb settings.
     * 
     * @param string $file
     * @param string $size
     * @return string
     */
    public static function asThumb($file, $size)
    {
        if (!isset($file)) return null;
        
        $file = dirname($file) . '/' . $size . '.'  . basename($file);
        return self::asUrl($file);
    }
}
