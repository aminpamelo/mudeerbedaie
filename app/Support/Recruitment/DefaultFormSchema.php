<?php

namespace App\Support\Recruitment;

class DefaultFormSchema
{
    public static function get(): array
    {
        return [
            'version' => 1,
            'pages' => [
                [
                    'id' => 'page-1',
                    'title' => 'About you',
                    'fields' => [
                        ['id' => 'f_name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'role' => 'name'],
                        ['id' => 'f_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'role' => 'email'],
                        ['id' => 'f_phone', 'type' => 'phone', 'label' => 'Phone', 'required' => true, 'role' => 'phone'],
                        ['id' => 'f_ic_number', 'type' => 'text', 'label' => 'IC number', 'required' => false],
                        ['id' => 'f_location', 'type' => 'text', 'label' => 'Location', 'required' => false],
                    ],
                ],
                [
                    'id' => 'page-2',
                    'title' => 'Platforms',
                    'fields' => [
                        [
                            'id' => 'f_platforms',
                            'type' => 'checkbox_group',
                            'label' => 'Platforms you can live on',
                            'required' => true,
                            'role' => 'platforms',
                            'options' => [
                                ['value' => 'tiktok', 'label' => 'TikTok'],
                                ['value' => 'shopee', 'label' => 'Shopee'],
                                ['value' => 'facebook', 'label' => 'Facebook'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'page-3',
                    'title' => 'Your story',
                    'fields' => [
                        ['id' => 'f_experience', 'type' => 'textarea', 'label' => 'Experience', 'required' => false, 'rows' => 4],
                        ['id' => 'f_motivation', 'type' => 'textarea', 'label' => 'Why do you want to join?', 'required' => false, 'rows' => 4],
                        ['id' => 'f_resume', 'type' => 'file', 'label' => 'Resume', 'required' => false, 'role' => 'resume', 'accept' => ['pdf', 'doc', 'docx'], 'max_size_kb' => 5120],
                    ],
                ],
            ],
        ];
    }
}
