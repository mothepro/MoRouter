<?php

namespace Mo\Router;

/**
 * Generates Slim routes from a NEON file containing routes & groups
 * @todo cache router
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
	private static $info;
	
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
			];
			
			// extract route info
			foreach([
				'route',		// path
				'middleware',	// Route / Group Middleware
				
				// route specific
				'conditions',	// parameter conditions
				'methods',		// HTTP methods
				'params',		// parameters
				
				'callable',		// if set its a route
				'alias',		// routes are the same [middleware and callable]
				'dispatch',		// route dispatches another route
			] as $arg)
				if(isset($tmp[$arg])) {
					$info[$arg] = $tmp[$arg];
					unset($tmp[$arg]);
				}
				
			// make list of methods
			if(isset($tmp['method'])) {
				if(isset($info['methods']) && is_array($info['methods']))
					$info['methods'][] = $tmp['method'];
				else
					$info['methods'] = array($tmp['method']);
				unset($tmp['method']);
			}
			
			// a group
			if(!empty($tmp)) {
				// Name of group is route pattern, if no route given
//				if(!isset($info['route']))
//					$info['route'] = $name;
				
				// make a group
				static::$groups[ $name ] = [
					'pattern'		=> $info['route'],
					'middleware'	=> $info['middleware'],
					'parent'		=> $group,
				];

				// run on all children
				static::create($tmp, $name);
			}
			
			// this is a route
			if(isset($info['callable']) || isset($info['alias']) || isset($info['dispatch'])) {
				$info['group'] = $group;
				static::$info[ $name ] = $info;
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
		// fix info from groups
		foreach(static::$info as &$info) {
			$info['pattern']	= self::getPatterns($info['group'], $info['route']);
			$info['middleware']	= self::getMiddlewares($info['group'], $info['middleware']);
		} unset($info);
		
		static::makeAlias();
		static::makeDispatches();
	}
	
	/**
	 * create dispatches fron names
	 */
	protected static function makeDispatches() {
		do {
			$alias2alias = false;
			foreach(static::$info as $name => $info) {
				if(!isset($info['dispatch']))
					continue;
				
				$myAlias = static::$info[ $info['dispatch'] ];
				
				// does my alias, have an alias?
				if(isset($myAlias['dispatch'])) {
					$alias2alias = true;
					static::$info[$name]['dispatch'] = $myAlias['dispatch'];
				} else
					static::$info[$name]['callable'] = $myAlias['callable'];
			}
		} while($alias2alias);
	}

	/**
	 * create aliases from names
	 */
	protected static function makeAlias() {
		do {
			$alias2alias = false;
			foreach(static::$info as $name => $info) {
				if(!isset($info['alias']))
					continue;
				
				$myAlias = static::$info[ $info['alias'] ];
				
				// does my alias, have an alias?
				if(isset($myAlias['alias'])) {
					$alias2alias = true;
					unset($info['alias']);
				}
				
				// $info overwrites alias
				$info['middleware'] = array_unique(array_merge($myAlias['middleware'], $info['middleware']), SORT_REGULAR);
				static::$info[$name] = array_merge($myAlias, $info);
			}
		} while($alias2alias);
	}
	
	/**
	 * Finds a specific route
	 * @param string $path what to search for
	 * @param string $method type of route
	 * @return array|null
	 */
//	private static function searchBy($path, $method = 'GET') {
//		$ret = null;
//		
//		// match by name
//		if(!isset(static::$info[ $path ]))
//			$ret = static::$info[$path];
//		
//		else foreach (static::$info as $info) {
//				if(!in_array($method, $info['methods']))
//					continue;
//				
//				// match if it the same pattern
//				if($info['route'] === $path)
//					$ret = $info;
//				
//				// match by pattern
//				elseif(preg_match($path, $info['route']))
//					$ret = $info;
//				
//				if(isset($ret))
//					break;
//			}
//		var_dump($ret);
//		return $ret;
//	}

	protected static function generate($file) {
		$data = static::getNeon($file);
		
		static::create($data);
		static::process();
		static::byURI();
		
		return $data;
	}
	
	public static function generateSlim(\Slim\Router $router, $file) {
		$data = static::generate($file);
		
		if(isset($data['conditions']))
			\Slim\Route::setDefaultConditions($data['conditions']);

		// create routes with callable
		foreach(static::$info as $name => $info) {
			if(!isset($info['callable']))
				continue;
			
			$route = new \Slim\Route($info['pattern'], $info['callable']);
//			$route->setPattern($info['pattern']);
//			$route->setCallable($info['callable']);
			
			if(!empty($info['params']))
				$route->setParams($info['params']);
			
			if(!empty($info['middleware']))
				$route->setMiddleware($info['middleware']);
			
			if(isset($info['conditions']))
				$route->setConditions($info['conditions']);
			
			if(isset($info['methods']))
				$route->appendHttpMethods($info['methods']);
			
			$route->setName($name);
			
			$routes[ $name ] = $route;
			unset(static::$info[$name]);
		}
		
		// add routes to router
		foreach($routes as $route)
			$router->map($route);
	}
}
