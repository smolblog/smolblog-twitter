<?php

namespace Smolblog\Twitter;

use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Smolblog\Core\Factories\ConnectionCredentialFactory;
use Smolblog\Core\Models\ConnectionCredential;
use Smolblog\OAuth2\Client\Provider\{Twitter, TwitterUser};

final class TwitterConnectorTest extends TestCase {
	private $provider;
	private $factory;
	private $userId;
	public function setUp(): void {
		$state = uniqid();
		$pkce = uniqid();
		$userName = uniqid();
		$this->userId = uniqid();

		$user = $this->createStub(TwitterUser::class);
		$user->method('getId')->willReturn($this->userId);
		$user->method('getUsername')->willReturn($userName);

		$this->provider = $this->createStub(Twitter::class);
		$this->provider->method('getAuthorizationUrl')->willReturnCallback(function($args) {
			return $args['redirect_uri'];
		});
		$this->provider->method('getState')->willReturn($state);
		$this->provider->method('getPkceVerifier')->willReturn($pkce);
		$this->provider->method('getAccessToken')->willReturn($this->createStub(AccessToken::class));
		$this->provider->method('getResourceOwner')->willReturn($user);

		$this->factory = $this->createStub(ConnectionCredentialFactory::class);
		$this->factory->method('credentialWith')->willReturnCallback(function($provider, $key) {
			return $this->createStub(ConnectionCredential::class);
		});
	}

	public function testInitializationDataCanBeRetrieved() {
		$connector = new TwitterConnector(provider: $this->provider, factory: $this->factory);
		$callbackUrl = 'https://smol.blog/api/twitter';

		$info = $connector->getInitializationData($callbackUrl);
		$this->assertEquals($callbackUrl, $info->url);
		$this->assertEquals($this->provider->getState(), $info->state);
		$this->assertEquals($this->provider->getPkceVerifier(), $info->info['verifier']);
	}

	public function testCredentialCanBeCreated() {
		$connector = new TwitterConnector(provider: $this->provider, factory: $this->factory);

		$cred = $connector->createCredential(code: uniqid(), info: [
			'verifier' => $this->provider->getPkceVerifier(),
			'user_id' => $this->userId,
		]);
		$this->assertInstanceOf(ConnectionCredential::class, $cred);
	}
}
