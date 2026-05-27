<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Servicio de autenticacion para la API REST.
 *
 * Gestiona el registro de usuarios, login con verificacion de contrasena,
 * generacion de tokens JWT (implementacion simple con HMAC-SHA256),
 * y validacion de tokens para proteger rutas.
 *
 * Los usuarios se almacenan en la misma base de datos SQLite en una tabla
 * separada `users`. Las contrasenas se hashean con bcrypt via password_hash().
 *
 * Caracteristicas PHP 8: constructor promotion, readonly, named arguments, match
 */
class AuthService
{
    /** Duracion del token en segundos (24 horas por defecto) */
    private const JWT_TTL_DEFAULT = 86400;

    /** Algoritmo de firma para hash_hmac() */
    private const JWT_ALGO = 'SHA256';

    /** Nombre del algoritmo en el header JWT (RFC 7518) */
    private const JWT_ALG_NAME = 'HS256';

    /** Conexion PDO a la base de datos */
    private readonly PDO $pdo;

    /**
     * Obtiene la clave secreta JWT desde variable de entorno.
     *
     * @return string Clave secreta para firmar tokens JWT
     * @throws AppException Si JWT_SECRET no esta configurado
     */
    private static function getJwtSecret(): string
    {
        $secret = getenv('JWT_SECRET');
        if ($secret === false || $secret === '') {
            throw new AppException(
                message: 'JWT_SECRET no esta configurado. Define la variable de entorno JWT_SECRET.',
                code: AppException::ERROR_GENERAL,
            );
        }
        return $secret;
    }

    /**
     * Obtiene la duracion del token JWT desde variable de entorno con fallback.
     *
     * @return int Duracion en segundos
     */
    private static function getJwtTtl(): int
    {
        $ttl = getenv('JWT_TTL');
        return ($ttl !== false && $ttl !== '') ? (int) $ttl : self::JWT_TTL_DEFAULT;
    }

    /**
     * Inicializa el servicio de autenticacion.
     *
     * Obtiene la conexion desde el Singleton de Database.
     * La tabla de usuarios se crea en Database::inicializarTablas().
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Registra un nuevo usuario en el sistema.
     *
     * Valida los datos de entrada, hashea la contrasena con bcrypt
     * y almacena el usuario en la base de datos.
     *
     * @param string $username Nombre de usuario (3-50 caracteres, alfanumerico)
     * @param string $password Contrasena en texto plano (minimo 6 caracteres)
     * @return array{id: int, username: string, created_at: string} Datos del usuario creado
     * @throws ValidationException Si los datos no cumplen las validaciones
     * @throws AppException Si el nombre de usuario ya existe o hay error de BD
     */
    public function registrar(string $username, string $password): array
    {
        // Validar nombre de usuario
        $username = trim($username);
        if ($username === '') {
            throw new ValidationException(
                message: 'El nombre de usuario no puede estar vacio',
                code: ValidationException::ERROR_CAMPO_VACIO,
                campo: 'username',
            );
        }

        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            throw new ValidationException(
                message: 'El nombre de usuario debe tener entre 3 y 50 caracteres',
                code: ValidationException::ERROR_FORMATO_INVALIDO,
                campo: 'username',
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new ValidationException(
                message: 'El nombre de usuario solo puede contener letras, numeros y guiones bajos',
                code: ValidationException::ERROR_FORMATO_INVALIDO,
                campo: 'username',
            );
        }

        // Validar contrasena
        if (mb_strlen($password) < 6) {
            throw new ValidationException(
                message: 'La contrasena debe tener al menos 6 caracteres',
                code: ValidationException::ERROR_LONGITUD_INVALIDA,
                campo: 'password',
            );
        }

        // Verificar que el usuario no exista ya
        if ($this->existeUsuario($username)) {
            throw new AppException(
                message: "El nombre de usuario '{$username}' ya esta registrado",
                code: AppException::ERROR_GENERAL,
            );
        }

        try {
            // Hashear la contrasena con bcrypt
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)'
            );

            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $passwordHash,
            ]);

            $userId = (int) $this->pdo->lastInsertId();

            // Recuperar el usuario completo
            return $this->obtenerUsuarioPorId($userId);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al registrar usuario: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Autentica un usuario y genera un token JWT.
     *
     * Verifica las credenciales usando password_verify() y, si son correctas,
     * genera un token JWT firmado con HMAC-SHA256.
     *
     * @param string $username Nombre de usuario
     * @param string $password Contrasena en texto plano
     * @return array{token: string, type: string, expires_in: int, user: array} Token y datos del usuario
     * @throws ValidationException Si los campos estan vacios
     * @throws AppException Si las credenciales son invalidas
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);

        // Validar campos no vacios
        if ($username === '' || $password === '') {
            throw new ValidationException(
                message: 'El nombre de usuario y la contrasena son obligatorios',
                code: ValidationException::ERROR_CAMPO_VACIO,
                campo: 'credentials',
            );
        }

        try {
            // Buscar usuario por nombre
            $stmt = $this->pdo->prepare(
                'SELECT * FROM users WHERE username = :username'
            );
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // Verificar que el usuario existe y la contrasena es correcta
            if ($user === false || !password_verify($password, $user['password_hash'])) {
                throw new AppException(
                    message: 'Credenciales invalidas: usuario o contrasena incorrectos',
                    code: AppException::ERROR_GENERAL,
                );
            }

            $ttl = self::getJwtTtl();

            // Generar token JWT
            $token = $this->generarToken(
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
        } catch (AppException $e) {
            // Re-lanzar excepciones propias
            throw $e;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al autenticar usuario: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Valida un token JWT y retorna los datos del payload.
     *
     * Verifica la firma HMAC-SHA256 y la expiracion del token.
     * Este metodo actua como middleware de autenticacion.
     *
     * @param string $token Token JWT completo (header.payload.signature)
     * @return array{user_id: int, username: string, iat: int, exp: int} Payload del token
     * @throws AppException Si el token es invalido, expirado o la firma no coincide
     */
    public function validarToken(string $token): array
    {
        // Separar las tres partes del JWT
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw new AppException(
                message: 'Token JWT invalido: formato incorrecto',
                code: AppException::ERROR_GENERAL,
            );
        }

        [$headerB64, $payloadB64, $firmaB64] = $partes;

        // Decodificar y validar el algoritmo del header
        $header = json_decode(
            $this->base64UrlDecode($headerB64),
            associative: true,
        );

        if ($header === null || !isset($header['alg']) || $header['alg'] !== self::JWT_ALG_NAME) {
            throw new AppException(
                message: 'Token JWT invalido: algoritmo no soportado',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Verificar la firma
        $firmaEsperada = $this->base64UrlEncode(
            hash_hmac(self::JWT_ALGO, "{$headerB64}.{$payloadB64}", self::getJwtSecret(), true)
        );

        if (!hash_equals($firmaEsperada, $firmaB64)) {
            throw new AppException(
                message: 'Token JWT invalido: firma no coincide',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Decodificar el payload
        $payload = json_decode(
            $this->base64UrlDecode($payloadB64),
            associative: true,
        );

        if ($payload === null) {
            throw new AppException(
                message: 'Token JWT invalido: payload corrupto',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Verificar expiracion
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            throw new AppException(
                message: 'Token JWT expirado',
                code: AppException::ERROR_GENERAL,
            );
        }

        return $payload;
    }

    /**
     * Extrae el token Bearer del encabezado Authorization.
     *
     * Busca el token en el header HTTP Authorization con formato
     * "Bearer <token>". Es el punto de entrada del middleware de
     * autenticacion.
     *
     * @param string|null $authHeader Header de autorizacion. Si null, se lee de $_SERVER.
     * @return array{user_id: int, username: string} Datos del usuario autenticado
     * @throws AppException Si no hay token o es invalido
     */
    public function autenticar(?string $authHeader = null): array
    {
        // Obtener el header Authorization desde $_SERVER si no se proporciona
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
                message: 'Token de autenticacion requerido. Usa el header: Authorization: Bearer <token>',
                code: AppException::ERROR_GENERAL,
            );
        }

        // Extraer el token del formato "Bearer <token>"
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            throw new AppException(
                message: 'Formato de token invalido. Usa: Authorization: Bearer <token>',
                code: AppException::ERROR_GENERAL,
            );
        }

        $token = $matches[1];

        // Validar el token y retornar los datos del usuario
        return $this->validarToken($token);
    }

    // ---------------------------------------------------------------
    //  Metodos privados para JWT
    // ---------------------------------------------------------------

    /**
     * Genera un token JWT firmado con HMAC-SHA256.
     *
     * Estructura del JWT:
     * - Header: algoritmo y tipo de token
     * - Payload: datos del usuario, timestamps de emision y expiracion
     * - Signature: firma HMAC-SHA256 del header y payload
     *
     * @param int $userId ID del usuario
     * @param string $username Nombre de usuario
     * @return string Token JWT completo (header.payload.signature)
     */
    private function generarToken(int $userId, string $username): string
    {
        $ttl = self::getJwtTtl();

        // Cabecera del JWT
        $header = [
            'alg' => self::JWT_ALG_NAME,
            'typ' => 'JWT',
        ];

        // Payload con datos del usuario y timestamps
        $payload = [
            'user_id' => $userId,
            'username' => $username,
            'iat' => time(),                 // Emitido en (issued at)
            'exp' => time() + $ttl,          // Expira en
        ];

        // Codificar header y payload en Base64URL
        $headerB64 = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // Generar la firma HMAC-SHA256
        $firma = $this->base64UrlEncode(
            hash_hmac(self::JWT_ALGO, "{$headerB64}.{$payloadB64}", self::getJwtSecret(), true)
        );

        // Concatenar las tres partes con puntos
        return "{$headerB64}.{$payloadB64}.{$firma}";
    }

    /**
     * Codifica datos en Base64 URL-safe (RFC 4648).
     *
     * Reemplaza +/ por -_ y elimina el padding = para
     * compatibilidad con URLs y headers HTTP.
     *
     * @param string $data Datos a codificar
     * @return string Datos codificados en Base64URL
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica datos desde Base64 URL-safe (RFC 4648).
     *
     * Restaura los caracteres estandar de Base64 y agrega el
     * padding necesario antes de decodificar.
     *
     * @param string $data Datos codificados en Base64URL
     * @return string Datos decodificados
     */
    private function base64UrlDecode(string $data): string
    {
        // Restaurar caracteres estandar y agregar padding
        $data = strtr($data, '-_', '+/');
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }

        return base64_decode($data, strict: true) ?: '';
    }

    // ---------------------------------------------------------------
    //  Metodos publicos de consulta de usuarios
    // ---------------------------------------------------------------

    /**
     * Obtiene el perfil del usuario autenticado por su ID.
     *
     * @param int $userId ID del usuario autenticado
     * @return array{id: int, username: string, created_at: string} Datos del perfil
     * @throws NotFoundException Si el usuario no existe
     */
    public function obtenerPerfil(int $userId): array
    {
        return $this->obtenerUsuarioPorId($userId);
    }

    // ---------------------------------------------------------------
    //  Metodos privados de consulta de usuarios
    // ---------------------------------------------------------------

    /**
     * Verifica si un nombre de usuario ya esta registrado.
     *
     * @param string $username Nombre de usuario a verificar
     * @return bool true si el usuario ya existe
     */
    private function existeUsuario(string $username): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE username = :username'
        );
        $stmt->execute([':username' => $username]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene los datos de un usuario por su ID.
     *
     * @param int $id ID del usuario
     * @return array{id: int, username: string, created_at: string} Datos del usuario
     * @throws NotFoundException Si el usuario no existe
     */
    public function obtenerUsuarioPorId(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, created_at FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if ($user === false) {
            throw new NotFoundException(
                recurso: 'usuario',
                identificador: $id,
            );
        }

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'created_at' => $user['created_at'],
        ];
    }
}
