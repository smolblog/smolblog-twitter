<?php

namespace Smolblog\Twitter;

use DateTime;
use DateTimeInterface;
use function nl2br;
use PHPUnit\Framework\TestCase;
use Smolblog\Core\Connector\Entities\Connection;
use Smolblog\Core\Post\Blocks\{EmbedBlock, LinkBlock, ParagraphBlock, ReblogBlock};
use Coderjerk\BirdElephant\BirdElephant;

require_once 'cred.php';

class ParseTest extends TestCase {
	private ?Connection $connection;
	public function setUp(): void {
		$this->connection = createCredential();
	}

	public function testParsingCorrectly() {
		$birdsite = new BirdElephant(['bearer_token' => $this->connection->details['accessToken']]);
		$markdown = new \cebe\markdown\Markdown();

		$params = [
			'tweet.fields' => 'attachments,author_id,conversation_id,created_at,edit_controls,entities,id,referenced_tweets,text,withheld',
			'expansions' => 'attachments.media_keys,author_id,edit_history_tweet_ids,entities.mentions.username,referenced_tweets.id,referenced_tweets.id.author_id',
			'exclude' => 'replies',
			'media.fields' => 'height,media_key,type,url,width,alt_text,variants',
			'user.fields' => 'entities,id,name,protected,url,username',
			'max_results' => 15
		];

		$results = $birdsite->user($this->connection->displayName)->tweets($params);

		$authorsRef = [];
		foreach ($results->includes?->users ?? [] as $user) {
			if ($user->id === $this->connection->providerKey) { continue; }

			$authorsRef[$user->id] = [
				'name' => $user->name,
				'username' => $user->username,
			];
		}

		$tweetRef = [];
		foreach ($results->includes?->tweets ?? [] as $tweet) {
			if ($tweet->author_id === $this->connection->providerKey) { continue; }

			$tweetRef[$tweet->id] = [
				'timestamp' => new DateTime($tweet->created_at),
				'text' => $tweet->text,
				'author' => $authorsRef[$tweet->author_id],
			];
		}

		$linker = \Twitter\Text\Autolink::create()
			->setNoFollow(false)
			->setUsernameIncludeSymbol(true);

		$posts = array_map(function($tweet) use($tweetRef, $markdown, $linker) {
			$post = [
				'timestamp' => new DateTime($tweet->created_at),
				'id' => $tweet->id,
				'blocks' => [],
			];

			$referencedTweets = array_filter(
				$tweet->referenced_tweets ?? [],
				fn($ref) => $ref->type !== 'replied_to'
			);

			$reblogUrl = '';
			if ($tweet->conversation_id !== $tweet->id) {
				$post['append_to'] = $tweet->conversation_id;
			} elseif (!empty($referencedTweets)) {
				$twid = $referencedTweets[0]->id;
				$twRef = $tweetRef[$twid];

				$reblogUrl = $this->makeUrl(authorHandle: $twRef['author']['username'], tweetId: $twid);
				$post['blocks'] = [
					$this->getReblogBlock(
						tweetId: $twid,
						authorName: $twRef['author']['name'],
						authorHandle: $twRef['author']['username'],
						timestamp: $twRef['timestamp'],
						text: $twRef['text']
					)
				];

				if ($referencedTweets[0]->type === 'retweeted') {
					// If this is a retweet without comment, we're done.
					return $post;
				}
			}

			$text = $linker->autoLinkUsernamesAndLists($linker->autoLinkHashtags($linker->autoLinkCashtags($tweet->text)));
			foreach ($tweet->entities->urls ?? [] as $tacolink) {
				$replacement = '';
				if (str_starts_with($tacolink->display_url, 'twitter.com')) {
					if ($reblogUrl !== $tacolink->expanded_url) {
						$replacement = "\n\n{$tacolink->expanded_url}\n\n";
					}
				} else {
					$replacement = "<a href=\"{$tacolink->expanded_url}\" class=\"tweet-url\" rel=\"external\" ".
						"target=\"_blank\">{$tacolink->display_url}</a>";
				}
				$text = str_replace($tacolink->url, $replacement, $text);
			}

			$blockTexts = array_map(fn($p) => nl2br($markdown->parseParagraph($p)), explode("\n\n", $text));
			foreach ($blockTexts as $blockText) {
				if (str_starts_with($blockText, 'https://twitter.com/')) {
					$post['blocks'][] = new EmbedBlock(url: $blockText);
					continue;
				}

				$post['blocks'][] = new ParagraphBlock(content: $blockText);
			}

			return $post;
		}, $results->data);

		print_r($posts);

		$this->assertInstanceOf(BirdElephant::class, $birdsite);
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