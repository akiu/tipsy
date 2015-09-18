<?php

namespace Tipsy;

class View {
	private $_layout = 'layout';
	private $_headers;
	private $_rendering = false;
	private $_stack;
	private $_path = '';
	private $_tipsy;
	private $_filters = [];
	private $_extension = '.phtml';

	public function __construct ($args = []) {
		$this->headers = [];

		$this->config($args);
		
		$this->_tipsy = $args['tipsy'];
		$this->_scope = $scope;
	}
	
	public function config($args = null) {
		if (isset($args['layout'])) {
			$this->_layout = $args['layout'];
		}
		
		if (isset($args['stack'])) {
			$this->_stack = $args['stack'];
		}
		
		if (isset($args['path'])) {
			$this->_path = $args['path'];
		}
	}
	
	public function stack() {
		$stack = $this->tipsy()->config()['view']['stack'];
		if (!$stack) {
			$stack = [''];
		}
		return $stack;
	}
	
	public function mtime($file) {
		return filemtime($this->file($file));
	}
	
	public function file($src) {
		$stack = $this->stack();

		// absolute path
		if ($src{0} == '/' && file_exists($src)) {
			return $src;
		}

		foreach ($stack as $dir) {
			$path = joinPaths($this->_path, $dir, $src.$this->_extension);
			if (file_exists($path) && is_file($path)) {
				$file = $path;
				break;
			}
			$path = joinPaths($this->_path, $dir, $src);
			if (file_exists($path) && is_file($path)) {
				$file = $path;
				break;
			}
		}

		return $file;
	}
	
	public function layout() {
		return $this->file($this->_layout);
	}

	public function render($view, $params = null) {
		if (isset($params['set'])) {
			foreach ($params['set'] as $key => $value) {
				$$key = $value;
			}
		}

		$file = $this->file($view);
		if (!$file) {
			throw new Exception('Could not find view file: "'.$view.'" in "'.(implode(',',$this->stack())).'"');
		}
		$layout = $this->layout();
		

		$p = $this->scope()->properties();

		extract($this->scope()->properties(), EXTR_REFS);
		
		$difVars = get_defined_vars();

		$include = function($view, $scope) use ($difVars, $p) {
			$use = [];

			foreach ($scope as $k => $var) {
				if ($scope[$k] != $difVars[$k] && !in_array($k, ['Request', 'difVars', 'include'])) {
					$use[$k] = $var;
				}
			}

			return $this->render($view, ['set' => $use]);
		};
		
		// @todo: add all the other services
		$Request = $this->tipsy()->request();

		if ($this->_rendering || !isset($params['display'])) {
			
			ob_start();
			include($file);
			$page = $this->filter(ob_get_contents(),$params);
			ob_end_clean();
			
		} else {

			$this->_rendering = true;
			ob_start();
			include($file);
			$this->content = $this->filter(ob_get_contents(),$params);
			ob_end_clean();
			
			if ($layout) {
				ob_start();
				include($layout);
				$page = $this->filter(ob_get_contents(),$params);
				ob_end_clean();
				$this->_rendering = false;
			} else {
				$page = $this->content;
			}
		}		
		
		if (isset($params['var'])) {
			$this->{$params['var']} = $page;
		}
		return $page;	
	}

	public function display($view,$params=null) {
	/*
		if (!headers_sent()) {
			foreach ($this->headers->http as $key => $value) {
				header(isset($value['name']) ? $value['name'].': ' : '' . $value['value'],isset($value['replace']) ? $value['replace'] : true);
			}
		}
		*/
		if (is_null($params)) {
			$params['display'] = true;
		}
		echo $this->render($view,$params);
	}

	public function filter($content) {
		foreach ($this->_filters as $filter) {
			$content = $filter::filter($content);
		}
		return $content;
	}
	
	public function tipsy() {
		return $this->_tipsy;
	}
	
	public function scope(&$scope = null) {
		if ($scope) {
			$this->_scope = $scope;
		}
		return $this->_scope;
	}
}