<?php

namespace App\Services\Ai\DTOs;

/** Bounded planner proposals; unknown values never become invented audience facts. */
final class AiDesignIntent
{
    public const OPTIONS = [
        'goal' => ['showcase', 'conversion', 'reading', 'productivity', 'information', 'unknown'],
        'interaction' => ['pointer', 'touch', 'mixed', 'unknown'],
        'session' => ['brief', 'sustained', 'unknown'],
        'expression' => ['restrained', 'balanced', 'expressive', 'unknown'],
        'density' => ['compact', 'comfortable', 'unknown'],
        'color_family' => ['neutral', 'warm', 'cool', 'unknown'],
        'color_mode' => ['light', 'dark', 'unknown'],
    ];

    /** @param array<string, mixed> $values */
    private function __construct(private readonly array $values) {}

    public static function fromArray(array $input): self
    {
        $values = [];
        foreach (self::OPTIONS as $field => $options) {
            $value = $input[$field] ?? null;
            $values[$field] = in_array($value, $options, true) ? $value : 'unknown';
        }
        $values['audience'] = is_string($input['audience'] ?? null)
            ? mb_substr(trim($input['audience']), 0, 200) : '';
        $accent = $input['accent_hex'] ?? null;
        $values['accent_hex'] = is_string($accent) && preg_match('/^#[0-9a-fA-F]{6}$/D', $accent) === 1
            ? strtolower($accent) : null;
        $values['assumptions'] = [];
        foreach (array_slice(is_array($input['assumptions'] ?? null) ? $input['assumptions'] : [], 0, 6) as $assumption) {
            if (is_string($assumption) && trim($assumption) !== '') {
                $values['assumptions'][] = mb_substr(trim($assumption), 0, 200);
            }
        }

        return new self($values);
    }

    public function value(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** Schema and validation share the same finite vocabulary. All fields are optional. */
    public static function declaration(): array
    {
        $properties = [];
        foreach (self::OPTIONS as $field => $options) {
            $properties[$field] = ['type' => 'string', 'enum' => $options];
        }
        $properties['audience'] = ['type' => 'string', 'maxLength' => 200];
        $properties['accent_hex'] = ['type' => 'string', 'minLength' => 7, 'maxLength' => 7, 'pattern' => '^#[0-9a-fA-F]{6}$'];
        $properties['assumptions'] = ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string', 'maxLength' => 200]];

        return ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false,
            'description' => 'Optional UI intent inferred from the relevant conversation. Unknown is valid. Record unsupported choices as assumptions; never infer color preferences from demographic stereotypes.'];
    }

    /** @return array<string, array<string>> */
    public static function validationRules(): array
    {
        $rules = ['design_intent' => ['sometimes', 'array:'.implode(',', array_keys(self::declaration()['properties']))]];
        foreach (self::OPTIONS as $field => $options) {
            $rules['design_intent.'.$field] = ['sometimes', 'string', 'in:'.implode(',', $options)];
        }

        return array_merge($rules, [
            'design_intent.audience' => ['sometimes', 'string', 'max:200'],
            'design_intent.accent_hex' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'design_intent.assumptions' => ['sometimes', 'array', 'max:6'],
            'design_intent.assumptions.*' => ['required', 'string', 'max:200'],
        ]);
    }
}
