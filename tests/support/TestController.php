<?php

use Jasny\Controller;

/**
 * A test controller
 */
class TestController extends Controller
{
    use Controller\RouteAction;
    use Controller\View\Twig;
    use Controller\Session;

    protected $users = [
        1 => [
            'id' => 1,
            'name' => 'Arnold',
            'email' => 'arnold@example.com'
        ],
        2 => [
            'id' => 2,
            'name' => 'Sergey',
            'email' => 'sergey@example.com'
        ]
    ];
    
    
    protected function before()
    {
        $this->respondWith('text/plain');
        $this->byDefaultSerializeTo('json');
        
        if ($this->getQueryParam('fail', false)) {
            return $this->badRequest("Failing as requested");
        }
    }
    
    
    public function defaultAction()
    {
        $world = $this->getQueryParam('world', 'planet');
        $this->output("<h1>hello $world</h1>", "text/html");
    }
    
    public function getAction($id)
    {
        if (empty($id) || !isset($this->users[$id])) {
            return $this->notFound("User not found");
        }
        
        $this->output($this->users[$id]);
    }
    
    public function saveAction($id = null)
    {
        if (isset($id) && !isset($this->users[$id])) {
            return $this->notFound("User not found");
        }
        
        $user = $this->getInput() + (isset($this->users[$id]) ? $this->users[$id] : ['id' => 3]);
        
        $errors = [];
        if (empty($user['name'])) $errors[] = 'name is required';
        if (empty($user['email'])) $errors[] = 'email is required';
        
        if (!empty($errors)) {
            $this->badRequest(compact('errors'));
        }
        
        if (!isset($id)) {
            $this->created("/users/{$user['id']}");
        }
        
        $this->output($user);
    }
}
