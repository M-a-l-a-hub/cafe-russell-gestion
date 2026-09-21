<?php

declare(strict_types=1);

/**
 * Erreur destinée directement au client (statut HTTP + code stable).
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $statutHttp,
        private string $codeErreur,
        string $message
    ) {
        parent::__construct($message);
    }

    public function statutHttp(): int
    {
        return $this->statutHttp;
    }

    public function codeErreur(): string
    {
        return $this->codeErreur;
    }
}
