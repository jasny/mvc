<?php

namespace Jasny\MVC;

/**
 * Class for the flash message
 */
class Flash
{
    /**
     * @var object
     */
    protected static $data;
    
    
    /**
     * Check if the flash is set.
     * 
     * @return boolean
     */
    public static function isIssued()
    {
        return isset($_SESSION['flash']) || isset(static::$data);
    }
    
    /**
     * Set the flash.
     * 
     * @param mixed $type     flash type, eg. 'error', 'notice' or 'success'
     * @param mixed $message  flash message
     */
    public static function set($type, $message)
    {
        static::$data = (object)['type'=>$type, 'message'=>$message];
        $_SESSION['flash'] = static::$data;
    }
    
    /**
     * Get the flash.
     * 
     * @return object
     */
    public static function get()
    {
        if (!isset(static::$data) && isset($_SESSION['flash'])) {
            static::$data = (object)$_SESSION['flash'];
            unset($_SESSION['flash']);
        }
        
        return static::$data;
    }
    
    /**
     * Reissue the flash.
     */
    public static function reissue()
    {
        if (!isset(static::$data) && isset($_SESSION['flash'])) {
            static::$data = (object)$_SESSION['flash'];
        } else {
            $_SESSION['flash'] = static::$data;
        }
    }
    
    /**
     * Clear the flash.
     */
    public static function clear()
    {
        self::$data = null;
        unset($_SESSION['flash']);
    }
    
    
    
    /**
     * Get the flash type
     * 
     * @return string
     */
    public static function getType()
    {
        $data = static::get();
        return isset($data) ? $data->type : null;
    }
    
    /**
     * Get the flash message
     * 
     * @return string
     */
    public static function getMessage()
    {
        $data = static::get();
        return isset($data) ? $data->message : null;
    }
    
    /**
     * Cast object to string
     * 
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getMessage();
    }
}
