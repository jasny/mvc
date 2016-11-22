<?php

namespace Jasny\MVC;

use Jasny\HttpMessage\Uri;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Jasny\Router;

/**
 * Test combining all packages
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Router
     */
    protected $router;
    
    public function setUp()
    {
        $routes = new Router\Routes\Glob([
            '/' => (object)['controller' => 'test'],
            '/users/* +GET' => (object)['controller' => 'test', 'action' => 'get', 'id' => '$2']
        ]);
        
        $this->router = new Router($routes);
    }
    
    /**
     * Create request object
     * 
     * @param string $method
     * @param string $uri
     * @return ServerRequest
     */
    protected function createRequest($method, $uri)
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri($uri));
    }
    
    
    public function testDefaultAction()
    {
        $request = $this->createRequest('GET', 'http://www.example.com/')->withQueryParams(['world' => 'mars']);
        $response = $this->router->handle($request, new Response());
        
        $this->assertEquals('<h1>hello mars</h1>', (string)$response->getBody());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
    }
    
    public function testGetUserAction()
    {
        $request = $this->createRequest('GET', 'http://www.example.com/users/1');
        $response = $this->router->handle($request, new Response());
        
        $expect = json_encode([
            'id' => 1,
            'name' => 'Arnold',
            'email' => 'arnold@example.com'
        ]);
        
        $this->assertJsonStringEqualsJsonString($expect, (string)$response->getBody());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }
    
    public function testGetUserNotFoundAction()
    {
        $request = $this->createRequest('GET', 'http://www.example.com/users/99');
        $response = $this->router->handle($request, new Response());
                
        $this->assertEquals('User not found', (string)$response->getBody());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
    }
}
