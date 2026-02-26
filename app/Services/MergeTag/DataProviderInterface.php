<?php

declare(strict_types=1);

namespace App\Services\MergeTag;

interface DataProviderInterface
{
    /**
     * Get a value for the given field from the context.
     *
     * @param  string  $field  The field name (e.g., 'name', 'items.0.name')
     * @param  array  $context  The context data containing models and values
     * @return string|null The resolved value or null if not found
     */
    public function getValue(string $field, array $context): ?string;
}
