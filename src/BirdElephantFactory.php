<?php

namespace Smolblog\Twitter;

use Coderjerk\BirdElephant\BirdElephant;
use Smolblog\Core\Environment;

/**
 * Factory for creating BirdElephant objects with either a bearer token or app creds
 */
class BirdElephantFactory {
	/**
	 * Construct the factory
	 *
	 * @param Environment $env Smolblog environment.
	 */
	public function __construct(
		private Environment $env
	) {
	}

	/**
	 * Create a BirdElephant object with the environment-given app keys.
	 *
	 * @return BirdElephant
	 */
	public function createWithAppKeys(): BirdElephant {
		return new BirdElephant([
			'consumer_key' => $this->env->twitterAppId ?? '',
			'consumer_secret' => $this->env->twitterAppSecret ?? '',
		]);
	}

	/**
	 * Create a BirdElephant object with the given bearer token
	 *
	 * @param string $token Authenticated bearer token.
	 * @return BirdElephant
	 */
	public function createWithBearerToken(string $token): BirdElephant {
		return new BirdElephant(['bearer_token' => $token]);
	}
}
