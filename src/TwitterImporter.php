<?php

namespace Smolblog\Twitter;

use DateTimeImmutable;
use DateTimeInterface;
use cebe\markdown\Markdown;
use Smolblog\Core\Connector\Entities\{Connection, Channel};
use Smolblog\Core\Importer\{ImportablePost, Importer, ImportResults, RemoveAlreadyImported};
use Smolblog\Core\Post\{Post, PostStatus};
use Smolblog\Core\Post\Blocks\{EmbedBlock, LinkBlock, ParagraphBlock, ReblogBlock};
use Smolblog\Framework\Identifier;
use Twitter\Text\Autolink;

/**
 * Importer class for Twitter
 */
class TwitterImporter implements Importer {
	/**
	 * Construct the importer
	 *
	 * @param RemoveAlreadyImported $filterService Service to exclude posts that already exist.
	 * @param BirdElephantFactory   $factory       Factory to get a BirdElephant object.
	 * @param AutoLink              $twitterLinker AutoLink from the Twitter Text library.
	 * @param Markdown              $markdown      Markdown processor.
	 */
	public function __construct(
		private RemoveAlreadyImported $filterService,
		private BirdElephantFactory $factory,
		private AutoLink $twitterLinker,
		private Markdown $markdown,
	) {
	}

	/**
	 * Get posts from the given Channel/Connection according to the given options.
	 *
	 * @param Connection $connection Authenticated connection to use.
	 * @param Channel    $channel    Channel to pull posts from.
	 * @param array      $options    Options to use, such as types or pagination.
	 * @return ImportResults Array of Posts ready to insert and optional command to fetch next page.
	 */
	public function getPostsFromChannel(Connection $connection, Channel $channel, array $options): ImportResults {
		$birdsite = $this->factory->createWithBearerToken($connection->details['accessToken']);
		$params = [
			'tweet.fields' =>
				'attachments,author_id,conversation_id,created_at,edit_controls,entities,id,' .
				'referenced_tweets,text,withheld',
			'expansions' =>
				'attachments.media_keys,author_id,edit_history_tweet_ids,entities.mentions.username,' .
				'referenced_tweets.id,referenced_tweets.id.author_id',
			'exclude' => 'replies',
			'media.fields' => 'height,media_key,type,url,width,alt_text,variants',
			'user.fields' => 'entities,id,name,protected,url,username',
			'max_results' => 15
		];
		$results = $birdsite->user($connection->displayName)->tweets($params);

		$filtered = $this->filterService->run(posts: array_map(
			fn($p) => new ImportablePost(url: $this->getTweetUrl($connection->displayName, $p->id), postData: $p),
			$results->data
		));
		if (empty($filtered)) {
			// If everything in this batch is imported, we're at the end of the road.
			return new ImportResults([]);
		}

		$authorsRef = [];
		foreach ($results->includes?->users ?? [] as $user) {
			if ($user->id === $connection->providerKey) {
				continue;
			}

			$authorsRef[$user->id] = [
				'name' => $user->name,
				'username' => $user->username,
			];
		}

		$tweetRef = [];
		foreach ($results->includes?->tweets ?? [] as $tweet) {
			if ($tweet->author_id === $connection->providerKey) {
				continue;
			}

			$tweetRef[$tweet->id] = [
				'timestamp' => new DateTimeImmutable($tweet->created_at),
				'text' => $tweet->text,
				'author' => $authorsRef[$tweet->author_id],
			];
		}

		$mediaRef = [];
		foreach ($results->includes?->media ?? [] as $media) {
			if ('photo' === $media->type) {
				$mediaRef[$media->media_key] = [
					'type' => 'image',
					'url'  => $media->url,
					'alt'  => $media->alt_text ?? 'Image from Twitter',
				];
			} elseif ('video' === $media->type || 'animated_gif' === $media->type) {
				$video_url     = '#';
				$video_bitrate = -1;
				foreach ($media->variants as $vidinfo) {
					if ('video/mp4' === $vidinfo->content_type && $vidinfo->bit_rate > $video_bitrate) {
						$video_bitrate = $vidinfo->bit_rate;
						$video_url     = $vidinfo->url;
					}
				}

				$mediaRef[$media->media_key] = [
					'type' => 'video',
					'url'  => $video_url,
					'alt'  => 'Video from Twitter',
					'atts' => ( 'animated_gif' === $media->type ) ? 'autoplay loop ' : null,
				];
			}//end if
		}//end foreach

		$posts = [];
		$threadParts = [];
		foreach ($filtered as $importable) {
			$tweet = $importable->postData;

			$postArgs = [
				'user_id' => $connection->userId,
				'timestamp' => new DateTimeImmutable($tweet->created_at),
				'slug' => $tweet->id,
				'status' => PostStatus::Published,
				'syndicationUrls' => [ $importable->url ],
				'meta' => [ 'twitterId' => $tweet->id ],
			];

			$referencedTweets = array_filter(
				$tweet->referenced_tweets ?? [],
				fn($ref) => $ref->type !== 'replied_to'
			);

			$reblogUrl = '';
			$append = false;
			if ($tweet->conversation_id !== $tweet->id) {
				$append = true;
			} elseif (!empty($referencedTweets)) {
				$twid = $referencedTweets[0]->id;
				$twRef = $tweetRef[$twid];

				$reblogUrl = $this->getTweetUrl(authorHandle: $twRef['author']['username'], tweetId: $twid);
				$postArgs['content'] = [
					$this->getReblogBlock(
						tweetId: $twid,
						authorName: $twRef['author']['name'],
						authorHandle: $twRef['author']['username'],
						timestamp: $twRef['timestamp'],
						text: $twRef['text']
					)
				];
				$postArgs['reblog'] = $reblogUrl;

				if ($referencedTweets[0]->type === 'retweeted') {
					// If this is a retweet without comment, we're done.
					continue;
				}
			}//end if

			$content = $this->getTweetContent(
				mediaRef: $mediaRef,
				tweetRef: $tweetRef,
				reblogUrl: $reblogUrl,
				entities: $tweet->entities ?? [],
				text: $tweet->text,
			);
			$postArgs['content'] = [ ...$postArgs['content'], ...$content ];
			$postArgs['tags'] = array_map(fn($entity) => $entity->tag, $tweet->entities?->hashtags ?? []);

			if ($append) {
				$threadParts[] = [
					'timestamp' => $postArgs['timestamp'],
					'content' => $postArgs['content'],
					'tags' => $postArgs['tags'],
					'rootTweetId' => $tweet->conversation_id,
				];
				continue;
			}

			$posts[] = new Post(...$postArgs);
		}//end foreach

		return new ImportResults(posts: $posts);
	}

	/**
	 * Create a Twitter URL from a author and tweet ID
	 *
	 * @param string $authorHandle Handle of the tweet author.
	 * @param string $tweetId      ID of the tweet.
	 * @return string URL for the tweet.
	 */
	private function getTweetUrl(string $authorHandle, string $tweetId): string {
		return "https://twitter.com/$authorHandle/status/$tweetId";
	}

	/**
	 * Create a Reblog Block from the given info
	 *
	 * @param string            $tweetId      ID of the tweet.
	 * @param string            $authorName   Tweet author's display name.
	 * @param string            $authorHandle Tweet author's handle.
	 * @param DateTimeInterface $timestamp    Time and date of the tweet.
	 * @param string            $text         Text content of the tweet.
	 * @return ReblogBlock
	 */
	private function getReblogBlock(
		string $tweetId,
		string $authorName,
		string $authorHandle,
		DateTimeInterface $timestamp,
		string $text
	): ReblogBlock {
		$url = $this->getTweetUrl($authorHandle, $tweetId);
		$date = $timestamp->format('F j, Y');

		return new ReblogBlock(
			url: $url,
			link: new LinkBlock(
				url: $url,
				title: "$authorName on Twitter",
				pullQuote: $text,
				pullQuoteCaption:
					"<a href='https://twitter.com/$authorHandle' rel='external' target='_blank'>" .
					"$authorName</a> on <a href='$url' rel='external' target='_blank'>$date</a>",
			),
		);
	}

	/**
	 * Parse the text content of a tweet
	 *
	 * Creates links of entities and URLs. Embeds referenced tweets. Sets up images.
	 *
	 * @param array  $mediaRef  Referenced media in this batch.
	 * @param array  $tweetRef  Referenced tweets in this batch.
	 * @param string $reblogUrl Reblog URL for this tweet.
	 * @param array  $entities  Listed entities for this tweet.
	 * @param string $text      Unprocessed text for this tweet.
	 * @return \Smolblog\Core\Post\Block[] Content of this tweet as blocks.
	 */
	private function getTweetContent(
		array $mediaRef,
		array $tweetRef,
		string $reblogUrl,
		array $entities,
		string $text
	): array {

		$blockTexts = array_map(fn($p) => nl2br($this->markdown->parseParagraph($p)), explode("\n\n", $text));
		foreach ($blockTexts as $blockText) {
			$text = $this->twitterLinker->autoLinkUsernamesAndLists(
				$this->twitterLinker->autoLinkHashtags(
					$this->twitterLinker->autoLinkCashtags($tweet->text)
				)
			);
			foreach ($tweet->entities?->urls ?? [] as $tacolink) {
				$replacement = '';
				if (str_starts_with($tacolink->display_url, 'twitter.com')) {
					if ($reblogUrl !== $tacolink->expanded_url) {
						$replacement = "\n\n{$tacolink->expanded_url}\n\n";
					}
				} else {
					$replacement = "<a href=\"{$tacolink->expanded_url}\" class=\"tweet-url\" rel=\"external\" " .
						"target=\"_blank\">{$tacolink->display_url}</a>";
				}
				$text = str_replace($tacolink->url, $replacement, $text);
			}

			if (str_starts_with($blockText, 'https://twitter.com/')) {
				$postArgs['content'][] = new EmbedBlock(url: $blockText);
				continue;
			}

			$postArgs['content'][] = new ParagraphBlock(content: $blockText);
		}//end foreach

		return [];
	}
}
