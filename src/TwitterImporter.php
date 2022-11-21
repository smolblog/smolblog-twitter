<?php

namespace Smolblog\Twitter;

use Smolblog\Core\Importer\{Importer, ImporterConfig, RemoveAlreadyImported};

/**
 * Importer class for Twitter
 */
class TwitterImporter implements Importer {
	/**
	 * Get the configuration for the importer. This will let the Registrar
	 * get the information it needs without having to instantiate.
	 *
	 * @return ImporterConfig
	 */
	public static function config(): ImporterConfig {
		return new ImporterConfig(slug: 'twitter');
	}

	/**
	 * Construct the importer
	 *
	 * @param RemoveAlreadyImported $filterService Service to exclude posts that already exist.
	 * @param BirdElephantFactory   $factory       Factory to get a BirdElephant object.
	 */
	public function __construct(
		private RemoveAlreadyImported $filterService,
		private BirdElephantFactory $factory,
	) {
	}

	/**
	 * Get posts from the given Channel/Connection according to the given options.
	 *
	 * @param Connection $connection Authenticated connection to use.
	 * @param Channel    $channel    Channel to pull posts from.
	 * @param array      $options    Options to use, such as types or pagination.
	 * @return ImportResults Array of Posts ready to insert and optional command to fetch next page.
	 */
	public function getPostsFromChannel(Connection $connection, Channel $channel, array $options): ImportResults {
		return new ImportResults([]);
	}
}
