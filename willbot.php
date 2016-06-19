<?php
header('Content-type: application/json');
$input = urldecode($_POST["i"]);
//$input = "i think i";
error_reporting(E_ALL);
ini_set('display_errors', 'on');

$WORD_LIMIT = 30;
$ORDER = 2;

$m = new MongoClient();
$db = $m->selectDB("willbot");
$chains = $db->selectCollection('chains');
$collectionCount = $chains->count();

function getwords($key){
	global $m, $db, $chains;
	$query = array('key' => $key);
	$result = $chains->findOne($query);
	
	//if no key matches, return a random entry
	if($result == null){
		return null;
	}
	
	//pick a random word from the word list to return
	$wordcount = count($result['words']);
	$randomnum = rand(0, $wordcount-1);
	return $result['words'][$randomnum];
}

function keyexists($key){
	global $m, $db, $chains;
	$query = array('key' => $key);
	$result = $chains->find($query);
	return $result->count() > 0;
}

function logwords($key, $words){
	global $m, $db, $chains;
	$criteria = array("key" => $key);
	if(keyexists($key)){

		$addtoset = array('$addToSet' => array("words" => $words[0]));
		$chains->update($criteria, $addtoset);
	}
	else{
		$set = array('$set' => array("key" => $key, "words" => $words));
		$chains->update($criteria, $set, array("upsert" => true));
	}
}

function augmentbrain($input){
	global $ORDER;
	$inputwords = explode(" ", $input);
	$wordcount = count($inputwords);
	
	$keyarray = array();
	
	for($i = 0; $i< $wordcount - $ORDER; $i++){
		$key = array_slice($inputwords, $i, 2);
		$words = array_slice($inputwords, $i + $ORDER, 1);
		logwords($key, $words);
		array_push($keyarray, $key);
	}
	
	return $keyarray;
}

//sanitize our input a bit
$input = strtolower($input);
$input = preg_replace("#[[:punct:]]#", "", $input);

//add these words to the lexicon
$keyarray = augmentbrain($input);
//split the input into individual words
$input_words = explode(' ', $input);
$input_count = count($input_words);


$bestphrase = "";
foreach($keyarray as $key){
	$nextkey = $key;
	$phrase = array($key[0], $key[1]);
	for($i = $WORD_LIMIT; $i > 0; $i--){
		$next_word = getwords($nextkey);
		if($next_word != null){
			array_push($phrase, $next_word);
			$nextkey = array_slice($phrase,-2,2,false);
		}
		else{
			//if we didn't find a next word, use what we got and save it off
			//if it's longer than the best phrase so far
			$newphrase = join(" ", $phrase);
			
			if(strlen($newphrase) > $bestphrase){
				$bestphrase = $newphrase;
			}
			break;
		}
	}
	//this assumes we found enough words up the to the word limit
	$newphrase = join(" ", $phrase);
	if(strlen($newphrase) > $bestphrase){
		$bestphrase = $newphrase;
	}
}
echo json_encode(array('response' => $bestphrase));
?>