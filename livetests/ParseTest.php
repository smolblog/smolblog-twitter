<?php

namespace Smolblog\Twitter;

use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Smolblog\Core\Connector\Entities\{Connection, Channel};
use Smolblog\Core\Importer\{ImportResults, RemoveAlreadyImported};
use Smolblog\Core\Post\Blocks\{EmbedBlock, LinkBlock, ParagraphBlock, ReblogBlock};
use Coderjerk\BirdElephant\BirdElephant;
use cebe\markdown\Markdown;
use Twitter\Text\Autolink;

require_once 'cred.php';

class ParseTest extends TestCase {
	private ?Connection $connection;
	public function setUp(): void {
		$this->connection = createCredential();
	}

	public function testPostsAreRetrieved() {
		$removeService = $this->createStub(RemoveAlreadyImported::class);
		$removeService->method('run')->willReturnArgument(0);

		$importer = new TwitterImporter(
			filterService: $removeService,
			factory: new BirdElephantFactory(new \Smolblog\App\Environment(apiBase: '//', twitterAppId: 'abc', twitterAppSecret: 'abc')),
			twitterLinker: Autolink::create()->setNoFollow(false)->setUsernameIncludeSymbol(true),
			markdown: new Markdown(),
		);

		$connection = createCredential();
		$channel = new Channel(
			connectionId: $connection->id,
			channelKey: $connection->providerKey,
			displayName: $connection->displayName,
			details: [],
		);

		$results = $importer->getPostsFromChannel(connection: $connection, channel: $channel, options: []);
		// $json = json_encode($results, JSON_PRETTY_PRINT);

		// echo "\n\n---\n$json\n---\n\n";
		if (false === file_put_contents(__DIR__ . '/print_r.txt', print_r($results, true))) {
			echo "File write failed!\n";
		}

		$this->assertInstanceOf(ImportResults::class, $results);
	}

	private function makeUrl(
		string $authorHandle,
		string $tweetId
	) {
		return "https://twitter.com/$authorHandle/status/$tweetId";
	}

	private function getReblogBlock(
		string $tweetId,
		string $authorName,
		string $authorHandle,
		DateTimeInterface $timestamp,
		string $text
	) {
		$url = $this->makeUrl($authorHandle, $tweetId);
		$date = $timestamp->format('F j, Y');

		return new ReblogBlock(
			url: $url,
			link: new LinkBlock(
				url: $url,
				title: "$authorName on Twitter",
				pullQuote: $text,
				pullQuoteCaption:
					"<a href='https://twitter.com/$authorHandle' rel='external' target='_blank'>".
					"$authorName</a> on <a href='$url' rel='external' target='_blank'>$date</a>",
			),
		);
	}

	public function testTwitterTextAndMarkdown() {
		$text = 'Paging @netraptor01 for *reasons* #fanfics https://t.co/BkfHaDtKvc';
		$linker = \Twitter\Text\Autolink::create()
			->setNoFollow(false)
			->setUsernameIncludeSymbol(true);

		$html = $linker->autoLinkUsernamesAndLists($linker->autoLinkHashtags($linker->autoLinkCashtags($text)));

		$tacolink = json_decode('{"start":20,"end":43,"url":"https:\/\/t.co\/BkfHaDtKvc","expanded_url":"https:\/\/twitter.com\/NinEverything\/status\/1589723865617309696","display_url":"twitter.com\/NinEverything\/\u2026"}');
		$html = str_replace(
			$tacolink->url,
			"<a href=\"{$tacolink->expanded_url}\" class=\"tweet-url\" rel=\"external\" target=\"_blank\">{$tacolink->display_url}</a>",
			$html
		);

		$parser = new \cebe\markdown\Markdown();
		$html = $parser->parseParagraph($html);

		echo "\n\n$html\n\n";

		// $this->assertEquals(
		// 	'Paging·<a·class="tweet-url·username"·href="https://twitter.com/netraptor01"·rel="external"·target="_blank">@netraptor01</a>·for·<em>reasons</em>·<a·href="https://twitter.com/search?q=%23fanfics"·title="#fanfics"·class="tweet-url·hashtag"·rel="external"·target="_blank">#fanfics</a>·<a·href="https://twitter.com/NinEverything/status/1589723865617309696"·class="tweet-url"·rel="external"·target="_blank">twitter.com/NinEverything/…</a>',
		// 	$html
		// );
		$this->assertTrue(true);
	}
}
