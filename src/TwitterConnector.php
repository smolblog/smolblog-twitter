<?php

namespace Smolblog\Twitter;

use Smolblog\Core\{Connector, ConnectorInitData};
use Smolblog\Core\Models\ConnectionCredential;
use Smolblog\OAuth2\Client\Provider\Twitter as TwitterOAuth;

class TwitterConnector implements Connector {
	private TwitterOAuth $provider;

	public function __construct() {
		$this->provider = new TwitterOAuth([
			'clientId'          => $env->envVar('TWITTER_APPLICATION_ID'),
			'clientSecret'      => $env->envVar('TWITTER_APPLICATION_SECRET'),
		]);
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

		$credential = ConnectionCredential::create(withData: [
			'provider' => $this->slug(),
			'provider_key' => $twitterUser->getId(),
		]);
		$credential->user_id = $info['user_id'];
		$credential->display_name = $twitterUser->getUsername();
		$credential->details = $token;
		$credential->save();

		return $credential;
	}
}
