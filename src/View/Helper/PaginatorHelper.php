<?php


namespace StackPagination\View\Helper;

use Cake\View\Helper\PaginatorHelper as BaseHelper;
use Stacks\Model\Entity\StackEntity;
use StackPagination\Lib\ScopeNameTrait;

class PaginatorHelper extends BaseHelper
{

    use ScopeNameTrait;

    /**
     * Generate a scope/model identifier for a paginated layer
     *
     * @param $stack StackEntity
     * @param $layer string
     * @return string
     */
    public function scope($stack, $layer)
    {
        $result = "{$stack->getRootLayerName()}_{$stack->rootId()}_$layer";
        return $result;
    }
}

