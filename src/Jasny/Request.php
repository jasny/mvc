<?php

namespace Jasny;

/**
 * Static class for the current HTTP request.
 */
class Request
{
    /**
     * HTTP status codes
     * @var array
     */
    static public $httpStatusCodes = [
        200 => '200 OK',
        201 => '201 Created',
        202 => '202 Accepted',
        204 => '204 No Content',
        205 => '205 Reset Content',
        206 => '206 Partial Content',
        301 => '301 Moved Permanently',
        302 => '302 Found',
        303 => '303 See Other',
        304 => '304 Not Modified',
        307 => '307 Temporary Redirect',
        308 => '308 Permanent Redirect',
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        402 => "402 Payment Required",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        409 => "409 Conflict",
        410 => "410 Gone",
        415 => "415 Unsupported Media Type",
        429 => "429 Too Many Requests",
        500 => "500 Internal server error",
        501 => "501 Not Implemented",
        503 => "503 Service unavailable"
    ];
    
    /**
     * Common input and output formats with associated MIME
     * @var array
     */
    static public $contentFormats = [
        'text/html' => 'html',
        'application/json' => 'json',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'text/plain' => 'text',
        'application/javascript' => 'js',
        'text/css' => 'css',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpeg',
        'application/x-www-form-urlencoded' => 'post',
        'multipart/form-data' => 'post',
        '*/*' => 'html'
    ];
    
    
    /**
     * Get the HTTP protocol
     * 
     * @return string;
     */
    protected static function getProtocol()
    {
        return isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
    }
    
    /**
     * Return the request method.
     * 
     * Usually REQUEST_METHOD, but this can be overwritten by $_POST['_method'].
     * Method is alway uppercase.
     * 
     * @return string
     */
    public static function getMethod()
    {
        return strtoupper(!empty($_POST['_method']) ? $_POST['_method'] : $_SERVER['REQUEST_METHOD']);
    }
    
    /**
     * Get the input format.
     * Uses the 'Content-Type' request header.
     * 
     * @param string $as  'short' or 'mime'
     * @return string
     */
    public static function getInputFormat($as='short')
    {
        if (empty($_SERVER['CONTENT_TYPE'])) {
            $mime = trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]);
        }
        
        return $as !== 'mime' && isset(static::$contentFormats[$mime]) ?
            static::$contentFormats[$mime] :
            $mime;
    }
    
    
    /**
     * Get the request input data, decoded based on Content-Type header.
     * 
     * @return mixed
     */
    public static function getInput()
    {
        switch (static::getInputFormat()) {
            case 'post': return $_FILES + $_POST;
            case 'json': return json_decode(file_get_contents('php://input'));
            case 'xml':  return simplexml_load_string(file_get_contents('php://input'));
            default:     return file_get_contents('php://input');
        }
    }
    
    /**
     * Get the output format.
     * Tries 'Content-Type' response header, otherwise uses 'Accept' request header.
     * 
     * @param string $as  'short' or 'mime'
     * @return string
     */
    public static function getOutputFormat($as='short')
    {
        foreach (headers_list() as $header) {
            if (strpos($header, 'Content-Type:') === 0) {
                $mime = trim(explode(';', substr($header, 13))[0]);
                break;
            }
        }
        
        if (!isset($mime) && !empty($_SERVER['HTTP_ACCEPT'])) {
            $mime = trim(explode(';', $_SERVER['HTTP_ACCEPT'])[0]);
        }
        
        if (!isset($mime)) $mime = '*/*';
        if ($mime === 'application/javascript' && !empty($_GET['callback'])) $mime = 'application/json';
        
        return $as !== 'mime' && isset(static::$contentFormats[$mime]) ?
            static::$contentFormats[$mime] :
            $mime;
    }
    
    /**
     * Check if request is an AJAX request.
     * 
     * @return boolean
     */
    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if requested to wrap JSON as JSONP response.
     * Assumes that the output format is json.
     * 
     * @return boolean
     */
    public static function isJsonp()
    {
        return !empty($_GET['callback']);
    }
    
    /**
     * Returns the HTTP referer if it is on the current host.
     * 
     * @return string
     */
    public static function getLocalReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) ==
            $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_REFERER'] : null;
    }
    
    
    /**
     * Set the headers with HTTP status code and content type.
     * @link http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
     * 
     * Examples:
     * <code>
     *   static::respondWith(200, 'json');
     *   static::respondWith(200, 'application/json');
     *   static::respondWith(204);
     *   static::respondWith('json');
     * </code>
     * 
     * @param int    $httpCode  HTTP status code (may be omitted)
     * @param string $format    Mime or content format
     */
    public static function respondWith($httpCode, $format=null)
    {
        if (!isset($format) && !is_int($httpCode) && !ctype_digit($httpCode)) {
            $format = $httpCode;
            $httpCode = null;
        }

        if (isset($httpCode)) {
            header(static::getProtocol() . ' ' . static::$httpStatusCodes[$httpCode]);
        }
        
        if (isset($format)) {
            $contentType = array_search($format, static::$contentFormats) ?: $format;
            header("Content-Type: {$contentType}");
        }
    }
    
    /**
     * Output result as json, xml or image.
     * 
     * @param mixed $data
     */
    public function output($data)
    {
        $format = static::getOutputFormat();
        
        switch ($format) {
            case 'json': static::outputJSON($data); break;
            case 'xml':  static::outputXML($data); break;
            
            case 'jpeg':
            case 'png':
            case 'gif':  static::outputImage($data, $format); break;
            
            case 'html':
                trigger_error("To output HTML please use a view", E_USER_ERROR);
        
            default:
                $type = (is_object($data) ? get_class($data) . ' ' : '') . gettype($data);
                $a = in_array($type[0], explode('', 'aeiouAEIOU')) ? 'an' : 'a';
                trigger_error("Don't know how to convert $a $type to $format", E_USER_ERROR);
        }
    }
        
    /**
     * Output result as json
     * 
     * @param \DomNode|\SimpleXMLElement $result
     */
    protected function outputXML($result)
    {
        if (!$result instanceof \DOMNode && !$result instanceof \SimpleXMLElement) {
            $type = (is_object($result) ? get_class($result) . ' ' : '') . gettype($result);
            $a = in_array($type[0], explode('', 'aeiouAEIOU')) ? 'an' : 'a';
            throw new \Exception("Was expecting a DOMNode or SimpleXMLElement object, got $a $type");
        }
        
        static::respondWith('xml');
        echo $result instanceof \DOMNode ?
            $result->ownerDocument->saveXML($result) :
            $result->asXML();
    }
    
    /**
     * Output result as json
     * 
     * @param mixed $result
     */
    protected function outputJSON($result)
    {
        if ($this->request->isJsonp()) {
            static::respondWith(200, 'js');
            echo $_GET['callback'] . '(' . json_encode($result) . ')';
            return;
        }

        static::respondWith('json');
        echo json_encode($result);
    }
    
    /**
     * Output an error image
     * 
     * @param resource  $image   GD image
     * @param string    $format  'jpeg', 'png' or 'gif'
     */
    protected static function outputImage($image, $format)
    {
        if (!is_resource($image)) {
            $type = (is_object($image) ? get_class($image) . ' ' : '') . gettype($image);
            $a = in_array($type[0], explode('', 'aeiouAEIOU')) ? 'an' : 'a';
            throw new \Exception("Was expecting a GD resource, got $a $type");
        }
        
        static::respondWith("image/$format");
        
        $out = 'image' . $format;
        $out($image);
    }
    

    /**
     * Output an HTTP error
     * 
     * @param int           $httpCode  HTTP status code
     * @param string|object $message
     * @param string        $format    The output format (auto detect by default)
     */
    public static function outputError($httpCode, $message, $format=null)
    {
        if (ob_get_level() > 1) ob_end_clean();

        if (!isset($format)) $format = static::getOutputFormat();
        
        switch ($format) {
            case 'json':
                return static::outputErrorJson($httpCode, $message);
                
            case 'xml':
                return static::outputErrorXml($httpCode, $message);
            
            case 'image':
                $format = 'png';
            case 'png':
            case 'gif':
            case 'jpeg':
                return static::outputErrorImage($httpCode, $message, $format);
        }
        
        static::respondWith($httpCode, 'text');
        echo is_array($message) ? $message : json_encode($message, JSON_PRETTY_PRINT);
    }
    
    /**
     * Output error as json
     * 
     * @param int           $httpCode
     * @param string|object $error
     */
    protected static function outputErrorJson($httpCode, $error)
    {
        $result = ['_error' => $error, '_httpCode' => $httpCode];
        
        static::respondWith($httpCode, 'json');
        static::output($result);
    }
    
    /**
     * Output error as xml
     * 
     * @param int           $httpCode
     * @param string|object $error
     */
    protected static function outputErrorXml($httpCode, $error)
    {
        static::respondWith($httpCode, 'xml');
        
        echo '<error>';
        
        if (is_scalar($error)) {
            echo htmlspecialchars($error);
        } else {
            foreach ($error as $key => $value) {
                echo "<$key>" . htmlspecialchars($value) . "</$key>";
            }
        }
        echo '</error>';
    }
    
    /**
     * Output an error image
     * 
     * @param string        $format  'jpeg', 'png' or 'gif'
     * @param string|object $error
     */
    protected static function outputErrorImage($format, $error=null)
    {
        $image = imagecreate(100, 100);
        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 255, 0, 0);
        
        imagefill($image, 0, 0, $black);
        imageline($image, 0, 0, 100, 100, $red);
        imageline($image, 0, 100, 100, 0, $red);

        if (is_scalar($error)) {
            header("X-Error: " . str_replace('\n', ' ', $error));
        } elseif (isset($error)) {
            foreach ($error as $key => $value) {
                header("X-Error-" . ucfirst($key) . ": " . str_replace('\n', ' ', $value));
            }
        }
        
        static::respondWith($format);
        static::output($image);
    }
}
