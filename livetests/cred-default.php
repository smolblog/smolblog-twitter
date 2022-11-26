<?php

namespace Smolblog\Twitter;

function createCredential() {
	return new \Smolblog\Core\Connector\Entities\Connection(
		userId: 1,
		provider: 'twitter',
		providerKey: 'xxx',
		displayName: 'xxx',
		details: [
			"accessToken" => "xxx",
			"refreshToken" => "xxx",
		],
	);
}

function getProviderArgs() {
	return [
		'clientId' => 'xxx',
		'clientSecret' => 'xxx',
		'redirectUri' => "https://smol.blog/wp-json/smolblog/v2/connect/callback/twitter",
	];
}
