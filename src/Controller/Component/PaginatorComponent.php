<?php


namespace StackPagination\Controller\Component;

use Cake\Datasource\Exception\PageOutOfBoundsException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Http\Response;
use Cake\Utility\Hash;
use StackPagination\DataSource\LayerPaginator;
use Stacks\Model\Table\StacksTable;
use Cake\Controller\Component\PaginatorComponent as CorePaginator;
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


    /**
     *
     * @param $seedQuery Query The query that will produce the seed ids
     * @param $options array
     * @return ResultSetInterface|Response StackSet is the result, array means 'redirect here' because of out of range page req
     */
    public function index($seedQuery, $options)
    {
        $this->getController()->viewBuilder()->setLayout('index');
        return $this->block($seedQuery, $options);
    }

    /**
     * Filter and Paginate a stackSet, create View variables for rendering
     *
     * $options =
     * [
     *      tableName => string stackTableName, [name=>[config], or StackTable {obj}
     *      seedName => the distiller to use
     *      formClass => name of the class or null to use naming conventions (table-alias + Filter)
     *      paging => array of pagination settings including 'scope'
     * ]
     * $seedQuery is a query that is matched to and can be modified by the formClass'
     *      post data and the conditions that result from it
     * $options[paging] will must have a 'scope' key on it that matches query params
     *      to their appropriate query. There is no actual identifier on the query,
     *      your code simple must coordinate these two elements.
     *      - Additionally, the scope key is included in Filter forms on the page as
     *      a hidden 'pagingScope' filed. This matches filters to paginated blocks.
     *
     * @param $seedQuery Query|QueryInterface The query that will produce the seed ids
     * @param $options array
     * @return ResultSetInterface|Response StackSet is the result, array means 'redirect here' because of out of range page req
     */
    public function block($seedQuery, $options) {
        /* @var StacksTable $Table */
        list($tableName, $Table) = $this->initStackTable($options);
        $scope = Hash::get($options, 'paging.scope');

        $this->SeedFilter->setConfig( [
            'tableAlias' => $tableName,
            'formClass' => Hash::get($options, 'formClass')
        ])
            ->applyFilter($seedQuery, $scope);

        try {
            $stackSet = $this->getController()->paginate(
                $Table->pageFor(
                    Hash::get($options, 'seedName'),
                    $seedQuery->toArray()),
                Hash::get($options, 'paging')
            );

            $this->pagingateLayers($stackSet, $options);

        } catch (NotFoundException $e) {
            return $this->getController()->redirect(
                $this->showLastPage($scope)
            );
        }
        return $stackSet;
    }

    public function pagingateLayers($stackSet, $options)
    {
        if (isset($options['paging']['layers'])) {
            $this->layerPaginate($stackSet, $options['paging']['layers']);
//            (new LayerPaginator())($stackSet, $options['paging']['layers']);
        }
    }

    /**
     * Direct call for layer pagination
     *
     * assumes top lever layer keys on settings:
     * [
     *   'tenants' => ['page' => 3],
     *   'warehouse_workers' => ['limit' => 10]
     * ]
     *
     * @todo $object should tolerate a StackSet too
     *
     * @param object $object
     * @param array $settings
     * @return ResultSetInterface
     */
    public function layerPaginate(object $object, array $settings = []): ResultSetInterface
    {
        $request = $this->_registry->getController()->getRequest();
        $oldPaginator = $this->_paginator;
        $this->setPaginator(new LayerPaginator());

        try {
            $results = $this->_paginator->paginate(
                $object,
                $request->getQueryParams(),
                $settings
            );
            $this->_setPagingParams();
            $this->setPaginator($oldPaginator);
        } catch (PageOutOfBoundsException $e) {
            $this->_setPagingParams();

            throw new NotFoundException(null, null, $e);
        }

        return $results;
    }

    /**
     * Redirect to last page when request exceeds page limit
     *
     * @return array $url the url params-array for redirect()
     */
    private function showLastPage($scope)
    {
        $qParams = $this->getController()->getRequest()->getQueryParams();
        $reqPage = $qParams[$scope]['page'];
        $lastPage = $this->getScopesBlock($scope)['pageCount'];
        if ($lastPage > 1) {
            $qParams[$scope]['page'] = $lastPage;
        } else {
            unset($qParams[$scope]['page']);
        }

        /* @todo what other values should go into the array for complete support? */

        $url = array_merge(
            [
                'controller' => $this->getController()->getRequest()->getParam('controller'),
                'action' => $this->getController()->getRequest()->getParam('action'),
            ],
            $this->getController()->getRequest()->getParam('pass'),
            $qParams
            );

        $this->Flash->error("Redirected to page $lastPage. Page $reqPage did not exist.");
        return $url;
    }

    /**
     * @param $scope
     * @return array
     */
    private function getScopesBlock($scope)
    {
        $blocks = collection($this->getController()->getRequest()->getAttribute('paging'));
        /* This drops a top-level key and can't be a filter */
        return $blocks->reduce(function ($result, $block, $key) use ($scope) {
            if ($block['scope'] == $scope) {
                $result = $block;
            }
            return $result;
        }, []);
    }

    /**
     * Setup the Table and table environmental values for this block() call
     *
     * $option['tableName'] may be:
     *  [name => [config-vals]]
     *  'name'
     *  TabelObject {}
     *
     * @param $options
     * @return array
     */
    protected function initStackTable($options): array
    {
        try {
            $tableOption = Hash::get($options, 'tableName');

            if (is_string($tableOption)) {
                $StackTable = $tableOption;
                $Table = TableRegistry::getTableLocator()->get($StackTable, []);
            }
            elseif (is_array($tableOption)) {
                $name = array_shift(array_keys($tableOption));
                $StackTable = $name;
                $Table = TableRegistry::getTableLocator()->get($StackTable, $tableOption[$name]);
            }
            elseif ($tableOption instanceof StacksTable) {
                $StackTable = namespaceSplit(get_class($tableOption));
                $Table = $tableOption;
            }
            else {
                throw new \Exception();
            }

            return array($StackTable, $Table);

        } catch (\Exception $e) {
            $msg = 'The "tableName" key was not set or incorrect. It is required and must
            be a string (stackTable name), array (["stackTableName" => [config]], or StackTable {object}.';
            throw new \BadMethodCallException($msg);
        }
    }

    public function l_extractData($object, array $params, array $settings)
    {
        $alias = 'default';
        $defaults = $this->getDefaults($alias, $settings);
        $options = $this->mergeOptions($params, $defaults);
        $options = $this->validateSort($object, $options);
        $options = $this->checkLimit($options);

        $options += ['page' => 1, 'scope' => null];
        $options['page'] = (int)$options['page'] < 1 ? 1 : (int)$options['page'];
        [$finder, $options] = $this->_extractFinder($options);

        return compact('defaults', 'options', 'finder');

    }
}
