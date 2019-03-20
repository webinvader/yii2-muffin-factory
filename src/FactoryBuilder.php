<?php
/**
 * Created by solly [26.11.17 2:43]
 */

namespace insolita\muffin;

use Faker\Generator as Faker;
use yii\db\ActiveRecord;

class FactoryBuilder
{
    /**
     * The model definitions in the container.
     *
     * @var array
     */
    protected $definitions;

    /**
     * The model being built.
     *
     * @var string
     */
    protected $class;

    /**
     * The name of the model being built.
     *
     * @var string
     */
    protected $name = 'default';

    /**
     * The model states.
     *
     * @var array
     */
    protected $states;

    /**
     * The states to apply.
     *
     * @var array
     */
    protected $activeStates = [];

    /**
     * The model states.
     *
     * @var array
     */
    protected $afterStore;

    /**
     * The states to apply.
     *
     * @var array
     */
    protected $activeAfterStore = null;

    /**
     * The model states.
     *
     * @var array
     */
    protected $beforeStore;

    /**
     * The states to apply.
     *
     * @var array
     */
    protected $activeBeforeStore = null;

    /**
     * The Faker instance for the builder.
     *
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * The number of models to build.
     *
     * @var int|null
     */
    protected $amount = null;

    /**
     * Create an new builder instance.
     *
     * @param  string $class
     * @param  string $name
     * @param  array $definitions
     * @param  array $states
     * @param  \Faker\Generator $faker
     */
    public function __construct(
        $class,
        $name,
        array $definitions,
        array $states,
        Faker $faker,
        array $beforeStore,
        array $afterStore
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->faker = $faker;
        $this->states = $states;
        $this->beforeStore = $beforeStore;
        $this->afterStore = $afterStore;
        $this->definitions = $definitions;
    }

    /**
     * Set the amount of models you wish to create / make.
     *
     * @param  int $amount
     *
     * @return $this
     */
    public function times($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Set the states to be applied to the model.
     *
     * @param  array|mixed $states
     *
     * @return $this
     */
    public function states($states)
    {
        $this->activeStates = is_array($states) ? $states : func_get_args();

        return $this;
    }

    /**
     * Callback before store item
     * Set false to disable callback assigned in definitions
     *
     * @param  callable|false
     *
     * @return $this
     */
    public function beforeStore($callable = false)
    {
        if ($callable === false) {
            $this->activeBeforeStore = false;
        } else {
            $this->activeBeforeStore = is_callable($callable) ? $callable : null;
        }

        return $this;
    }

    /**
     * Callback after store item
     * Set false to disable callback assigned in definitions
     *
     * @param  callable|null|false $states
     *
     * @return $this
     */
    public function afterStore($callable = false)
    {
        if ($callable === false) {
            $this->activeAfterStore = false;
        } else {
            $this->activeAfterStore = is_callable($callable) ? $callable : null;
        }
        return $this;
    }

    /**
     * Create a model and persist it in the database if requested.
     *
     * @param  array $attributes
     *
     * @return \Closure
     */
    public function lazy(array $attributes = [])
    {
        return function () use ($attributes) {
            return $this->create($attributes);
        };
    }

    /**
     * Create a collection of models and persist them to the database.
     *
     * @param  array $attributes
     *
     * @return  mixed
     * @throws \InvalidArgumentException
     */
    public function create(array $attributes = [])
    {
        $results = $this->make($attributes);

        if ($results instanceof ActiveRecord) {
            $this->store([$results]);
        } else {
            $this->store($results);
        }

        return $results;
    }

    /**
     * Create a collection of models.
     *
     * @param  array $attributes
     *
     * @return array|ActiveRecord[]|ActiveRecord
     * @throws \InvalidArgumentException
     */
    public function make(array $attributes = [])
    {
        if ($this->amount === null) {
            return $this->makeInstance($attributes);
        }

        if ($this->amount < 1) {
            return new $this->class;
        }

        $models = [];
        for ($i = 0; $i < $this->amount; $i++) {
            $models[] = $this->makeInstance($attributes);
        }
        return $models;
    }

    /**
     * Create an array of raw attribute arrays.
     *
     * @param  array $attributes
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function raw(array $attributes = [])
    {
        if ($this->amount === null) {
            return $this->getRawAttributes($attributes);
        }

        if ($this->amount < 1) {
            return [];
        }

        return array_map(
            function () use ($attributes) {
                return $this->getRawAttributes($attributes);
            }, range(1, $this->amount)
        );
    }

    /**
     * @param  array|ActiveRecord[] $results
     *
     * @return void
     */
    protected function store(array $results)
    {
        foreach ($results as $model) {
            $this->applyBeforeStore($model);
            $model->save(false);
            $this->applyAfterStore($model);
        }
    }

    /**
     * Get a raw attributes array for the model.
     *
     * @param  array $attributes
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getRawAttributes(array $attributes = [])
    {
        $definition = call_user_func(
            $this->definitions[$this->class][$this->name],
            $this->faker,
            $attributes
        );

        return $this->expandAttributes(
            array_merge($this->applyStates($definition, $attributes), $attributes)
        );
    }

    /**
     * Make an instance of the model with the given attributes.
     *
     * @param  array $attributes
     *
     * @return ActiveRecord
     * @throws \InvalidArgumentException
     */
    protected function makeInstance(array $attributes = [])
    {
        if (!isset($this->definitions[$this->class][$this->name])) {
            throw new \InvalidArgumentException("Unable to locate factory with name [{$this->name}] [{$this->class}].");
        }
        $instance = new $this->class();
        $instance->setAttributes($this->getRawAttributes($attributes), false);
        return $instance;
    }

    /**
     * Apply the active states to the model definition array.
     *
     * @param  array $definition
     * @param  array $attributes
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function applyStates(array $definition, array $attributes = [])
    {
        foreach ($this->activeStates as $state) {
            if (!isset($this->states[$this->class][$state])) {
                throw new \InvalidArgumentException("Unable to locate [{$state}] state for [{$this->class}].");
            }
            $definition = array_merge(
                $definition,
                $this->stateAttributes($state, $attributes)
            );
        }
        return $definition;
    }

    /**
     * Apply the active before store call back or the callback that have been set in definitions
     *
     * @param  $model
     */
    protected function applyBeforeStore($model)
    {
        $callback = $this->activeBeforeStore;

        if ($callback === false) {
            return;
        }

        if (is_null($callback) && isset($this->beforeStore[$this->class])) {
            $callback = $this->beforeStore[$this->class];
        }
        if (!is_null($callback) && is_callable($callback)) {
            call_user_func($callback, $model, $this->faker);
        }
    }

    /**
     * Apply the active before store call back or the callback that have been set in definitions
     *
     * @param  $model
     */
    protected function applyAfterStore($model)
    {
        $callback = $this->activeAfterStore;

        if ($callback === false) {
            return;
        }

        if (is_null($callback) && isset($this->afterStore[$this->class])) {
            $callback = $this->afterStore[$this->class];
        }
        if (!is_null($callback) && is_callable($callback)) {
            call_user_func($callback, $model, $this->faker);
        }
    }

    /**
     * Get the state attributes.
     *
     * @param  string $state
     * @param  array $attributes
     *
     * @return array
     */
    protected function stateAttributes($state, array $attributes)
    {
        $stateAttributes = $this->states[$this->class][$state];

        if (!is_callable($stateAttributes)) {
            return $stateAttributes;
        }

        return call_user_func(
            $stateAttributes,
            $this->faker,
            $attributes
        );
    }

    /**
     * Expand all attributes to their underlying values.
     *
     * @param  array $attributes
     *
     * @return array
     */
    protected function expandAttributes(array $attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_callable($attribute) && !is_string($attribute)) {
                $attribute = $attribute($attributes);
            }

            if ($attribute instanceof static) {
                $attribute = $attribute->create()->getPrimaryKey();
            }

            if ($attribute instanceof ActiveRecord) {
                $attribute = $attribute->getPrimaryKey();
            }
        }

        return $attributes;
    }
}
