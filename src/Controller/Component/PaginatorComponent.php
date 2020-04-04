<?php


namespace StackPagination\Controller\Component;

use Cake\Datasource\ResultSetInterface;
use Cake\Http\Response;
use Cake\Utility\Hash;
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
     *      formClass => name of the class or null to use naming conventions
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
     * @param $seedQuery Query The query that will produce the seed ids
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
        } catch (NotFoundException $e) {
            return $this->getController()->redirect(
                $this->showLastPage($scope)
            );
        }
        return $stackSet;
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
}
