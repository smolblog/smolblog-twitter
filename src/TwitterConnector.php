<?php

namespace Smolblog\Twitter;

use Smolblog\Core\Connector\{Connector, ConnectorWithRefresh, ConnectorInitData};
use Smolblog\Core\Connector\Entities\{AuthRequestState, Channel, Connection};
use Smolblog\OAuth2\Client\Provider\Twitter as TwitterOAuth;

/**
 * Handle authenticating against the Twitter API
 */
class TwitterConnector implements ConnectorWithRefresh {
	public const SLUG = 'twitter';

	/**
	 * Create the connector instance
	 *
	 * @param TwitterOAuth $provider Instance of the OAuth library to use.
	 */
	public function __construct(
		private TwitterOAuth $provider,
	) {
	}

	/**
	 * Get the information needed to start an OAuth session with the provider
	 *
	 * @param string $callbackUrl URL to pass to Twitter to redirect back to.
	 * @return ConnectorInitData
	 */
	public function getInitializationData(string $callbackUrl = null): ConnectorInitData {
		// Callback URL is given in the provider constructor; will need to refactor that.
		$authUrl = $this->provider->getAuthorizationUrl();
		$state = $this->provider->getState();

		// We also need to store the PKCE Verification code so we can send it with
		// the authorization code request.
		$verifier = $this->provider->getPkceVerifier();

		return new ConnectorInitData(
			url: $authUrl,
			state: $state,
			info: [ 'verifier' => $verifier ],
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
		$token = $this->provider->getAccessToken('authorization_code', [
			'code' => $code,
			'code_verifier' => $info->info['verifier'],
		]);
		$twitterUser = $this->provider->getResourceOwner($token);

		return new Connection(
			userId: $info->userId,
			provider: self::SLUG,
			providerKey: $twitterUser->getId(),
			displayName: $twitterUser->getUsername(),
			details: [
				'accessToken' => $token->getToken(),
				'refreshToken' => $token->getRefreshToken(),
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

	public function connectionNeedsRefresh(Connection $connection): bool {
		return false;
	}

	public function refreshConnection(Connection $connection): Connection {
		return $connection;
	}
}
