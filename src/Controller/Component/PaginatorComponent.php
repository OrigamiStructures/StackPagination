<?php


namespace StackPagination\Controller\Component;

use Cake\Utility\Hash;
use StackPagination\Exception\BadClassConfigurationException;
use StackPagination\Interfaces\FilteringInterface;
use Stacks\Model\Lib\StackSet;
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


    /**
     *
     * @param $seedQuery Query The query that will produce the seed ids
     * @param $options array
     * @return StackSet
     */
    public function index($seedQuery, $seedTarget, $options)
    {
        $this->getController()->viewBuilder()->setLayout('index');
        return $this->block($seedQuery, $options);
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
     * @return StackSet|array StackSet is the result, array means 'redirect here' because of out of range page req
     */
    public function block($seedQuery, $options) {
        $StackTable = Hash::get($options, 'tableName');
        $Table = TableRegistry::getTableLocator()->get($StackTable);
        $Table->Identity = $this->getController()->getRequest()->getAttribute('identity');
        osd($Table->Identity);die;
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
            return $this->getController()->redirect(
                $this->showLastPage($pagingParams['scope'])
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
}
