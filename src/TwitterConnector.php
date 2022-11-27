<?php

namespace Smolblog\Twitter;

use Smolblog\App\Environment;
use Smolblog\Core\Connector\{AuthRequestState, Channel, Connection, Connector, ConnectorConfig, ConnectorInitData};
use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * Handle authenticating against the Twitter API
 */
class TwitterConnector implements Connector {
	public const SLUG = 'twitter';

	/**
	 * Construct the connector
	 *
	 * @throws TwitterException When $env does not have twitterAppId and twitterAppSecret.
	 * @param Environment $env App Environment with Twitter application keys.
	 */
	public function __construct(
		private Environment $env
	) {
		if (!isset($env->twitterAppId) || !isset($env->twitterAppSecret)) {
			throw new TwitterException('Environment does not have Twitter application keys.');
		}
	}

	/**
	 * Get the information needed to start an OAuth session with the provider
	 *
	 * @param string $callbackUrl URL to pass to Twitter to redirect back to.
	 * @return ConnectorInitData
	 */
	public function getInitializationData(string $callbackUrl = null): ConnectorInitData {
		$provider = new TwitterOAuth(
			$this->env->twitterAppId ?? '',
			$this->env->twitterAppSecret ?? ''
		);

		$requestToken = $provider->oauth('oauth/request_token', ['oauth_callback' => $callbackUrl]);
		$url = $provider->url('oauth/authorize', ['oauth_token' => $requestToken['oauth_token']]);

		return new ConnectorInitData(
			url: $url,
			state: $requestToken['oauth_token'],
			info: [
				'oauth_token'        => $request_token['oauth_token'],
				'oauth_token_secret' => $request_token['oauth_token_secret'],
			],
		);
	}

	/**
	 * Handle the OAuth callback from the provider and create the credential
	 *
	 * @param string           $code Code given to the OAuth callback.
	 * @param AuthRequestState $info Info from the original request.
	 * @return null|Connection Created credential, null on failure
	 */
	public function createConnection(string $code, AuthRequestState $info): ?Connection {
		$provider = = new TwitterOAuth(
			$this->env->twitterAppId ?? '',
			$this->env->twitterAppSecret ?? '',
			$info->info['oauth_token'],
			$info->info['oauth_token_secret']
		);
		$access_info = $provider->oauth('oauth/access_token', [ 'oauth_verifier' => $code ]);
		print_r($access_info);
		die;

		return new Connection(
			userId: $info->userId,
			provider: self::SLUG,
			providerKey: $twitterUser->getId(),
			displayName: $access_info['screen_name'],
			details: [
				'oauth_token' => $access_info['oauth_token'],
				'oauth_token_secret' => $access_info['oauth_token_secret'],
			],
		);
	}

	/**
	 * Get the channels enabled by the Connection.
	 *
	 * @param Connection $connection Account to get Channels for.
	 * @return Channel[] Array of Channels this Connection can use
	 */
	public function getChannels(Connection $connection): array {
		// Currently, Twitter accounts only have one Channel.
		return [
			new Channel(
				connectionId: $connection->id,
				channelKey: $connection->providerKey,
				displayName: $connection->displayName,
				details: [],
			)
		];
	}
}
