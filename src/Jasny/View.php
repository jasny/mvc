<?php

namespace Jasny;

/**
 * Base class for view loaders
 */
abstract class View
{
    /**
     * Mapping extension to loader
     * @var type 
     */
    public static $map = [
        'twig' => '\Jasny\View\Twig'
    ];
    
    
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
     * @param string $ext  File extension
     * @return string
     */
    protected final static function getClass($ext)
    {
        $class = get_called_class();
        
        $refl = new \ReflectionClass($class);
        if ($refl->isAbstract()) {
            if (!isset(self::$map[$ext])) throw new \Exception("Don't know how to view a '.$ext' file");
            $class = self::$map[$ext];
        }
        
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
        $class = static::getClass(pathinfo($name, PATHINFO_EXTENSION));
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
