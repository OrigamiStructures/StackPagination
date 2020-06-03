<?php


namespace StackPagination\Lib;


use Stacks\Model\Entity\StackEntity;

trait ScopeNameTrait
{

    /**
     * Return scope label in for 'modelName_[optionalId]_layerName'
     *
     * @param StackEntity|string $root
     * @param string $layer
     * @param string $id
     * @return string
     */
    public function scopeName($root, $layer, $id = '')
    {
        if ($root instanceof StackEntity) {
            $id = $root->rootID();
            $root = $root->getRootLayerName();
        }
        return "{$root}_{$id}_{$layer}";
    }

}
