<?php

declare(strict_types=1);

namespace PHPdot\Error;

/**
 * Interface for error code enums.
 *
 * Each module defines its own backed string enum implementing this interface.
 * The enum value IS the error code (e.g., '00010001').
 */
interface ErrorCodeInterface
{
    /**
     * Get the error code (the enum value).
     */
    public function getCode(): string;

    /**
     * Get the human-readable English message (fallback).
     */
    public function getMessage(): string;

    /**
     * Get the translation key (e.g., 'errors.user.not_found').
     */
    public function getDescription(): string;

    /**
     * Get the error category.
     */
    public function getType(): ErrorType;

    /**
     * Get the HTTP status code.
     */
    public function getHttpStatus(): int;

    /**
     * Get all error details.
     *
     * @return array{
     *     message: string,
     *     description: string,
     *     type: ErrorType,
     *     httpStatus: int,
     * }
     */
    public function getDetails(): array;
}
