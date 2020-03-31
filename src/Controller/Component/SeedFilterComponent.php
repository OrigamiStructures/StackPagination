<?php


namespace StackPagination\Controller\Component;


use StackPagination\Exception\BadClassConfigurationException;
use Cake\Controller\Component;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class SeedFilterComponent extends Component
{

    /**
     * @var bool|string
     */
    protected $tableAlias = false;

    /**
     * @var bool|\Cake\ORM\Table
     */
    protected $table = false;

    /**
     * @var bool|string
     */
    protected $formClass = false;

    /**
     * @var bool|\Cake\Form\Form
     */
    protected $form = false;

    /**
     * @var bool|string
     */
    protected $filterScope = false;

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
     * @return mixed
     */
    public function addFilter($query)
    {
        $Request = $this->getController()->getRequest();
        $Session = $Request->getSession();
        $Table = $this->getTable();
        $Filter = $this->getForm();

        /**
         * This one value will go out to render the page
         * It may go as is or be reassigned post data which
         * might carry error reporting
         */
        $formContext = $Table->newEntity([]);

        if ($Request->is(['post', 'put']) && $Filter->execute($Request->getData())) {
            $query->where($Filter->conditions);
            $Session->write('filter', [
                'path' => $this->filterScope,
                'conditions' => $Filter->conditions
            ]);
            $formContext = $Request->getData();
        }
        else {
            //@todo resolve scoping and path storage
            $query->where($Session->read("filter.conditions") ?? []);
        }
        /*
         * This one seems ok, but it will need to be associated with a
         * particular paging scope.
         */
        $this->getController()->set('filterSchema', $formContext);

        return $query;
    }

    public function exportFormContext($name)
    {

    }

    /**
     * Get the table instance
     *
     * @return bool|\Cake\ORM\Table
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
     * @return Cake\Form\Form
     */
    public function getForm()
    {
//        if ($this->form === false) {
            /* @todo this won't find forms will it? */
        $class = $this->getConfig('formClass');
            $this->form = new $class();
//        }
        return $this->form;
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
     */
    private function validateConfig($config): void
    {
        // we're only alowing string config values
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
