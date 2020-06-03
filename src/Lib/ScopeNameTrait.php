<?php


namespace StackPagination\Lib;


use Stacks\Model\Entity\StackEntity;

trait ScopeNameTrait
{

    public function scopeName($root, $layer, $id = '')
    {
        if ($root instanceof StackEntity) {
            $id = $root->rootID();
            $root = $root->getRootLayerName();
        }
        return "{$root}_{$id}_{$layer}";
    }

//    public function scope($root, $layer, $id = '')
//    {
//        return
//    }

}
