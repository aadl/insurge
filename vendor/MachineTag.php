<?php

/*

http://www.aaronland.info/php/machinetag/
Copyright (c) 2007 Aaron Straup Cope.

This is free software, you may use it and distribute it under the
same terms as Perl itself.

$Id: MachineTag.php,v 1.1 2007/08/15 02:25:27 asc Exp $

*/

class MachineTag {

		function MachineTag($raw){
			$this->_raw = $raw;
			$this->_mt = 0;

			if (preg_match("/^([a-z](?:[a-z0-9_]+))\:([a-z](?:[a-z0-9_]+))\=(.*)/i", $raw, $m)){

				$this->_namespace = $m[1];
				$this->_predicate = $m[2];
				$this->_value = $m[3];
				$this->_mt = 1;
				return 1;
			}

		}
		
		function is_machinetag(){
			return $this->_mt;
		}
		
		function namespace(){
			return $this->_namespace;
		}

		function predicate(){
			return $this->_predicate;
		}

		function value(){
			return $this->_value;
		}

		function raw(){
			return $this->_raw;
		}
}

?>