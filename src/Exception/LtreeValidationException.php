<?php
namespace kr0lik\ltree\Exception;

use Throwable;

class LtreeValidationException extends LtreeException
{
    /**
     * @var array<string, string[]>
     */
    protected $errors = [];

    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(array $errors, $code = 0, Throwable $previous = null)
    {
        $this->errors = $errors;

        parent::__construct($this->errorsToString(), $code, $previous);
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function errorsToString(): string
    {
        $errors = [];

        foreach ($this->errors as $attribute => $error) {
            $message = implode(' ', $error);

            $errors[] = $attribute.': '.$message;
        }

        return implode(' ', $errors);
    }
}