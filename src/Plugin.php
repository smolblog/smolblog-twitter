<?php

namespace Smolblog\Twitter;

use Smolblog\App\Smolblog;
use Smolblog\App\Plugin\{Plugin as SmolblogPlugin, PluginPackage};
use Smolblog\App\Hooks\{CollectingConnectors, CollectingImporters};
use Smolblog\Core\Importer\RemoveAlreadyImported;
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
	 * @param Smolblog $app Smolblog App instance being intiialized.
	 * @return void
	 */
	public static function setup(Smolblog $app) {
		$app->container->addShared(Twitter::class, fn() => new Twitter([
			'clientId' => $app->env->twitterAppId ?? '',
			'clientSecret' => $app->env->twitterAppSecret ?? '',
			'redirectUri' => "{$app->env->apiBase}connect/callback/twitter",
		]));
		$app->container->addShared(TwitterConnector::class)
			->addArgument(Twitter::class);
		$app->container->addShared(TwitterImporter::class)
			->addArgument(RemoveAlreadyImported::class)
			->addArgument(BirdElephantFactory::class);

		$app->events->subscribeTo(
			CollectingConnectors::class,
			fn($event) => $event->connectors['twitter'] = TwitterConnector::class
		);
		$app->events->subscribeTo(
			CollectingImporters::class,
			fn($event) => $event->connectors['twitter'] = TwitterImporter::class
		)
	}
}
