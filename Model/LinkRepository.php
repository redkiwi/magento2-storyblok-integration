<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

class LinkRepository
{
    private array $links = [];

    public function addLinks(array $links): void
    {
        foreach ($links as $link) {
            if (isset($link['uuid'])) {
                $this->links[$link['uuid']] = $link;
            }
        }
    }

    public function getLinkByUuid(string $uuid): ?array
    {
        return $this->links[$uuid] ?? null;
    }
}