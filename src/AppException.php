<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Jerarquía de excepciones personalizadas para la aplicación.
 * Custom exception hierarchy for the application.
 *
 * Proporciona excepciones específicas para distintos tipos de errores:
 * - AppException: excepción base de la aplicación
 * - ValidationException: errores de validación de datos de entrada
 * - NotFoundException: recurso no encontrado
 *
 * Provides specific exceptions for different error types:
 * - AppException: base application exception
 * - ValidationException: input data validation errors
 * - NotFoundException: resource not found
 */

// --- Base application exception / Excepción base de la aplicación ---
class AppException extends \RuntimeException
{
    /** Códigos de error generales / General error codes */
    public const ERROR_GENERAL = 1000;
    public const ERROR_DATABASE = 1001;
    public const ERROR_FILESYSTEM = 1002;

    public function __construct(
        string $message,
        int $code = self::ERROR_GENERAL,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Excepción para errores de validación de datos de entrada.
 * Exception for input data validation errors.
 *
 * Incluye códigos de error específicos por tipo de validación
 * y el nombre del campo que falló la validación.
 *
 * Includes specific error codes per validation type
 * and the name of the field that failed validation.
 */
class ValidationException extends AppException
{
    /** Códigos de error de validación / Validation error codes */
    public const ERROR_EMPTY_FIELD = 2000;
    public const ERROR_INVALID_PRIORITY = 2001;
    public const ERROR_INVALID_STATUS = 2002;
    public const ERROR_INVALID_ID = 2003;
    public const ERROR_INVALID_FORMAT = 2004;
    public const ERROR_INVALID_LENGTH = 2005;

    /**
     * @param string $message Mensaje descriptivo del error / Descriptive error message
     * @param int $code Código de error específico / Specific error code
     * @param string $field Nombre del campo que falló / Name of the failed field
     */
    public function __construct(
        string $message,
        int $code = self::ERROR_EMPTY_FIELD,
        public readonly string $field = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Excepción cuando un recurso no se encuentra en la base de datos.
 * Exception when a resource is not found in the database.
 *
 * Almacena el tipo de recurso y su identificador para generar
 * mensajes descriptivos automáticamente.
 *
 * Stores the resource type and identifier to automatically
 * generate descriptive messages.
 */
class NotFoundException extends AppException
{
    /** Código de error de recurso no encontrado / Resource not found error code */
    public const ERROR_TASK_NOT_FOUND = 3000;

    /**
     * @param string $resource Tipo de recurso no encontrado / Type of resource not found
     * @param int|string $identifier Identificador del recurso buscado / Searched resource identifier
     */
    public function __construct(
        public readonly string $resource,
        public readonly int|string $identifier,
        ?\Throwable $previous = null,
    ) {
        $message = "{$resource} not found with identifier: {$identifier}";
        parent::__construct($message, self::ERROR_TASK_NOT_FOUND, $previous);
    }
}
