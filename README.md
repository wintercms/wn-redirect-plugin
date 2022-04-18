# Redirect plugin

![Banner](https://github.com/wintercms/wn-redirect-plugin/raw/main/.github/banner.png?raw=true)

Manage all your HTTP redirects with an easy to use GUI. This is an essential SEO plugin. With this plugin installed you can manage redirects directly from Winter's beautiful interface. Many webmasters and SEO specialists use redirects to optimise their website for search engines.

[![Version](https://img.shields.io/github/v/release/wintercms/wn-redirect-plugin?sort=semver&style=flat-square)](https://github.com/wintercms/wn-redirect-plugin/releases)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/winter/wn-redirect-plugin?style=flat-square)
[![License](https://img.shields.io/github/license/wintercms/wn-redirect-plugin?label=open%20source&style=flat-square)](https://packagist.org/packages/winter/wn-redirect-plugin)
[![Discord](https://img.shields.io/discord/816852513684193281?label=discord&style=flat-square)](https://discord.gg/D5MFSPH6Ux)

## Features

* **Quick** matching algorithm
* A **test** utility for redirects
* Matching using **placeholders** (dynamic paths)
* Matching using **regular expressions**
* **Exact** path matching
* **Importing** and **exporting** redirect rules
* **Schedule** redirects (e.g. active for 2 months)
* Redirect to **external** URLs
* Redirect to **internal** CMS pages
* Redirect to relative or absolute URLs
* Redirect **log**
* **Categorize** redirects
* **Statistics**
    * Hits per redirect
    * Popular redirects per month (top 10)
    * Popular crawlers per month (top 10)
    * Number of redirects per month
    * And more...
* Multilingual ***(Need help translating!)***
* Supports MySQL, SQLite and Postgres
* HTTP status codes 301, 302, 303, 404, 410
* Caching

## History

- 2016: Originally built by Alwin Drenth, a Software Engineer at Van der Let & Partners.
- 2018: The plugin is re-distributed under the vendor name VDLP.Redirect (formerly known as Adrenth.Redirect).
- 2022: The plugin is forked by the Winter CMS maintainers and made available for Winter CMS as Winter.Redirect

The Winter.Redirect plugin is currently maintained by the Winter CMS maintainers and you (the open-source community).

## What does this plugin offer?

This plugin adds a 'Redirects' section to the main menu of Winter CMS. This plugin has a unique and fast matching algorithm to match your redirects before your website is being rendered.

## Requirements

* Winter CMS 1.1 or higher.
* PHP version 7.4 or higher.
* PHP extensions: `ext-curl` and `ext-json`.

## Supported database platforms

* MySQL
* Postgres
* SQLite

## Supported HTTP status codes

* `HTTP/1.1 301 Moved Permanently`
* `HTTP/1.1 302 Found`
* `HTTP/1.1 303 See Other`
* `HTTP/1.1 404 Not Found`
* `HTTP/1.1 410 Gone`

## Supported HTTP request methods

* `GET`
* `POST`
* `HEAD`

## Performance

All redirects are stored in the database and will be automatically "published" to a file which the internal redirect mechanism uses to determine if a certain request needs to be redirected. This is way faster than querying a database.

This plugin is designed to be fast and should have no negative effect on the performance of your website.

To gain maximum performance with this plugin:

* Enable redirect caching using a "in-memory" caching method (see Caching).
* Maintain your redirects frequently to keep the number of redirects as low as possible.
* Try to use placeholders to keep your number of redirect low (less redirects is better performance).

## Caching

If your website has a lot of redirects it is recommended to enable redirect caching. You can enable redirect caching in the settings panel of this plugin.

Only cache drivers which support tagged cache are supported. So driver `file` and `database` are not supported. For this plugin database and file caching do not increase performance, but can actually have a negative influence on performance. So it is recommended to use an in-memory caching solution like `memcached` or `redis`.

### How caching works

If caching is enabled (and supported) every request which is handled by this plugin will be cached. It will be stored with tag `Winter.Redirect`.

When you modify a redirect all redirect cache will be invalidated automatically. It is also possible to manually clear the cache using the 'Clear cache' button in the Backend.

## Placeholders

This plugin makes advantage of the `symfony/routing` package. So if you need more info on how to make placeholder requirements for your redirection URLs, please go to: https://symfony.com/doc/current/components/routing/introduction.html#usage

## Contribution

Please feel free to [contribute](https://github.com/wintercms/wn-redirect-plugin) to this awesome plugin.

## Questions? Need help?

If you have any question about how to use this plugin, please don't hesitate to contact us via the Winter CMS [Discord](https://discord.gg/D5MFSPH6Ux). We're happy to help you.
