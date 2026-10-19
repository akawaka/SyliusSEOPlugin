# Multi-tenant compatibility sheet

| Section | Content |
|---|---|
| Entities | `akawaka_seo_metadata` (`akawaka_seo.seo_metadata`, customer data), `akawaka_seo_image_alt` (`akawaka_seo.image_alt`, customer data), `akawaka_seo_robots_txt` (`akawaka_seo.robots_txt`, customer data, one row per channel), `akawaka_seo_redirect` (`akawaka_seo.redirect`, customer data). Each one is a `sylius_resource` with interface, factory and repository (`Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository`), is not final, has a no-argument constructor and private properties with accessors. Code is typed on the interfaces and creates objects through `akawaka_seo.factory.*`. |
| Uniqueness | `Redirect.source_path` (business, global), `ImageAlt(image_path, locale)` (business, global), `SeoMetadata(subject_type, subject_id, locale)` (business, global), `RobotsTxt.channel_code` (business, global). All are declared in the ORM mapping so that the platform can rewrite them as `(organization_id, ...)`. |
| Sequences | None. |
| SQL outside the ORM | None. Everything goes through DQL / QueryBuilder in the resource repositories (including the delete of a subject's metadata, `SeoMetadataRepository::deleteForSubject()`). |
| Cache | No application cache pool. `robots.txt` and the sitemaps send `Cache-Control: public, max-age=3600` and `Vary: Host`; a shared cache must key on the host. |
| Files | None written. The sitemap is generated in memory, `robots.txt` lives in the database. |
| Secrets | None. The bundle options (`akawaka_sylius_seo.subjects`, `description_max_length`, `sitemap.page_size`) are container parameters shared by the whole instance. |
| Webhooks | None. |
| Async / scheduled | No message, command or scheduled task. |
| Admin | Menu entry `SEO` (audit, image alt texts, redirections) added through `sylius.menu.admin.main`; the SEO tab of products, taxons and the `robots.txt` field of channels are per shop. Routes: `/admin/seo/redirects*` (resource, permission aware), `/admin/seo/audit`, `/admin/seo/image-alts`. No global (instance-wide) setting is exposed in the admin. |
| Outgoing | No outgoing call, no telemetry. |

## Known limits

- `Redirect`, `ImageAlt` and `SeoMetadata` rows have no channel column: they are scoped by the platform's organization column only. Two channels of the same tenant share them.
- `SeoMetadata` references its subject by `subject_type` + integer `subject_id` (no foreign key), and `RobotsTxt` references its channel by code.
- The canonical URL and the absolute URLs (alternates, sitemap, JSON-LD) are built from the router context of the current request, so they are only correct for the channel being browsed.
- A matched redirection increments `hits` and flushes on the request.
