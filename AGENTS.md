See @CLAUDE.md

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
