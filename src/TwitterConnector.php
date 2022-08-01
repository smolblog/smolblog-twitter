<?php

namespace Smolblog\Twitter;

use Smolblog\Core\{Connector, ConnectorInitData};
use Smolblog\Core\Factories\ConnectionCredentialFactory;
use Smolblog\OAuth2\Client\Provider\Twitter as TwitterOAuth;

/**
 * Handle authenticating against the Twitter API
 */
class TwitterConnector implements Connector {
	/**
	 * Create the connector instance
	 *
	 * @param TwitterOAuth                $provider Instance of the OAuth library to use.
	 * @param ConnectionCredentialFactory $factory  Factory for creating the ConnectionCredential.
	 */
	public function __construct(
		private TwitterOAuth $provider,
		private ConnectionCredentialFactory $factory
	) {
	}

	/**
	 * Identifier for the provider.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'twitter';
	}

	/**
	 * Get the information needed to start an OAuth session with the provider
	 *
	 * @param string $callbackUrl URL to pass to Twitter to redirect back to.
	 * @return ConnectorInitData
	 */
	public function getInitializationData(string $callbackUrl): ConnectorInitData {
		$authUrl = $this->provider->getAuthorizationUrl(['redirect_uri' => $callbackUrl]);
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
	 * @param string $code Code given to the OAuth callback.
	 * @param array  $info Info from the original request.
	 * @return null|ConnectionCredential Created credential, null on failure
	 */
	public function createCredential(string $code, array $info = []): ?ConnectionCredential {
		$token = $this->provider->getAccessToken('authorization_code', [
			'code' => $code,
			'code_verifier' => $info['verifier'],
		]);
		$twitterUser = $this->provider->getResourceOwner($token);

		$credential = $factory->credentialWith(
			provider: $this->slug(),
			key: $twitterUser->getId(),
		);
		$credential->user_id = $info['user_id'];
		$credential->display_name = $twitterUser->getUsername();
		$credential->details = $token;
		$credential->save();

		return $credential;
	}
}
