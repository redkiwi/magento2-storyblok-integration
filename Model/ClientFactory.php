<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

class ClientFactory
{
    /** @var Config */
    private $config;

    /** @var \Storyblock\ClientFactory */
    private $clientFactory;

    public function __construct(
        Config $config,
        \Storyblok\ClientFactory $clientFactory
    ) {
        $this->config = $config;
        $this->clientFactory = $clientFactory;
    }

    public function create(array $data = []): \Storyblok\Client
    {
        $data = array_merge(
            [
                'apiKey' => $this->config->apiKey(),
            ],
            $data
        );
        $client = $this->clientFactory->create($data);

        // Always set language and fallback language
        $client->language($this->config->language());
        $client->fallbackLanguage($this->config->fallbackLanguage());

        return $client;
    }
}
