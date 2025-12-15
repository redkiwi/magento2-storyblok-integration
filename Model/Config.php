<?php

declare(strict_types=1);

namespace MediaLounge\Storyblok\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    const API_KEY_CONFIG_PATH = 'storyblok/general/api_key';
    const SLUG_PREFIX_CONFIG_PATH = 'storyblok/general/slug_prefix';
    const HOME_SLUG_CONFIG_PATH = 'storyblok/home_page/home_slug';
    const RESOLVE_LINKS_CONFIG_PATH = 'storyblok/general/resolve_links';
    const LANGUAGE_CONFIG_PATH = 'storyblok/general/language';
    const FALLBACK_LANGUAGE_CONFIG_PATH = 'storyblok/general/fallback_language';
    const EXCLUDED_CONTENT_TYPES_CONFIG_PATH = 'storyblok/general/excluded_content_types';
    const SHOW_BREADCRUMBS_CONFIG_PATH = 'storyblok/seo/show_breadcrumbs';
    const ADD_CANONICAL_CONFIG_PATH = 'storyblok/seo/add_canonical';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function apiKey(): string
    {
        return $this->scopeConfig->getValue(
            self::API_KEY_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        ) ?? '';
    }

    public function slugPrefix(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::SLUG_PREFIX_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function homeSlug(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::HOME_SLUG_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function resolveLinks(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::RESOLVE_LINKS_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function locale(): string
    {
        return (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    }

    public function language(): string
    {
        $configured = $this->scopeConfig->getValue(
            self::LANGUAGE_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );

        if ($configured) {
            return (string)$configured;
        }

        // Transform locale: nl_NL → nl-NL
        return str_replace('_', '-', $this->locale());
    }

    public function fallbackLanguage(): string
    {
        $configured = $this->scopeConfig->getValue(
            self::FALLBACK_LANGUAGE_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );

        if ($configured) {
            return (string)$configured;
        }

        // Extract language code: nl_NL → nl
        $locale = $this->locale();
        $underscorePos = strpos($locale, '_');
        return $underscorePos !== false ? substr($locale, 0, $underscorePos) : $locale;
    }

    public function excludedContentTypes(): array
    {
        $configured = $this->scopeConfig->getValue(
            self::EXCLUDED_CONTENT_TYPES_CONFIG_PATH,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        if (empty($configured)) {
            return [];
        }

        // Split by newlines and trim each line
        $types = explode("\n", (string)$configured);
        return array_filter(array_map('trim', $types));
    }

    public function showBreadcrumbs(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::SHOW_BREADCRUMBS_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function addCanonical(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::ADD_CANONICAL_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }
}
