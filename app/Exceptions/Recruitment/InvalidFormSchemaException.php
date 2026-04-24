<?php

namespace App\Exceptions\Recruitment;

use RuntimeException;

class InvalidFormSchemaException extends RuntimeException
{
    /** @var string[] */
    public array $errors;

    /**
     * @param  string[]  $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Invalid form schema: '.implode('; ', $errors));
    }
}
