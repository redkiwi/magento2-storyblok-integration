<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

class SpaceHashProvider
{
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Get short hash of API key to uniquely identify Storyblok space
     */
    public function getHash(): string
    {
        $apiKey = $this->config->apiKey();

        return $apiKey ? substr(md5($apiKey), 0, 8) : '';
    }
}
