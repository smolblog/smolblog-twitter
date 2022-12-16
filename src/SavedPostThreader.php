<?php

namespace Smolblog\Twitter;

use Smolblog\Framework\Service;

class SavedPostThreader implements Service {
	public function __construct(
		private PostReader $reader,
		private PostWriter $writer,
	) {
	}

	public function run(array $threadParts): void {
		$postsToSave = [];

		foreach ($threadParts as $rootTwitterId => $parts) {
			$basePost = $reader->findBy(meta: ['twitterId' => $rootTwitterId]);
			if (!isset($basePost)) {
				continue;
			}
			$newContent = $basePost->content;
			$newTags = $basePost->tags;
			$newSyndicationUrls = $basePost->syndicationUrls;

			usort($parts, fn($a, $b) => $a['timestamp'] <= $b['timestamp'] ? -1 : 1);
			foreach ($parts as $part) {
				$newContent = [...$newContent, ...$part['content']];
				$newTags = [...$newTags, ...$part['tags']];
				$newSyndicationUrls[] = $part['url'];
			}

			$postsToSave[] = $basePost->newWith(content: $newContent, tags: $newTags, syndicationUrls: $newSyndicationUrls);
		}

		$writer->saveMany($postsToSave);
	}
}
