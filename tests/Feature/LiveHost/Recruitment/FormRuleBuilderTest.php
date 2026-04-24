<?php

use App\Services\Recruitment\FormRuleBuilder;
use App\Support\Recruitment\DefaultFormSchema;
use Illuminate\Validation\Rules\In;

/**
 * Stringify a rule list so tests can assert on mixed string/Rule objects.
 *
 * @param  array<int, mixed>  $rules
 * @return array<int, string>
 */
function ruleStrings(array $rules): array
{
    return array_map(
        fn ($rule) => $rule instanceof In ? (string) $rule : (string) $rule,
        $rules,
    );
}

it('produces rules for all 9 data fields from the default schema and skips display-only types', function () {
    $rules = (new FormRuleBuilder)->build(DefaultFormSchema::get());

    expect(array_keys($rules))->toEqual([
        'f_name',
        'f_email',
        'f_phone',
        'f_ic_number',
        'f_location',
        'f_platforms',
        'f_experience',
        'f_motivation',
        'f_resume',
    ]);
});

it('heading and paragraph fields produce no rules', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'h', 'type' => 'heading', 'text' => 'Hi'],
                ['id' => 'p', 'type' => 'paragraph', 'text' => 'Intro'],
                ['id' => 'f_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'role' => 'email'],
            ],
        ]],
    ];

    $rules = (new FormRuleBuilder)->build($schema);

    expect(array_keys($rules))->toEqual(['f_email']);
});

it('required field gets required and non-required gets nullable', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'f_email', 'type' => 'email', 'label' => 'E', 'required' => true, 'role' => 'email'],
                ['id' => 'f_notes', 'type' => 'text', 'label' => 'N', 'required' => false],
            ],
        ]],
    ];

    $rules = (new FormRuleBuilder)->build($schema);

    expect($rules['f_email'][0])->toBe('required');
    expect($rules['f_notes'][0])->toBe('nullable');
});

it('email type emits email and max:255', function () {
    $rules = (new FormRuleBuilder)->build(DefaultFormSchema::get());

    expect($rules['f_email'])->toContain('email');
    expect($rules['f_email'])->toContain('max:255');
});

it('file type emits mimes and max rules from accept and max_size_kb', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'f_email', 'type' => 'email', 'label' => 'E', 'required' => true, 'role' => 'email'],
                ['id' => 'f_resume', 'type' => 'file', 'label' => 'Resume', 'required' => false, 'accept' => ['pdf', 'doc'], 'max_size_kb' => 5120],
            ],
        ]],
    ];

    $rules = (new FormRuleBuilder)->build($schema);

    expect($rules['f_resume'])->toContain('file');
    expect($rules['f_resume'])->toContain('mimes:pdf,doc');
    expect($rules['f_resume'])->toContain('max:5120');
});

it('checkbox_group emits array, and buildArrayItemRules emits field.* with in constraint', function () {
    $schema = DefaultFormSchema::get();
    $builder = new FormRuleBuilder;
    $rules = $builder->build($schema);
    $itemRules = $builder->buildArrayItemRules($schema);

    expect($rules['f_platforms'])->toContain('array');
    expect($itemRules)->toHaveKey('f_platforms.*');

    $stringified = ruleStrings($itemRules['f_platforms.*']);
    expect($stringified)->toContain('string');
    expect($stringified)->toContain('in:"tiktok","shopee","facebook"');
});

it('select and radio emit in rules with comma-separated values', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'f_email', 'type' => 'email', 'label' => 'E', 'required' => true, 'role' => 'email'],
                ['id' => 'f_drop', 'type' => 'select', 'label' => 'D', 'options' => [
                    ['value' => 'a', 'label' => 'A'],
                    ['value' => 'b', 'label' => 'B'],
                ]],
                ['id' => 'f_radio', 'type' => 'radio', 'label' => 'R', 'options' => [
                    ['value' => 'x', 'label' => 'X'],
                    ['value' => 'y', 'label' => 'Y'],
                ]],
            ],
        ]],
    ];

    $rules = (new FormRuleBuilder)->build($schema);

    expect(ruleStrings($rules['f_drop']))->toContain('in:"a","b"');
    expect(ruleStrings($rules['f_radio']))->toContain('in:"x","y"');
});
