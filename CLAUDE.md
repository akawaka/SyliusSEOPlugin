# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

```bash
# Frontend setup
(cd vendor/sylius/test-application && yarn install)
(cd vendor/sylius/test-application && yarn build)
vendor/bin/console assets:install

# Database setup
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n

# Start server
symfony server:start -d

# Database reset with fixtures / frontend rebuild / complete test app initialization
composer run database-reset
composer run frontend-clear
composer run test-app-init
```

## Architecture

Sylius 2 plugin (`Akawaka\SyliusSEOPlugin`) providing SEO metadata for the storefront. See `README.md` for the feature overview and `AGENTS.md` for the coding conventions.

- **Domain**: `src/Entity/SeoMetadata` (one row per subject type, subject id and locale, holding only overrides), `src/Repository`, migration in `src/Migrations`.
- **Resolution**: `src/Resolver/SeoMetadataResolver` merges the stored overrides with the templates configured per subject type (`akawaka_sylius_seo.subjects`); `PlaceholderRenderer` fills `{name}`-style placeholders, `SubjectTypeResolver` maps an entity to its type, `AlternateUrlGenerator` builds the hreflang alternates. Result: `Model/ResolvedSeoMetadata`.
- **Storefront**: `templates/shop/head.html.twig` is attached to the `sylius_shop.base.head` twig hook by the extension and renders description, robots, canonical, hreflang, Open Graph and Twitter tags; `Twig/SeoExtension` exposes `akawaka_seo_metadata()`; `EventListener/TitleReplacementListener` swaps the `<title>` (a Twig block that a hook cannot replace).
- **Admin SEO tab**: `Form/Extension/{Product,Taxon}TypeExtension` add an unmapped `akawaka_seo` field (`Form/Type/SeoSubjectType`, one `SeoMetadataType` per locale); `Persister/SeoMetadataPersister` stashes the submitted data and writes the rows on `sylius.{product,taxon}.post_{create,update}` (`EventListener/SeoSubjectSavedListener`). Templates in `templates/admin`, attached by the extension through twig hooks. `ChannelTypeExtension` + `Robots/RobotsTxtManager` do the same for the per-channel `robots.txt`.
- **Technical SEO**: `Resolver/JsonLdBuilder` (schema.org graph), `Sitemap/SitemapGenerator` + `Controller/SitemapController`, `Controller/RobotsTxtController`; routes in `config/routes/shop.yaml`.
- **Redirections**: `Entity/Redirect` (Sylius resource `akawaka_seo.redirect`, grid in `config/app/grids.yaml`), `EventListener/NotFoundRedirectListener` (404 → redirect), `Doctrine/SlugChangeRedirectListener` (onFlush: 301 on slug change).
- **Image alt texts**: `Entity/ImageAlt` (keyed by image path and locale), `Controller/ImageAltController` (bulk edit page, CSRF protected), `EventListener/ImageAltListener` rewrites the `alt` of storefront `<img>` tags whose src is an imagine URL of a known path.
- **Audit**: `Audit/SeoAuditor` applies the `Enum/AuditRule` rules; `Controller/AuditController` renders `templates/admin/audit`.
- **CMS integration (optional)**: `config/cms.php` is loaded by the extension only when the `AkawakaSyliusCMSPlugin` bundle is registered; `Integration/Cms/CmsPages` reads the CMS pages by class name (no `use` of CMS classes, the CMS owns their SEO fields) and feeds the sitemap, audit, subject provider, JSON-LD and slug-change redirects through nullable services. Never add a hard dependency on the CMS plugin. `CmsIntegrationTest` is skipped when it is absent; to run it, temporarily `composer require --dev akawaka/sylius-cms-plugin` through a path repository.
- **Config**: `src/DependencyInjection` (alias `akawaka_sylius_seo`), `config/services.php`, `config/config.yaml`.
- **Test application**: `tests/TestApplication` (built on `sylius/test-application`).

### Tests and quality
- `vendor/bin/phpunit` (unit tests in `tests/Unit`, functional tests in `tests/Functional` booting the test application on the `test` database — see README), `vendor/bin/ecs check`, `vendor/bin/phpstan analyse -c phpstan.neon` (level max).
