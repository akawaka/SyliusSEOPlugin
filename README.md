<p align="center">
    <img src="doc/logo-akawaka.png" style="width: 500px;">
</p>

<p align="center">
AKAWAKA is a software development company specializing in e-commerce platforms and custom web applications.
We help businesses evolve or rebuild their platforms with a pragmatic approach: improving existing solutions or starting from scratch, notably with Sylius.
Our team also handles data migration, SEO preservation, and business continuity.
With strong expertise in PHP and Symfony, Akawaka builds web solutions tailored to our clients’ business needs.
</p>

<h1 align="center">Akawaka Sylius SEO Plugin</h1>

<p align="center">Meta tags, canonical, <code>hreflang</code>, Open Graph and Twitter cards for <strong>Sylius 2.x</strong>, with per-entity overrides and configurable default templates.</p>

## Features

- **Meta title and description** per product and taxon, translated per locale, edited in an **SEO tab** of the admin forms with a search result (SERP) preview and character counters.
- **Default templates** per subject type (`{name} | {channel}`), used whenever a field is empty; the description is stripped of HTML and truncated.
- **Canonical URL** (current URL by default, overridable) and **robots** (`index`/`noindex`, `follow`/`nofollow`).
- **`hreflang` alternates** for every locale enabled in the channel.
- **Open Graph and Twitter cards**, with the first product image as default picture.
- **JSON-LD** structured data: `Organization`, `BreadcrumbList`, `Product` with `Offer` / `AggregateOffer` and `AggregateRating`.
- **XML sitemap** (`/sitemap.xml`): paginated product and taxon sections with `hreflang` alternates and images; `noindex` pages are left out.
- **`robots.txt`** per channel (`/robots.txt`), editable in the channel form, with a default content and an automatic `Sitemap:` line.
- **Redirections** 301/302 managed in **SEO → Redirections**, applied when a URL answers 404, with hit counters. A 301 is created automatically when the slug of a product or taxon changes (existing chains are collapsed).
- **Image alt texts** (**SEO → Image alt texts**): list the product or taxon images of a locale, filter those without alt text and edit them in bulk. They are applied to the storefront `<img>` tags whatever the theme, by matching the image file, so no Sylius template is overridden.
- **SEO audit** (**SEO → SEO audit**): missing, too short or too long titles and descriptions, duplicated titles, `noindex` pages, products without image or with images lacking alt text, per channel and locale.
- **Akawaka CMS plugin integration** (optional, see [CMS pages](#cms-pages)).
- Rendered automatically in the `<head>` of the Sylius shop through a twig hook. Translations: English and French.


## Requirements

- PHP 8.2+
- Sylius 2.0+

## Installation

Add the [Akawaka Flex recipes](https://github.com/akawaka/symfony-recipes) endpoint to your project, then require the plugin:

```bash
composer config --json extra.symfony.endpoint '["https://api.github.com/repos/akawaka/symfony-recipes/contents/index.json?ref=flex/main","flex://defaults"]'
composer require akawaka/sylius-seo-plugin
```

Symfony Flex registers the bundle and creates `config/packages/akawaka_seo.yaml` and `config/routes/akawaka_seo.yaml` (see [Manual installation](#manual-installation-without-flex) for their content).

If `public/robots.txt` or `public/sitemap.xml` exist, the web server serves them instead of the plugin: remove them.

Then update the database:

```bash
bin/console doctrine:migrations:migrate
```

### Manual installation (without Flex)

Replace the first step above with:

```bash
composer require akawaka/sylius-seo-plugin
```

Register the bundle and import the configuration:

```php
// config/bundles.php
return [
    // ...
    Akawaka\SyliusSEOPlugin\AkawakaSyliusSEOPlugin::class => ['all' => true],
];
```

```yaml
# config/packages/akawaka_seo.yaml
imports:
    - { resource: '@AkawakaSyliusSEOPlugin/config/config.yaml' }
```

```yaml
# config/routes/akawaka_seo.yaml
akawaka_seo_admin:
    resource: '@AkawakaSyliusSEOPlugin/config/routes/admin.yaml'

akawaka_seo_shop:
    resource: '@AkawakaSyliusSEOPlugin/config/routes/shop.yaml'
```

## Configuration

```yaml
# config/packages/akawaka_seo.yaml
akawaka_sylius_seo:
    description_max_length: 160
    sitemap:
        page_size: 5000   # URLs per sitemap section (max 50000)
    subjects:
        product:
            class: Sylius\Component\Core\Model\ProductInterface
            title_template: '{name} | {channel}'
            description_template: '{description}'
        taxon:
            class: Sylius\Component\Core\Model\TaxonInterface
            title_template: '{name} | {channel}'
            description_template: '{description}'
```

Available placeholders: `{name}`, `{description}`, `{channel}` and any property of the entity through `{subject.code}`; unknown placeholders render empty.

## CMS pages

If the [Akawaka Sylius CMS plugin](https://github.com/akawaka/sylius-cms-plugin) is installed, this plugin covers its pages too. Nothing is required: the integration is enabled when the CMS bundle is registered, and the SEO plugin never depends on it.

- the published pages are listed in the sitemap (`/sitemap-page-N.xml`) with their `hreflang` alternates;
- the audit checks their meta title and description (the CMS fields) and links to the CMS page editor;
- changing the slug of a page creates a 301 redirect;
- `Organization` and `BreadcrumbList` JSON-LD are added to the page.

The SEO fields of a page (meta title, description, Open Graph image) stay edited in the CMS plugin, which also prints the meta tags, canonical and `hreflang` of its pages: this plugin does not duplicate them.

## Theming

The tags are rendered by `templates/shop/head.html.twig`. Override it in your application:

```
templates/bundles/AkawakaSyliusSEOPlugin/shop/head.html.twig
```

## Development

```bash
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console doctrine:database:create && vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n
symfony server:start        # the test application, admin at /admin

vendor/bin/phpunit --testsuite Unit   # PHP unit tests
# CmsIntegrationTest runs only when akawaka/sylius-cms-plugin is installed (skipped otherwise)
APP_ENV=test vendor/bin/phpunit --testsuite Functional   # needs the test database (see below)
vendor/bin/ecs check        # coding standard
vendor/bin/phpstan analyse -c phpstan.neon   # level max
```

With Docker: `cp compose.override.dist.yml compose.override.yml && docker compose up -d`, then run the commands above inside the `php` container (`docker compose exec php sh`).

The functional tests boot the test application and use the `test` database (`DATABASE_URL` in `tests/TestApplication/.env.test`, override it in your environment). Create it once with `APP_ENV=test vendor/bin/console doctrine:database:create && APP_ENV=test vendor/bin/console doctrine:migrations:migrate -n && APP_ENV=test vendor/bin/console sylius:fixtures:load -n`.

Project conventions are listed in [`AGENTS.md`](AGENTS.md).

## Roadmap

1. Audit: catalog pagination, scheduled run and export.
2. Alt texts: edit them directly in the product media tab and reuse them in the sitemap image entries.

## License

MIT.
