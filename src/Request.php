<?php

namespace Jasny\MVC;

/**
 * Static class for the current HTTP request.
 */
class Request
{
    /**
     * Common input and output formats with associated MIME
     * @var array
     */
    public static $contentFormats = [
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
        'image/x-icon' => 'ico',
        'application/x-www-form-urlencoded' => 'post',
        'multipart/form-data' => 'post'
    ];
    
    /**
     * File extensions to format mapping
     * @var array
     */
    public static $fileExtension = [
        'jpg' => 'jpeg',
        'txt' => 'text'
    ];
    
    /**
     * Allow the use $_POST['_method'] as request method.
     * @var boolean
     */
    public static $allowMethodOverride = false;
    
    /**
     * Always set 'Content-Type' to 'text/plain' with a 4xx or 5xx response.
     * This is useful when handing jQuery AJAX requests, since jQuery doesn't deserialize errors.
     * 
     * @var boolean
     */
    public static $forceTextErrors = false;

    
    /**
     * Get the client IP address
     * 
     * @param boolean|string $proxy  Trust proxy (true = all proxies or CIDR)
     * @return string
     */
    public static function getClientIp($proxy = false)
    {
        if (!isset($_SERVER['REMOTE_ADDR'])) return null;
        
        if (
            is_string($proxy) &&
            \Jasny\ip_in_cidr($_SERVER['REMOTE_ADDR'], $proxy) &&
            !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            list($ip) = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return $ip;
        }
        
        if ($proxy === true && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return end($ips);
        }
        
        if (isset($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        
        return null;
    }
    
    /**
     * Get the HTTP protocol
     * 
     * @return string;
     */
    protected static function getProtocol()
    {
        return isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
    }
    
    /**
     * Return the request method.
     * 
     * @return string
     */
    public static function getMethod()
    {
        return static::$allowMethodOverride && !empty($_POST['_method']) ?
            strtoupper($_POST['_method']) :
            strtoupper($_SERVER['REQUEST_METHOD']);
    }
    
    
    /**
     * Get the input format.
     * Uses the 'Content-Type' request header.
     * 
     * @param string $as  'short' or 'mime'
     * @return string
     */
    public static function getInputFormat($as)
    {
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'];
        } elseif (!empty($_SERVER['HTTP_CONTENT_TYPE'])) {
            $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
        } else {
            return null;
        }
        
        $mime = trim(explode(';', $contentType)[0]);
        
        return $as !== 'mime' && isset(static::$contentFormats[$mime]) ?
            static::$contentFormats[$mime] :
            $mime;
    }
    
    /**
     * Check `Content-Type` request header to see if input format is supported.
     * Respond with "415 Unsupported Media Type" if the format isn't supported.
     * 
     * @param string|array $support  Supported formats (short or mime)
     * @param callback     $failed   Callback when format is not supported
     */
    public static function supportInputFormat($support, $failed = null)
    {
        $mime = static::getInputFormat('mime');
        
        if (!isset($mime)) {
            if (file_get_contents('php://input', false, null, -1, 1) === '') return;
        } else {
            if (static::matchMime($mime, $support)) return;
        }
        
        // Not supported
        $message = isset($mime) ?
            "The request body is in an unsupported format" :
            "The 'Content-Type' request header isn't set";
        
        if (isset($failed)) call_user_func($failed, $message, $support);
        
        static::outputError("415 Unsupported Media Type", $message);
        exit();
    }
    
    /**
     * Check the Content-Type of the request.
     * 
     * @param string       $mime
     * @param string|array $formats  Short format or MIME, may contain wildcard
     * @return mixed
     */
    protected static function matchMime($mime, $formats)
    {
        $fnWildcardMatch = function($ret, $pattern) use ($mime) {
            return $ret || fnmatch($pattern, $mime);
        };

        return
            isset(static::$contentFormats[$mime]) && in_array(static::$contentFormats[$mime], $formats) ||
            array_reduce($formats, $fnWildcardMatch, false);
    }
    
    /**
     * Get the request input data, decoded based on Content-Type header.
     * 
     * @return mixed
     */
    public static function getInput()
    {
        switch (static::getInputFormat('short')) {
            case 'post': return $_POST + static::getPostedFiles();
            case 'json': return json_decode(file_get_contents('php://input'), true);
            case 'xml':  return simplexml_load_string(file_get_contents('php://input'));
            default:     return file_get_contents('php://input');
        }
    }
    
    /**
     * Get $_FILES properly grouped.
     * 
     * @return array
     */
    protected static function getPostedFiles()
    {
        $files = $_FILES;
        
        foreach ($files as &$file) {
            if (!is_array($file['error'])) continue;
            
            $group = [];
            foreach (array_keys($file['error']) as $key) {
                foreach (array_keys($file) as $elem) {
                    $group[$key][$elem] = $file[$elem][$key];
                }
            }
            $file = $group;
        }
        
        return $files;
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
     * Get the output format.
     * Tries 'Content-Type' response header, otherwise uses 'Accept' request header.
     * 
     * @param string $as  'short' or 'mime'
     * @return string
     */
    public static function getOutputFormat($as)
    {
        // Explicitly set as Content-Type response header
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $mime = trim(explode(';', substr($header, 13))[0]);
                break;
            }
        }
        
        // Accept request header
        if (!isset($mime) && !empty($_SERVER['HTTP_ACCEPT'])) {
            $mime = trim(explode(',', $_SERVER['HTTP_ACCEPT'])[0]);
        }
        
        if (!isset($mime)) $mime = '*/*';
        if ($mime === 'application/javascript' && !empty($_GET['callback'])) $mime = 'application/json';
        
        if ($mime !== '*/*') {
            return $as === 'mime' || !isset(static::$contentFormats[$mime]) ?
                $mime : static::$contentFormats[$mime];
        }
        
        // File extension
        $ext = pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_EXTENSION);
        if ($ext) {
            if (isset(static::$fileExtension[$ext])) $ext = static::$fileExtension[$ext];
            return ($as === 'mime') ? (array_search($ext, static::$contentFormats) ?: $ext) : $ext;
        }
        
        // Don't know (default to HTML)
        return $as === 'mime' ? '*/*' : 'html';
    }

    /**
     * Accept requests from a specific origin.
     * 
     * Sets HTTP Access-Control headers (CORS).
     * @link http://www.w3.org/TR/cors/
     * 
     * The following settings are available:
     *  - expose-headers
     *  - max-age
     *  - allow-credentials (default true)
     *  - allow-methods (default '*')
     *  - allow-headers (default '*')
     * 
     * <code>
     *   Request::allowOrigin('same');
     *   Request::allowOrigin('www.example.com');
     *   Request::allowOrigin('*.example.com');
     *   Request::allowOrigin(['*.example.com', 'www.example.net']);
     *   Request::allowOrigin('*');
     * 
     *   Request::allowOrigin('same', [], function() {
     *     static::respondWith("403 forbidden", 'html');
     *     echo "<h1>Forbidden</h1><p>Sorry, we have a strict same-origin policy.</p>";
     *     exit();
     *   });
     * </code>
     * 
     * @param string|array $urls      Allowed URL/URLs, may use wildcards or "same"
     * @param array        $settings
     * @param callback     $failed    Called when origin is not allowed
     */
    public static function allowOrigin($urls, array $settings = [], $failed = null)
    {
        if (!isset($_SERVER['HTTP_ORIGIN'])) return;
        
        $origin = static::matchOrigin($urls);
       
        static::setAllowOriginHeaders($origin ?: $urls, $settings);
        
        if (!isset($origin)) {
            $message = "Origin not allowed";
            if (isset($failed)) call_user_func($failed, $message, $urls);
            static::outputError("403 forbidden", $message);
            exit();
        }
    }

    /**
     * Match `Origin` header to supplied urls.
     * 
     * @param string|array $urls
     * @return string
     */
    protected function matchOrigin($urls)
    {
        if ($urls === '*') return '*';
        
        if (!is_array($urls)) $urls = (array)$urls;
        
        $origin = parse_url($_SERVER['HTTP_ORIGIN']) + ['port' => 80];
        
        foreach ($urls as &$url) {
            if ($url === 'same') $url = '//' . $_SERVER['HTTP_HOST'];
            if (strpos($url, ':') === false && substr($url, 0, 2) !== '//') $url = '//' . $url;
            
            $match = parse_url($url);
            $found =
                (!isset($match['scheme']) || $match['scheme'] === $origin['scheme']) &&
                (!isset($match['port']) || $match['port'] === $origin['port']) &&
                fnmatch($match['host'], $origin['host']);
            
            if ($found) return $_SERVER['HTTP_ORIGIN'];
        }
        
        return null;
    }
    
    /**
     * Sets HTTP Access-Control headers (CORS).
     * 
     * @param string|array $origin
     * @param array        $settings
     */
    protected function setAllowOriginHeaders($origin, array $settings = [])
    {
        foreach ((array)$origin as $url) {
            header("Access-Control-Allow-Origin: $url");
        }
        
        if (isset($settings['expose-headers'])) {
            header("Access-Control-Allow-Credentials: " . join(', ', (array)$settings['expose-headers']));
        }
        
        if (isset($settings['max-age'])) {
            header("Access-Control-Max-Age: " . join(', ', (array)$settings['max-age']));
        }
        
        $cre = isset($settings['allow-credentials']) ? $settings['allow-credentials'] : true;
        header("Access-Control-Allow-Credentials: " . (is_string($cre) ? $cre : ($cre ? 'true' : 'false')));
        
        $methods = isset($settings['allow-methods']) ? $settings['allow-methods'] : '*';
        header("Access-Control-Allow-Methods: " . join(', ', (array)$methods));

        $headers = isset($settings['allow-headers']) ? $settings['allow-headers'] : '*';
        header("Access-Control-Allow-Headers: " . join(', ', (array)$headers));
    }

    /**
     * Set HTTP caching policy.
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html
     * @link https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/http-caching#defining-optimal-cache-control-policy
     * 
     * <code>
     *   Request::setCachingPolicy('no-store');
     *   Request::setCachingPolicy(['no-cache', 'private', 'max-age' => '10 days']);
     * </code>
     * 
     * @param string|array $control
     * @param string       $etag
     */
    public function setCachingPolicy($control, $etag = null)
    {
        trigger_error("Not implemented yet", E_USER_WARNING);
    }
    
    
    /**
     * Set the headers with HTTP status code and content type.
     * @link http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
     * 
     * Examples:
     * <code>
     *   Request::respondWith(200, 'json');
     *   Request::respondWith(200, 'application/json');
     *   Request::respondWith(204);
     *   Request::respondWith("204 Created");
     *   Request::respondWith('json');
     *   Request::respondWith(['json', 'xml']);
     * </code>
     * 
     * @param int          $httpCode  HTTP status code (may be omitted)
     * @param string|array $format    Mime or content format
     * @param callback     $failed    Called if format isn't accepted
     */
    public static function respondWith($httpCode, $format = null)
    {
        // Shift arguments if $httpCode is omitted
        if (!is_int($httpCode) && !preg_match('/^\d{3}\b/', $httpCode)) {
            list($httpCode, $format) = array_merge([null], func_get_args());
        }

        if (isset($httpCode)) http_response_code((int)$httpCode);
        
        if (isset($format)) {
            $contentType = array_search($format, static::$contentFormats) ?: $format;
            header("Content-Type: {$contentType}");
        }
    }
    
    /**
     * Output result as json, xml or image.
     * 
     * @param mixed  $data
     * @param string $format  Mime or content format
     */
    public function output($data, $format = null)
    {
        if (!isset($format)) {
            $format = static::getOutputFormat('short');
        } else {
            $format = isset(static::$contentFormats[$format]) ? static::$contentFormats[$format] : $format;
        }
        
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
                trigger_error("Don't know how to convert a $type to $format", E_USER_ERROR);
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
            throw new \Exception("Was expecting a DOMNode or SimpleXMLElement object, got a $type");
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
        if (static::isJsonp()) {
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
            throw new \Exception("Was expecting a GD resource, got a $type");
        }
        
        static::respondWith("image/$format");
        
        $out = 'image' . $format;
        $out($image);
    }


    /**
     * Output an HTTP error
     * 
     * @param int    $httpCode  HTTP status code
     * @param mixed  $message
     * @param string $format    The output format (auto detect by default)
     */
    public static function outputError($httpCode, $message, $format=null)
    {
        if (ob_get_level() > 1) ob_end_clean();

        if (!isset($format)) {
            $format = static::getOutputFormat('short');
        } else {
            $format = isset(static::$contentFormats[$format]) ? static::$contentFormats[$format] : $format;
        }
        
        switch ($format) {
            case 'json':
                return static::outputErrorJson($httpCode, $message);
                
            case 'xml':
                return static::outputErrorXml($httpCode, $message);
            
            case 'image':
            case 'png':
            case 'gif':
            case 'jpeg':
                if ($format === 'image') $format = 'png';
                return static::outputErrorImage($httpCode, $message, $format);

            case 'text':
            case 'html':
            default:
                return static::outputErrorText($httpCode, $message, $format);
        }
    }
    
    /**
     * Output error as json
     * 
     * @param int   $httpCode
     * @param mixed $error
     */
    protected static function outputErrorJson($httpCode, $error)
    {
        $result = is_scalar($error) ? compact('error') : $error;
        if (static::isJsonp()) $result['httpCode'] = $httpCode;
        
        static::respondWith($httpCode, static::isJsonp() ? 'js' : 'json');
        static::output($result);
    }
    
    /**
     * Output error as xml
     * 
     * @param int    $httpCode
     * @param mixed  $error
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
     * @param int    $httpCode
     * @param string $format  'jpeg', 'png' or 'gif'
     * @param mixed  $error
     */
    protected static function outputErrorImage($httpCode, $format, $error=null)
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
        
        static::respondWith($httpCode, $format);
        static::output($image);
    }

    /**
     * Output an error as text or HTML
     * 
     * @param int    $httpCode
     * @param mixed  $error
     * @param string $format  'text' or 'html'
     * @param int    $indent
     */
    protected static function outputErrorText($httpCode, $error, $format, $indent = 0)
    {
        if ($format !== 'html') $format = 'text';
        
        if (isset($httpCode)) static::respondWith($httpCode, $format);
        
        if (is_resource($error)) {
            echo "Unexpected error";
            trigger_error("An error occured, but the message is a " . get_resource_type($error) . " resource",
                E_USER_WARNING);
            return;
        }
        
        if (is_array($error) && count($error) === 1 && key($error) === 0) {
            $error = $error[0];
        }
        
        if (is_scalar($error) || (is_object($error) && method_exists($error, '__toString'))) {
            echo $error;
        } elseif ($format === 'html') {
            static::outputErrorListAsHTML($error);
        } else {
            static::outputErrorListAsText($error, $indent);
        }
    }
    
    /**
     * Output a list of errors as HTML
     * 
     * @param mixed $error
     */
    protected function outputErrorListAsHTML($error)
    {
        if (is_int(key($error))) {
            echo "<ul>";
            foreach ($error as $key => $value) {
                echo "<li>", static::outputErrorText(null, $value, 'html'), "</li>";
            }
            echo "</ul>";
        } else {
            echo "<dl>";
            foreach ($error as $key => $value) {
                echo "<dt>", $key, "</dt><dd>", static::outputErrorText(null, $value, 'html'), "</dd>";
            }
            echo "</dl>";
        }
    }
    
    /**
     * Output a list of errors as text
     * 
     * @param mixed $error
     * @param int   $indent
     */
    protected function outputErrorListAsText($error, $indent)
    {
        foreach ($error as $key => $value) {
            echo str_repeat(" ", $indent),
                is_int($key) ? '- ' : $key . ': ',
                static::outputErrorText(null, $value, 'text', $indent + 2), "\n";
        }
    }
}
