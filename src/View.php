<?php

namespace Jasny\MVC;

/**
 * Base class for view loaders.
 * 
 * <code>
 *   View::load("index.html.twig")->display();  // Specify the extension of the template
 * 
 *   View::using('twig');                       // or set the default view loader
 *   View::load("index")->display();
 * </code>
 */
abstract class View
{
    /**
     * The default view (by extension)
     * @var type
     */
    public static $default;
    
    /**
     * Mapping extension to loader
     * @var type 
     */
    public static $map = [
        'twig' => 'Jasny\View\Twig'
    ];

    /**
     * Cached flash message
     * @var array
     */
    protected static $flash;
    
    
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
    abstract public function display($context);

    
    /**
     * Set a global variable.
     * 
     * @param string $name   Variable name
     * @param mixed  $value
     * @return View $this
     */
    abstract public function set($name, $value);
    
    /**
     * Expose a function to the view.
     * 
     * @param string $function  Variable name
     * @param mixed  $callback
     * @return View $this
     */
    abstract public function expose($function, $callback=null);

    
    /**
     * Get or set default view loader
     * 
     * @param string $ext
     * @return string
     */
    public static function using($ext=null)
    {
        if (isset($ext)) self::$default = $ext;
        return self::$default;
    }
    
    /**
     * Get the view loader class to use
     * 
     * @param string $name  File name
     * @return string
     */
    protected final static function getClass($name)
    {
        $class = get_called_class();
        
        $refl = new \ReflectionClass($class);
        if ($refl->isAbstract()) {
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: self::$default;
            if (!$ext) throw new \Exception("Don't know how to view '$name'. No extension and no default view loader");
            
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
        $class = static::getClass($name);
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
}
