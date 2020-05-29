<?php


namespace StackPagination\Middleware;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Utility\Hash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IndexFilterManagmentMiddleware implements MiddlewareInterface
//class IndexFilterManagmentMiddleware
{

    protected $scopes = [
        'AdminPanel_people' => [
            'AdminPanel_admin-panel',
        ],
    ];

    /**
     * Process an incoming server request.
     *
     * On many pages, users can search to filter the content.
     *
     * There will be some set of related pages where the filter should continue
     * to exist (eg. index --> view/x --> index OR index?page=2 --> index?page=3).
     *
     * This middleware looks for a saved filter and base on the current request
     * path and decides whether to keep or delete it.
     *
     * @todo THIS HAD BEEN DELETED. IS IT NO LONGER VALID? ============
     * Filters are saved in the session on the 'filter' key.
     *   There are two keys in the next level.
     * 'path' names the controller_action where the filter was created. This
     *   string will be a key that gets the list of other controller_actions
     *   that are in-scope for the filter.
     * 'conditions' has keys that name what paging block the filters belong to
     *   (the key is the model/scope name from the pagination data blocks)
     *   and each key has an array that can be given to the query->where()
     *   method.
     *
     * The entire filter lives or dies together.
     *
     * Only one filter can exist at a time, but the filter can manage multiple
     * filtered stacks within it's page scope.
     *
     * [
     *   'filter' => [
     *     'path' => 'Cardfile_people',
     *     'conditions' => [
     *        'paging_scope1' => [
     *          'OR' => [
     *            'first_name' => 'Don',
     *            'last_name' => 'Drake'
     *          ]
     *        ],
     *        'paging_scope2' => [
     *          'first_name' => 'Jason',
     *        ]
     *     ]
     *   ]
     * ] END====================

     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /* @var ServerRequest $request */
        /* @var Session $session */

        $session = $request->getSession();
        $sessionData = $session->read() ?? [];
        $requestPath = $request->getParam('controller') . '_' . $request->getParam('action');
        $filterPath = Hash::get($sessionData, 'filter.path' ) ?? 'empty';
        $allowedPaths = Configure::read('filter_scopes.' . $filterPath);

        if (
            $request->getParam('plugin') !== 'DebugKit' //ignore all DebugKit requests
            && isset($sessionData['filter']['path'])          //ignore if there is no filter stored
            && $filterPath !== $requestPath                   //ignore if we're actually on the original page
            && !in_array($requestPath, $allowedPaths)         //ignore if this path is an approved path
        ) {
            $session->delete('filter');            //well then, delete the filter
        }

        unset($session, $sessionData, $requestPath, $allowedPaths); //be a good middleware citizen

        return $handler->handle($request);
    }
}
