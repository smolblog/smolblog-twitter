<?php

namespace Smolblog\Twitter;

use Smolblog\App\Smolblog;
use Smolblog\App\Environment;
use Smolblog\App\Plugin\{Plugin as SmolblogPlugin, PluginPackage};
use Smolblog\App\Hooks\CollectingConnectors;
use Abraham\TwitterOAuth\TwitterOAuth;

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
		$app->container->addShared(TwitterConnector::class)
			->addArgument(Environment::class);

		$app->events->subscribeTo(
			CollectingConnectors::class,
			fn($event) => $event->connectors['twitter'] = TwitterConnector::class
		);
	}
}
