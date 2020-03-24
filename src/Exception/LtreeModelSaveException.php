<?php
namespace kr0lik\ltree\Exception;

use Throwable;

class LtreeModelSaveException extends LtreeException
{
    protected $errors = [];

    public function __construct(array $errors, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->errors = $errors;

        parent::__construct($message, $code, $previous);
    }
}