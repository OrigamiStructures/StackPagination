<?php


namespace StackPagination\View\Helper;

use Cake\View\Helper\PaginatorHelper as BaseHelper;

class PaginatorHelper extends BaseHelper
{

    public function scope($stack, $layer)
    {
        return "-$layer-";
    }
}
