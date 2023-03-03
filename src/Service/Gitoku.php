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

    public function getTaxonomy(): array
    {
        return $this->cache->get('gitoku_taxonomy_' . $this->apiVersion, function (ItemInterface $item) {

            $item->expiresAfter(60);
            $jsonOb = $this->request('/taxonomy');

            for($idx = 0; $idx < sizeof($jsonOb["media"]);++$idx){
                if($jsonOb["media"][$idx]['name'] == "web"){
                    $webObj = $jsonOb["media"][$idx];
                    for($idx2 = 0; $idx2 < sizeof($webObj["formats"]);++$idx2){
                        if($webObj["formats"][$idx2]["type"] == "image" || $webObj["formats"][$idx2]["type"] == "html"){
                            $jsonOb["media"][$idx]["formats"][$idx2]["scopes"]["1920x1080"] = "Full screen " . $webObj["formats"][$idx2]["type"];
                            $jsonOb["media"][$idx]["formats"][$idx2]["scopes"]["3840x2160"] = "Ultra HD " . $webObj["formats"][$idx2]["type"];
                        }
                    }
                }
            }
            return $jsonOb;
        });
    }

    public function getInfo(string $url, array $categories = []): array
    {
        //todo until adShares team correct new pages issue
        return ['rank'=>1,'info'=>'ok','categories'=>['unknown'],'quality'=>'high','updated_at'=>'2023-01-01 12:00:00'];
    }

    public function getBatchInfo(int $limit = 1000, int $offset = 0, DateTimeInterface $changedAfter = null): array
    {
        //todo until adShares team correct new pages issue
        return [];
    }

    public function reassessment(array $data): array
    {
        //todo until adShares team correct new pages issue
        return [];
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

