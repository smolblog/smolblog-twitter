<?php

namespace Smolblog\Twitter;

use Smolblog\Core\App;
use Smolblog\Core\Plugin\{Plugin as SmolblogPlugin, PluginPackage};
use Smolblog\Core\Events\CollectingConnectors;
use Smolblog\OAuth2\Client\Provider\Twitter;

/**
 * Plugin class for this library
 */
class Plugin implements SmolblogPlugin {
	/**
	 * Get the information about this Plugin
	 *
	 * @return PluginPackage
	 */
	public static function config(): PluginPackage {
		return new PluginPackage(
			package: 'smolblog/twitter',
			version: 'dev-main',
			title: 'Smolblog Twitter Connection',
			description: 'Connection to the Twitter API for authentication and incoming and outgoing syndication.',
			authors: [
				['name' => 'Smolblog', 'website' => 'https://smolblog.org/'],
				['name' => 'Evan Hildreth', 'website' => 'https://www.oddevan.com/']
			],
		);
	}

	/**
	 * Plugin bootstrapping function called by the App
	 *
	 * @param App $app Smolblog App instance being intiialized.
	 * @return void
	 */
	public static function setup(App $app) {
		$app->container->addShared(Twitter::class, fn() => new Twitter([
			'clientId' => 'mememe',
			'clientSecret' => 'youyou'
		]));
		$app->container->addShared(TwitterConnector::class)
			->addArgument(Twitter::class)

		$app->events->subscribeTo(CollectingConnectors::class, self::class . '::registerConnector');
	}

	/**
	 * Provide the class for the connector so it can be registered.
	 *
	 * @param CollectingConnectors $event Event collecting Connector classes.
	 * @return void
	 */
	public static function registerConnector(CollectingConnectors $event): void {
		$event->connectors[] = TwitterConnector::class;
	}
}
