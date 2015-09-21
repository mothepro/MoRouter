<?php
namespace Mo\Router;

/**
 * API help
 *
 * @author Maurice Prosper <maurice.prosper@ttu.edu>
 */
abstract class Middleware {
	/**
	 * Allow JSONP callback
	 * @var string
	 */
	public static $callback;
	
	/**
	 * Use Protected JSON which can't be run as JS
	 * @var boolean
	 */
	public static $protected = false;

	/**
	 * We are going to run the API command,
	 * then output the contents in API template
	 * @param \Slim\Route $route
	 */
	public static function json(\Slim\Route $route) {
		$app = \Slim\Slim::getInstance();
		$old = $route->getCallable();

		$route->setCallable(function () use ($app, $old, $route) {
			try { // call original route
				$ret = call_user_func_array(
						$old,
						array_values($route->getParams())
					);
			} catch (\Exception $e) { // $ret['data'] === false
				$ret['error'] = $e->getMessage();
				$app->response->setStatus(500);
			}
			
			// JSONP
			static::$callback	= $app->request->get('callback', static::$callback);	
//			static::$protected	= $app->request->get('protected', static::$protected);	

			// headers
			$ret['status'] = $app->response->getStatus();
			$app->response->headers->set('Content-Type', 'application/json');
			
			// template
			if(count($ret) > 1) {
				$echo = json_encode($ret);

				if(static::$callback)
					$echo = sprintf('%s(%s);', static::$callback, $echo);
				elseif(static::$protected)
					$echo = sprintf('while(1);%s', $echo);
//				else
//					$echo = json_encode($ret);

				$app->response->setBody($echo);
			}
		});
	}
}
