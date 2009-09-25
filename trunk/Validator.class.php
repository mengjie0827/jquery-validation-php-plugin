<?php
/**
 * Validates input arrays (such as POST)
 *
 * Copyright (c) 2009 Yes2web - Internet Solutions Permission is hereby granted, free of charge, to any 
 * person obtaining a copy of this software and associated documentation files (the "Software"), to deal 
 * in the Software without restriction, including without limitation the rights to use, copy, modify, merge, 
 * publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the 
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial 
 * portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT 
 * LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN 
 * NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * @license MIT
 * @package validator
 */

/**
 * Validator class
 * 
 * Main class for validation, based on and influenced by the jQuery validation plugin available 
 * at http://bassistance.de/jquery-plugins/jquery-plugin-validation/.
 *
 * @package validator
 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
 */
class Validator {
	/**
	 * Collection of validation methods
	 * @var ValidationMethodCollection
	 */
	protected $oMethodCollection;
	/**
	 * List of lists of rules associated with fields
	 * @param String[][] [field_name => [rule_name => callback]] 
	 */
	protected $asClassRule = array();
	/**
	 * List of messages associated with rules.
	 * Set in construct, extendMessages
	 * 
	 * @see Validator->__construct()
	 * @see Validator->extendMessages()
	 * @see Validator->getMessage()
	 * @var String[] [rule_name => message]
	 */
	protected $asMessage = array(
							'required' => 'This is a required field',
							'required' => 'This field is required.',
							'remote' => 'Please fix this field.',
							'email' => 'Please enter a valid email address.',
							'url' => 'Please enter a valid URL.',
							'date' => 'Please enter a valid date.',
							'dateISO' => 'Please enter a valid date (ISO).',
							'dateDE' => 'Bitte geben Sie ein gŸltiges Datum ein.',
							'number' => 'Please enter a valid number.',
							'numberDE' => 'Bitte geben Sie eine Nummer ein.',
							'digits' => 'Please enter only digits',
							'creditcard' => 'Please enter a valid credit card number.',
							'equalTo' => 'Please enter the same value again.',
							'accept' => 'Please enter a value with a valid extension.',
							'maxlength' => 'Please enter no more than {0} characters.',
							'minlength' => 'Please enter at least {0} characters.',
							'rangelength' => 'Please enter a value between {0} and {1} characters long.',
							'range' => 'Please enter a value between {0} and {1}.',
							'max' => 'Please enter a value less than or equal to {0}.',
							'min' => 'Please enter a value greater than or equal to {0}.'
						);
	/**
	 * List of values provided for validation (last call to Validator->validate())
	 * @see Validator->validate()
	 * @var String[] [field_name => value]
	 */
	protected $asValue = array();

	/**
	 * CSS-class attached to Validator->sErrorElement when calling Validator->showError().
	 * Set using options in constructor
	 * 
	 * @see Validator->__construct()
	 * @see Validator->showError()
	 * @var String class name for CSS (default "error") 
	 */
	protected $sErrorClass = 'error';
	/**
	 * HTML element name to wrap error messages in when calling Validator->showError().
	 * Set using options in constructor
	 * 
	 * @see Validator->__construct()
	 * @see Validator->showError()
	 * @var String HTML element name (default "label")
	 */
	protected $sErrorElement = 'label';
	/**
	 * List of messages collected for those fields where the value did not validate.
	 * Messages are taken from the Validator->asErrorMessage
	 * @var String[] [field_name => error_message]
	 */
	protected $asErrorMessage =  null;
	/**
	 * Function to call when validation fails, e.g.
	 * function invalid_callback(array $asPostData, Validator $oValidator){
	 * 		header('Location: '.$_SERVER['HTTP_REFERER'].'?numError='.$oValidator->numberOfInvalids());
	 * 		die('headered to form after validation fail.');
	 * }
	 * (Optional) part of the options provided to the constructor.
	 * 
	 * @see Validator->__construct()
	 * @see Validator->validate()
	 * @param Callable (String) Name of the callable function.
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
	 * 
	 * Also available:
	 * 	errorClass: CSS class to put on errorElement when showing the error message in a form (default "error")
	 * 	errorElement: HTML element name to wrap the error message in (default "label")
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
 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
 */
class ValidatorMethodCollection extends ArrayObject {
	/**
	 * Called, from Validator::validate. $sName is the name of the rule, specified by
	 * the first paramater in Validat::addMethod ($sName). The second parameter is the
	 * list of arguments provided to the method: the first is the value of the field to
	 * be validated, the second is an [optional] configuration parameters specified when 
	 * adding rules to a class of fields (actually: a list of fields)
	 * 
 	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
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
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @param String $sSeparator (Optional) day/month/year separator, defaults to '/'
	 * @return bool Is the provided value a valid date
	 */
	public function date($sValue, $sSeparator = '/'){
		return preg_match('~[0-3][0-9]'.$sSeparator.'[0-3][0-9]'.$sSeparator.'[0-9]{4}~', $sValue) === 1;
	}
	/**
	 * Is the value composed solely of digits? \
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value valid 
	 */
	public function digits($sValue){
		return preg_match('~^[0-9]+$~', $sValue) === 1;
	}

	/**
	 * Is the value an email address.
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid email address
	 */
	public function email($sValue, $mOption){
		return preg_match('/^[a-z0-9!#$%&*+=?^_`{|}~-]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*@([-a-z0-9]+\.)+([a-z]{2,3}|info|arpa|aero|coop|name|museum)$/i', $sValue) === 1; 
	}
	
	/**
	 * Is the provided value equal to some other value.
	 * This other value must be accessible in the $_POST array, so if you want to use the equalTo validation,
	 * POST your forms. 
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @param String $sEqualTo Name of field this value should equal
	 * @return bool Is the provided value a valid 
	 */
	public function equalTo($sValue, $sEqualTo){
		if ($sEqualTo{0} === '#'){
			$sEqualTo = substr($sEqualTo, 1);
		}
		return strcmp($sValue, $_POST[$sEqualTo]) === 0;
	}
	/**
	 * "Makes the element require a given maximum."
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param float $fValue value to validate
	 * @param float $fMax Maximum value of the provided value
	 * @return bool Is the provided value a valid 
	 */
	public function max($fValue, $fMax){
		return is_numeric($fValue) && $fValue >= $fMax;
	}
	/**
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @param int $iLength the maximal length of the provided String
	 * @return bool Is the provided value a valid 
	 */
	public function maxlength($sValue, $iLength){
		return isset($sValue{$iLength}) === false;
	}
	/**
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
	 */
	public function minlength($sValue, $iLength){
		return isset($sValue{--$iLength}); # pre-decrement: zero-indexed char-array
	}
	/**
	 * "Makes the element require a given minimum."
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
	 */
	public function min($sValue, $iMin){
		return is_numeric($sValue) && $sValue <= $iMin;
	}
	/**
	 * Makes the element require a given value range.
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
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
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
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
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
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
	
	/**
	 * 
	 * @author G.J.C. van Ahee <van.ahee@yes2web.nl>
	 * @param String $sValue value to validate
	 * @return bool Is the provided value a valid 
	 */
	public function url($sValue){
# TODO: insert URL regex
		return preg_match('/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*@([-a-z0-9]+\.)+([a-z]{2,3}|info|arpa|aero|coop|name|museum)$/i', $sValue) === 1; 
	}
}
?>