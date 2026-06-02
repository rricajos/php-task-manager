<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Interfaz para el servicio de autenticación.
 * Interface for the authentication service.
 *
 * Define el contrato para registro, login, validación de tokens JWT
 * y consulta de perfiles de usuario.
 *
 * Defines the contract for registration, login, JWT token validation
 * and user profile queries.
 */
interface AuthServiceInterface
{
    /**
     * Registra un nuevo usuario.
     * Registers a new user.
     *
     * @param string $username Nombre de usuario / Username
     * @param string $password Contraseña en texto plano / Plain text password
     * @return array{id: int, username: string, created_at: string} Datos del usuario / User data
     */
    public function register(string $username, string $password): array;

    /**
     * Autentica un usuario y genera un token JWT.
     * Authenticates a user and generates a JWT token.
     *
     * @param string $username Nombre de usuario / Username
     * @param string $password Contraseña / Password
     * @return array{token: string, type: string, expires_in: int, user: array{id: int, username: string}} Token y datos / Token and data
     */
    public function login(string $username, string $password): array;

    /**
     * Valida un token JWT y retorna el payload.
     * Validates a JWT token and returns the payload.
     *
     * @param string $token Token JWT completo / Complete JWT token
     * @return array{user_id: int, username: string, iat: int, exp: int} Payload del token / Token payload
     */
    public function validateToken(string $token): array;

    /**
     * Extrae y valida el token Bearer del header Authorization.
     * Extracts and validates the Bearer token from the Authorization header.
     *
     * @param string|null $authHeader Header de autorización / Authorization header
     * @return array{user_id: int, username: string} Datos del usuario / User data
     */
    public function authenticate(?string $authHeader = null): array;

    /**
     * Obtiene el perfil del usuario por ID.
     * Gets the user profile by ID.
     *
     * @param int $userId ID del usuario / User ID
     * @return array{id: int, username: string, created_at: string} Datos del perfil / Profile data
     */
    public function getProfile(int $userId): array;
}
