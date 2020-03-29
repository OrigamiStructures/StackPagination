<?php


namespace StackPagination\Middleware;

use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexFilterManagmentMiddleware
{

    protected $scopes = [
        'AdminPanel.people' => [
            'AdminPanel.people',
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
     *     'conditions' => [
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

        $session = $request->getSession();

        $filter = $session->read();
//        osd($filter);
//        osd($this->scopes);

        if (!isset($filter['path']) && !isset($filter['Auth'])) {
            return $next($request, $response);
        }

        $requestPath = $request->getParam('controller') . '.' . $request->getParam('action');

//        osd($requestPath);
//        osd(!in_array($requestPath, $this->scopes[$filter['path']] ));
//        if (!in_array($requestPath, $this->scopes[$filter['path']] )) {
//            $session->delete('filter');
//        }
        return $next($request, $response);
    }
}
