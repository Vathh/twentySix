<?php

namespace App\Http\Controllers\Api\Concerns;

trait MapsIndexPageForApi
{
    /**
     * @param  array{items: list<array<string, mixed>>, has_more: bool, summary?: string}  $pageData
     * @return array{items: list<array<string, mixed>>, has_more: bool, summary?: string}
     */
    protected function indexPageWithoutUrls(array $pageData): array
    {
        $payload = [
            'items' => array_map(static function (array $item): array {
                unset($item['url']);

                return $item;
            }, $pageData['items']),
            'has_more' => $pageData['has_more'],
        ];

        if (array_key_exists('summary', $pageData)) {
            $payload['summary'] = $pageData['summary'];
        }

        return $payload;
    }
}
