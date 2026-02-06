<?php

declare(strict_types=1);

namespace App\Services\MergeTag;

use App\Services\MergeTag\DataProviders\CartDataProvider;
use App\Services\MergeTag\DataProviders\ContactDataProvider;
use App\Services\MergeTag\DataProviders\FunnelDataProvider;
use App\Services\MergeTag\DataProviders\OrderDataProvider;
use App\Services\MergeTag\DataProviders\PaymentDataProvider;
use App\Services\MergeTag\DataProviders\SessionDataProvider;
use App\Services\MergeTag\DataProviders\SystemDataProvider;
use Carbon\Carbon;

class MergeTagEngine
{
    protected array $context = [];

    protected array $dataProviders = [];

    public function __construct()
    {
        $this->dataProviders = [
            'contact' => new ContactDataProvider,
            'order' => new OrderDataProvider,
            'cart' => new CartDataProvider,
            'funnel' => new FunnelDataProvider,
            'payment' => new PaymentDataProvider,
            'session' => new SessionDataProvider,
            'system' => new SystemDataProvider,
        ];
    }

    /**
     * Set the context data for variable resolution.
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add data to the existing context.
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Get the current context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Replace all merge tags in the given text.
     *
     * Pattern supports:
     * - {{variable}} - simple variable
     * - {{category.field}} - dotted notation
     * - {{variable|modifier}} - with modifier
     * - {{variable|modifier:"value"}} - with modifier and argument
     */
    public function resolve(string $text): string
    {
        // Pattern: {{variable}} or {{variable|modifier:"value"}}
        $pattern = '/\{\{([a-z_][a-z0-9_\.]*(?:\[\d+\])?(?:\|[a-z_]+(?::"[^"]*")?)*)\}\}/i';

        return preg_replace_callback($pattern, function ($matches) {
            return $this->resolveVariable($matches[1]);
        }, $text);
    }

    /**
     * Resolve a single variable.
     */
    protected function resolveVariable(string $variable): string
    {
        // Parse modifiers (e.g., |default:"Guest"|upper)
        $parts = explode('|', $variable);
        $variablePath = array_shift($parts);
        $modifiers = $parts;

        // Get the value from data providers
        $value = $this->getValueFromPath($variablePath);

        // Apply modifiers
        foreach ($modifiers as $modifier) {
            $value = $this->applyModifier($value, $modifier);
        }

        return $value ?? '';
    }

    /**
     * Get value from dot-notation path.
     *
     * Examples:
     * - contact.name -> ContactDataProvider::getValue('name')
     * - order.items[0].name -> OrderDataProvider::getValue('items.0.name')
     * - current_date -> SystemDataProvider::getValue('current_date')
     */
    protected function getValueFromPath(string $path): ?string
    {
        // Handle array access like order.items[0].name -> order.items.0.name
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path);
        $segments = explode('.', $path);

        $category = array_shift($segments);
        $field = implode('.', $segments);

        // Check if it's a known category with a data provider
        if (isset($this->dataProviders[$category])) {
            return $this->dataProviders[$category]->getValue($field, $this->context);
        }

        // Fallback: treat as system variable (e.g., current_date)
        if (empty($segments)) {
            return $this->dataProviders['system']->getValue($category, $this->context);
        }

        // Try direct context access
        return $this->getFromContext($path);
    }

    /**
     * Get value directly from context array.
     */
    protected function getFromContext(string $path): ?string
    {
        $segments = explode('.', $path);
        $value = $this->context;

        foreach ($segments as $segment) {
            if (is_array($value) && isset($value[$segment])) {
                $value = $value[$segment];
            } elseif (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
            } else {
                return null;
            }
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Apply a modifier to a value.
     *
     * Supported modifiers:
     * - default:"value" - fallback if empty
     * - format:"pattern" - date/number formatting
     * - upper - uppercase
     * - lower - lowercase
     * - ucfirst - capitalize first letter
     * - trim - trim whitespace
     */
    protected function applyModifier(?string $value, string $modifier): ?string
    {
        // Parse modifier and argument
        if (preg_match('/^([a-z_]+)(?::"([^"]*)")?$/i', $modifier, $matches)) {
            $modifierName = strtolower($matches[1]);
            $argument = $matches[2] ?? null;

            return match ($modifierName) {
                'default' => empty($value) ? $argument : $value,
                'format' => $this->formatValue($value, $argument),
                'upper' => $value !== null ? mb_strtoupper($value) : null,
                'lower' => $value !== null ? mb_strtolower($value) : null,
                'ucfirst' => $value !== null ? ucfirst($value) : null,
                'trim' => $value !== null ? trim($value) : null,
                default => $value,
            };
        }

        return $value;
    }

    /**
     * Format a value using the given pattern.
     */
    protected function formatValue(?string $value, ?string $pattern): ?string
    {
        if ($value === null || $pattern === null) {
            return $value;
        }

        // Try to parse as date
        try {
            $date = Carbon::parse($value);

            return $date->format($pattern);
        } catch (\Exception) {
            // Not a date, try number formatting
        }

        // Try number formatting
        if (is_numeric($value)) {
            // Pattern like "0.00" means 2 decimal places
            if (preg_match('/^0*(\.0+)?$/', $pattern)) {
                $decimals = strlen($pattern) - strpos($pattern, '.') - 1;
                if ($decimals < 0) {
                    $decimals = 0;
                }

                return number_format((float) $value, $decimals);
            }
        }

        return $value;
    }

    /**
     * Extract all merge tag variables from text.
     */
    public function extractVariables(string $text): array
    {
        preg_match_all('/\{\{([a-z_][a-z0-9_\.]*(?:\[\d+\])?)\}\}/i', $text, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Validate that all merge tags in text are valid for the given trigger.
     */
    public function validateForTrigger(string $text, string $triggerType): array
    {
        $availableVariables = VariableRegistry::getVariablesForTrigger($triggerType);
        $usedVariables = $this->extractVariables($text);
        $errors = [];

        foreach ($usedVariables as $variable) {
            if (! $this->isVariableAvailable($variable, $availableVariables)) {
                $errors[] = [
                    'variable' => $variable,
                    'message' => "Variable '{{$variable}}' is not available for trigger '$triggerType'",
                ];
            }
        }

        return $errors;
    }

    /**
     * Check if a variable is available in the given variable list.
     */
    protected function isVariableAvailable(string $variable, array $availableVariables): bool
    {
        // Handle array access notation
        $normalizedVariable = preg_replace('/\[\d+\]/', '', $variable);

        foreach ($availableVariables as $category => $data) {
            if (! isset($data['variables'])) {
                continue;
            }

            foreach (array_keys($data['variables']) as $availableVar) {
                // Exact match
                if ($availableVar === $variable || $availableVar === $normalizedVariable) {
                    return true;
                }

                // Wildcard match for array items (e.g., order.items.*.name)
                $pattern = str_replace('*', '\d+', preg_quote($availableVar, '/'));
                if (preg_match('/^'.$pattern.'$/', $normalizedVariable)) {
                    return true;
                }
            }
        }

        // Check system variables (no category prefix)
        if (isset($availableVariables['system']['variables'][$variable])) {
            return true;
        }

        return false;
    }

    /**
     * Preview merge tags with example data.
     */
    public function preview(string $text): string
    {
        $pattern = '/\{\{([a-z_][a-z0-9_\.]*(?:\[\d+\])?(?:\|[a-z_]+(?::"[^"]*")?)*)\}\}/i';

        return preg_replace_callback($pattern, function ($matches) {
            $variable = explode('|', $matches[1])[0];
            $example = VariableRegistry::getExampleValue($variable);

            return $example ?? $matches[0];
        }, $text);
    }
}
