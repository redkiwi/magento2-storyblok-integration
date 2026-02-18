# Magento 2 Storyblok Integration
![Unit Tests](https://github.com/Media-Lounge/magento2-storyblok-integration/workflows/Unit%20Tests/badge.svg)
![Coding Standards](https://github.com/Media-Lounge/magento2-storyblok-integration/workflows/Coding%20Standards/badge.svg)
[![codecov](https://codecov.io/gh/Media-Lounge/magento2-storyblok-integration/branch/master/graph/badge.svg?token=5GDZEF7FMQ)](https://codecov.io/gh/Media-Lounge/magento2-storyblok-integration)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

Our Magento 2 integration allows developers and digital agencies to create content-rich pages that are easily editable using the Storyblok interface.

![Storyblok Integration Overview](https://user-images.githubusercontent.com/661330/109396266-a0e5ce80-7928-11eb-933a-2ed1e86ad42b.gif)

## Why Storyblok?

Storyblok allows you to manage content through a CMS that is intuitive and easy to use. It offers features like:

- Visual Editor
- Content Types with Blocks
- Custom Fields
- Internationalization Support
- Content Scheduling, and more!

It can be used as an **alternative to Magento Commerce's Page Builder** as it provides all of its features and, combined with our integration module, it allows for a more pleasant developer experience when it comes to creating custom blocks.

## Documentation

* [Installation](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Installation)
* [Getting Started](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Getting-Started)
* [Configuration](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Configuration)
* [Creating Blocks](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Creating-Blocks)
* [Helper Methods](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Helper-Methods)
* [Editable Sections](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Editable-Sections)
* [SEO](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/SEO)
* [Custom Fields](https://github.com/Media-Lounge/magento2-storyblok-integration/wiki/Custom-Fields)

## Asset Proxy

Optional feature that routes Storyblok asset URLs (images, PDFs, videos, etc.) through Magento, enabling Varnish/page cache to serve assets from the same domain.

### Configuration

1. Navigate to **Stores > Configuration > Media Lounge > Storyblok > General > Asset Proxy** and set to **Yes**
2. Configure **Asset Hosts** with your Storyblok CDN hostname(s) as a comma-separated list

Storyblok uses regional CDN domains. See [Storyblok Asset Documentation](https://www.storyblok.com/docs/concepts/assets) for your region's hosts. The first host is used for fetching, all are matched for URL rewriting. Examples: `a.storyblok.com` (EU), `a-us.storyblok.com` (US), `a-ap.storyblok.com` (AU).

### How It Works

When enabled, Storyblok asset URLs from configured hosts are rewritten to `{base_url}/storyblok/asset/proxy/path/{encoded_path}`. The proxy fetches the asset from the primary host and returns it with `Cache-Control: public, max-age=31536000` headers, allowing Varnish to cache the response.

Two integration points:
- **Output plugin** — Automatically rewrites all Storyblok asset URLs in Element block HTML output (images, WYSIWYG, everything)
- **`ViewModel\AssetProxy`** — For use in custom `.phtml` templates outside Storyblok blocks via `getProxiedUrl($url)`

### Cache Behavior

Proxied assets are served with a 1-year cache header. Since Storyblok asset URLs contain content hashes, they are effectively immutable — a new asset produces a new URL.

## Videos

If you want to see it in action you can watch our [YouTube playlist](https://www.youtube.com/watch?v=I8_TCyOCKAo&list=PLn3mpLgxMjLSRWmmmf0RRX8wEVgv4dZ0k).

---

### Do you want to work in projects like these?

At Media Lounge we're always looking for talented Magento developers, if you're interested feel free to [get in touch](https://twitter.com/jahvi)!

<a href="https://www.medialounge.co.uk" target="_blank">
  <img src="https://user-images.githubusercontent.com/661330/109396090-d3db9280-7927-11eb-9a96-ee1706db5779.png" alt="Media Lounge Logo" width="200" height="27" />
</a>
