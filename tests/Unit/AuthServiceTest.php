<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\AppException;
use MiniProject\AuthService;
use MiniProject\Database;
use MiniProject\NotFoundException;
use MiniProject\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el servicio de autenticación.
 * Unit tests for the authentication service.
 *
 * @covers \MiniProject\AuthService
 * @covers \MiniProject\Database
 */
class AuthServiceTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');
        $this->auth = new AuthService(jwtSecret: 'test_secret_key_for_phpunit');
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests de register()
    // ---------------------------------------------------------------

    public function testRegisterValidUser(): void
    {
        $result = $this->auth->register(
            username: 'testuser',
            password: 'password123',
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertSame('testuser', $result['username']);
        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function testRegisterEmptyUsername(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->auth->register(username: '', password: 'password123');
    }

    public function testRegisterTooShortUsername(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('between 3 and 50 characters');

        $this->auth->register(username: 'ab', password: 'password123');
    }

    public function testRegisterTooLongUsername(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('between 3 and 50 characters');

        $longUsername = str_repeat('a', 51);
        $this->auth->register(username: $longUsername, password: 'password123');
    }

    public function testRegisterInvalidCharactersUsername(): void
    {
        try {
            $this->auth->register(username: 'user@name!', password: 'password123');
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(ValidationException::ERROR_INVALID_FORMAT, $e->getCode());
            $this->assertStringContainsString('letters, numbers and underscores', $e->getMessage());
        }
    }

    public function testRegisterShortPassword(): void
    {
        try {
            $this->auth->register(username: 'testuser', password: '12345');
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(ValidationException::ERROR_INVALID_LENGTH, $e->getCode());
            $this->assertStringContainsString('at least 6 characters', $e->getMessage());
        }
    }

    public function testRegisterDuplicateUsername(): void
    {
        $this->auth->register(username: 'duplicado', password: 'password123');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('already registered');

        $this->auth->register(username: 'duplicado', password: 'otrapassword');
    }

    // ---------------------------------------------------------------
    //  Tests de login()
    // ---------------------------------------------------------------

    public function testLoginSuccessful(): void
    {
        $this->auth->register(username: 'loginuser', password: 'secreto123');

        $result = $this->auth->login(username: 'loginuser', password: 'secreto123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame('Bearer', $result['type']);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertIsInt($result['expires_in']);
        $this->assertSame('loginuser', $result['user']['username']);
    }

    public function testLoginNonExistentUsername(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->auth->login(username: 'noexisto', password: 'password123');
    }

    public function testLoginWrongPassword(): void
    {
        $this->auth->register(username: 'userpass', password: 'correcta123');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->auth->login(username: 'userpass', password: 'incorrecta');
    }

    public function testLoginEmptyFields(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('required');

        $this->auth->login(username: '', password: '');
    }

    public function testLoginEmptyUsernameFilledPassword(): void
    {
        $this->expectException(ValidationException::class);

        $this->auth->login(username: '', password: 'password123');
    }

    public function testLoginFilledUsernameEmptyPassword(): void
    {
        $this->expectException(ValidationException::class);

        $this->auth->login(username: 'testuser', password: '');
    }

    // ---------------------------------------------------------------
    //  Tests de validateToken()
    // ---------------------------------------------------------------

    public function testValidateValidToken(): void
    {
        $this->auth->register(username: 'tokenuser', password: 'password123');
        $loginResult = $this->auth->login(username: 'tokenuser', password: 'password123');

        $payload = $this->auth->validateToken($loginResult['token']);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('username', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame('tokenuser', $payload['username']);
    }

    public function testValidateTokenIncorrectFormat(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('incorrect format');

        $this->auth->validateToken('token-sin-puntos');
    }

    public function testValidateTokenInvalidSignature(): void
    {
        $this->auth->register(username: 'firmauser', password: 'password123');
        $loginResult = $this->auth->login(username: 'firmauser', password: 'password123');

        // Modify the signature part of the token
        $parts = explode('.', $loginResult['token']);
        $parts[2] = 'firma_invalida_modificada';
        $modifiedToken = implode('.', $parts);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('signature mismatch');

        $this->auth->validateToken($modifiedToken);
    }

    public function testValidateExpiredToken(): void
    {
        // Manually build an expired token by creating the JWT structure
        // with an expiration time in the past
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $header = rtrim(strtr($header, '+/', '-_'), '=');

        $payload = base64_encode(json_encode([
            'user_id' => 1,
            'username' => 'expired',
            'iat' => time() - 7200,
            'exp' => time() - 3600, // Expired 1 hour ago
        ]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');

        // Sign with the test secret key
        $secret = 'test_secret_key_for_phpunit';
        $signature = hash_hmac('SHA256', "{$header}.{$payload}", $secret, true);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $expiredToken = "{$header}.{$payload}.{$signatureB64}";

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('expired');

        $this->auth->validateToken($expiredToken);
    }

    // ---------------------------------------------------------------
    //  Tests de getProfile()
    // ---------------------------------------------------------------

    public function testGetProfile(): void
    {
        $registered = $this->auth->register(
            username: 'perfiluser',
            password: 'password123',
        );

        $profile = $this->auth->getProfile(userId: $registered['id']);

        $this->assertIsArray($profile);
        $this->assertSame($registered['id'], $profile['id']);
        $this->assertSame('perfiluser', $profile['username']);
        $this->assertArrayHasKey('created_at', $profile);
    }

    public function testGetProfileNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->auth->getProfile(userId: 99999);
    }

    // ---------------------------------------------------------------
    //  Tests de registro de multiples usuarios
    // ---------------------------------------------------------------

    public function testRegisterMultipleUsers(): void
    {
        $user1 = $this->auth->register(username: 'usuario_uno', password: 'password123');
        $user2 = $this->auth->register(username: 'usuario_dos', password: 'password456');
        $user3 = $this->auth->register(username: 'usuario_tres', password: 'password789');

        // Verify all have unique IDs
        $this->assertNotSame($user1['id'], $user2['id']);
        $this->assertNotSame($user2['id'], $user3['id']);
        $this->assertNotSame($user1['id'], $user3['id']);

        // Verify each has its correct username
        $this->assertSame('usuario_uno', $user1['username']);
        $this->assertSame('usuario_dos', $user2['username']);
        $this->assertSame('usuario_tres', $user3['username']);
    }

    // ---------------------------------------------------------------
    //  Tests adicionales de validacion de registro
    // ---------------------------------------------------------------

    public function testRegisterUsernameTrimmed(): void
    {
        $result = $this->auth->register(
            username: '  trimmed_user  ',
            password: 'password123',
        );

        $this->assertSame('trimmed_user', $result['username']);
    }

    public function testRegisterUsernameWithUnderscore(): void
    {
        $result = $this->auth->register(
            username: 'user_name_123',
            password: 'password123',
        );

        $this->assertSame('user_name_123', $result['username']);
    }

    public function testRegister3CharacterUsername(): void
    {
        $result = $this->auth->register(
            username: 'abc',
            password: 'password123',
        );

        $this->assertSame('abc', $result['username']);
    }

    public function testRegister50CharacterUsername(): void
    {
        $username = str_repeat('a', 50);

        $result = $this->auth->register(
            username: $username,
            password: 'password123',
        );

        $this->assertSame($username, $result['username']);
    }

    public function testRegister6CharacterPassword(): void
    {
        $result = $this->auth->register(
            username: 'minpass',
            password: '123456',
        );

        $this->assertIsInt($result['id']);
    }

    // ---------------------------------------------------------------
    //  Tests de seguridad JWT: validacion de algoritmo
    // ---------------------------------------------------------------

    public function testValidateTokenAlgNoneRejected(): void
    {
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $header = rtrim(strtr($header, '+/', '-_'), '=');

        $payload = base64_encode(json_encode([
            'user_id' => 1,
            'username' => 'attacker',
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');

        $token = "{$header}.{$payload}.emptysig";

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('unsupported algorithm');

        $this->auth->validateToken($token);
    }

    public function testValidateTokenWrongAlgorithmRejected(): void
    {
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $header = rtrim(strtr($header, '+/', '-_'), '=');

        $payload = base64_encode(json_encode([
            'user_id' => 1,
            'username' => 'attacker',
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');

        $secret = 'test_secret_key_for_phpunit';
        $signature = hash_hmac('SHA256', "{$header}.{$payload}", $secret, true);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $token = "{$header}.{$payload}.{$signatureB64}";

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('unsupported algorithm');

        $this->auth->validateToken($token);
    }

    // ---------------------------------------------------------------
    //  Tests de JWT_SECRET obligatorio
    // ---------------------------------------------------------------

    public function testConstructorWithoutSecretAndWithoutEnvThrows(): void
    {
        // Ensure no env var is set
        putenv('JWT_SECRET');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('JWT_SECRET');

        new AuthService();
    }

    public function testConstructorReadsSecretFromEnvWhenNotInjected(): void
    {
        putenv('JWT_SECRET=env_secret_value');

        try {
            $auth = new AuthService();

            // Should not throw — secret is read from env
            $auth->register(username: 'envuser', password: 'password123');
            $loginResult = $auth->login(username: 'envuser', password: 'password123');

            $this->assertNotEmpty($loginResult['token']);
        } finally {
            putenv('JWT_SECRET');
        }
    }

    // ---------------------------------------------------------------
    //  Tests de authenticate() con header inyectado
    // ---------------------------------------------------------------

    public function testAuthenticateWithInjectedHeader(): void
    {
        $this->auth->register(username: 'authuser', password: 'password123');
        $loginResult = $this->auth->login(username: 'authuser', password: 'password123');

        $result = $this->auth->authenticate('Bearer ' . $loginResult['token']);

        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame('authuser', $result['username']);
    }

    public function testAuthenticateWithEmptyHeader(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Authentication token required');

        $this->auth->authenticate('');
    }

    public function testAuthenticateWithInvalidFormat(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Invalid token format');

        $this->auth->authenticate('NotBearer some-token');
    }
}
