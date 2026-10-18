<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** Replaces `{name}` / `{subject.code}` placeholders with values from a context array. */
final class PlaceholderRenderer
{
    private PropertyAccessorInterface $accessor;

    public function __construct(?PropertyAccessorInterface $accessor = null)
    {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context): string
    {
        $rendered = preg_replace_callback(
            '/\{([a-zA-Z_][\w.]*)\}/',
            fn (array $m): string => $this->lookup($m[1], $context),
            $template,
        );

        return trim(preg_replace('/\s+/', ' ', (string) $rendered) ?? '');
    }

    /** @param array<string, mixed> $context */
    private function lookup(string $path, array $context): string
    {
        $segments = explode('.', $path);
        $root = array_shift($segments);

        if (!array_key_exists($root, $context)) {
            return '';
        }

        $value = $context[$root];

        foreach ($segments as $segment) {
            if (!is_object($value) && !is_array($value)) {
                return '';
            }

            try {
                $value = $this->accessor->getValue($value, is_array($value) ? '[' . $segment . ']' : $segment);
            } catch (ExceptionInterface) {
                return '';
            }
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
