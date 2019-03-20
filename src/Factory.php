<?php
/**
 * Created by solly [26.11.17 2:40]
 */

namespace insolita\muffin;

use ArrayAccess;
use Faker\Generator as Faker;
use Yii;
use yii\helpers\FileHelper;

class Factory implements ArrayAccess
{
    /**
     * The model definitions in the container.
     *
     * @var array
     */
    protected $definitions = [];

    /**
     * The registered model states.
     *
     * @var array
     */
    protected $beforeStoreCallbacks = [];

    /**
     * The registered model states.
     *
     * @var array
     */
    protected $states = [];

    /**
     * The registered model states.
     *
     * @var array
     */
    protected $afterStoreCallbacks = [];


    /**
     * The Faker instance for the builder.
     *
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * Create a new factory instance.
     *
     * @param  \Faker\Generator $faker
     * @param string $factoryPath
     */
    public function __construct(Faker $faker, $factoryPath = '@tests/factories')
    {
        $this->faker = $faker;
        $this->load(Yii::getAlias($factoryPath));
    }

    /**
     * Define a class with a given short-name.
     *
     * @param  string $class
     * @param  string $name
     * @param  callable $attributes
     *
     * @return $this
     */
    public function defineAs($class, $name, callable $attributes)
    {
        return $this->define($class, $attributes, $name);
    }

    /**
     * Define a class with a given set of attributes.
     *
     * @param  string $class
     * @param  callable $attributes
     * @param  string $name
     *
     * @return $this
     */
    public function define($class, callable $attributes, $name = 'default')
    {
        $this->definitions[$class][$name] = $attributes;

        return $this;
    }

    /**
     * Define a state with a given set of attributes.
     *
     * @param  string $class
     * @param  string $state
     * @param  callable|array $attributes
     *
     * @return $this
     */
    public function state($class, $state, $attributes)
    {
        $this->states[$class][$state] = $attributes;

        return $this;
    }

    /**
     * Define a callback that will be called before store by default
     * It is called after
     *
     * @param  string $class
     * @param  callable $callback
     *
     * @return $this
     */
    public function beforeStore($class, $callback)
    {
        $this->beforeStoreCallbacks[$class] = $callback;

        return $this;
    }

    /**
     * Define a callback that will be called before store by default
     *
     * @param  string $class
     * @param  callable $callback
     *
     * @return $this
     */
    public function afterStore($class, $callback)
    {
        $this->afterStoreCallbacks[$class] = $callback;

        return $this;
    }

    /**
     * Create an instance of the given model and persist it to the database.
     *
     * @param  string $class
     * @param  array $attributes
     *
     * @return \yii\db\ActiveRecord|\yii\db\ActiveRecord[]
     */
    public function create($class, array $attributes = [])
    {
        return $this->of($class)->create($attributes);
    }

    /**
     * Create an instance of the given model and type and persist it to the database.
     *
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     *
     * @return \yii\db\ActiveRecord|\yii\db\ActiveRecord[]
     */
    public function createAs($class, $name, array $attributes = [])
    {
        return $this->of($class, $name)->create($attributes);
    }

    /**
     * Create an instance of the given model.
     *
     * @param  string $class
     * @param  array $attributes
     *
     * @return \yii\db\ActiveRecord|\yii\db\ActiveRecord[]
     * @throws \InvalidArgumentException
     */
    public function make($class, array $attributes = [])
    {
        return $this->of($class)->make($attributes);
    }

    /**
     * Create an instance of the given model and type.
     *
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     *
     * @return \yii\db\ActiveRecord|\yii\db\ActiveRecord[]
     * @throws \InvalidArgumentException
     */
    public function makeAs($class, $name, array $attributes = [])
    {
        return $this->of($class, $name)->make($attributes);
    }

    /**
     * Get the raw attribute array for a given named model.
     *
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     *
     * @return array
     */
    public function rawOf($class, $name, array $attributes = [])
    {
        return $this->raw($class, $attributes, $name);
    }

    /**
     * Get the raw attribute array for a given model.
     *
     * @param  string $class
     * @param  array $attributes
     * @param  string $name
     *
     * @return array
     */
    public function raw($class, array $attributes = [], $name = 'default')
    {
        return array_merge(
            call_user_func($this->definitions[$class][$name], $this->faker),
            $attributes
        );
    }

    /**
     * Create a builder for the given model.
     *
     * @param  string $class
     * @param  string $name
     *
     * @return \insolita\muffin\FactoryBuilder
     */
    public function of($class, $name = 'default')
    {
        return new FactoryBuilder(
            $class, $name, $this->definitions, $this->states, $this->faker, $this->beforeStoreCallbacks,
            $this->afterStoreCallbacks
        );
    }

    /**
     * Load factories from path.
     *
     * @param  string $path
     *
     * @return $this
     */
    public function load($path)
    {
        $factory = $this;

        if (is_dir($path)) {
            foreach (FileHelper::findFiles($path, ['only' => ['*.php']]) as $file) {
                require $file;
            }
        }

        return $factory;
    }

    /**
     * @param  string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->definitions[$offset]);
    }

    /**
     * @param  string $offset
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function offsetGet($offset)
    {
        return $this->make($offset);
    }

    /**
     * @param  string $offset
     * @param  callable $value
     *
     * @return $this
     */
    public function offsetSet($offset, $value)
    {
        return $this->define($offset, $value);
    }

    /**
     * @param  string $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->definitions[$offset]);
    }
}
