<?php

namespace Mo\Router;

/**
 * Generates Slim routes from a NEON file containing routes & groups
 * 
 * Format
 * Group Name:
 *	
 * 
 *
 * @author Maurice Prosper <maurice.prosper@ttu.edu>
 */
abstract class Router {
	/**
	 * @var \Slim\Router
	 */
	protected static $router;
	
	/**
	 * Route info and group
	 * @var string[][]
	 */
	private static $routes;
	
	/**
	 * Group names and patterns
	 * @var array
	 */
	private static $groups;

	/**
	 * Get NEON file
	 * @param string $file
	 * @return mixed[]
	 */
	protected static function getNeon($file) {
		$file = realpath($file);
		
		if(empty($file) || !is_file($file))
			throw new \Exception ('File not found');
		
		$data = file_get_contents($file);
		$ret = \Nette\Neon\Neon::decode($data);

		// root key
		if(isset($ret['router']))
			$ret = $ret['router'];
		
		if(empty($ret))
			throw new \Exception ('No Routes found');
		
		return $ret;
	}
	
	/**
	 * Gets routes or group from an array
	 * @param mixed[] $data
	 */
	protected static function create($data, $group = null) {
		foreach($data as $name => $tmp) {
			$info = [
				'route'			=> '',
				'middleware'	=> [],
				'methods'		=> [],
			];
			
			// extract route info
			foreach([
				'route',		// path
				
				'middleware',	// Route / Group Middleware
				'conditions',	// parameter conditions
				'defaults',		// parameter defaults
				'methods',		// HTTP methods
				
				'callable',		// if set its a route
				'alias',		// routes are the same [middleware and callable]
				'dispatch',		// route dispatches another route
			] as $arg)
				if(isset($tmp[$arg])) {
					$info[$arg] = $tmp[$arg];
					unset($tmp[$arg]);
				}
				
			// make list of methods
			if(isset($info['method'])) {
				$info['methods'][] = $info['method'];
				unset($info['method']);
			}
			
			// a group
			if(!isset($info['callable'])) {
				// Name of group is route pattern
				// if(!isset($tmp['route']))
				//	$info['route'] = $name;
				
				// make a group
				static::$groups[ $name ] = [
					'pattern'		=> $info['route'],
					'middleware'	=> $info['middleware'],
					'parent'		=> $group,
				];

				// run on all children
				static::create($tmp, $name);
				
			// just a route
			} else {
				$info['group'] = $group;
				static::$routes[ $name ] = $info;
			}
		}
	}
	
	/**
	 * Prepends before pattern with the route from the group.
	 * Called recursivly
	 * @param tring $group
	 * @param string $before
	 * @return string
	 */
	private static function getPatterns($group, $before = '') {
		if(isset(static::$groups[$group]))
			$before = self::getPatterns(
					static::$groups[$group]['parent'],
					static::$groups[$group]['pattern']
				) . $before;
		
		return $before;
	}
	
	/**
	 * Merges before middleware with the middleware from the group.
	 * Called recursivly
	 * @param string $group
	 * @param string[] $before
	 * @return string[][]
	 */
	private static function getMiddlewares($group, $before = array()) {
		if(isset(static::$groups[$group]))
			$before = array_merge(
					self::getMiddlewares(
						static::$groups[$group]['parent'],
						static::$groups[$group]['middleware']
					),
					$before
				);
		
		return $before;
	}
	
	/**
	 * Creates routes from created data
	 * Adds them to the router
	 */
	protected static function process() {
		foreach(static::$routes as $name => $info) {
			if(!isset($info['callable']) || !isset($info['route']))
				throw new Exception ('Required route option missing in '. $name);

			// groups
			$pattern = self::getPatterns($info['group'], $info['route']);
			$middleware = self::getMiddlewares($info['group'], $info['middleware']);

			$route = new \Slim\Route($pattern, $info['callable']);
//			$route->setPattern($pattern);
			$route->setMiddleware($middleware);
//			$route->setCallable($info['callable']);
			$route->setConditions($info['conditions']);
			$route->appendHttpMethods($info['methods']);
			$route->setName($name);
			
			static::$router->map($route);
		}
	}

	public static function generate(\Slim\Router $router, $file) {
		$data = static::getNeon($file);
		static::$router = $router;
		
		if(isset($data['conditions'])) {
			\Slim\Route::setDefaultConditions($data['conditions']);
			unset($data['conditions']);
		}
		
		static::create($data);
		static::process();
	}
}
