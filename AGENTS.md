# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, etc.) when working with code in this repository.

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

Sylius 2 plugin (`Akawaka\SyliusSEOPlugin`) providing SEO metadata for the storefront. See `README.md` for the feature overview.

- **Domain**: `src/Entity/SeoMetadata` (one row per subject type, subject id and locale, holding only overrides), `src/Repository`, migration in `src/Migrations`.
  Every entity (`SeoMetadata`, `ImageAlt`, `RobotsTxt`, `Redirect`) is a Sylius resource (`config/app/resources.yaml`) with an interface: type on the interface, create through `akawaka_seo.factory.*`, query through `akawaka_seo.repository.*`, never `new Entity()` or `Entity::class` in the code. See `docs/multi-tenant.md`.
- **Resolution**: `src/Resolver/SeoMetadataResolver` merges the stored overrides with the templates configured per subject type (`akawaka_sylius_seo.subjects`); `PlaceholderRenderer` fills `{name}`-style placeholders, `SubjectTypeResolver` maps an entity to its type, `AlternateUrlGenerator` builds the hreflang alternates. Result: `Model/ResolvedSeoMetadata`.
- **Storefront**: `templates/shop/head.html.twig` is attached to the `sylius_shop.base.head` twig hook by the extension and renders description, robots, canonical, hreflang, Open Graph and Twitter tags; `Twig/SeoExtension` exposes `akawaka_seo_metadata()`; `EventListener/TitleReplacementListener` swaps the `<title>` (a Twig block that a hook cannot replace).
- **Admin SEO tab**: `Form/Extension/{Product,Taxon}TypeExtension` add an unmapped `akawaka_seo` field (`Form/Type/SeoSubjectType`, one `SeoMetadataType` per locale); `Persister/SeoMetadataPersister` stashes the submitted data and writes the rows on `sylius.{product,taxon}.post_{create,update}` (`EventListener/SeoSubjectSavedListener`). Templates in `templates/admin`, attached by the extension through twig hooks. `ChannelTypeExtension` + `Robots/RobotsTxtManager` do the same for the per-channel `robots.txt`.
- **Technical SEO**: `Resolver/JsonLdBuilder` (schema.org graph), `Sitemap/SitemapGenerator` + `Controller/SitemapController`, `Controller/RobotsTxtController`; routes in `config/routes/shop.yaml`.
- **Redirections**: `Entity/Redirect` (Sylius resource `akawaka_seo.redirect`, grid in `config/app/grids.yaml`), `EventListener/NotFoundRedirectListener` (404 → redirect), `Doctrine/SlugChangeRedirectListener` (onFlush: 301 on slug change).
- **Image alt texts**: `Entity/ImageAlt` (keyed by image path and locale), `Controller/ImageAltSaveController` (AJAX save used by the inline editor of the audit page, CSRF protected), `EventListener/ImageAltListener` rewrites the `alt` of storefront `<img>` tags whose src is an imagine URL of a known path.
- **Audit**: `Audit/SeoAuditor` applies the `Enum/AuditRule` rules (each rule maps to an `Enum/AuditField` that the audit page edits inline through `Controller/SeoMetadataSaveController` → `SeoMetadataPersister::updateField()`, and image alts through `ImageAltSaveController`); `Controller/AuditController` renders `templates/admin/audit`.
- **CMS integration (optional)**: `config/cms.php` is loaded by the extension only when the `AkawakaSyliusCMSPlugin` bundle is registered; `Integration/Cms/CmsPages` reads the CMS pages by class name (no `use` of CMS classes, the CMS owns their SEO fields) and feeds the sitemap, audit, subject provider, JSON-LD and slug-change redirects through nullable services. Never add a hard dependency on the CMS plugin. `CmsIntegrationTest` is skipped when it is absent; to run it, temporarily `composer require --dev akawaka/sylius-cms-plugin` through a path repository.
- **Config**: `src/DependencyInjection` (alias `akawaka_sylius_seo`), `config/services.php`, `config/config.yaml`.
- **Test application**: `tests/TestApplication` (built on `sylius/test-application`).

- **Validation**: constraint messages live in `translations/validators.*.yaml` (the validator domain), labels and UI in `messages.*.yaml`. The unmapped SEO fields declare `validation_groups: ['Default']` (`SeoSubjectType`) because the admin forms validate with the `sylius` group only.

### Tests and quality
- `vendor/bin/phpunit` (unit tests in `tests/Unit`, functional tests in `tests/Functional` booting the test application on the `test` database — see README), `vendor/bin/ecs check`, `vendor/bin/phpstan analyse -c phpstan.neon` (level max).

# Project rules

## Constants and states

- Never use class constants (`public const X = '...'`) to represent a fixed set of values (robots directives, subject types, modes…). Use a PHP backed **Enum** instead, in `src/Enum/`.
- Doctrine columns holding such a value are mapped with `enumType:` and typed with the enum in the entity (never `string`).
- Forms use `EnumType` (or choices built from the enum cases); labels come from a `label()` method on the enum returning a translation key.
- In YAML, reference enum cases with `!php/enum Fqcn::Case->value`, never with hardcoded strings.

## SEO conventions

- Stored `SeoMetadata` only holds **overrides**: a null or blank field falls back to the configured template of the subject type. Never copy the resolved value into the database.
- All head values go through `SeoMetadataResolver`; templates (`templates/shop`) only render a `ResolvedSeoMetadata`, they contain no fallback logic.
- Keep the plugin compatible with multi-tenant platforms: entities overridable, no SQL outside the ORM, no global state.

## Workflow / state machine

- Any lifecycle status (e.g. page draft/published) is driven by the **Symfony Workflow** component, configured as a `state_machine` under `framework.workflows` in `config/app/workflow.yaml`.
- Places and transition names come from enums (`PageStatus`, `PageTransition`); the marking store is `method` on the `status` property.
- The status is never edited through a free form field: it changes only through transitions (grid `apply_transition` actions and routes using `applyStateMachineTransitionAction`, or `WorkflowInterface::apply()` in code). Guard with `$workflow->can()`.
