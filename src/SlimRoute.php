<?php

namespace Mo\Router;

/**
 * Description of Route
 *
 * @author Maurice Prosper <maurice.prosper@ttu.edu>
 */
class SlimRoute extends \Slim\Route {
	/**
	 * Better formatting for regex conditions
	 * supports conditions for parameter paths
	 * 
	 * @param string $m
	 * @return string regex
	 */
	protected function matchesCallback($m) {
		$this->paramNames[] = $m[1];
		$regex = '[^/]+'; // default
		
		// group
        if (substr($m[0], -1) === '+') {
            $this->paramNamesPath[$m[1]] = 1;
			$regex = '.+';
			
			// with condition
			if (isset($this->conditions[$m[1]]))
				$regex = sprintf('((%s)/?)+', $this->conditions[$m[1]]);
        }
		
		// condition
		elseif (isset($this->conditions[$m[1]]))
            $regex = $this->conditions[$m[1]];
		
        return sprintf('(?P<%s>%s)', $m[1], $regex);
	}
	
	/**
	 * Now Decodes string before conditions
	 * {@inherited}
	 */
	public function matches($resourceUri) {
		return parent::matches( urldecode($resourceUri) );
	}
}
