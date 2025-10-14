<?php

namespace App\Exceptions;

use Exception;

class ParserException extends Exception
{
    /**
     * Получить детальную информацию об ошибке для логирования или вывода
     *
     * @return string
     */
    public function getDetailedMessage(): string
    {
        $previousMessage = $this->getPrevious() ? "\nPrevious: " . $this->getPrevious()->getMessage() : '';
        return sprintf(
            "[%s] %s in %s:%d%s\nStack trace:\n%s",
            static::class,
            $this->getMessage(),
            $this->getFile(),
            $this->getLine(),
            $previousMessage,
            $this->getTraceAsString()
        );
    }
}
