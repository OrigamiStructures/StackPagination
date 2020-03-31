<?php


namespace StackPagination\Controller\Component;

use Cake\Utility\Hash;
use StackPagination\Exception\BadClassConfigurationException;
use StackPagination\Interfaces\FilteringInterface;
use Stacks\Model\Table\StacksTable;
use Cake\Controller\Component\PaginatorComponent as CorePaginator;
use Cake\Controller\ComponentRegistry;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;


/**
 * Class PaginatorComponent
 * @package StackPagination\Controller\Component
 * @property SeedFilterComponent $SeedFilter
 */
class PaginatorComponent extends CorePaginator
{
    public $components = ['Flash', 'StackPagination.SeedFilter'];

    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);
    }

    /**
     * Configure this PaginatorComponent extension
     *
     * Requires the controller to implement FilteringInterface
     * This will handle user search filters that persist over some
     * scope of pages and which cooperate with pagination
     *
     * Requires PreferencesComponent (might be installed in AppController?)
     *
     * @param array $config
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
//        $interfaces = class_implements($this->getController());
//        if (!$interfaces || !in_array('StackPagination\Interfaces\FilteringInterface', $interfaces)) {
//            $message = (get_class($this->getController())) . ' must implement FilteringInterface '
//            . 'to work with the Pagination component.';
//            throw new BadClassConfigurationException($message);
//        }

    }

    /**
     * Redirect to last page when request exceeds page limit
     *
     * @return array $url the url params-array for redirect()
     */
    public function showLastPage($scope)
    {
        $qParams = $this->getController()->getRequest()->getQueryParams();
        $reqPage = $qParams[$scope]['page'];
        $lastPage = $this->getScopesBlock($scope)['pageCount'];
        if ($lastPage > 1) {
            $qParams[$scope]['page'] = $lastPage;
        } else {
            unset($qParams[$scope]['page']);
        }

        $this->Flash->error("Redirected to page $lastPage. Page $reqPage did not exist.");
        return [
            'controller' => $this->getController()->getRequest()->getParam('controller'),
            'action' => $this->getController()->getRequest()->getParam('action'),
            '?' => $qParams
        ];
    }

    /**
     * @param $scope
     * @return array
     */
    private function getScopesBlock($scope)
    {
//        osd($this->getController()->getRequest()->getAttribute('paging'));
        $blocks = collection($this->getController()->getRequest()->getAttribute('paging'));
        $block = $blocks->reduce(function ($result, $block, $key) use ($scope) {
            if ($block['scope'] == $scope) {
                $result = $block;
            }
            return $result;
        }, []);

        return $block;
    }

    /**
     *
     * @return \Cake\Http\Response|null
     */
    public function index($seedQuery, $seedTarget, $pagingScope, $varName = 'stackSet')
    {
        $this->getController()->viewBuilder()->setLayout('index');
        return $this->block($seedQuery, $seedTarget, $pagingScope);
    }

    /**
     * Filter and Paginate a stackSet, create View variables for rendering
     *
     * $options =
     * [
     *      tableName => the stackTable class to use
     *      seedName => the distiller to use
     *      varName => 'stackSet' or supply an override variable name
     *      paging => array of pagination settings including 'scope'
     * ]
     *
     * @param $seedQuery Query The query that will produce the seed ids
     * @param $options array
     * @param $pagingScope string The 'pagingParams.scopeKey' to us for pagination
     * @return Query
     */
    public function block($seedQuery, $options) {
        $StackTable = Hash::get($options, 'tableName');
        $Table = TableRegistry::getTableLocator()->get($StackTable);
        $seedName = Hash::get($options, 'seedName');
        $pagingParams = Hash::get($options, 'paging');
//        $varName = Hash::get($options, 'varName');
        $scope = Hash::get($options, 'paging.scope');
        $formClass = Hash::get($options, 'formClass');

        //expects the stackTable to be filtered
        $this->SeedFilter->setConfig( 'tableAlias', $StackTable);
        //can take custom form class but will use one from naming conventions
        $this->SeedFilter->setConfig('formClass', $formClass ?? 'App\Filter\\' . $StackTable . 'Filter');

        //sets search form vars and adds current post (if any) to query
        $this->SeedFilter->addFilter($seedQuery, $scope);

        try {
            /* @var StacksTable $Table */

            $stackSet = $this->getController()->paginate(
                $Table->pageFor($seedName, $seedQuery->toArray()),
                $pagingParams
            );
        } catch (NotFoundException $e) {
            return /*$this->getController()->redirect(*/
                $this->showLastPage($pagingParams['scope'])
            /*)*/;
        }

//        $this->getController()->set($varName, $stackSet);
//        $this->getController()->set('pagingScope', $scope);

        return $stackSet;
    }

    /**
     * Produce the StackTable instance and seed name for a filtered, paginated stack query
     *
     * Filtered, paginated queries are similiar, but act different stacks
     * and the seeds for the query may be on any of the seed types supported
     * by the stack. So the call is made with a param that names both the
     * StackTable and seed. These values are sent as a `dot` delimited string.
     *
     * This method validates the values and returns an array containing
     * the table instance and the seed name string.
     *
     * @param $seedTarget string A 'TableAlias.seedName'
     * @return array [StackTableInstance, 'seedName']
     */
//    private function parseTarget($seedTarget)
//    {
//        list($tableAlias, $seedName) = explode('.', $seedTarget);
//        $table = TableRegistry::getTableLocator()->get($tableAlias);
//        /* @var StacksTable $table */
//
//        return [$table, $seedName];
//
//    }

    /**
     * Get current prefs-settings for 'paging' and add a scope key to it
     *
     * The `pagingParms` key must name one of the Preferences schema json
     * blocks that define a user customizable paging config block.
     *
     * The `scopeKey` can be any string you want to identify the role of
     * the paginated records. It will show up in the query params of the url.
     * If you set it to 'member_candidate' your urls would like like this:
     * `clearstudio.com/cardfile/view/19?member_candidate[page]=4`
     *
     * @param $pagingScope string A 'pagingParams.scopeKey' to us for pagination
     * @return array
     */
//    public function getPagingParams($pagingScope): array
    public function addScope($pagingScope, $params) : array
    {
        list($pagingParams, $scopeKey) = explode('.', $pagingScope);
//        $params = $this->getController()->Prefs->getPagingAttrs($pagingParams);
        $params['scope'] = $scopeKey;
        return $params;
    }

    /**
     * Unused. retained for reference while addScope is debugged
     * @param $name
     * @return array
     */
    public function getPagingAttrs($name)
    {
        $path = "paging.$name";

        $attrs = Hash::insert([], "limit", $this->for("$path.limit"));
        $attrs = Hash::insert($attrs, "order", [
            $this->for("$path.sort") => $this->for("$path.dir")
        ]);
        $attrs = Hash::insert($attrs, "scope", $this->for("$path.scope"));

        return $attrs;
    }

}
