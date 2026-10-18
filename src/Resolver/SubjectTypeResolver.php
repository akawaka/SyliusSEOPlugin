<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

final class SubjectTypeResolver
{
    /** @param array<string, array{class: string, title_template: string, description_template: ?string}> $subjects */
    public function __construct(private readonly array $subjects)
    {
    }

    public function resolveType(object $subject): ?string
    {
        foreach ($this->subjects as $type => $definition) {
            if ($subject instanceof $definition['class']) {
                return $type;
            }
        }

        return null;
    }

    /** @return array{title_template: string, description_template: ?string} */
    public function templatesFor(string $type): array
    {
        return [
            'title_template' => $this->subjects[$type]['title_template'],
            'description_template' => $this->subjects[$type]['description_template'],
        ];
    }
}
