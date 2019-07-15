<?php

namespace Paykun\Errors;
class PkError extends \Exception
{
    public function __construct($message, $code)
    {
        $this->code = $code;

        $this->message = $message;
    }
}
