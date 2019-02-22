<?php
/**
 * Pherret - MVC framework in a single file
 *
 * @author Joji Mitsumoto <mitsumotocs@gmail.com>
 * @license MIT
 */
namespace Pherret
{
    /**
     * PSR-4 compliant auto loader
     *
     * @package Pherret
     */
    class ClassLoader
    {
        /** @var string $directory */
        protected static $directory = '.';

        /**
         * @param $path
         * @return void
         */
        public static function setDirectory($path)
        {
            static::$directory = rtrim($path, '/');
        }

        /**
         * @param string $class
         * @return bool
         */
        public static function load($class)
        {
            $file = static::$directory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            if (is_file($file)) {
                require_once $file;
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * The app - routing and error handling
     *
     * @package Pherret
     */
    class App
    {
        const VERSION = '2019.2.22';

        /** @var array $routes */
        protected static $routes = [];
        /** @var \Closure $errorHandler Exception will be passed */
        protected static $errorHandler;

        /**
         * @param string|null $method
         * @param string $pattern
         * @param \Closure $callback
         */
        public static function route($method = null, $pattern, \Closure $callback)
        {
            array_unshift(static::$routes, [
                'method' => is_string($method) ? strtoupper($method) : null,
                'pattern' => $pattern,
                'callback' => $callback
            ]);
        }

        /**
         * @return void
         */
        public static function run()
        {
            // routing
            $path = trim(preg_replace(
                sprintf('/^%s/', preg_quote(dirname($_SERVER['SCRIPT_NAME']), '/')),
                '',
                preg_replace('/\?.*$/', '', urldecode($_SERVER['REQUEST_URI']))
            ), '/');
            $callback = null;
            $params = [];
            foreach (static::$routes as $i => $route) {
                if (@preg_match($route['pattern'], $path, $matches) === 1) {
                    if (is_null($route['method']) || $route['method'] === $_SERVER['REQUEST_METHOD']) {
                        $callback = $route['callback'];
                        $params = array_slice($matches, 1);
                        break;
                    }
                }
            }

            // callback
            try {
                if (is_callable($callback)) {
                    call_user_func_array($callback, $params);
                } else {
                    throw new \RuntimeException('Not Found', 404);
                }
            } catch (\Exception $e) {
                if (is_callable(static::$errorHandler)) {
                    call_user_func(static::$errorHandler, $e);
                } else {
                    static::defaultErrorHandler($e);
                }
            }
        }

        /**
         * @param \Closure $callback
         * @return void
         */
        public static function setErrorHandler(\Closure $callback)
        {
            static::$errorHandler = $callback;
        }

        /**
         * @param \Exception $e
         * @return void
         */
        protected static function defaultErrorHandler(\Exception $e)
        {
            $class = get_class($e);
            $code = $e->getCode();
            $message = $e->getMessage();
            http_response_code((!empty($code) && is_int($code)) ? $code : 500);
            die(sprintf('%s: %s (%d)', $class, $message, $code));
        }
    }

    /**
     * JSON based config accessor
     *
     * @package Pherret
     */
    class Config
    {
        const PATH_SEPARATOR = '.';

        /** @var array $values */
        protected static $values = [];

        /**
         * @param string $file
         * @return array
         * @throws \LogicException
         */
        public static function load($file)
        {
            if (is_readable($file)) {
                $values = json_decode(file_get_contents($file), true);
                if (!is_null($values)) {
                    static::$values = array_replace_recursive(static::$values, $values);
                } else {
                    throw new \LogicException(sprintf('Config file "%s" may be corrupted.', $file));
                }
            } else {
                throw new \LogicException(sprintf('Config file "%s" is not readable.', $file));
            }
            return static::$values;
        }

        /**
         * @param string|null $path
         * @return mixed|array
         */
        public static function get($path = null)
        {
            if (isset($path)) {
                $keys = explode('.', $path);
                $value = static::$values;
                foreach ($keys as $key) {
                    if (isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        throw new \LogicException(sprintf('Value "%s" is not defined.', $path));
                    }
                }
                return $value;
            } else {
                return static::$values;
            }
        }

        /**
         * @param string $path
         * @return bool
         */
        public static function has($path)
        {
            try {
                static::get($path);
                return true;
            } catch (\LogicException $e) {
                return false;
            }
        }
    }

    /**
     * Controller
     *
     * @package Pherret
     */
    class Controller
    {
        /** @var AbstractView $view */
        protected $view;
        /** @var array $input HTTP input values can be stored here. */
        protected $input = [];

        /**
         * @param $name
         * @param $arguments
         * @throws \RuntimeException
         */
        public function __call($name, $arguments)
        {
            throw new \RuntimeException(sprintf('Action "%s" is not implemented in %s.', $name, get_class($this)), 404);
        }

        /**
         * @param string $url
         * @param int|null $code
         */
        protected function redirect($url, $code = null)
        {
            http_response_code(isset($code) ? intval($code) : 302);
            header('Location: ' . $url);
            exit;
        }
    }

    /**
     * Abstract view
     *
     * @package Pherret
     */
    abstract class AbstractView
    {
        /** @var array $headers */
        protected $headers = [];
        /** @var array $data */
        public $data = [];

        /**
         * @param $name
         * @return mixed|null
         */
        public function __get($name)
        {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        }

        /**
         * @param $name
         * @param $value
         * @return void
         */
        public function __set($name, $value)
        {
            $this->data[$name] = $value;
        }

        /**
         * @return string
         */
        public function __toString()
        {
            return print_r($this->data, true);
        }

        /**
         * @param string $name
         * @param mixed $value
         * @return $this
         */
        public function addHeader($name, $value)
        {
            $this->headers[$name] = $value;
            return $this;
        }

        /**
         * @param string $name
         * @return $this
         */
        public function removeHeader($name)
        {
            unset($this->headers[$name]);
            return $this;
        }

        /**
         * @return void
         */
        public function sendHeaders()
        {
            foreach ($this->headers as $name => $value) {
                if (isset($value)) {
                    header($name . ': ' . $value);
                }
            }
        }

        /**
         * @return mixed
         */
        public function render()
        {
            $this->sendHeaders();
            return $this->__toString();
        }
    }

    /**
     * JSON view
     *
     * @package Pherret
     */
    class JsonView extends AbstractView
    {
        /**
         * @return string
         */
        public function __toString()
        {
            return json_encode($this->data);
        }

        /**
         * @return void
         */
        public function render()
        {
            $this->addHeader('Content-Type', 'application/json');
            echo parent::render();
        }
    }

    /**
     * Html view
     *
     * @package Pherret
     */
    class HtmlView extends AbstractView
    {
        /** @var string $directory Directory path to be searched template files from */
        protected static $directory;
        /** @var string $template File name of the template */
        protected $template;

        /**
         * @param string|null $template
         */
        public function __construct($template = null)
        {
            if (isset($template)) {
                $this->setTemplate($template);
            }
        }

        /**
         * @param string $directory
         * @return void
         */
        public static function setDirectory($directory)
        {
            static::$directory = rtrim($directory, '/');
        }

        /**
         * @param $file
         * @return $this
         * @throws \LogicException
         */
        public function setTemplate($file)
        {
            if (!empty(static::$directory)) {
                $file = static::$directory . DIRECTORY_SEPARATOR . $file;
            }
            if (is_readable($file)) {
                $this->template = $file;
                return $this;
            } else {
                throw new \LogicException(sprintf('Template "%s" is not available.', $file), 500);
            }
        }

        /**
         * @return void
         */
        public function render()
        {
            parent::render();
            $this->addHeader('Content-Type', 'text/html');
            $data = &$this->data;
            include_once $this->template;
        }

        /**
         * @return void
         */
        public function dump()
        {
            parent::render();
            $this->addHeader('Content-Type', 'text/html');
            echo sprintf('<pre>%s</pre>', print_r($this->data, true));
        }
    }

    /**
     * Database accessor
     *
     * @package Pherret
     */
    class Database
    {
        /** @var \PDO $pdo */
        protected static $pdo;

        /**
         * @param \PDO $pdo
         * @return void
         */
        public static function setConnection(\PDO $pdo)
        {
            static::$pdo = $pdo;
        }

        /**
         * @return \PDO
         */
        public static function getConnection()
        {
            return static::$pdo;
        }

        /**
         * @param string $sql
         * @param array $params
         * @return array
         * @throws \PDOException|\LogicException
         */
        public static function query($sql, array $params = [])
        {
            if (static::$pdo instanceof \PDO) {
                static::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                static::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $statement = static::$pdo->prepare($sql);
                $statement->execute(array_values($params));
                return $statement->fetchAll();
            } else {
                throw new \LogicException('A valid PDO instance must be given by using setConnection() method before querying.');
            }
        }

        /**
         * @return bool
         * @throws \PDOException
         */
        public static function beginTransaction()
        {
            return static::$pdo->beginTransaction();
        }

        /**
         * @return bool
         * @throws \PDOException
         */
        public static function commit()
        {
            return static::$pdo->commit();
        }

        /**
         * @return bool
         * @throws \PDOException
         */
        public static function rollback()
        {
            return static::$pdo->rollBack();
        }
    }

    /**
     * Model
     *
     * @package Pherret
     */
    class Model
    {
        const TABLE = 'model';

        /** @var int $id */
        public $id;

        /**
         * @param static $instance
         * @return void
         * @throws \InvalidArgumentException
         */
        protected static function validateInstance($instance)
        {
            if (get_class($instance) !== static::class) {
                throw new \InvalidArgumentException(sprintf('%s only accepts an instance of itself.', static::class));
            }
        }

        /**
         * @return array
         */
        public function getValues()
        {
            return get_object_vars($this);
        }

        /**
         * @param static $instance
         * @param array $values
         * @return void
         */
        protected static function inflate(&$instance, array $values)
        {
            static::validateInstance($instance);
            $instance->id = intval($values['id']);
        }

        /**
         * @param static $instance
         * @param array $values
         * @return void
         */
        protected static function deflate($instance, array &$values)
        {
            static::validateInstance($instance);
            $values['id'] = $instance->id;
        }

        /**
         * @param array $results
         * @return null|static
         */
        protected static function toInstance(array $results)
        {
            if (!empty($results)) {
                $instance = new static;
                static::inflate($instance, $results[0]);
                return $instance;
            } else {
                return null;
            }
        }

        /**
         * @param array $results
         * @return static[]
         */
        protected static function toInstanceArray(array $results)
        {
            $instances = [];
            foreach ($results as $result) {
                $instance = new static;
                static::inflate($instance, $result);
                $instances[] = $instance;
            }
            return $instances;
        }

        /**
         * @param int $id
         * @return static|null
         */
        public static function getById($id)
        {
            $sql = sprintf('SELECT * FROM "%s" WHERE "id" = ?;', static::TABLE);
            return static::toInstance(Database::query($sql, [intval($id)]));
        }

        /**
         * @return static[]
         */
        public static function getAll()
        {
            $sql = sprintf('SELECT * FROM "%s";', static::TABLE);
            return static::toInstanceArray(Database::query($sql));
        }

        /**
         * @param int|null $id
         * @return static|static[]|null
         */
        public static function get($id = null)
        {
            return isset($id) ? static::getById($id) : static::getAll();
        }

        /**
         * @param int|null $count
         * @return static|static[]|null
         */
        public static function getLatest($count = 1)
        {
            $count = intval($count) ?: 1;
            $sql = sprintf('SELECT * FROM "%s" ORDER BY "id" DESC LIMIT %d;', static::TABLE, $count);
            $results = Database::query($sql);
            if ($count === 1) {
                return static::toInstance($results);
            } else {
                return static::toInstanceArray($results);
            }
        }

        /**
         * @param int|null $count
         * @return static|static[]|null
         */
        public static function getOldest($count = 1)
        {
            $count = intval($count) ?: 1;
            $sql = sprintf('SELECT * FROM "%s" ORDER BY "id" ASC LIMIT %d;', static::TABLE, $count);
            $results = Database::query($sql);
            if ($count === 1) {
                return static::toInstance($results);
            } else {
                return static::toInstanceArray($results);
            }
        }

        /**
         * @param static $instance
         * @return bool
         */
        public static function has($instance)
        {
            static::validateInstance($instance);
            return static::getById($instance->id) instanceof static;
        }

        /**
         * @param static $instance
         * @return static
         */
        public static function add($instance)
        {
            static::validateInstance($instance);
            $instance->id = null;
            $params = [];
            static::deflate($instance, $params);
            $sql = sprintf('INSERT INTO "%s" ("%s") VALUES (%s);',
                static::TABLE,
                implode('", "', array_keys($params)),
                implode(', ', array_pad([], count($params), '?'))
            );
            Database::query($sql, $params);
            return static::getLatest();
        }

        /**
         * @param static $instance
         * @return static
         * @throws \LogicException
         */
        public static function update($instance)
        {
            static::validateInstance($instance);
            if (!static::has($instance)) {
                throw new \LogicException('An unsaved instance cannot be updated.');
            }
            $params = [];
            static::deflate($instance, $params);
            unset($params['id']);
            $sql = sprintf('UPDATE "%s" SET "%s WHERE "id" = ?;',
                static::TABLE,
                implode('" = ?, "', array_keys($params)) . '" = ?'
            );
            Database::query($sql, array_merge($params, [$instance->id]));
            return static::getById($instance->id);
        }

        /**
         * @param static $instance
         * @return static
         * @throws \LogicException
         */
        public static function delete($instance)
        {
            static::validateInstance($instance);
            if (!static::has($instance)) {
                throw new \LogicException('An unsaved instance cannot be deleted.');
            }
            $sql = sprintf('DELETE FROM "%s" WHERE id = ?;',static::TABLE);
            Database::query($sql, [$instance->id]);
            return $instance;
        }

        /**
         * @return void
         */
        public static function deleteAll()
        {
            Database::query(sprintf('DELETE FROM "%s";',static::TABLE));
        }
    }
}

namespace {
    spl_autoload_register(['Pherret\ClassLoader', 'load']);
}