<?php

namespace Smolblog\Twitter;

use Smolblog\Core\App;
use Smolblog\Core\Events\CollectingConnectors;
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
	$smolblog->container->addShared(Twitter::class, fn() => new Twitter([
		'clientId' => 'mememe',
		'clientSecret' => 'youyou'
	]));
	$smolblog->container->addShared(TwitterConnector::class)
		->addArgument(Twitter::class)
		->addArgument(ConnectionCredentialFactory::class);

	$smolblog->events->subscribeTo(CollectingConnectors::class, function ($event) {
		$event->connectors[] = TwitterConnector::class;
	});
}
