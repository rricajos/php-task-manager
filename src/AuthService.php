<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Servicio de autenticaci\u00f3n para la API REST.
 * Authentication service for the REST API.
 *
 * Gestiona el registro de usuarios, login con verificaci\u00f3n de contrase\u00f1a,
 * generaci\u00f3n de tokens JWT (implementaci\u00f3n simple con HMAC-SHA256),
 * y validaci\u00f3n de tokens para proteger rutas.
 *
 * Manages user registration, login with password verification,
 * JWT token generation (simple implementation with HMAC-SHA256),
 * and token validation to protect routes.
 *
 * Los usuarios se almacenan en la misma base de datos SQLite en una tabla
 * separada `users`. Las contrase\u00f1as se hashean con bcrypt via password_hash().
 *
 * Users are stored in the same SQLite database in a separate `users` table.
 * Passwords are hashed with bcrypt via password_hash().
 *
 * Caracter\u00edsticas PHP 8: constructor promotion, readonly, named arguments, match
 * PHP 8 features: constructor promotion, readonly, named arguments, match
 */
class AuthService implements AuthServiceInterface
{
    /**
     * Duraci\u00f3n del token en segundos (24 horas por defecto).
     * Token duration in seconds (24 hours by default).
     */
    private const JWT_TTL_DEFAULT = 86400;

    /**
     * Algoritmo de firma para hash_hmac().
     * Signing algorithm for hash_hmac().
     */
    private const JWT_ALGO = 'SHA256';

    /**
     * Nombre del algoritmo en el header JWT (RFC 7518).
     * Algorithm name in the JWT header (RFC 7518).
     */
    private const JWT_ALG_NAME = 'HS256';

    /**
     * Clave secreta JWT (inyectada o leída de entorno).
     * JWT secret key (injected or read from environment).
     */
    private readonly string $jwtSecret;

    /**
     * Duración del token JWT en segundos.
     * JWT token duration in seconds.
     */
    private readonly int $jwtTtl;

    /**
     * Conexi\u00f3n PDO a la base de datos.
     * PDO connection to the database.
     */
    private readonly PDO $pdo;

    /**
     * Inicializa el servicio de autenticación.
     * Initializes the authentication service.
     *
     * Acepta opcionalmente la clave secreta y TTL para facilitar el testing.
     * Si no se proporcionan, los lee de las variables de entorno.
     *
     * Optionally accepts the secret key and TTL for easier testing.
     * If not provided, reads them from environment variables.
     *
     * @param string $jwtSecret Clave secreta JWT (vacío = leer de entorno) / JWT secret (empty = read from env)
     * @param int $jwtTtl Duración del token en segundos (0 = leer de entorno) / Token duration in seconds (0 = read from env)
     * @throws AppException Si JWT_SECRET no est\u00e1 configurado / If JWT_SECRET is not configured
     */
    public function __construct(
        string $jwtSecret = '',
        int $jwtTtl = 0,
    ) {
        // Resolve JWT secret: injected value or environment variable
        if ($jwtSecret !== '') {
            $this->jwtSecret = $jwtSecret;
        } else {
            $secret = getenv('JWT_SECRET');
            if ($secret === false || $secret === '') {
                throw new AppException(
                    message: 'JWT_SECRET is not configured. Define the JWT_SECRET environment variable.',
                    code: AppException::ERROR_GENERAL,
                );
            }
            $this->jwtSecret = $secret;
        }

        // Resolve JWT TTL: injected value, environment variable, or default
        if ($jwtTtl > 0) {
            $this->jwtTtl = $jwtTtl;
        } else {
            $envTtl = getenv('JWT_TTL');
            $this->jwtTtl = ($envTtl !== false && $envTtl !== '') ? (int) $envTtl : self::JWT_TTL_DEFAULT;
        }

        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Registra un nuevo usuario en el sistema.
     * Registers a new user in the system.
     *
     * Valida los datos de entrada, hashea la contrase\u00f1a con bcrypt
     * y almacena el usuario en la base de datos.
     *
     * Validates input data, hashes the password with bcrypt
     * and stores the user in the database.
     *
     * @param string $username Nombre de usuario (3-50 caracteres, alfanum\u00e9rico) / Username (3-50 characters, alphanumeric)
     * @param string $password Contrase\u00f1a en texto plano (m\u00ednimo 6 caracteres) / Plain text password (minimum 6 characters)
     * @return array{id: int, username: string, created_at: string} Datos del usuario creado / Created user data
     * @throws ValidationException Si los datos no cumplen las validaciones / If data does not pass validations
     * @throws AppException Si el nombre de usuario ya existe o hay error de BD / If the username already exists or there is a DB error
     */
    public function register(string $username, string $password): array
    {
        // Validate username
        $username = trim($username);
        if ($username === '') {
            throw new ValidationException(
                message: 'Username cannot be empty',
                code: ValidationException::ERROR_EMPTY_FIELD,
                field: 'username',
            );
        }

        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            throw new ValidationException(
                message: 'Username must be between 3 and 50 characters',
                code: ValidationException::ERROR_INVALID_FORMAT,
                field: 'username',
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new ValidationException(
                message: 'Username can only contain letters, numbers and underscores',
                code: ValidationException::ERROR_INVALID_FORMAT,
                field: 'username',
            );
        }

        // Validate password
        if (mb_strlen($password) < 6) {
            throw new ValidationException(
                message: 'Password must be at least 6 characters',
                code: ValidationException::ERROR_INVALID_LENGTH,
                field: 'password',
            );
        }

        // Verify that the user does not already exist
        if ($this->userExists($username)) {
            throw new AppException(
                message: "Username '{$username}' is already registered",
                code: AppException::ERROR_GENERAL,
            );
        }

        try {
            // Hash the password with bcrypt
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)'
            );

            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $passwordHash,
            ]);

            $userId = (int) $this->pdo->lastInsertId();

            // Retrieve the complete user
            return $this->getUserById($userId);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error registering user: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Autentica un usuario y genera un token JWT.
     * Authenticates a user and generates a JWT token.
     *
     * Verifica las credenciales usando password_verify() y, si son correctas,
     * genera un token JWT firmado con HMAC-SHA256.
     *
     * Verifies credentials using password_verify() and, if correct,
     * generates a JWT token signed with HMAC-SHA256.
     *
     * @param string $username Nombre de usuario / Username
     * @param string $password Contrase\u00f1a en texto plano / Plain text password
     * @return array{token: string, type: string, expires_in: int, user: array{id: int, username: string}} Token y datos del usuario / Token and user data
     * @throws ValidationException Si los campos est\u00e1n vac\u00edos / If fields are empty
     * @throws AppException Si las credenciales son inv\u00e1lidas / If credentials are invalid
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);

        // Validate non-empty fields
        if ($username === '' || $password === '') {
            throw new ValidationException(
                message: 'Username and password are required',
                code: ValidationException::ERROR_EMPTY_FIELD,
                field: 'credentials',
            );
        }

        try {
            // Find user by name
            $stmt = $this->pdo->prepare(
                'SELECT * FROM users WHERE username = :username'
            );
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // Verify that the user exists and the password is correct
            if ($user === false || !password_verify($password, $user['password_hash'])) {
                throw new AppException(
                    message: 'Invalid credentials: incorrect username or password',
                    code: AppException::ERROR_GENERAL,
                );
            }

            $ttl = $this->jwtTtl;

            // Generate JWT token
            $token = $this->generateToken(
                userId: (int) $user['id'],
                username: $user['username'],
            );

            return [
                'token' => $token,
                'type' => 'Bearer',
                'expires_in' => $ttl,
                'user' => [
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                ],
            ];
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error authenticating user: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Valida un token JWT y retorna los datos del payload.
     * Validates a JWT token and returns the payload data.
     *
     * Verifica la firma HMAC-SHA256 y la expiraci\u00f3n del token.
     * Este m\u00e9todo act\u00faa como middleware de autenticaci\u00f3n.
     *
     * Verifies the HMAC-SHA256 signature and token expiration.
     * This method acts as authentication middleware.
     *
     * @param string $token Token JWT completo (header.payload.signature) / Complete JWT token (header.payload.signature)
     * @return array{user_id: int, username: string, iat: int, exp: int} Payload del token / Token payload
     * @throws AppException Si el token es inv\u00e1lido, expirado o la firma no coincide / If the token is invalid, expired or the signature does not match
     */
    public function validateToken(string $token): array
    {
        // Split the three parts of the JWT
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new AppException(
                message: 'Invalid JWT token: incorrect format',
                code: AppException::ERROR_GENERAL,
            );
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Decode and validate the header algorithm
        $header = json_decode(
            $this->base64UrlDecode($headerB64),
            associative: true,
        );

        if ($header === null || !isset($header['alg']) || $header['alg'] !== self::JWT_ALG_NAME) {
            throw new AppException(
                message: 'Invalid JWT token: unsupported algorithm',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Verify the signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac(self::JWT_ALGO, "{$headerB64}.{$payloadB64}", $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            throw new AppException(
                message: 'Invalid JWT token: signature mismatch',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Decode the payload
        $payload = json_decode(
            $this->base64UrlDecode($payloadB64),
            associative: true,
        );

        if ($payload === null) {
            throw new AppException(
                message: 'Invalid JWT token: corrupted payload',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            throw new AppException(
                message: 'JWT token expired',
                code: AppException::ERROR_GENERAL,
            );
        }

        return $payload;
    }

    /**
     * Extrae el token Bearer del encabezado Authorization.
     * Extracts the Bearer token from the Authorization header.
     *
     * Busca el token en el header HTTP Authorization con formato
     * "Bearer <token>". Es el punto de entrada del middleware de
     * autenticaci\u00f3n.
     *
     * Looks for the token in the HTTP Authorization header with format
     * "Bearer <token>". This is the entry point of the authentication
     * middleware.
     *
     * @param string|null $authHeader Header de autorizaci\u00f3n. Si null, se lee de $_SERVER. / Authorization header. If null, read from $_SERVER.
     * @return array{user_id: int, username: string} Datos del usuario autenticado / Authenticated user data
     * @throws AppException Si no hay token o es inv\u00e1lido / If there is no token or it is invalid
     */
    public function authenticate(?string $authHeader = null): array
    {
        // Get the Authorization header from $_SERVER if not provided
        if ($authHeader === null) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';

            if ($authHeader === '' && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
        }

        if ($authHeader === '') {
            throw new AppException(
                message: 'Authentication token required. Use the header: Authorization: Bearer <token>',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Extract the token from the "Bearer <token>" format
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            throw new AppException(
                message: 'Invalid token format. Use: Authorization: Bearer <token>',
                code: AppException::ERROR_GENERAL,
            );
        }

        $token = $matches[1];

        // Validate the token and return the user data
        return $this->validateToken($token);
    }

    // ---------------------------------------------------------------
    //  Private methods for JWT
    // ---------------------------------------------------------------

    /**
     * Genera un token JWT firmado con HMAC-SHA256.
     * Generates a JWT token signed with HMAC-SHA256.
     *
     * Estructura del JWT:
     * - Header: algoritmo y tipo de token
     * - Payload: datos del usuario, timestamps de emisi\u00f3n y expiraci\u00f3n
     * - Signature: firma HMAC-SHA256 del header y payload
     *
     * JWT structure:
     * - Header: algorithm and token type
     * - Payload: user data, issuance and expiration timestamps
     * - Signature: HMAC-SHA256 signature of header and payload
     *
     * @param int $userId ID del usuario / User ID
     * @param string $username Nombre de usuario / Username
     * @return string Token JWT completo (header.payload.signature) / Complete JWT token (header.payload.signature)
     */
    private function generateToken(int $userId, string $username): string
    {
        $ttl = $this->jwtTtl;

        // JWT header
        $header = [
            'alg' => self::JWT_ALG_NAME,
            'typ' => 'JWT',
        ];

        // Payload with user data and timestamps
        $payload = [
            'user_id' => $userId,
            'username' => $username,
            'iat' => time(),                 // Issued at
            'exp' => time() + $ttl,          // Expires at
        ];

        // Encode header and payload in Base64URL
        $headerB64 = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // Generate the HMAC-SHA256 signature
        $signature = $this->base64UrlEncode(
            hash_hmac(self::JWT_ALGO, "{$headerB64}.{$payloadB64}", $this->jwtSecret, true)
        );

        // Concatenate the three parts with dots
        return "{$headerB64}.{$payloadB64}.{$signature}";
    }

    /**
     * Codifica datos en Base64 URL-safe (RFC 4648).
     * Encodes data in Base64 URL-safe (RFC 4648).
     *
     * Reemplaza +/ por -_ y elimina el padding = para
     * compatibilidad con URLs y headers HTTP.
     *
     * Replaces +/ with -_ and removes the = padding for
     * compatibility with URLs and HTTP headers.
     *
     * @param string $data Datos a codificar / Data to encode
     * @return string Datos codificados en Base64URL / Base64URL encoded data
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica datos desde Base64 URL-safe (RFC 4648).
     * Decodes data from Base64 URL-safe (RFC 4648).
     *
     * Restaura los caracteres est\u00e1ndar de Base64 y agrega el
     * padding necesario antes de decodificar.
     *
     * Restores standard Base64 characters and adds the
     * necessary padding before decoding.
     *
     * @param string $data Datos codificados en Base64URL / Base64URL encoded data
     * @return string Datos decodificados / Decoded data
     */
    private function base64UrlDecode(string $data): string
    {
        // Restore standard characters and add padding
        $data = strtr($data, '-_', '+/');
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }

        return base64_decode($data, strict: true) ?: '';
    }

    // ---------------------------------------------------------------
    //  Public user query methods
    // ---------------------------------------------------------------

    /**
     * Obtiene el perfil del usuario autenticado por su ID.
     * Gets the authenticated user's profile by their ID.
     *
     * @param int $userId ID del usuario autenticado / Authenticated user ID
     * @return array{id: int, username: string, created_at: string} Datos del perfil / Profile data
     * @throws NotFoundException Si el usuario no existe / If the user does not exist
     */
    public function getProfile(int $userId): array
    {
        return $this->getUserById($userId);
    }

    // ---------------------------------------------------------------
    //  Private user query methods
    // ---------------------------------------------------------------

    /**
     * Verifica si un nombre de usuario ya est\u00e1 registrado.
     * Checks if a username is already registered.
     *
     * @param string $username Nombre de usuario a verificar / Username to check
     * @return bool true si el usuario ya existe / true if the user already exists
     */
    private function userExists(string $username): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE username = :username'
        );
        $stmt->execute([':username' => $username]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene los datos de un usuario por su ID.
     * Gets user data by their ID.
     *
     * @param int $id ID del usuario / User ID
     * @return array{id: int, username: string, created_at: string} Datos del usuario / User data
     * @throws NotFoundException Si el usuario no existe / If the user does not exist
     */
    public function getUserById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, created_at FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if ($user === false) {
            throw new NotFoundException(
                resource: 'user',
                identifier: $id,
            );
        }

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'created_at' => $user['created_at'],
        ];
    }
}
