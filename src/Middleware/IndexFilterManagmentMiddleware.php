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

        return $handler->handle($request, $handler);
    }
}
