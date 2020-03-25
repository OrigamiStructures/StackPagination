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
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);
    }

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
    public function initialize(array $config)
    {
        $this->validateConfig($config);

        $this->tableAlias = Hash::get($config, 'tableAlias');
        $this->formClass = Hash::get($config, 'formClass') ?? 'App\Filter\\' . $this->tableAlias . 'Filter';
        $this->useCase = Hash::get($config, 'useCase') ?? 'index';
        $this->useCase = Hash::get($config, 'filterScope') ??
            $this->getController()->request->getParam('controller')
            . '.' . $this->getController()->request->getParam('action');

    }

    public function addSeedFilter($query)
    {
        $request = $this->getController()->request;

        if ($request->is(['post', 'put'])) {
            $whereThis = $this->getForm()->execute($request->getData());
            // persist the filter for future and paginated viewing
            $request->getSession()
                ->write('filter', ['path' => $this->filterScope, 'conditions' => $whereThis]);
        } else {
            // respond to stored filters in cases there was no post
            $whereThis = $request->getSession()->read("filter.conditions") ?? [];
        }
        $query->where($whereThis);

        // set the values needed to render a search/filter for on the index page
        $modes = ['is', 'starts', 'ends', 'contains', 'isn\'t'];
        $identity = $this->getTable()->newEntity([]);
        $identity->modes = $modes;
        $this->getController()->set('filterSchema', $identity);
        return $query;
    }

    /**
     * Get the table instance
     *
     * @return bool|\Cake\ORM\Table
     */
    protected function getTable()
    {
        if ($this->table === false) {
            $this->table = TableRegistry::getTableLocator()->get($this->tableAlias);
        }
        return $this->table;
    }

    /**
     * Get the form instance
     *
     * @return bool|\Cake\Form\Form
     */
    public function getForm(): bool
    {
        if ($this->form === false) {
            $this->form = TableRegistry::getTableLocator()->get($this->formClass);
        }
        return $this->form;
    }

    /**
     * Insure $config contains valid data types
     *
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
