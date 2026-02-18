# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Magento 2 module integrating Storyblok CMS with Magento 2, enabling visual content management through Storyblok's editor instead of Magento Page Builder.

## Commands

```bash
# Run tests
./vendor/bin/phpunit --testdox

# Run single test
./vendor/bin/phpunit --filter TestMethodName

# Check code style (Magento2 standard)
./vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml --ignore="*vendor/*" .

# Format code
npm run format
```

## Architecture

### Core Data Flow
```
Request → Router → StoryRepository (cached) → Container Block → Render Block Tree → HTML
                                           ↓
                                      Plugins (SEO)
```

### Key Components

- **Router** (`Controller/Router.php`) - Matches URLs to Storyblok stories
- **StoryRepository** (`Model/StoryRepository.php`) - Fetches/caches stories from Storyblok API
- **Container Block** (`Block/Container.php`) - Recursively renders story content
- **LinkResolver** (`ViewModel/LinkResolver.php`) - Converts Storyblok links to Magento URLs
- **Config** (`Model/Config.php`) - All configuration access
- **Plugins** (`Plugin/`) - SEO features (hreflangs, canonical, breadcrumbs, robots)
- **AssetProxyUrl** (`Model/AssetProxyUrl.php`) - Rewrites Storyblok asset URLs to proxy URLs (hex encoding via bin2hex/hex2bin)
- **AssetProxy** (`Model/AssetProxy.php`) - Fetches remote assets from Storyblok CDN
- **AssetProxyOutput** (`Plugin/AssetProxyOutput.php`) - afterToHtml plugin that rewrites URLs in rendered block HTML
- **DisableSessionForProxy** (`Plugin/DisableSessionForProxy.php`) - Prevents PHP session start for proxy routes

### Adding Block Types

1. Create template: `view/frontend/templates/story/{component}.phtml`
2. Template receives story block data via `$block->getData()`
3. Create block class in `Block/Container/Element/` only if custom logic needed

## Code Conventions

### PHP Standards
- `declare(strict_types=1);` required
- Constructor property promotion with readonly: `private readonly Type $property`
- Type hints on all parameters and returns

### Class Organization
- Public API at top of files
- Private/protected helper methods at bottom

### Caching
- Cache key format: `{slug}_{language}`
- Cache tags: `storyblok_{story_id}`
- Bypass cache with `?_storyblok=1` parameter

### Testing
- Tests in `Test/Unit/` mirror source structure
- Mock all external dependencies
- Test data in `Test/Unit/_files/`

## Configuration Paths

Key config values accessed via `Model/Config`:
- `storyblok/general/api_key` - API token
- `storyblok/general/language` - Language code
- `storyblok/general/slug_prefix` - URL prefix
- `storyblok/home_page/home_slug` - Homepage slug
- `storyblok/general/asset_proxy_enabled` - Enable/disable asset proxy
- `storyblok/general/asset_hosts` - Comma-separated Storyblok CDN hostnames
