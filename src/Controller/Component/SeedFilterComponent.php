<?php


namespace StackPagination\Controller\Component;


use Cake\Controller\ComponentRegistry;
use Cake\ORM\Table;
use StackPagination\Exception\BadClassConfigurationException;
use Cake\Controller\Component;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Form\Form;

class SeedFilterComponent extends Component
{

    /**
     * @var bool|Table
     */
    protected $table = false;

    /**
     * @var bool|Form
     */
    protected $form = false;

    /**
     * @var array
     */
    protected $_defaultConfig = [
        'tableAlias' => null,
        'formClass' => null,
        'useCase' => null,
        'filterScope' => null
    ];

    /**
     * SeedFilterComponent constructor.
     *
     * $config keys
     * ['tableAlias'] required
     * ['formClass'] if one is not provided then 'App\Form\' . $tableAlias . 'Filter' is used
     * ['useCase'] I'm assuming something like this will be needed to control the behavior of filters
     *      on index pages vs multi-layer searches. Sets to 'index' by default
     * ['cacheScope'] if not set, :controller.:action will be used
     *
     * @param ComponentRegistry $registry
     * @param array $config
     */
//    public function __construct(ComponentRegistry $registry, array $config = [])
//    {
//        parent::__construct($registry, $config);
//    }

    /**
     * Constructor hook method.
     *
     * Implement this method to avoid having to overwrite
     * the constructor and call parent.
     *
     * @param array $config The configuration settings provided to this component.
     * @return void
     * @throws BadClassConfigurationException
     */
//    public function initialize(array $config) : void
//    {
//        parent::initialize($config);
//        osd($this->getConfig());
//        $this->validateConfig($config);
//
//        //expects the stackTable to be filtered
//        $this->tableAlias = Hash::get($config, 'tableAlias');
//        //can take custom form class but will use one from naming conventions
//        $this->formClass = Hash::get($config, 'formClass') ?? 'App\Filter\\' . $this->tableAlias . 'Filter';
//        //not sure of the role of this
//        $this->useCase = Hash::get($config, 'useCase') ?? 'index';
//        //filter persistance scope, accepts custom, will default to 'here'
//        $this->useCase = Hash::get($config, 'filterScope') ??
//            $this->getController()->request->getParam('controller')
//            . '.' . $this->getController()->request->getParam('action');
//
//    }

    /**
     * Add a user defined filter to the pre-distilation seed query
     *
     * This replaces `concreteController::userFilter
     * @param $query
     * @param $scope string the model/scope name from the pagination block for this query
     * @return mixed
     */
    public function applyFilter($query, $scope)
    {
        $Request = $this->getController()->getRequest();
        $Session = $Request->getSession();
        $Filter = $this->getForm();
        $filter = $Session->read('filter') ?? [];

        /**
         * This one value will go out to render the page
         * It may go as is or be reassigned post data which
         * might carry error reporting
         */
        $formContext = empty($Request->getData()) ? $filter['data'] ?? [] : $Request->getData(); //$Table->newEntity([]);
//        debug($formContext);
//        debug($Request->is(['post', 'put']));
//        debug(!empty($formContext));
//        debug(($Request->is(['post', 'put']) || !empty($formContext)));
//        debug(($formContext['pagingScope'] ?? '') == $scope);
//        debug($Filter->execute((array) $formContext));

        if (($Request->is(['post', 'put']) || !empty($formContext))
            && ($formContext['pagingScope'] ?? '') == $scope
            && $Filter->execute((array) $formContext)
        ) {
            $query->where($Filter->conditions);
            $filter = array_merge(
                $filter,
                [
                    'path' => $this->endPointIdentifier(),
                    'conditions' => [$scope => $Filter->conditions],
                    'data' => $formContext,
                ]
            );
            $Session->write('filter', $filter);
        }
        else {
            $query->where($Session->read("filter.conditions.$scope") ?? []);
        }
        /* This one seems ok, but it will need to be associated with a particular paging scope. */
        $this->getController()->set('filterSchema', $formContext);

        return $query;
    }

    /**
     * Get the table instance
     *
     * @return bool|Table
     */
    protected function getTable()
    {
//        if ($this->table === false) {
            $this->table = TableRegistry::getTableLocator()->get($this->getConfig('tableAlias'));
//        }
        return $this->table;
    }

    /**
     * Get the form instance
     *
     * @return Form
     */
    public function getForm()
    {
        $class = $this->getConfig('formClass')
            ?? 'App\Filter\\' . $this->getConfig('tableAlias') . 'Filter';
        $this->form = new $class();
        return $this->form;
    }

    /**
     * @return string
     */
    protected function endPointIdentifier(): string
    {
        return $this->getController()->getRequest()->getParam('controller')
            . '_' . $this->getController()->getRequest()->getParam('action');
    }

    /**
     * Insure $config contains valid data types
     *
     * [
     *  'tableAlias' => null,
     *  'formClass' => null,
     *  'useCase' => null,
     *  'filterScope' => null
     * ]
     * @param $config
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function validateConfig($config): void
    {
        // we're only allowing string config values
        $defaultKeys = array_keys($this->_defaultConfig);
        $configErrors = collection($config())->reduce(
            function ($errors, $value, $key) use ($defaultKeys) {
                if (in_array($key, $defaultKeys) && !is_string($value)) {
                    $errors[] = "\$config[$key] must be a string. ";
                }
                return $errors;
            }, []);
        // and this is the one required config value
        if (is_null(Hash::get($config, 'tableAlias'))) {
            $configErrors[] = '$config[\'tableAlias\'] must be set.';
        }
        if (!empty($configErrors)) {
            $msg = 'SeedFilterComponent errors: ' . implode(' ', $configErrors);
            throw new BadClassConfigurationException($msg);
        }
    }
}
