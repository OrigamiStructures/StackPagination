<?php


namespace StackPagination\Middleware;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Utility\Hash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexFilterManagmentMiddleware
{

    protected $scopes = [
        'AdminPanel_people' => [
            'AdminPanel_people',
        ],
    ];

    /**
     * Maintain content filters within sensible page scope
     *
     * On index pages, users can search to filter the content.
     *
     * There will be some set of related pages where the filter should continue
     * to exist (eg. index -> view/x -> index or index?page=2 -> index?page=3).
     *
     * This middleware looks for a saved filter and base on the current request
     * decides whether to keep or delete it.
     *
     * Filters are saved on the session on the 'filter' key.
     *   There are two keys in the next level.
     * 'path' names the controller/action where the filter was created. This
     *   string will be a key that gets the list of other controller/actions
     *   that are in-scope for keeping the filter.
     * 'conditions' holds the array that can be given to the query->where()
     *   method.
     *
     * [
     *   'filter' => [
     *     'path' => 'Cardfile.people',
     *     'scope1' => [
     *       'OR' => [
     *         'first_name' => 'Don',
     *         'last_name' => 'Drake'
     *       ]
     *     ]
     *     'scope2' => [
     *       'OR' => [
     *         'first_name' => 'Don',
     *         'last_name' => 'Drake'
     *       ]
     *     ]
     *   ]
     * ]
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next The next middleware to call.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        /* @var ServerRequest $request */
        /* @var Session $session */
        /* @var string $requestPath */

        $session = $request->getSession();
        $sessionData = $session->read();
        $requestPath = $request->getParam('controller') . '_' . $request->getParam('action');

//        osd($request->getParam('plugin') !== 'DebugKit');
//        osd(isset($sessionData['filter']['path']), 'filter path set for ' . $sessionData['filter']['path']);
//        osd($this->scopes[$sessionData['filter']['path']], "matches $requestPath?");
        if (
            $request->getParam('plugin') !== 'DebugKit'
            && isset($sessionData['filter']['path'])
            && !in_array($requestPath, $this->scopes[$sessionData['filter']['path']])
        ) {
            $session->delete('filter');
        }

        unset($session, $sessionData, $requestPath);

        return $next($request, $response);
    }
}
