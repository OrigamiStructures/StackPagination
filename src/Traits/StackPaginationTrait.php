<?php


namespace StackPagination\Traits;


use Cake\ORM\Query;

trait StackPaginationTrait
{

    /**
     * Create the paginated-index-page ecosystem
     *
     * @param $seedQuery Query
     * @param $config array
     */
    protected function _index($seedQuery, $config) : void
    {
        $this->_list($seedQuery, $config);
    }

    /**
     * Create the paginated-data-set ecosystem
     *
     * @param $seedQuery Query
     * @param $config array
     */
    protected function _list($seedQuery, $config) : void
    {
        $this->userFilter($seedQuery);

    }

    public function clearFilter()
    {
        $this->getRequest()->getSession()->delete('filter');
        return $this->redirect($this->referer());
    }

}
