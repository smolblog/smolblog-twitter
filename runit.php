<?php

require_once __DIR__ . '/vendor/autoload.php';

use Coderjerk\BirdElephant\BirdElephant;

$credentials = array(
	// 'consumer_key' => xxxxxx,
	// 'consumer_secret' => xxxxxx,
	// 'bearer_token' => 'AAAAAAAAAAAAAAAAAAAAALVsdQEAAAAAaV0b%2Fio%2Fz9njgZ2Ynf49u01dxnw%3DOuCqbf7O2ECmyrZ50wS5lZSWXUvgYobJpeVqGfZT3fSLwjDhrB',
	'bearer_token' => 'RXV0U05Hcldsd2VrUkJ4YUFHYS11Q20wVWltUEJocDFtZS1oTkNQXzNSQWlJOjE2NjY1NzE1Njc2OTI6MToxOmF0OjE'
);

$client = new BirdElephant($credentials);
$user = $client->user('oddevan');

$allTweetOptions = 'attachments,conversation_id,created_at,entities,id,in_reply_to_user_id,referenced_tweets,source,text,withheld';

try {
print_r($user->tweets(['tweet.fields' => $allTweetOptions]));
} catch (GuzzleHttp\Exception\ClientException $e) {
	echo $e->getResponse()->getBody();
}
