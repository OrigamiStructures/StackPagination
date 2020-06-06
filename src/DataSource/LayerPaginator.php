<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.5.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace StackPagination\DataSource;

use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\Exception\PageOutOfBoundsException;
use Cake\Datasource\Paginator;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Routing\Router;
use Stacks\Constants\LayerCon;
use Stacks\Model\Lib\Layer;
use Stacks\Model\Entity\StackEntity;
use Stacks\Model\Lib\LayerAccessProcessor;
use Stacks\Model\Lib\StackSet;
use StackPagination\Lib\ScopeNameTrait;

/**
 * This class is used to paginate layer data in stack entities.
 *
 * Cake-normal pagination works off an evolving query. But stack entities arrive
 * with all their layer data included.
 *
 * So the goal of this code is to modify the layers included in the provided stacks
 * and return those stacks with only the layer-pages required and with all the
 * Cake-normal background data. In particular, there is an array of 'paging' data
 * that is used by the PaginatorHelper to implement the paging tools and page
 * link creation.
 *
 * This code is written over the template of Cake\Datasource\Paginator which is
 * the normal handler of a controllers $this->paginate(query, settings) calls.
 * Whenever possible, parent properties and processes are used in an effort to
 * exactly match the returned 'paging' data and settings features.
 *
 * To reach this paginator you must have the StackPagination plugin installed.
 * Then, from the controller you can make one of three calls from your controller
 * depending on the structure of your settings array.
 * @todo clean up this call and naming mess
 *
 * If you have
 * $settings = [
 *     normal-query-settings-keys,
 *     'layer' => [
 *         'layerNameOne' => [layer-settings-keys],
 *         'layerNameTwo' => [layer-settings-keys],
 *     ]
 * ]
 * then you will use one of these calls:
 *
 * $this->Paginator->block(query, settings) //for a page of stacks with paginated layers
 * $this->Paginator->paginateLayers(StackSet|StackEntity, settings) //to paginate the layer data
 *
 * If you have only the array of layer settings (the value of 'layer' in the previous example)
 * then you can call:
 *
 * $this->Paginator->layerPaginate(StackSet|StackEntity, settings)
 *
 * As with the Cake-normal pagination this paginator works from a blending of
 * pre-established defaults, settings passed as an argument on the call, and
 * query arguments in the Request. In combination they explain how to paginate the data.
 *
 * Where query arguments exits they will prevail. When a query argument is missing
 * the corresponding 'setting' value will be used if available. Last in precedence
 * is the defaultConfig values.
 *
 */
class LayerPaginator extends Paginator
{

    /**
     * Cake-normal settings data uses a 'model' key to determine query it should apply to.
     * It also uses a 'scope' key which plays a role in query argument naming.
     *
     * StackPagination synchronizes the values and only uses the term 'scope'.
     *
     * This scope value is the name coordinates the various streams of settings that
     * lead to pagination and which associates tools on the page with the displayed
     * data they refer to. A VERY IMPORTANT VALUE in other words.
     *
     * When paginating layer data, the resulting 'paging' array refers to
     * the layer of a specific record of a specific kind. So the scope value for
     * layer pagination must tell us three things:
     *
     * 1. what kind of stack
     * 2. which specific record of that kind
     * 3. which layer in the stack.
     *
     * When default config data is being prepared, it will refer to any layer
     * of a specific type regardless of which entity it is in. In this case
     * the scope value will
     *
     * This trait provides a method to make the name
     */
    use ScopeNameTrait;

    /**
     * @todo defaults can't be set yet, only hard-coded into this class. Because of the
     *     way paginator datasources are injected into the component a setter will have to
     *     be written on the component to make this work
     *
     * To insure all requested data can be paginated even when there are no passed
     * settings or query arguments, a list of defaults is maintained as config data.
     * These follow exactly the same pattern as Cake-normal pagination defaults
     * and this property could be omitted so the parent values are used.
     *
     * @todo document optional layer specific settings
     *
     * The standard top-level keys will apply to all layers. It is possible
     * to add layer-name keys that hold an array of layer specific values.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'page' => 1,
        'limit' => 20,
        'maxLimit' => 100,
        'whitelist' => ['limit', 'sort', 'page', 'direction'],
//        optional individual settings for layers
//        'tenants' => [
//            'page' => 1,
//            'limit' => 10,
//            'maxLimit' => 50,
//            'whitelist' => ['sort', 'page', 'direction'],
//        ]
    ];

    /**
     * Handles automatic pagination of model records.
     *
     *
     * @param StackEntity $object The repository or query to paginate.
     * @param array $params Request params
     * @param array $settings The settings/configuration used for pagination.
     * @return ResultSetInterface Query results
     * @throws PageOutOfBoundsException
     */
    public function paginate($object, array $params = [], array $settings = []): ResultSetInterface
    {

        $data = $this->extractData($object, $params, $settings);

        collection($data['options'])
            ->map(function($opts, $key) use (&$data, $object) {
                $dat = [];
                extract($data);// $defaults, $options, $objects
                /* @var array $defaults
                 * @var array $options
                 * @var array $objects */

                preg_match('/(.*)_([0-9]*)_(.*)/', $key, $match);
                $defaultKey = $match[1] . '__' . $match[3];
                $id = $match[2];
                $layer = $match[3];
                $dat['defaults'] = $data['defaults'][$defaultKey];
                $dat['options'] = $opts;

                $processor = $objects[$id]
                    ->getLayer($layer);
                $dat['count'] = $processor->rawCount(); //all records
                $argObj = $processor->find();
                $this->addLayerFilter($key, $argObj);
                $this->addLayerSort($options[$key], $defaults[$defaultKey], $argObj);
                $this->addLayerPagination($options[$key], $defaults[$defaultKey], $argObj);
                $results = $dat['count'] > 0 ? $argObj->toArray() : [];
                $dat['numResults'] = count($results); //records in the current desired page
                $object->element($id, LayerCon::LAYERACC_ID)->$layer = new Layer($results, $layer);

                $pagingParams = $this->buildParams($dat);
                $this->_pagingParams[$key] = $pagingParams;
                /*@todo should we reset the target page here to make the params accurate?*/
//                if ($pagingParams['requestedPage'] > $pagingParams['page']) {
//                    throw new PageOutOfBoundsException([
//                        'requestedPage' => $pagingParams['requestedPage'],
//                        'pagingParams' => $this->_pagingParams,
//                    ]);
//                }

            })->toArray();

        return $object;
    }

    protected function addLayerFilter($key, $argObj)
    {
        //read cached query and return 3 values

    }

    /**
     * @param array|object|bool $options
     * @param $defaults
     * @param $argObj
     */
    protected function addLayerSort($options, $defaults, $argObj)
    {
        $order = false;
        if (isset($options['order'])) {
            $order = $options['order'];
        }
        elseif (isset($defaults['order'])) {
            $order = $defaults['order'];
        }
        if ($order && !empty($order)) {
            $argObj->specifySort(key($order), current($order) == 'asc' ? SORT_ASC : SORT_DESC);
        }
    }

    protected function addLayerPagination($options, $defaults, $argObj)
    {
        if (isset($options['page'])) { $page = $options['page']; }
        else { $page = $defaults['page']; }

        if (isset($options['limit'])) { $limit = $options['limit']; }
        else { $limit = $defaults['limit']; }

        $argObj->specifyPagination($page, $limit);
    }

    /**
     * Get total count of records.
     *
     * @param QueryInterface $query Query instance.
     * @param array $data Pagination data.
     * @return int|null
     */
//    protected function getCount(QueryInterface $query, array $data): ?int
//    {
//        return $query->count();
//    }

    /**
     * Extract pagination data needed
     *
     * @param StackEntity|StackSet $stack
     * @param array $params Request params
     * @param array $settings The settings/configuration used for pagination.
     * @return array Array with keys 'defaults', 'options' and 'finder'
     */
    protected function extractData( $stack, array $params, array $settings): array
    {
        $layers = array_intersect(array_keys($settings), $stack->getLayerList());
        $root = $stack->getRootLayerName();

        /*
         * Work out defaults for each layer
         * These will be keyed 'rootName__layerName'
         */
        $defaults = collection($layers)
            ->reduce(function($accum, $layer) use ($settings, $root) {
                $scope = $this->scopeName($root, $layer);
                $settings[$scope] = $settings[$layer];
                $settings[$scope] += ['scope' => $scope, 'layer' => $layer];
                $accum[$scope] = $this->getDefaults( $layer, $settings[$scope] ?? []);
                return $accum;
            }, []);

        /*
         * Merge defaults and queryArgs for each individual stackEntity's layers
         * These will be keyed 'rootName_XX_layerName'
         */
        $queryArgs = Router::getRequest()->getQueryParams();
        $mergeOptions = function ($stack, $layers, $root, $queryArgs, $defaults) {
            return collection($layers)
                ->reduce(function($accum, $layer) use ($root, $stack, $queryArgs, $defaults) {
                    $scope = $this->scopeName($root, $layer);
                    $key = $this->scopeName($root, $layer, $stack->rootId());
                    $accum[$key] = $this->mergeOptions(
                        $queryArgs[$key] ?? [], $defaults[$scope]
                    );
                    $accum[$key]['scope'] = $key;
                    return $accum;
                }, []);
        };

        $options = [];
        if ($stack instanceof StackSet) {
            foreach ($stack->getData() as $entity) {
                $options = $mergeOptions(
                    $entity, $layers, $root, $queryArgs, $defaults);
            }
        }
        else {
            $options = $mergeOptions(
                $stack, $layers, $root, $queryArgs, $defaults);
        }

        /*
         * list requested sort fields in $defaults for validation
         * nothing is currently done to validate the existence of the sort columns
         */
        if ($stack instanceof StackSet) {
            $objects = $stack->getData();
        } else {
            $objects = [$stack->rootID() => $stack];
        }
        $options = collection($options)
            ->map(function($localOpt, $key) use ($objects) {
                preg_match('/_([0-9]*)_/', $key, $match);
                return $this->validateSort($objects[$match[1]], $localOpt);
            })->toArray();

        /*
         * manage limit and page
         * add any finder information
         * that's a wrap
         */
        $options = collection($options)
            ->map(function($localOpt) use ($objects) {
                $localOpt = $this->checkLimit($localOpt);
                $localOpt += ['page' => 1];
                $localOpt['page'] = (int)$localOpt['page'] < 1 ? 1 : (int)$localOpt['page'];
                [$finder, $options] = $this->_extractFinder($localOpt);
                $localOpt = $options + ['finder' => $finder];
                return $localOpt;
            })->toArray();

        return compact('defaults', 'options', 'objects');
    }

    /**
     * Build pagination params.
     *
     * @param array $data Paginator data containing keys 'options',
     *   'count', 'defaults', 'finder', 'numResults'.
     * @return array Paging params.
     */
    protected function buildParams(array $data): array
    {
        $limit = $data['options']['limit'];

        $paging = [
            'count' => $data['count'],
            'current' => $data['numResults'],
            'perPage' => $limit,
            'page' => $data['options']['page'],
            'requestedPage' => $data['options']['page'],
        ];

        $paging = $this->addPageCountParams($paging, $data);
        $paging = $this->addStartEndParams($paging, $data);
        $paging = $this->addPrevNextParams($paging, $data);
        $paging = $this->addSortingParams($paging, $data);

        $paging += [
            'limit' => $data['defaults']['limit'] != $limit ? $limit : null,
            'scope' => $data['options']['scope'],
            'finder' => $data['options']['finder'],
        ];

        return $paging;
    }

    /**
     * Add "page" and "pageCount" params.
     *
     * @param array $params Paging params.
     * @param array $data Paginator data.
     * @return array Updated params.
     */
    protected function addPageCountParams(array $params, array $data): array
    {
        $page = $params['page'];
        $pageCount = 0;

        if ($params['count'] !== null) {
            $pageCount = max((int)ceil($params['count'] / $params['perPage']), 1);
            $page = min($page, $pageCount);
        } elseif ($params['current'] === 0 && $params['requestedPage'] > 1) {
            $page = 1;
        }

        $params['page'] = $page;
        $params['pageCount'] = $pageCount;

        return $params;
    }

    /**
     * Add "start" and "end" params.
     *
     * @param array $params Paging params.
     * @param array $data Paginator data.
     * @return array Updated params.
     */
    protected function addStartEndParams(array $params, array $data): array
    {
        $start = $end = 0;

        if ($params['current'] > 0) {
            $start = (($params['page'] - 1) * $params['perPage']) + 1;
            $end = $start + $params['current'] - 1;
        }

        $params['start'] = $start;
        $params['end'] = $end;

        return $params;
    }

    /**
     * Add "prevPage" and "nextPage" params.
     *
     * @param array $params Paginator params.
     * @param array $data Paging data.
     * @return array Updated params.
     */
    protected function addPrevNextParams(array $params, array $data): array
    {
        $params['prevPage'] = $params['page'] > 1;
        if ($params['count'] === null) {
            $params['nextPage'] = true;
        } else {
            $params['nextPage'] = $params['count'] > $params['page'] * $params['perPage'];
        }

        return $params;
    }

    /**
     * Add sorting / ordering params.
     *
     * @param array $params Paginator params.
     * @param array $data Paging data.
     * @return array Updated params.
     */
    protected function addSortingParams(array $params, array $data): array
    {
        $defaults = $data['defaults'];
        $order = (array)$data['options']['order'];
        $sortDefault = $directionDefault = false;

        if (!empty($defaults['order']) && count($defaults['order']) === 1) {
            $sortDefault = key($defaults['order']);
            $directionDefault = current($defaults['order']);
        }

        $params += [
            'sort' => $data['options']['sort'],
            'direction' => isset($data['options']['sort']) ? current($order) : null,
            'sortDefault' => $sortDefault,
            'directionDefault' => $directionDefault,
            'completeSort' => $order,
        ];

        return $params;
    }

    /**
     * Extracts the finder name and options out of the provided pagination options.
     *
     * This could be used to specify data result types or inject other
     * parameters into the mix. It's pretty much a carry-over of
     * the old query-focused code at this point
     *
     * @param array $options the pagination options.
     * @return array An array containing in the first position the finder name
     *   and in the second the options to be passed to it.
     */
    protected function _extractFinder(array $options): array
    {
        $type = !empty($options['finder']) ? $options['finder'] : 'all';
        unset($options['finder'], $options['maxLimit']);

        if (is_array($type)) {
            $options = (array)current($type) + $options;
            $type = key($type);
        }

        return [$type, $options];
    }

    /**
     * Get paging params after pagination operation.
     *
     * @return array
     */
    public function getPagingParams(): array
    {
        return $this->_pagingParams;
    }

    /**
     * Merges the various options that Paginator uses.
     * Pulls settings together from the following places:
     *
     * - General pagination settings
     * - Model specific settings.
     * - Request parameters
     *
     * The result of this method is the aggregate of all the option sets
     * combined together. You can change config value `whitelist` to modify
     * which options/values can be set using request parameters.
     *
     * @param array $params Request params.
     * @param array $settings The settings to merge with the request data.
     * @return array Array of merged options.
     */
    public function mergeOptions(array $params, array $settings): array
    {
        $params = array_intersect_key($params, array_flip($this->getConfig('whitelist')));

        return array_merge($settings, $params);
    }

    /**
     * Get the settings for a $model. If there are no settings for a specific
     * repository, the general settings will be used.
     *
     * @param string $alias Model name to get settings for.
     * @param array $settings The settings which is used for combining.
     * @return array An array of pagination settings for a model,
     *   or the general settings.
     */
    public function getDefaults(string $alias, array $settings): array
    {
        if (isset($settings[$alias])) {
            $settings = $settings[$alias];
        }

        $defaults = $this->getConfig($alias) ?? $this->getConfig();
        $maxLimit = $settings['maxLimit'] ?? $defaults['maxLimit'];
        $limit = $settings['limit'] ?? $defaults['limit'];

        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $settings['maxLimit'] = $maxLimit;
        $settings['limit'] = $limit;

        return $settings + $defaults;
    }

    /**
     * Validate that the desired sorting can be performed on the $object.
     *
     * Only fields or virtualFields can be sorted on. The direction param will
     * also be sanitized. Lastly sort + direction keys will be converted into
     * the model friendly order key.
     *
     * You can use the whitelist parameter to control which columns/fields are
     * available for sorting via URL parameters. This helps prevent users from ordering large
     * result sets on un-indexed values.
     *
     * If you need to sort on associated columns or synthetic properties you
     * will need to use a whitelist.
     *
     * Any columns listed in the sort whitelist will be implicitly trusted.
     * You can use this to sort on synthetic columns, or columns added in custom
     * find operations that may not exist in the schema.
     *
     * The default order options provided to paginate() will be merged with the user's
     * requested sorting field/direction.
     *
     * @param RepositoryInterface $object Repository object.
     * @param array $options The pagination options being used for this request.
     * @return array An array of options with sort + direction removed and
     *   replaced with order if possible.
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function validateSort($object, array $options): array
    {
        if (isset($options['sort'])) {
            $direction = null;
            if (isset($options['direction'])) {
                $direction = strtolower($options['direction']);
            }
            if (!in_array($direction, ['asc', 'desc'], true)) {
                $direction = 'asc';
            }

            $order = isset($options['order']) && is_array($options['order']) ? $options['order'] : [];

            $options['order'] = [$options['sort'] => $direction] + $order;
        } else {
            $options['sort'] = null;
        }
        unset($options['direction']);

        if (empty($options['order'])) {
            $options['order'] = [];
        }
        if (!is_array($options['order'])) {
            return $options;
        }

        $inWhitelist = false;
        if (isset($options['sortWhitelist'])) {
            $field = key($options['order']);
            $inWhitelist = in_array($field, $options['sortWhitelist'], true);
            if (!$inWhitelist) {
                $options['order'] = [];
                $options['sort'] = null;

                return $options;
            }
        }

        if (
            $options['sort'] === null
            && count($options['order']) === 1
            && !is_numeric(key($options['order']))
        ) {
            $options['sort'] = key($options['order']);
        }

        /* @var StackEntity $object */

        return $options;
    }

    /**
     * Remove alias if needed.
     *
     * @param array $fields Current fields
     * @param string $model Current model alias
     * @return array $fields Unaliased fields where applicable
     */
    protected function _removeAliases(array $fields, string $model): array
    {
        $result = [];
        foreach ($fields as $field => $sort) {
            if (strpos($field, '.') === false) {
                $result[$field] = $sort;
                continue;
            }

            [$alias, $currentField] = explode('.', $field);

            if ($alias === $model) {
                $result[$currentField] = $sort;
                continue;
            }

            $result[$field] = $sort;
        }

        return $result;
    }

    /**
     * Prefixes the field with the table alias if possible.
     *
     * @param LayerAccessProcessor $object
     * @param array $order Order array.
     * @param bool $whitelisted Whether or not the field was whitelisted.
     * @return array Final order array.
     */
//    protected function _prefix($object, array $order, bool $whitelisted = false): array
//    {
//        $fields = is_null($object) ? false : $object->getVisible();
//
//        $tableOrder = [];
//        foreach ($order as $key => $value) {
//            if (is_numeric($key)) {
//                $tableOrder[] = $value;
//                continue;
//            }
//            $field = $key;
//
//            if (!$fields) {
//                $tableOrder[$field] = $value;
//                continue;
//            }
//
//            if ($whitelisted) {
//                if (in_array($field, $fields)) {
//                    $field = $field;
//                }
//                $tableOrder[$field] = $value;
//            } elseif (in_array($field, $fields)) {
//                $tableOrder[$field] = $value;
//            } elseif ($whitelisted) {
//                $tableOrder[$field] = $value;
//            }
//        }
//        return $tableOrder;
//    }

    /**
     * Check the limit parameter and ensure it's within the maxLimit bounds.
     *
     * @param array $options An array of options with a limit key to be checked.
     * @return array An array of options for pagination.
     */
    public function checkLimit(array $options): array
    {
        $options['limit'] = (int)$options['limit'];
        if (empty($options['limit']) || $options['limit'] < 1) {
            $options['limit'] = 1;
        }
        $options['limit'] = max(min($options['limit'], $options['maxLimit']), 1);

        return $options;
    }
}

