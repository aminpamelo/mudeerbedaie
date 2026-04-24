<?php

namespace App\Services\Recruitment;

use App\Exceptions\Recruitment\InvalidFormSchemaException;

class FormSchemaValidator
{
    public const FIELD_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number', 'url',
        'select', 'radio', 'checkbox_group',
        'file', 'date', 'datetime',
        'heading', 'paragraph',
    ];

    public const DATA_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number', 'url',
        'select', 'radio', 'checkbox_group',
        'file', 'date', 'datetime',
    ];

    public const CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];

    public const ROLE_COMPAT = [
        'name' => ['text'],
        'email' => ['email'],
        'phone' => ['phone'],
        'resume' => ['file'],
        'platforms' => ['checkbox_group'],
    ];

    public function validate(array $schema): void
    {
        $errors = [];

        if (($schema['version'] ?? null) !== 1) {
            $errors[] = 'schema.version must be 1';
        }

        $pages = $schema['pages'] ?? null;
        if (! is_array($pages) || count($pages) === 0) {
            $errors[] = 'schema.pages must be a non-empty array';
            throw new InvalidFormSchemaException($errors);
        }

        $pageIds = [];
        $fieldIds = [];
        $rolesSeen = [];
        $hasDataField = false;

        foreach ($pages as $pi => $page) {
            $pid = $page['id'] ?? null;
            if (! $pid) {
                $errors[] = "page[{$pi}].id is required";
            } elseif (in_array($pid, $pageIds, true)) {
                $errors[] = "page[{$pi}].id '{$pid}' is duplicated";
            } else {
                $pageIds[] = $pid;
            }

            if (empty($page['title'])) {
                $errors[] = "page[{$pi}].title is required";
            }

            foreach (($page['fields'] ?? []) as $fi => $field) {
                $fid = $field['id'] ?? null;
                $type = $field['type'] ?? null;

                if (! $fid) {
                    $errors[] = "page[{$pi}].field[{$fi}].id is required";

                    continue;
                }
                if (in_array($fid, $fieldIds, true)) {
                    $errors[] = "field id '{$fid}' is duplicated";
                }
                $fieldIds[] = $fid;

                if (! in_array($type, self::FIELD_TYPES, true)) {
                    $errors[] = "field '{$fid}' has invalid type '{$type}'";

                    continue;
                }

                $isDisplayOnly = in_array($type, ['heading', 'paragraph'], true);

                if ($isDisplayOnly) {
                    if (empty($field['text'])) {
                        $errors[] = "field '{$fid}' of type {$type} requires 'text'";
                    }
                } else {
                    $hasDataField = true;
                    if (empty($field['label'])) {
                        $errors[] = "field '{$fid}' label is required";
                    }

                    if (in_array($type, self::CHOICE_TYPES, true)) {
                        $opts = $field['options'] ?? [];
                        if (! is_array($opts) || count($opts) === 0) {
                            $errors[] = "field '{$fid}' must have at least one option";
                        } else {
                            $values = [];
                            foreach ($opts as $oi => $opt) {
                                if (empty($opt['value'])) {
                                    $errors[] = "field '{$fid}' option[{$oi}] value is required";
                                } elseif (in_array($opt['value'], $values, true)) {
                                    $errors[] = "field '{$fid}' option value '{$opt['value']}' is duplicated";
                                } else {
                                    $values[] = $opt['value'];
                                }
                                if (empty($opt['label'])) {
                                    $errors[] = "field '{$fid}' option[{$oi}] label is required";
                                }
                            }
                        }
                    }

                    $role = $field['role'] ?? null;
                    if ($role !== null && $role !== '') {
                        if (! isset(self::ROLE_COMPAT[$role])) {
                            $errors[] = "field '{$fid}' has unknown role '{$role}'";
                        } else {
                            if (isset($rolesSeen[$role])) {
                                $errors[] = "role '{$role}' is used by more than one field (also on '{$rolesSeen[$role]}')";
                            } else {
                                $rolesSeen[$role] = $fid;
                            }
                            if (! in_array($type, self::ROLE_COMPAT[$role], true)) {
                                $errors[] = "field '{$fid}' role '{$role}' is incompatible with type '{$type}'";
                            }
                        }
                    }
                }
            }
        }

        if (! $hasDataField) {
            $errors[] = 'schema must contain at least one data-collecting field';
        }

        if (! isset($rolesSeen['email'])) {
            $errors[] = 'schema must contain exactly one field with role "email"';
        }

        if ($errors !== []) {
            throw new InvalidFormSchemaException($errors);
        }
    }
}
