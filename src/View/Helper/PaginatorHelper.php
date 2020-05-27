<?php


namespace StackPagination\View\Helper;

use Cake\View\Helper\PaginatorHelper as BaseHelper;
use Stacks\Model\Entity\StackEntity;

class PaginatorHelper extends BaseHelper
{

    /**
     * Generate a scope/model identifier for a paginated layer
     *
     * @param $stack StackEntity
     * @param $layer string
     * @return string
     */
    public function scope($stack, $layer)
    {
        $result = "{$stack->getRootLayerName()}-{$stack->rootId()}-$layer";
        return $result;
    }
}

