<?php

namespace Smolblog\Twitter;

use PHPUnit\Framework\TestCase;
use Smolblog\OAuth2\Client\Provider\Twitter;
use Smolblog\Core\Connector\Entities\Connection;
use League\OAuth2\Client\Token\AccessToken;

require_once 'cred.php';

class RefreshTest extends TestCase {
	private ?Connection $connection;
	private ?Twitter $provider;
	public function setUp(): void {
		$this->connection = createCredential();
		$this->provider = new Twitter(getProviderArgs());
	}

	public function tearDown(): void {
		echo "\n\n--------\n";
		echo json_encode($this->connection, JSON_PRETTY_PRINT);
		echo "\n--------\n\n";
	}

	public function testTheTokenWillRefreshIfNeeded() {
		$connector = new TwitterConnector(provider: $this->provider);

		if ($connector->connectionNeedsRefresh(connection: $this->connection)) {
			$old = $this->connection->details['accessToken'];
			$this->connection = $connector->refreshConnection($this->connection);
			$this->assertNotEquals($old, $this->connection->details['accessToken']);
		}

		$this->assertInstanceOf(
			\Smolblog\OAuth2\Client\Provider\TwitterUser::class,
			$this->provider->getResourceOwner(new AccessToken(['access_token' => $this->connection->details['accessToken']])),
		);
	}
}
