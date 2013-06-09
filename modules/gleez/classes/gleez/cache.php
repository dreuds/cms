<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Gleez Core Cache Class
 *
 * [Gleez Cache](gleez/cache/index) provides a common interface to a variety of caching engines.
 * Tags are supported where available natively to the cache system. Cache supports multiple
 * instances of cache engines through a grouped singleton pattern.
 *
 * ### Supported cache engines
 *
 * *  [APC](http://php.net/manual/en/book.apc.php)
 * *  File
 * *  [Memcache](http://memcached.org/)
 * *  [Memcached-tags](http://code.google.com/p/memcached-tags/)
 * *  [SQLite](http://www.sqlite.org/)
 * *  [Wincache](http://php.net/manual/en/book.wincache.php)
 *
 * ### Configuration settings
 *
 * Gleez Cache uses configuration groups to create cache instances. A configuration group can
 * use any supported driver, with successive groups using the same driver type if required.
 *
 * #### Configuration example
 *
 * Below is an example of a _memcache_ server configuration.
 *
 *     return array(
 *          'default'       => array(                      // Default group
 *                  'driver'         => 'memcache',        // using Memcache driver
 *                  'servers'        => array(             // Available server definitions
 *                         array(
 *                              'host'       => 'localhost',
 *                              'port'       => 11211,
 *                              'persistent' => FALSE
 *                         )
 *                  ),
 *                  'compression'    => FALSE,             // Use compression?
 *           ),
 *     )
 *
 * In cases where only one cache group is required, if the group is named `default` there is
 * no need to pass the group name when instantiating a cache instance.
 *
 * #### General cache group configuration settings
 *
 * Below are the settings available to all types of cache driver.
 *
 * Name           | Required | Description
 * -------------- | -------- | ---------------------------------------------------------------
 * driver         | __YES__  | (_string_) The driver type to use
 *
 * Details of the settings specific to each driver are available within the drivers documentation.
 *
 *
 * @package    Gleez\Cache
 * @version    2.0
 * @author     Sandeep Sangamreddi - Gleez
 * @copyright  (c) 2011-2013 Gleez Technologies
 * @license    http://gleezcms.org/license Gleez CMS License
 */
abstract class Gleez_Cache {

	const OLD = 1;
	const ALL = 2;
	const SEPARATOR = ':';
	const DEFAULT_EXPIRE = 86400;

	/**
	 * Default driver to use
	 * @var string
	 */
	public static $default = 'file';

	/**
	 * Gleez_Cache instances
	 * @var array
	 */
	public static $instances = array();

	/**
	 * Creates a singleton of a Cache group. If no group is supplied
	 * the __default__ cache group is used.
	 *
	 *     // Create an instance of the default group
	 *     $default_group = Cache::instance();
	 *
	 *     // Create an instance of a group
	 *     $foo_group = Cache::instance('foo');
	 *
	 *     // Access an instantiated group directly
	 *     $foo_group = Cache::$instances['default'];
	 *
	 * @param   string  $group  the name of the cache group to use [Optional]
	 * @return  Cache
	 * @throws  Cache_Exception
	 */
	public static function instance($group = NULL)
	{
		// If there is no group supplied
		if ($group === NULL)
		{
			// Use the default setting
			$group = Cache::$default;
		}

		if (isset(Cache::$instances[$group]))
		{
			// Return the current group if initiated already
			return Cache::$instances[$group];
		}

		$config = Kohana::$config->load('cache');
		$collection = $group;

		if ( ! $config->offsetExists($group))
		{
			// Use the default setting by gleez
			$group = Cache::$default;

			if (isset(Cache::$instances[$group]))
			{
				// Return the current group if initiated already
				return Cache::$instances[$group];
			}

			if ( ! $config->offsetExists($group))
			{
				throw new Cache_Exception('Failed to load Gleez Cache group: :group',
					array(':group' => $group)
				);
			}
		}

		$config = $config->get($group);
		$config['collection'] = 'cache_'.$collection;

		// Create a new cache type instance
		$cache_class = 'Cache_'.ucfirst($config['driver']);
		Cache::$instances[$group] = new $cache_class($config);

		// Return the instance
		return Cache::$instances[$group];
	}

	/**
	 * @var  Config
	 */
	protected $_config = array();

	/**
	 * Ensures singleton pattern is observed, loads the default expiry
	 *
	 * @param  array  $config  configuration
	 */
	protected function __construct(array $config)
	{
		$this->_config['prefix'] = isset($config['prefix']) ? $config['prefix'].self::SEPARATOR : md5(dirname(__FILE__)).self::SEPARATOR;

		$this->config($config);
	}

	/**
	 * Getter and setter for the configuration. If no argument provided, the
	 * current configuration is returned. Otherwise the configuration is set
	 * to this class.
	 *
	 *     // Overwrite all configuration
	 *     $cache->config(array('driver' => 'memcache', '...'));
	 *
	 *     // Set a new configuration setting
	 *     $cache->config('servers', array(
	 *          'foo' => 'bar',
	 *          '...'
	 *          ));
	 *
	 *     // Get a configuration setting
	 *     $servers = $cache->config('servers);
	 *
	 * @param   mixed  $key    Key to set to array, either array or config path [Optional]
	 * @param   mixed  $value  Value to associate with key [Optional]
	 * @return  mixed
	 */
	public function config($key = NULL, $value = NULL)
	{
		if ($key === NULL)
			return $this->_config;

		if (is_array($key))
		{
			$this->_config = $key;
		}
		else
		{
			if ($value === NULL)
				return Arr::get($this->_config, $key);

			$this->_config[$key] = $value;
		}

		return $this;
	}

	/**
	 * Overload the __clone() method to prevent cloning
	 *
	 * @return  void
	 * @throws  Cache_Exception
	 */
	final public function __clone()
	{
		throw new Cache_Exception('Cloning of Gleez_Cache objects is forbidden');
	}

	/**
	 * Retrieve a cached value entry by id.
	 *
	 *     // Retrieve cache entry from default group
	 *     $data = Cache::instance()->get('foo');
	 *
	 *     // Retrieve cache entry from default group and return 'bar' if miss
	 *     $data = Cache::instance()->get('foo', 'bar');
	 *
	 *     // Retrieve cache entry from memcache group
	 *     $data = Cache::instance('memcache')->get('foo');
	 *
	 * @param   string  $id       id of cache to entry
	 * @param   string  $default  default value to return if cache miss
	 * @return  mixed
	 * @throws  Cache_Exception
	 */
	abstract public function get($id, $default = NULL);

	/**
	 * Set a value to cache with id and lifetime
	 *
	 *     $data = 'bar';
	 *
	 *     // Set 'bar' to 'foo' in default group, using default expiry
	 *     Cache::instance()->set('foo', $data);
	 *
	 *     // Set 'bar' to 'foo' in default group for 30 seconds
	 *     Cache::instance()->set('foo', $data, 30);
	 *
	 *     // Set 'bar' to 'foo' in memcache group for 10 minutes
	 *     if (Cache::instance('memcache')->set('foo', $data, 600))
	 *     {
	 *          // Cache was set successfully
	 *          return
	 *     }
	 *
	 * @param   string   $id        id of cache entry
	 * @param   string   $data      data to set to cache
	 * @param   integer  $lifetime  lifetime in seconds
	 * @return  boolean
	 */
	abstract public function set($id, $data, $lifetime = 3600);

	/**
	 * Delete a cache entry based on id
	 *
	 *     // Delete 'foo' entry from the default group
	 *     Cache::instance()->delete('foo');
	 *
	 *     // Delete 'foo' entry from the memcache group
	 *     Cache::instance('memcache')->delete('foo')
	 *
	 * @param   string  $id  id to remove from cache
	 * @return  boolean
	 */
	abstract public function delete($id);

	/**
	 * Delete cache entries that matches the given pattern.
	 *
	 * @param  string  $pattern The cache key pattern
	 *
	 * @return Boolean true if no problem
	 *
	 * @see patternToRegexp
	 */
	abstract public function delete_pattern($pattern);

	/**
	 * Delete all cache entries
	 *
	 * Beware of using this method when using shared memory cache systems,
	 * as it will wipe every entry within the system for all clients.
	 *
	 * The clean mode can be:
	 *
	 *  + `Cache::ALL`: remove all keys (default)
	 *  + `Cache::OLD`: remove all expired keys
	 *
	 * ### Examples
	 *
	 * Delete all cache entries in the default group:<br>
	 * <code>
	 *   Cache::instance()->delete_all();
	 * </code>
	 *
	 * Delete all cache entries in the memcache group:
	 * <code>
	 *   Cache::instance('memcache')->delete_all();
	 * </code>
	 *
	 * @param   integer  $mode The clean mode [Optional]
	 * @return  boolean  TRUE if no problem
	 */
	abstract public function delete_all($mode = Cache::ALL);

	/**
	 * Gets many keys at once.
	 *
	 * @param  array $keys An array of keys
	 *
	 * @return array An associative array of data from cache
	 */
	public function getMany($keys)
	{
		$data = array();
		foreach ($keys as $key)
		{
			$data[$key] = $this->get($key);
		}

		return $data;
	}

	/**
	 * Computes lifetime.
	 *
	 * @param  integer $lifetime Lifetime in seconds
	 *
	 * @return integer Lifetime in seconds
	 */
	public function getLifetime($lifetime)
	{
		return is_null($lifetime) ? $this->config('lifetime') : $lifetime;
	}

	/**
	 * Converts a pattern to a regular expression.
	 *
	 * A pattern can use some special characters:
	 *
	 *  - * Matches a namespace (foo:*:bar)
	 *  - ** Matches one or more namespaces (foo:**:bar)
	 *
	 * @param  string $pattern  A pattern
	 *
	 * @return string A regular expression
	 */
	protected function _regxp_pattern($pattern)
	{
		$regexp = str_replace(
			array('\\*\\*', '\\*'),
			array('.+?',    '[^'.preg_quote(Cache::SEPARATOR, '#').']+'),
			preg_quote($pattern, '#')
		);

		return '#^'.$regexp.'$#';
	}

	/**
	 * Replaces troublesome characters with underscores.
	 *
	 *     // Sanitize a cache id
	 *     $id = $this->_sanitize_id($id);
	 *
	 * @param   string  $id  id of cache to sanitize
	 * @return  string
	 */
	protected function _sanitize_id($id)
	{
		// Change slashes and spaces to underscores
		return str_replace(array('/', '\\', ' '), '_', $id);
	}
}

class Cache_Exception extends Gleez_Exception {}
