<?php
/**
 * Validates input arrays (such as POST)
 *
 * @package validator
 */
class Validator {
	protected $oMethodCollection;
	protected $asClassRule = array();
	protected $asMessage = array('required' => 'This is a required field');
	protected $asValue = array();

	protected $sErrorClass = 'error';
	protected $sErrorElement = 'label';
	
	
	protected $asErrorMessage =  null;
	/**
	 * Function to call when validation fails, e.g.
	 * function invalid_callback(array $asPostData, Validator $oValidator){
	 * 		header('Location: '.$_SERVER['HTTP_REFERER'].'?numError='.$oValidator->numberOfInvalids());
	 * 		die('headered to form after validation fail.');
	 * }
	 * Part of the options provided to the constructor.
	 * @param String Name of the callable function.
	 */
	protected $sInvalidHandler = null;
	
	/**
	 * Options (optional) to supply to the validator
	 * Options are similar to the options provided to the jquery validator plugin. Currently
	 * only "rules" en "messages" are supported. Specifying these into an array will easily
	 * port to javascript, allowing easy integration of server and client sides. See below for 
	 * examples.
	 * 
	 * Rules:
	 * Key/value pairs defining custom rules. Key is the name of an element (or 
	 * a group of checkboxes/radio buttons) and should be available as the key in the array 
	 * to be validated, e.g. $_POST['name'] or $db_row['name'], value is an object consisting of 
	 * rule/parameter pairs or a plain String. Can be combined with class/attribute/metadata 
	 * rules. Each rule can be specified as having a depends-property to apply the rule only 
	 * in certain conditions. See the second example below for details.
	 * 
	 * The jquery validator plugin allows keys to point to functions. This is not supporten in the 
	 * php, as a function is not valid JSON and therefore cannot easily be ported between javascript
	 * and php. 
	 * 
	 * Providing rules (taken from an example in the jquery validator documentation) and encoding
	 * them as a php array:
	 * $asOption = array(
	 * 				'rules' => array(
	 * 					'name' => 'required',
	 * 					'email' => array(
	 * 						'required' => true,
	 * 						'email' => true
	 * 						)
	 * 					)
	 * 				);
	 * will translate easily into jquery validator options using php's json_encode:
	 * {
	 * 	"rules": {
	 * 		"name": "required",
	 * 		"email": {
	 * 	  		"required": true,
	 * 	 		"email": true
	 *  	}
	 * 	}
	 * }
	 * Example usage:
	 *	$().ready(function() {
	 *		$("form").validate(<?php echo json_encode($asValidatorOption); ?>);
	 *	});
	 * 
	 * Whereas the javascript can detect classnames and element-id, the php version
	 * obvious cannot. To integrate client and server as described, make sure all keys
	 * in the array refer to element-names, as these are the keys of the POST/GET array. 
	 */
	public function __construct(array $asOption = null){
		if (false === is_null($asOption)){
			if (isset($asOption['rules']) && 
				is_array($asOption['rules'])){
				foreach ($asOption['rules'] as $sField => $mRule){
					$this->addClassRules($mRule, $sField);
				}
			}
			if (isset($asOption['messages']) && 
				is_array($asOption['messages'])){
				$this->extendMessages($asOption['messages']);
			}
			if (isset($asOption['invalidHandler'])){
				if (false === is_callable($asOption['invalidHandler'])){
					throw new Exception('Invalid option set for "invalidHandler: not callable"');
				}
				$this->sInvalidHandler = $asOption['invalidHandler'];
			}
			if (isset($asOption['errorClass'])){
				$this->sErrorClass = $asOption['errorClass'];
			}
			if (isset($asOption['errorElement'])){
				$this->sErrorElement = $asOption['errorElement'];
			}
		}
		
		$this->oMethodCollection = new ValidatorMethodCollection(new ArrayIterator);
	}
	
	/**
	 * @param String $sName name of method
	 * @param String $sFunctionHandle Unique name of (global) function, e.g. from create_function
	 * @param String $sMessage Custom message delivered with failing field
	 */
	public function addMethod($sName, $sFunctionHandle, $sMessage = null){
		$this->oMethodCollection[$sName] = $sFunctionHandle;
		$this->asMessage[$sName] = $sMessage;
	}
	
	/**
	 * @param String[][] $asRule [rule_name => [param_name => value]]
	 * @param String $sField field name, ... 
	 */
	public function addClassRules($asRule, $sField){
		if (false === is_array($asRule)){
			$asRule = array($asRule => array());
		}
		$asArgv = func_get_args();
		for ($i = 1; $i < count($asArgv); ++$i){
			if (false === isset($this->asClassRule[$asArgv[$i]])){
				$this->asClassRule[$asArgv[$i]] = array();
			}
			# overwrite with newest values
			$this->asClassRule[$asArgv[$i]] = $asRule + $this->asClassRule[$asArgv[$i]];
		}
	}
	
	/**
	 * Validate a single element.
	 * @param String $sField Name of the element
	 * @param Mixed $mValue Value provided with the element
	 * @return bool is the elemnent valid
	 * @see Validator->validate()
	 */
	public function element($sField, $mValue){
		if (false === is_array($this->asErrorMessage)){
			$this->asErrorMessage = array();
		}
		# if there is a rule
		if (isset($this->asClassRule[$sField])){
			# apply all until one fails
			foreach ($this->asClassRule[$sField] as $sRule => $asRuleParam){
				if (false === (	# iff empty AND optional: don't check
						empty($mValue) &&	# easier checked than next line
						$this->optional($sField) 
					) &&
					false === $this->oMethodCollection->{$sRule}($mValue, $asRuleParam)){
					$this->asErrorMessage[$sField] = $this->getMessage($sField, $sRule, $asRuleParam);
					return false; # validation failed
				}
			}
		} # there are no validation rules for this element
		# true if valid
		return true;
	}
	
	/**
	 * add messages to the internal messages list
	 */
	public function extendMessages(array $asMessage){
		$this->asMessage = $asMessage + $this->asMessage;
	}
	
	/**
	 * helper method to distill messages from the array of messages
	 * @return String the message
	 */
	private function getMessage($sField, $sRule, $asRuleParam){
		$sMessage = isset($this->asMessage[$sField]) &&
					is_array($this->asMessage[$sField]) && 
					isset($this->asMessage[$sField][$sRule])? 
						$this->asMessage[$sField][$sRule]:(
						isset($this->asMessage[$sField])?
							$this->asMessage[$sField]:
							$this->asMessage[$sRule]);
		return preg_replace('~{(\d+)}~e', '$asRuleParam[$1]', $sMessage);
	}

	/**
	 * @return int the number of invalid fields
	 */
	public function numberOfInvalids(){
		return count($this->asErrorMessage);
	}
	
	/**
	 * Check whether the field is optional. Something is optional when there are either:
	 * + no rules set for it [OR]
	 * + there are rules, but none require the field
	 * 
	 * When validating an element, 
	 * 
	 * @see Validator->element()
	 * @param String $sField field name
	 * @return bool Whether the provided field is optional
	 */
	public function optional($sField){
		return 	false === (
					isset($this->asClassRule[$sField]) &&
					isset($this->asClassRule[$sField]['required'])
				);
	}

	/**
	 * Show an error for a specific field (if it is wrong)
	 * Fromat is gerenated according to options provided to the cosntructor   
	 */
	public function showError($sField){
		if (isset($this->asErrorMessage[$sField])){
			$sPrefix = '<'.$this->sErrorElement.($this->sErrorElement === 'label'? ' ':' html');
			$sPostfix = '</'.$this->sErrorElement.'>';
			
			return $sPrefix.
				'for="'.$sField.'" generated="true" class="'. 
				$this->sErrorClass.
				'">'. 
				$this->asErrorMessage[$sField]. 
				$sPostfix;
		}
	}
	
	/**
	 * 
	 */
	public function showValid($sField){
		return 	false === isset($this->asErrorMessage[$sField]) &&
				isset($this->asValue[$sField])? 
					$this->asValue[$sField]:'';
	}
	
	/**
	 * Checks if the selected form is valid or if all selected elements are valid.
	 * Throws Exception when no call to validate() has been made.
	 * @return bool were the values valid 
	 * @throws Exception when no call to validate() has been made.
	 */
	public function valid(){
//	don't require validation first?
//		if (is_null($this->asErrorMessage)){
//			throw new Exception('Call to valid() without having validated any values');
//		}
		return is_null($this->asErrorMessage) || count($this->asErrorMessage) === 0;
	}
	
	/**
	 * @param String[] $asValue [field =>value]
	 */
	public function validate(array $asValue){
		$this->asValue = $asValue;
		$this->asErrorMessage = array(); # reset error messages
		# loop through all fields in the provided list; not in the list -> not validated (unless required)
		foreach ($asValue as $sField => $sValue){
			$this->element($sField, $sValue);
		}
		# look for required fields that were not there  
		foreach ($this->asClassRule as $sField => $asRules){
			if (false === isset($asValue[$sField]) &&	# this field was not validatied, as it was not present in the list 
				isset($asRules['required'])){ 			# But is was REQUIRED ! !
				$this->asErrorMessage[$sField] = $this->getMessage($sField, 'required', $asRules['required']);
			}
		}
		if (is_callable($this->sInvalidHandler)){
			$sFunc = $this->sInvalidHandler;
			$sFunc($asValue, $this);
		}
		return $this->asErrorMessage;
	}
}
/**
 * Contains all validation methods/rules
 *
 * @package validator
 */
class ValidatorMethodCollection extends ArrayObject {
	/**
	 * Called, from Validator::validate. $sName is the name of the rule, specified by
	 * the first paramater in Validat::addMethod ($sName). The second parameter is the
	 * list of arguments provided to the method: the first is the value of the field to
	 * be validated, the second is an [optional] configuration parameters specified when 
	 * adding rules to a class of fields (actually: a list of fields)
	 * 
	 * @param String $sName name of the rule to be called
	 * @param String[] $asParam array containing the value of the field of to be validated and an array of options
	 */
	public function __call($sName, array $asParam){
		if (isset($this[$sName])){
			return $this[$sName](array_shift($asParam), array_shift($asParam)); # sValue, asRuleParam
		}
		# no validation set...
		else{
			return true;
		}
	}
	/**
	 * Makes the element require a date.
	 * Return true, if the value is a valid date. 
	 * Checks for ##-##-####, with an optional separator (here '-').
	 * No sanity checks, only the format must be valid, not the actual date, eg 39/39/2008 
	 * is a valid date. Month/day values may range from 00 to 39 due to the order of these 
	 * fields used by different locales.
	 */
	public function date($sValue, $sSeparator = '/'){
		return preg_match('~[0-3][0-9]'.$sSeparator.'[0-3][0-9]'.$sSeparator.'[0-9]{4}~', $sValue) === 1;
	}
	public function digits($sValue){
		return preg_match('~^[0-9]+$~', $sValue) === 1;
	}

	public function email($sValue, $mOption){
		return preg_match('/^[a-z0-9!#$%&*+=?^_`{|}~-]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*@([-a-z0-9]+\.)+([a-z]{2,3}|info|arpa|aero|coop|name|museum)$/i', $sValue) === 1; 
	}
	public function equalTo($sValue, $sEqualTo){
		if ($sEqualTo{0} === '#'){
			$sEqualTo = substr($sEqualTo, 1);
		}
		return strcmp($sValue, $_POST[$sEqualTo]) === 0;
	}
	/**
	 * "Makes the element require a given maximum."
	 */
	public function max($sValue, $iMax){
		return is_numeric($sValue) && $sValue >= $iMax;
	}
	public function maxlength($sValue, $iLength){
		return isset($sValue{$iLength}) === false;
	}
	public function minlength($sValue, $iLength){
		return isset($sValue{--$iLength}); # pre-decrement: zero-indexed char-array
	}
	/**
	 * "Makes the element require a given minimum."
	 */
	public function min($sValue, $iMin){
		return is_numeric($sValue) && $sValue <= $iMin;
	}
	/**
	 * Makes the element require a given value range.
	 * @return bool
	 */
	public function range($sValue, array $asRange){
		return $this->rangelength(intval($sValue), $asRange);
	}
	
	/**
	 * "Makes the element require a given value range [inclusive].
	 *	Return false, if the element is
	 *	some kind of text input and its length is too short or too long
	 *	a set of checkboxes has not enough or too many boxes checked
	 *	a select and has not enough or too many options selected"
	 * 
	 * Works on checkboxes/multi-selects when they are provided as arrays, i.e.:
	 * 	<input type="checkbox" name="asChecker[]" value="1" />
	 * 	<input type="checkbox" name="asChecker[]" value="2" />
	 */
	public function rangelength($sValue, array $asRange){
		# checkbox, multi-select (iff provided as array)
		if (is_array($sValue)){ # list of checkboxes
			$iCount = count($sValue);
		}
		# number
		elseif (is_numeric($sValue)){
			$iCount = $sValue;
		}
		# text value
		else {
			$iCount = strlen($sValue);
		}
		return $iCount >= $asRange[0] && $iCount <= $asRange[1];
	}
	
	/**
	 * Required: 
	 * [quotes taken from jquery validator docs: Validation > Methods > required]
	 * 			"Return false if the element is empty (text input) or unchecked (radio/checkbxo) or nothing selected (select)."
	 * 1) no additional argument: 
	 * 		is a value set?
	 * 			"Makes the element always required."
	 * 2) a String argument:
	 * 		The requirement depends on another element. To specify the "depending" element, only id's are supported, i.e. selectors
	 * 		of the form "#element-id" and the like (e.g. "element-id:checked" etc).
	 * 		It is assumed that the element-id and the name-attribute are the same, making the element-id the key in the POST-array.
	 * 			"Makes the element required, depending on the result of the given expression." 
	 * 3) a function argument:
	 * 		The field is only required when the provided function returns false, no arguments will be presented.
	 * 			"Makes the element required, depending on the result of the given callback."
	 */
	public function required($sValue, $mParam = null){
		if (false === is_null($mParam)){
			# a selector pointing to a specific checkbox-element: only required when checked (i.e. in the POST-array)
			if (is_string($mParam)){
				$asMatch = array();
				preg_match('~^#([^:\[]*)~', $mParam, $asMatch);
															# NOT required iff:
				if (false === isset($_POST[$asMatch[1]]) ||	# 	the field is not there (i.e. not checked)
					false === empty($_POST[$asMatch[1]])){	# 	the field is empty (i.e. select/input)
					return true;							# not required means valid!
				}
			}
			# function provided: only required when function returns false
			elseif (is_callable($mParam) && $mParam() === false){ 	# iff true, it IS required
				return true;										# so in this case: it's not
			}
		} # no additional parameter: just be there
		$sTrimmedValue = trim($sValue);
		return false === empty($sTrimmedValue);
	}
	
	public function url($sValue){
# TODO: insert URL regex
		return preg_match('/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*@([-a-z0-9]+\.)+([a-z]{2,3}|info|arpa|aero|coop|name|museum)$/i', $sValue) === 1; 
	}
}
?>