<?php

	class Obfuscator {

		public $strip=true;		// Strip comments and whitespace
		public $strip_comments=true;	// Strip comments (Automatically enabled when using $strip).
		public $b64=true;		// Base64 passover

		private $_globals=array();	// Global variables
		private $_classes=array();	// Class specific variables
		private $_functions=array();	// Function variables
		private $_vars=array();		// To keep up with various variables.
		private $code=null;
		private $tokens=null;
		private $class=false;
		private $function=false;
		private $depth=0;		// Keep track of how deep in curly brackets we are, so we can unset $class and $function when needed.
		private $algos;
		private $skip_variables=array('$_GET','$_POST','$_REQUIRE','$_SERVER','$_ENV','$_SESSION','$_FILES');

		public function __construct() {
			if (!defined('T_ML_COMMENT')) define('T_ML_COMMENT',T_COMMENT);
			$this->algos=hash_algos();
			return $this;
		}

		public function file($file) {
			if (file_exists($file)) $this->code=file_get_contents($file);
			return $this->tokenize();
		}

		public function code($text=null) {
			if (empty($text)) return $this->code;
			$this->code=$text;
			return $this->tokenize();
		}

		public function save($file) {
			if (!empty($this->code)) if (@file_put_contents($file,$this->code)) return true;
			return false;
		}

		private function random_string() {
			$number=round((mt_rand(1,mt_rand(1000,10000))*mt_rand(1,10))/mt_rand(1,10));
			if (!empty($this->algos)) $algo=$this->algos[mt_rand(0,(count($this->algos)-1))];
			$hash=hash($algo,$number);
			return $hash;
		}

		private function encode($tmp) {
			if ($this->strip) $tmp=preg_replace('/[\n\t\s]+/',' ',$tmp);
			$tmp=preg_replace('/^\<\?(php)*/','',$tmp);
			$tmp=preg_replace('/\?\>$/','',$tmp);
			$tmp=str_replace(array('\"','$','"'),array('\\\"','\$','\"'),$tmp);
			$tmp=trim($tmp);
			if ($this->b64) {
				$tmp=base64_encode("$tmp");
				$tmp="<?php \$code=base64_decode(\"$tmp\"); eval(\"return eval(\\\"\$code\\\");\") ?>\n";
			} else $tmp="<?php eval(eval(\"$tmp\")); ?>\n";
			$this->code=$tmp;
		}

		private function encode_string($text) {
			for ($i=0;$i<=strlen($text)-1;$i++) {
				$chr=ord(substr($text,$i,1));
				if ($chr==32||$chr==34||$chr==39) $tmp[]=chr($chr); // Space, leave it alone.
				elseif ($chr==92&&preg_match('/\\\(n|t|r|s)/',substr($text,$i,2))) {
					// New line, leave it alone, and add the next char with it.
					$tmp[]=substr($text,$i,2);
					$i++; // Skip the next character.
				}
				else $tmp[]='\x'.strtoupper(base_convert($chr,10,16));
			}
			if (!empty($tmp)) $text=implode('',$tmp);
			return $text;
		}

		private function generate_var($var,$function=null,$class=null) {
			while (empty($string)) {
				$string="\$_{$this->random_string()}";
				if (empty($function)&&empty($class)) {
					if (!empty($this->_globals[$var])) return $this->_globals[$var];
					else {
						if (in_array($string,$this->_globals)) $string=null;
						else $this->_globals[$var]=$string;
					}
				}
				elseif (!empty($function)&&empty($class)) {
					if (!empty($this->_functions[$var])) return $this->_functions[$var];
					else {
						if (in_array($string,$this->_functions)) $string=null;
						else $this->_functions[$var]=$string;
					}
				}
				elseif (!empty($function)&&!empty($class)) {
					if (!empty($this->_classes[$class]['functions'][$function][$var])) return $this->_classes[$class]['functions'][$function][$var];
					else {
						if (!empty($this->_classes[$class]['functions'][$function])&&in_array($string,$this->_classes[$class]['functions'][$function])) $string=null;
						else $this->_classes[$class]['functions'][$function][$var]=$string;
					}
				}
				elseif (empty($function)&&!empty($class)) {
					if (!empty($this->_classes[$class]['globals'][$var])) return $this->_classes[$class]['globals'][$var];
					else {
						if (!empty($this->_classes[$class]['globals'])&&in_array($string,$this->_classes[$class]['globals'])) $string=null;
						else $this->_classes[$class]['globals'][$var]=$string;
					}
				}
			}
			return $string;
		}

		public function pack() {
			if (empty($this->tokens)) return false;
			foreach ($this->tokens as $token_key=>&$token) {
				if (is_array($token)) {
					switch ($token[0]) {
						case T_FUNCTION:
							if ($this->tokens[$token_key-2][0]==T_VARIABLE) $this->function=$this->tokens[$token_key-2][1];
							elseif ($this->tokens[$token_key+2][0]==T_STRING) $this->function=$this->tokens[$token_key+2][1];
							break;
						case T_CLASS:
							$this->class=$this->tokens[$token_key+2][1];
							break;
						case T_VARIABLE:
							if ($token[1]=='$this') break; // Absolutely skip $this.
							if (in_array($token[1],$this->skip_variables)) {
								// Skip renaming anything that should be ignored, but encode it so that it's not in plaintext.
								$token[1]="\${$this->encode_string(substr($token[1],1))}";
								break;
							}
							if (!empty($this->tokens[$token_key-1][1])&&$this->tokens[$token_key-1][0]==T_DOUBLE_COLON) break; // Static class variable. Don't touch it.
							if (!empty($this->tokens[$token_key-2][1])&&$this->tokens[$token_key-2][0]==T_GLOBAL) {
								if ($this->function)
									if ($this->class) $token[1]=$this->_vars['classes'][$this->class][$this->function][$token[1]]=$this->generate_var($token[1]);
									else $token[1]=$this->_vars['functions'][$this->function][$token[1]]=$this->generate_var($token[1]);
								elseif ($this->class) die("\nPHP syntax error found. Exiting.\n");
							}
							elseif ($this->function) {
								if ($this->class) {
									if (!empty($this->_vars['classes'][$this->class][$this->function][$token[1]])) $token[1]=$this->_vars['classes'][$this->class][$this->function][$token[1]];
									else $token[1]=$this->generate_var($token[1],$this->function,$this->class);
								}
								else {
									if (!empty($this->_vars['functions'][$this->function][$token[1]])) $token[1]=$this->_vars['functions'][$this->function][$token[1]];
									else $token[1]=$this->generate_var($token[1],$this->function);
								}
							}
							elseif ($this->class) {
								$token[1]=$this->generate_var($token[1],null,$this->class);
							}
							else {
								$token[1]=$this->generate_var($token[1]);
							}
							break;
						case T_OBJECT_OPERATOR:
							if ($this->tokens[$token_key-1][1]=='$this'&&$this->function&&$this->class) {
								$this->tokens[$token_key-1][1]='$'.$this->encode_string('this');
								if ($this->tokens[$token_key+2]=='('); // Function, encode $this and leave it alone.
								else $this->tokens[$token_key+1][1]=substr($this->generate_var("\${$this->tokens[$token_key+1][1]}",null,$this->class),1);
							} else die("\nPHP syntax error found: \$this referenced outside of a class.\n");
							break;
						case T_DOUBLE_COLON:
							if ($this->tokens[$token_key-1][1]=='$this') {
								if ($this->function&&$this->class) {
									$this->tokens[$token_key-1][1]='$'.$this->encode_string('this');
									if ($this->tokens[$token_key+2]=='('); // Function, leave it alone.
									else $this->tokens[$token_key+1][1]=$this->generate_var($this->tokens[$token_key+1][1],null,$this->class);
								} else die("\nPHP syntax error found: \$this referenced outside of a class.\n");
							} else {
								if ($this->tokens[$token_key+2]=='('); // Function, leave it alone.
								else $this->tokens[$token_key+1][1]=$this->generate_var($this->tokens[$token_key+1][1],null,$this->tokens[$token_key-1][1]);
							}
							break;
						case T_COMMENT:
						case T_DOC_COMMENT:
						case T_ML_COMMENT: // Will be equal to T_COMMENT if not in PHP 4.
							if ($this->strip_comments||$this->strip) $token[1]=''; 
							break;
						case T_START_HEREDOC:
							// Automatically turn whitespace stripping off, because formatting needs to stay the same.
							$this->strip=false;
							break;
						case T_END_HEREDOC:
							$token[1]="\n{$token[1]}";
							break;
						case T_CURLY_OPEN:
						case T_DOLLAR_OPEN_CURLY_BRACES:
						case T_STRING_VARNAME:
							if ($this->function) $this->depth++;
							break;
					}
				} else {
					switch ($token) {
						case '{':
							if ($this->function) $this->depth++;
							break;
						case '}':
							$this->depth--;
							if ($this->depth<0) $this->depth=0;
							if ($this->function&&$this->depth==0) {
								$_functions=array(); // Empty function variables array
								$this->_vars['functions']=array(); // Empty any temp variables
								$this->function=false;
							}
							elseif ($this->class&&$this->depth==0) {
								$this->_vars['classes']=array(); // Empty any temp variables
								$this->class=false;
							}
							break;
					}
				}
			}
			$this->detokenize();
			return $this;
		}

		private function tokenize() {
			if (empty($this->code)) return false;
			$this->tokens=token_get_all($this->code);
			return $this;
		}

		private function detokenize() {
			if (empty($this->tokens)) return; // No tokens to parse. Exit.
			foreach ($this->tokens as &$token) {
				if (is_array($token)) {
					switch ($token[0]) {
						// Looks like overkill, but helpful when extending to encode certain things differently.
						case T_INCLUDE:
						case T_INCLUDE_ONCE:
						case T_REQUIRE:
						case T_REQUIRE_ONCE:
						case T_STATIC:
						case T_PUBLIC:
						case T_PRIVATE:
						case T_PROTECTED:
						case T_FUNCTION:
						case T_CLASS:
						case T_EXTENDS:
						case T_GLOBAL:
						case T_NEW:
						case T_ECHO:
						case T_DO:
						case T_WHILE:
						case T_SWITCH:
						case T_CASE:
						case T_BREAK:
						case T_CONTINUE:
						case T_ENDSWITCH:
						case T_CONST:
						case T_DECLARE:
						case T_ENDDECLARE:
						case T_FOR:
						case T_ENDFOR:
						case T_FOREACH:
						case T_ENDFOREACH:
						case T_IF:
						case T_ENDIF:
						case T_RETURN:
						case T_UNSET:
						case T_EXIT:
						case T_VAR:
						case T_STRING:
						case T_ENCAPSED_AND_WHITESPACE:
						case T_CONSTANT_ENCAPSED_STRING:
							$token[1]=$this->encode_string($token[1]);
							break;
					}
					$tmp[]=$token[1];
				}
				else $tmp[]=$token;
			}
			$tmp=implode('',$tmp);
			$this->encode($tmp);
		}
	
	}

?>
