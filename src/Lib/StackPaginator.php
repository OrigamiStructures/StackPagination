<?php
namespace StackPagination\Lib;

use Cake\Datasource\Paginator;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Query;
use StackPagination\DataSource\LayerPaginator;

/**
 * StackPaginator
 *
 * Subclass Paginator used by PaginationComponent to make it operate
 * on Stack results
 *
 * @author dondrake
 */
class StackPaginator extends Paginator {

	/**
	 * Implement pagination on a stack table query
	 *
	 * The stack query is packaged in a callable ($object). Then the
	 * pagination call is packaged ($paginatorCallable) and passed to the
	 * stack process. The reason: pagination must happen happen to a
	 * query that is created part way through stack assembly. Sending
	 * the pagination processes in as a callable allows it to be on the
	 * scene for this mid-stream use.
     *
     * Adjustments are made to the pagingParams:
     * Repository-keyed sets are converted to scope-keyed sets so that
     * multiple independent sets from a single Table are possible. These
     * scope keys also match keyed run-time filters from the user.
     * (see SeedFilterComponent and IndexFilterManagementMiddleware)
     * This integration allows pagination of filtered sets.
     *
     * Normal pagingParams:
     * ['TableName' => [
     *   //paging keys and values
     *   'scopeName' => 'thisScope',
     *   ]
     * ]
     *
     * Adjusted pagingParams:
     * ['thisScope' => [
     *   //paging keys and values
     *   'scopeName' => 'thisScope',
     *   ]
     * ]
	 *
	 * @todo Does this method have to do any additional work to make
	 *		 $params and $settings work properly?
	 *
	 * @param Callable $object findStackCallable
     * @param array $params Request query params
     * @param array $settings The settings/configuration used for pagination.
	 * @return Query
	 */
    public function paginate(object $object, array $params = [], array $settings = []): ResultSetInterface
    {
		$paginatorCallable = function($query) use ($params, $settings) {
		    /*
		     * $query is lost by paginate() so we need to read repository
		     * name first for the params fix later
		     */
            $alias = $query->getRepository()->getAlias();
			$result = parent::paginate($query, $params, $settings);
			/*
			 * Paging params are stored by Repository alias. Since our's are
			 * locked in by the stack structure, we need a new name to keep separate
			 * sets on their own paging scheme when they are also from the same
			 * repository. So we migrate the data block onto a key = to the scope key.
			 * Scope is the query key for the page so this makes sense and works.
			 *
			 * The block is added to the request by some other code.
			 * debug $this->request->getParam('paging') to see the result
			 */
            $scope = $this->_pagingParams[$alias]['scope'];
			$this->_pagingParams = [$scope => $this->_pagingParams[$alias]];
            return $result;
		};

        /**
         * @todo Exteded pagination: Explanation follows in comment
         * Go through the stacks and paginate each of their layers as appropriate.
         *
         * We can add new paging blocks for each layer case. There is a question though:
         * when a user pages through a layer, do we let that layer in each stack page
         * in synch? or do we page each individually?
         * If done individually, we need a new scoped paging block for each or a
         * way to track the desired page in each layer somehow; stepping outside the
         * established request-param method.
         * example/index?index[page]=2&piece[page]=id55-2+id671-3
         * example/index?index[page]=2&layer[piece]=id55-2+id671-3
         * We'd have to modify the Pagination helper to create these new query args
         *
         * Looks like making a new query arg pattern would take study. But running
         * all the layers in synch will not work because they won't all have the same
         * number of pages. So, who would determine the correct settings for the block?
         *
         * It should not be hard to id the scopes and let each run independently. It just
         * means the url query params could grow long and the paging arrays in the
         * request would be large
         * example/index?index[page]=2&piece55[page]=2&piece671[page]=3
         * Additionally, if we use ajax to load page fragments, we'll have to write
         * js page update routines to fix any pagination tool blocks so they know the
         * new query args an don't restore some old page state when used.
         */
		return $object($paginatorCallable);
    }

    public function paginateLayers($stackSet, $settings)
    {
        return (new LayerPaginator())($stackSet, $settings);
    }
}
