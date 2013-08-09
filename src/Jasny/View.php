<?php

namespace Jasny;

/**
 * Base class for view loaders
 */
abstract class View
{
    /**
     * Class name default loader.
     * Relative to namespace Jasny\View.
     * 
     * @var string
     */
    public static $default;
    
    /**
     * Cached flash message
     * @var array
     */
    protected static $flash;
    
    
    /**
     * Class constructor
     * 
     * @param string $name  Template filename
     */
    abstract public function __construct($name);

    /**
     * Render the template
     * 
     * @param array $context
     * @return string
     */
    abstract public function render($context);
    
    /**
     * Display the template
     * 
     * @param array $context
     * @return string
     */
    public function display($context)
    {
        echo $this->template->render($context);
    }

    
    /**
     * Get the view loader class to use
     * 
     * @return string
     */
    protected final static function getClass()
    {
        $class = get_called_class();
        
        $refl = new \ReflectionClass($class);
        if ($refl->isAbstract()) $class = (self::$default[0] == '\\' ? '' : __CLASS__ . '\\') . ucfirst(self::$default);
        
        return $class;
    }
    
    /**
     * Load a view.
     * 
     * @param string $name  Template filename
     * @return View
     */
    public static function load($name)
    {
        $class = static::getClass();
        return new $class($name);
    }
    
    /**
     * Check if a view exists.
     * {@internal You *need* to overwrite this in child classes to prevent a dead loop}}
     * 
     * @param string $name  Template filename
     * @return boolean
     */
    public static function exists($name)
    {
        $class = static::getClass();
        return call_user_func([$class, 'exists'], $name);
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
}
