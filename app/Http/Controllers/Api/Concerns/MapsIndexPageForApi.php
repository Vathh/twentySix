<?php

namespace App\Http\Controllers\Api\Concerns;

trait MapsIndexPageForApi
{
    /**
     * @param  array{items: list<array<string, mixed>>, has_more: bool}  $pageData
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    protected function indexPageWithoutUrls(array $pageData): array
    {
        return [
            'items' => array_map(static function (array $item): array {
                unset($item['url']);

                return $item;
            }, $pageData['items']),
            'has_more' => $pageData['has_more'],
        ];
    }
}
