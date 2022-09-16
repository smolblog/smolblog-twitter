<?php

namespace Smolblog\Twitter;

use Smolblog\Core\App;
use Smolblog\Core\Factories\ConnectionCredentialFactory;
use Smolblog\Core\Registrars\ConnectorRegistrar;
use Smolblog\OAuth2\Client\Provider\Twitter;

/**
 * Load the plugin's classes into the App's container and subscribe to any events.
 *
 * @param App $smolblog Current Smolblog instance.
 * @return void
 */
function loadPlugin(App $smolblog) {
	// Create the Twitter connector and register it.
	$twitter_connector = new TwitterConnector(
		provider: new Twitter(
			array(
				'clientId'     => 'mememe',
				'clientSecret' => 'youyou',
			)
		),
		factory: $smolblog->container->get(ConnectionCredentialFactory::class)
	);
	$smolblog->container->get(ConnectorRegistrar::class)->register($twitter_connector);
}
