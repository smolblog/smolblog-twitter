<?php

namespace Smolblog\Twitter;

use Smolblog\Core\App;
use Smolblog\Core\Plugin as SmolblogPlugin;
use Smolblog\Core\Events\CollectingConnectors;
use Smolblog\Core\Factories\ConnectionCredentialFactory;
use Smolblog\Core\Registrars\ConnectorRegistrar;
use Smolblog\OAuth2\Client\Provider\Twitter;

/**
 * Plugin class for this library
 */
class Plugin implements SmolblogPlugin {
	/**
	 * Create the Plugin object by loading its classes in the container and subscribing to events.
	 *
	 * @param App $smolblog Current Smolblog instance.
	 */
	public function __construct(App $smolblog) {
		$smolblog->container->addShared(Twitter::class, fn() => new Twitter([
			'clientId' => 'mememe',
			'clientSecret' => 'youyou'
		]));
		$smolblog->container->addShared(TwitterConnector::class)
			->addArgument(Twitter::class)
			->addArgument(ConnectionCredentialFactory::class);

		$smolblog->events->subscribeTo(CollectingConnectors::class, [$this, 'registerConnector']);
	}

	/**
	 * Provide the class for the connector so it can be registered.
	 *
	 * @param CollectingConnectors $event Event collecting Connector classes.
	 * @return void
	 */
	public function registerConnector(CollectingConnectors $event): void {
		$event->connectors[] = TwitterConnector::class;
	}
}
