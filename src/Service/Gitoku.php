<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdUser
 *
 * AdUser is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdUser is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace App\Service;

use DateTimeInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Gitoku implements PageInfoProviderInterface
{
    public const GITOKU_URL = 'https://gitoku.com';

    private HttpClientInterface $client;
    private CacheInterface $cache;
    private int $apiVersion = 1;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $client,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function version(int $apiVersion): PageInfoProviderInterface
    {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    protected function formatMessage($message)
    {

        $this->logger->info("000000000000000000000000000000000000000000000000000000000000");
        if (is_array($message)) {
            $this->logger->info("1111111111111111111111111111111111111111111");
            return var_export($message, true);
        } elseif ($message instanceof Jsonable) {
            $this->logger->info("22222222222222222222222222222222222222222");
            return $message->toJson();
        } elseif ($message instanceof Arrayable) {
            $this->logger->info("333333333333333333333333333333333333333333333333");
            return var_export($message->toArray(), true);
        }

        return $message;
    }
    public function getTaxonomy(): array
    {
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");
        $this->logger->info("==================================================================");

        return $this->cache->get('gitoku_taxonomy_' . $this->apiVersion, function (ItemInterface $item) {
            $strToFind = '"980x120":"Panorama"';
            $strToFindLen = strlen($strToFind);
            $item->expiresAfter(60);
            $taxonomy = $this->request('/taxonomy');

            $media0 = json_encode($taxonomy['media'][0]);
            $this->logger->info("taxonomy.media[0]: " . $media0);

            $jsonResult = json_encode($taxonomy);
            $pos = strpos($jsonResult, $strToFind, 0);

            $part1 = substr($jsonResult, 0, $pos + strlen($strToFind));
            $part2 = substr($jsonResult, $pos + strlen($strToFind) +1);


            $pos2 = strpos($part2, $strToFind);
            $part21 = substr($part2, 0, $pos2 + strlen($strToFind));
            $part22 = substr($part2, $pos2 + strlen($strToFind) +1);


            $resultStr = $part1 . ', "1920x1080": "TEST", ' . $part21 . ', "1920x1080": "TEST", ' . $part22;
            return json_decode($resultStr, true);
        });
    }

    public function getInfo(string $url, array $categories = []): array
    {
        return $this->request('/page-rank/' . urlencode($url) . '?' . http_build_query(['categories' => $categories]));
    }

    public function getBatchInfo(int $limit = 1000, int $offset = 0, DateTimeInterface $changedAfter = null): array
    {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];
        if (null !== $changedAfter) {
            $params['changedAfter'] = $changedAfter->format(DateTimeInterface::W3C);
        }
        return $this->request('/page-rank?' . http_build_query($params));
    }

    public function reassessment(array $data): array
    {
        return $this->request('/reassessment', 'POST', $data);
    }

    private function request(string $path, string $method = 'GET', ?array $data = null): array
    {
        $response = $this->client->request(
            $method,
            self::GITOKU_URL . '/api/v' . $this->apiVersion . $path,
            null !== $data ? ['json' => $data] : []
        );
        return $response->toArray();
    }
}

