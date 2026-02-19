<?php

namespace XLinic\Framework\Core\Sequence;

use InvalidArgumentException;

/**
 * Sequence Definition Value Object
 *
 * Represents the definition and configuration for an auto-incrementing
 * sequence used for generating sequential numbers, codes, or identifiers.
 * Supports various patterns, padding, and tenant-aware sequences.
 *
 * @package XLinic\Framework\Core\Sequence
 */
class SequenceDefinition
{
    /**
     * Sequence types
     */
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_ALPHA = 'alpha';
    public const TYPE_ALPHANUMERIC = 'alphanumeric';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Reset intervals
     */
    public const RESET_NEVER = 'never';
    public const RESET_YEARLY = 'yearly';
    public const RESET_MONTHLY = 'monthly';
    public const RESET_DAILY = 'daily';
    public const RESET_CUSTOM = 'custom';

    /**
     * Create a new sequence definition
     */
    public function __construct(
        protected string $key,
        protected string $name,
        protected string $type = self::TYPE_NUMERIC,
        protected string $prefix = '',
        protected string $suffix = '',
        protected int $padding = 4,
        protected int $startValue = 1,
        protected int $increment = 1,
        protected string $resetInterval = self::RESET_NEVER,
        protected ?string $resetPattern = null,
        protected bool $tenantAware = true,
        protected ?string $module = null,
        protected array $patterns = [],
        protected array $conditions = [],
        protected array $metadata = [],
        protected bool $active = true,
        protected ?string $description = null
    ) {
        $this->validate();
    }

    /**
     * Get the sequence key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the sequence name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the sequence type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the sequence prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the sequence suffix
     */
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Get the padding length
     */
    public function getPadding(): int
    {
        return $this->padding;
    }

    /**
     * Get the start value
     */
    public function getStartValue(): int
    {
        return $this->startValue;
    }

    /**
     * Get the increment value
     */
    public function getIncrement(): int
    {
        return $this->increment;
    }

    /**
     * Get the reset interval
     */
    public function getResetInterval(): string
    {
        return $this->resetInterval;
    }

    /**
     * Get the reset pattern
     */
    public function getResetPattern(): ?string
    {
        return $this->resetPattern;
    }

    /**
     * Check if sequence is tenant-aware
     */
    public function isTenantAware(): bool
    {
        return $this->tenantAware;
    }

    /**
     * Get the module this sequence belongs to
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * Get custom patterns
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Get sequence conditions
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if sequence is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get sequence description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Generate the full sequence pattern
     */
    public function generatePattern(array $context = []): string
    {
        $pattern = '';

        // Add prefix
        if (!empty($this->prefix)) {
            $pattern .= $this->processPlaceholders($this->prefix, $context);
        }

        // Add sequence number placeholder
        $pattern .= $this->getSequencePlaceholder();

        // Add suffix
        if (!empty($this->suffix)) {
            $pattern .= $this->processPlaceholders($this->suffix, $context);
        }

        return $pattern;
    }

    /**
     * Format a sequence number according to the definition
     */
    public function formatNumber(int $number, array $context = []): string
    {
        $formatted = '';

        // Add prefix
        if (!empty($this->prefix)) {
            $formatted .= $this->processPlaceholders($this->prefix, $context);
        }

        // Add formatted number
        $formatted .= $this->formatSequenceNumber($number);

        // Add suffix
        if (!empty($this->suffix)) {
            $formatted .= $this->processPlaceholders($this->suffix, $context);
        }

        return $formatted;
    }

    /**
     * Check if sequence should reset based on context
     */
    public function shouldReset(array $context = []): bool
    {
        if ($this->resetInterval === self::RESET_NEVER) {
            return false;
        }

        $lastReset = $context['last_reset'] ?? null;

        if (!$lastReset) {
            return false;
        }

        $lastResetTime = is_string($lastReset) ? strtotime($lastReset) : $lastReset;

        return match ($this->resetInterval) {
            self::RESET_YEARLY => date('Y', $lastResetTime) < date('Y'),
            self::RESET_MONTHLY => date('Y-m', $lastResetTime) < date('Y-m'),
            self::RESET_DAILY => date('Y-m-d', $lastResetTime) < date('Y-m-d'),
            self::RESET_CUSTOM => $this->evaluateCustomReset($context),
            default => false
        };
    }

    /**
     * Get the reset key for context-based resets
     */
    public function getResetKey(array $context = []): string
    {
        $key = $this->key;

        if ($this->tenantAware && !empty($context['tenant_id'])) {
            $key .= ':' . $context['tenant_id'];
        }

        return match ($this->resetInterval) {
            self::RESET_YEARLY => $key . ':' . date('Y'),
            self::RESET_MONTHLY => $key . ':' . date('Y-m'),
            self::RESET_DAILY => $key . ':' . date('Y-m-d'),
            self::RESET_CUSTOM => $key . ':' . $this->getCustomResetKey($context),
            default => $key
        };
    }

    /**
     * Parse a formatted sequence value back to components
     */
    public function parseSequence(string $value): array
    {
        $components = [
            'prefix' => '',
            'number' => 0,
            'suffix' => '',
            'full' => $value,
        ];

        $pattern = $this->generateRegexPattern();

        if (preg_match($pattern, $value, $matches)) {
            $components['prefix'] = $matches['prefix'] ?? '';
            $components['number'] = (int) ($matches['number'] ?? 0);
            $components['suffix'] = $matches['suffix'] ?? '';
        }

        return $components;
    }

    /**
     * Validate sequence conditions against context
     */
    public function validateConditions(array $context = []): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a definition from array
     */
    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            name: $definition['name'] ?? $key,
            type: $definition['type'] ?? self::TYPE_NUMERIC,
            prefix: $definition['prefix'] ?? '',
            suffix: $definition['suffix'] ?? '',
            padding: $definition['padding'] ?? 4,
            startValue: $definition['start_value'] ?? 1,
            increment: $definition['increment'] ?? 1,
            resetInterval: $definition['reset_interval'] ?? self::RESET_NEVER,
            resetPattern: $definition['reset_pattern'] ?? null,
            tenantAware: $definition['tenant_aware'] ?? true,
            module: $definition['module'] ?? null,
            patterns: $definition['patterns'] ?? [],
            conditions: $definition['conditions'] ?? [],
            metadata: $definition['metadata'] ?? [],
            active: $definition['active'] ?? true,
            description: $definition['description'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'padding' => $this->padding,
            'start_value' => $this->startValue,
            'increment' => $this->increment,
            'reset_interval' => $this->resetInterval,
            'reset_pattern' => $this->resetPattern,
            'tenant_aware' => $this->tenantAware,
            'module' => $this->module,
            'patterns' => $this->patterns,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
            'active' => $this->active,
            'description' => $this->description,
        ];
    }

    /**
     * Validate the sequence definition
     */
    protected function validate(): void
    {
        if (empty($this->key)) {
            throw new InvalidArgumentException('Sequence key cannot be empty');
        }

        if (empty($this->name)) {
            throw new InvalidArgumentException('Sequence name cannot be empty');
        }

        if (!in_array($this->type, [self::TYPE_NUMERIC, self::TYPE_ALPHA, self::TYPE_ALPHANUMERIC, self::TYPE_CUSTOM])) {
            throw new InvalidArgumentException('Invalid sequence type');
        }

        if ($this->padding < 1) {
            throw new InvalidArgumentException('Padding must be at least 1');
        }

        if ($this->startValue < 0) {
            throw new InvalidArgumentException('Start value cannot be negative');
        }

        if ($this->increment <= 0) {
            throw new InvalidArgumentException('Increment must be positive');
        }

        if (!in_array($this->resetInterval, [
            self::RESET_NEVER,
            self::RESET_YEARLY,
            self::RESET_MONTHLY,
            self::RESET_DAILY,
            self::RESET_CUSTOM
        ])) {
            throw new InvalidArgumentException('Invalid reset interval');
        }
    }

    /**
     * Get sequence placeholder for patterns
     */
    protected function getSequencePlaceholder(): string
    {
        return '{sequence}';
    }

    /**
     * Process placeholders in prefix/suffix
     */
    protected function processPlaceholders(string $text, array $context = []): string
    {
        $processed = $text;

        // Date placeholders
        $processed = str_replace('{YYYY}', date('Y'), $processed);
        $processed = str_replace('{YY}', date('y'), $processed);
        $processed = str_replace('{MM}', date('m'), $processed);
        $processed = str_replace('{DD}', date('d'), $processed);
        $processed = str_replace('{HH}', date('H'), $processed);
        $processed = str_replace('{II}', date('i'), $processed);
        $processed = str_replace('{SS}', date('s'), $processed);

        // Context placeholders
        foreach ($context as $key => $value) {
            $placeholder = '{' . strtoupper($key) . '}';
            $processed = str_replace($placeholder, (string) $value, $processed);
        }

        // Custom pattern processing
        foreach ($this->patterns as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $processed = $replacement($processed, $context);
            } else {
                $processed = str_replace($pattern, $replacement, $processed);
            }
        }

        return $processed;
    }

    /**
     * Format sequence number based on type
     */
    protected function formatSequenceNumber(int $number): string
    {
        return match ($this->type) {
            self::TYPE_NUMERIC => str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT),
            self::TYPE_ALPHA => $this->numberToAlpha($number),
            self::TYPE_ALPHANUMERIC => $this->numberToAlphaNumeric($number),
            self::TYPE_CUSTOM => $this->customFormat($number),
            default => (string) $number
        };
    }

    /**
     * Convert number to alphabetic representation
     */
    protected function numberToAlpha(int $number): string
    {
        $alpha = '';
        while ($number > 0) {
            $number--;
            $alpha = chr(65 + ($number % 26)) . $alpha;
            $number = intval($number / 26);
        }

        return str_pad($alpha, $this->padding, 'A', STR_PAD_LEFT);
    }

    /**
     * Convert number to alphanumeric representation
     */
    protected function numberToAlphaNumeric(int $number): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($chars);
        $result = '';

        while ($number > 0) {
            $result = $chars[$number % $base] . $result;
            $number = intval($number / $base);
        }

        return str_pad($result ?: '0', $this->padding, '0', STR_PAD_LEFT);
    }

    /**
     * Custom number formatting
     */
    protected function customFormat(int $number): string
    {
        // This can be extended for custom formatting logic
        return str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }

    /**
     * Generate regex pattern for parsing
     */
    protected function generateRegexPattern(): string
    {
        $prefixPattern = $this->prefix ? '(?P<prefix>' . preg_quote($this->prefix, '/') . ')' : '';
        $numberPattern = '(?P<number>\d+)';
        $suffixPattern = $this->suffix ? '(?P<suffix>' . preg_quote($this->suffix, '/') . ')' : '';

        return '/^' . $prefixPattern . $numberPattern . $suffixPattern . '$/';
    }

    /**
     * Evaluate custom reset condition
     */
    protected function evaluateCustomReset(array $context): bool
    {
        if (!$this->resetPattern) {
            return false;
        }

        // This can be extended for custom reset logic
        return false;
    }

    /**
     * Get custom reset key
     */
    protected function getCustomResetKey(array $context): string
    {
        if (!$this->resetPattern) {
            return 'custom';
        }

        // This can be extended for custom reset key generation
        return $this->processPlaceholders($this->resetPattern, $context);
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        $contextValue = $context[$field] ?? null;

        return match ($operator) {
            '=' => $contextValue == $value,
            '!=' => $contextValue != $value,
            '>' => $contextValue > $value,
            '>=' => $contextValue >= $value,
            '<' => $contextValue < $value,
            '<=' => $contextValue <= $value,
            'in' => is_array($value) && in_array($contextValue, $value),
            'not_in' => is_array($value) && !in_array($contextValue, $value),
            'exists' => isset($context[$field]),
            'not_exists' => !isset($context[$field]),
            default => true
        };
    }
}