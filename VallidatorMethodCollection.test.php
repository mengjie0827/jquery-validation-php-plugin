<?php
require 'Validator.class.php';

/**
 * Test the methods for the ValidatorMethodCollection
 * 
 * Setup of this document:
 * 	each method of the collection gets its own values to test,
 * 	these are in the $asValue array, which is a key-value store
 * 	containing [[pattern, params, bool]], where bool is the  
 * 	expected outcome. 
 * 	Each method will then be tested with a range of values.
 */

$oValidatorCollection = new ValidatorMethodCollection();

function test($sFunction, $asValue){
	global $oValidatorCollection;
	
	echo '
	Testing method <strong>', $sFunction, '</strong>
	<ol>';
	
	foreach ($asValue as $amContext){
		$sThingy = '"'.format($amContext[0]).'" should '.($amContext[2]? '':'not ').'pass '.$sFunction;
		if (false === is_null($amContext[1])){
			$sThingy .= ' '.format($amContext[1]);
		}
		$bResult = $amContext[2] === $oValidatorCollection->{$sFunction}($amContext[0], $amContext[1]);
		echo '
		<li>', $sThingy, ': ', ($bResult? 'pass':'FAIL'), '</li>';
	}
	echo '
	</ol>';
}

function format($mVal){
	if (is_array($mVal)){
		$mVal = '['.implode(', ', $mVal).']';
	}
	elseif (is_callable($mVal)){
		$mVal = 'callable function';
	}
	return $mVal;
}

//	public function __call($sName, array $asParam)

//	public function date($sValue, $sSeparator = '/')
test('date', array(
		array('39-39-2009', '-', true),
		array('12-12-2009', null, false),
		array('40-40-2009', '-', false),
		array('-1-12-2009', '-', false),
		array('2009/12/09', null, false),
		array('2/1/2009', null, false)
	)
);
//	public function email($sValue, $mOption)
test('email', array(
		# valids
		array('email@domain.com', null, true),
		array('firstname.lastname@domain.com', null, true),
		array('email@subdomain.domain.com', null, true),
		array('firstname+lastname@domain.com', null, true),
//		array('email@123.123.123.123', null, true), #who cares?
//		array('email@[123.123.123.123]', null, true), #who cares?
//		array('"email"@domain.com', null, true), #who cares?
		array('1234567890@domain.com', null, true),
		array('email@domain-one.com', null, true),
		array('_______@domain.com', null, true),
		array('email@domain.name', null, true),
		array('email@domain.com.au', null, true),
		array('firstname-lastname@domain.com', null, true),
		# invalids
		array('plainaddress', null, false),
		array('@domain.com', null, false),
		array('Joe Smith <email@domain.com>', null, false),
		array('email.domain.com', null, false),
		array('email@domain@domain.com', null, false),
		array('.email@domain.com', null, false),
		array('email.@domain.com', null, false),
//		array('email..email@domain.com', null, false), #who cares?
		array('@domain.com', null, false),
		array('email@domain.com (Joe Smith)', null, false),
		array('email@domain', null, false),
//		array('email@-domain.com', null, false), #who cares?
//		array('email@domain.web', null, false), #who cares?
		array('email@111.222.333.44444', null, false),
		array('email@domain..com', null, false)
	)
);
//	public function equalTo($sValue, $sEqualTo) # todo: test with post values
//	public function max($sValue, $iMax)
test('max', array(
		array(14, 12, false),
		array(14, 15, true),
		array(14, 14, true),
		array(-1, 12, true),
		array(-1, -2, false),
		array(-3, -2, true)
	)
);
//	public function minlength($sValue, $iLength)
test('minlength', array(
		array(array('aap', 'noot', 'mies'), 2, true),
		array(array('aap', 'noot', 'mies'), 3, true),
		array(array('aap', 'noot', 'mies'), 4, false),
		array(array('aap', 'noot', 'mies'), -1, false),
		array('aap', 2, true),
		array('aap', 3, true),
		array('aap', 4, false),
		array('aap', -1, false)
		
	)
);
//	public function min($sValue, $iMin)
test('min', array(
		array(14, 12, true),
		array(14, 15, false),
		array(14, 14, true),
		array(-1, 12, false),
		array(-1, -2, true),
		array(-3, -2, false)
	)
);
//	public function range($sValue, array $asRange)
test('range', array(
		array(0, array(1, 3), false),
		array(1, array(1, 3), true),
		array(2, array(1, 3), true),
		array(3, array(1, 3), true),
		array(4, array(1, 3), false)
	)
);
//	public function rangelength($sValue, array $asRange)
test('rangelength', array(
		array(array(), array(1, 3), false),
		array(array('aap'), array(1, 3), true),
		array(array('aap', 'noot'), array(1, 3), true),
		array(array('aap', 'noot', 'mies'), array(1, 3), true),
		array(array('aap', 'noot', 'mies', 'wim'), array(1, 3), false),
		array('', array(1, 3), false),
		array('n', array(1, 3), true),
		array('no', array(1, 3), true),
		array('noo', array(1, 3), true),
		array('noot', array(1, 3), false),
		array(0, array(1, 3), false),
		array(1, array(1, 3), true),
		array(2, array(1, 3), true),
		array(3, array(1, 3), true),
		array(4, array(1, 3), false)
	)
);
//	public function required($sValue, $mParam = null)
test('required', array( # todo: test with post values
		array('', null, false),
		array('aap', null, true),
		array(' ', null, false),
		array('aap', create_function('', 'return true;'), true),
		array('', create_function('', 'return true;'), false),
		array(' ', create_function('', 'return true;'), false),
		array('aap', create_function('', 'return false;'), true),
		array('', create_function('', 'return false;'), true),
		array(' ', create_function('', 'return false;'), true)
	)
);
//	public function url($sValue)
test('url', array(
		array('http://www.slashdot.org', null, true),
		array('http://www.slashdot.org:8080', null, true),
		array('https://www.slashdot.org', null, true),
		array('https://www.port.com:8080', null, true),
		array('http://www.slashdot.org/test/', null, true),
		array('http://double.topleveldomain.com.au', null, true),
		array('http://no-subdomain.org', null, true),
		array('http://yes.subdomain.org', null, true),
		array('http://double.yes.subdomain.org', null, true)
	)
);
?>