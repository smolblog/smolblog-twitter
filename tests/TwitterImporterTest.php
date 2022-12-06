<?php

namespace Smolblog\Twitter;

use Coderjerk\BirdElephant\{BirdElephant, User};
use PHPUnit\Framework\TestCase;
use Smolblog\Core\Connector\Entities\{Connection, Channel};
use Smolblog\Core\Importer\{ImportResults, RemoveAlreadyImported};

final class TwitterImporterTest extends TestCase {
	public function testPostsAreRetrieved() {
		$tweetJson = file_get_contents(__DIR__ . '/oddevan_tweets.json');

		$birdUser = $this->createStub(User::class);
		$birdUser->method('tweets')->willReturn(json_decode($tweetJson));

		$birdElephantStub = $this->createStub(BirdElephant::class);
		$birdElephantStub->method('user')->willReturn($birdUser);

		$birdFactory = $this->createStub(BirdElephantFactory::class);
		$birdFactory->method('createWithBearerToken')->willReturn($birdElephantStub);

		$removeService = $this->createStub(RemoveAlreadyImported::class);
		$removeService->method('run')->willReturnArgument(0);

		$importer = new TwitterImporter(
			filterService: $removeService,
			factory: $birdFactory,
		);

		$connection = new Connection(
			userId: 1,
			provider: 'twitter',
			providerKey: '15293682',
			displayName: 'oddevan',
			details: [
				"accessToken" => "1234567890abcdef",
				"refreshToken" => "1234567890abcdef",
			],
		);
		$channel = new Channel(
			connectionId: $connection->id,
			channelKey: $connection->providerKey,
			displayName: $connection->displayName,
			details: [],
		);

		$results = $importer->getPostsFromChannel(connection: $connection, channel: $channel, options: []);

		$this->assertInstanceOf(ImportResults::class, $results);
	}
}
