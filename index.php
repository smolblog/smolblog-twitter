<?php

namespace Smolblog\Twitter;

use Smolblog\Core\App;

/**
 * Load the plugin's classes into the App's container and subscribe to any events.
 *
 * @param App $smolblog Current Smolblog instance.
 * @return void
 */
function loadPlugin(App $smolblog) {
	// Create the Twitter connector and register it.
	$twitter_connector = new TwitterConnector(
		provider: new TwitterOAuth(
			array(
				'clientId'     => 'mememe',
				'clientSecret' => 'youyou',
			)
		),
		factory: $smolblog->container->get(ConnectionCredentialFactory::class)
	);
	$smolblog->container->get(ConnectorRegistrar::class)->register($twitter_connector);
}
