<?php

namespace Smolblog\Twitter;

use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Smolblog\Core\Connector\{Connection, AuthRequestState, Channel};
use Smolblog\OAuth2\Client\Provider\{Twitter, TwitterUser};

final class TwitterConnectorTest extends TestCase {
	private $provider;
	private $userId;
	private $authUrl = 'https://twitter.com/api/v2/oauth2/authorize';
	public function setUp(): void {
		$state = uniqid();
		$pkce = uniqid();
		$userName = uniqid();
		$this->userId = 5;

		$user = $this->createStub(TwitterUser::class);
		$user->method('getId')->willReturn($this->userId);
		$user->method('getUsername')->willReturn($userName);

		$this->provider = $this->createStub(Twitter::class);
		$this->provider->method('getAuthorizationUrl')->willReturn($this->authUrl);
		$this->provider->method('getState')->willReturn($state);
		$this->provider->method('getPkceVerifier')->willReturn($pkce);
		$this->provider->method('getAccessToken')->willReturn($this->createStub(AccessToken::class));
		$this->provider->method('getResourceOwner')->willReturn($user);
	}

	public function testInitializationDataCanBeRetrieved() {
		$connector = new TwitterConnector(provider: $this->provider);

		$info = $connector->getInitializationData();
		$this->assertEquals($this->authUrl, $info->url);
		$this->assertEquals($this->provider->getState(), $info->state);
		$this->assertEquals($this->provider->getPkceVerifier(), $info->info['verifier']);
	}

	public function testCredentialCanBeCreated() {
		$connector = new TwitterConnector(provider: $this->provider);

		$cred = $connector->createConnection(
			code: uniqid(),
			info: new AuthRequestState(
				id: uniqid(),
				userId: $this->userId,
				info: ['verifier' => $this->provider->getPkceVerifier()]
			)
		);
		$this->assertInstanceOf(Connection::class, $cred);
	}

	public function testSingleChannelIsGiven() {
		$connector = new TwitterConnector(provider: $this->provider);

		$channels = $connector->getChannels(connection: new Connection(
			userId: 5,
			provider: 'twitter',
			providerKey: '12345',
			displayName: '@smolbirb',
			details: [],
		));

		$this->assertIsArray($channels);
		$this->assertEquals(1, count($channels));
		$this->assertInstanceOf(Channel::class, $channels[0]);
	}
}
