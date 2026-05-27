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
 * Tests unitarios para el servicio de autenticacion.
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
        $this->auth = new AuthService();
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests de registrar()
    // ---------------------------------------------------------------

    public function testRegistrarUsuarioValido(): void
    {
        $resultado = $this->auth->registrar(
            username: 'testuser',
            password: 'password123',
        );

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('id', $resultado);
        $this->assertArrayHasKey('username', $resultado);
        $this->assertArrayHasKey('created_at', $resultado);
        $this->assertSame('testuser', $resultado['username']);
        $this->assertIsInt($resultado['id']);
        $this->assertGreaterThan(0, $resultado['id']);
    }

    public function testRegistrarUsernameVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no puede estar vacio');

        $this->auth->registrar(username: '', password: 'password123');
    }

    public function testRegistrarUsernameMuyCorto(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('entre 3 y 50 caracteres');

        $this->auth->registrar(username: 'ab', password: 'password123');
    }

    public function testRegistrarUsernameMuyLargo(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('entre 3 y 50 caracteres');

        $usernameLargo = str_repeat('a', 51);
        $this->auth->registrar(username: $usernameLargo, password: 'password123');
    }

    public function testRegistrarUsernameCaracteresInvalidos(): void
    {
        try {
            $this->auth->registrar(username: 'user@name!', password: 'password123');
            $this->fail('Deberia haber lanzado ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(ValidationException::ERROR_FORMATO_INVALIDO, $e->getCode());
            $this->assertStringContainsString('letras, numeros y guiones bajos', $e->getMessage());
        }
    }

    public function testRegistrarPasswordCorta(): void
    {
        try {
            $this->auth->registrar(username: 'testuser', password: '12345');
            $this->fail('Deberia haber lanzado ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(ValidationException::ERROR_LONGITUD_INVALIDA, $e->getCode());
            $this->assertStringContainsString('al menos 6 caracteres', $e->getMessage());
        }
    }

    public function testRegistrarUsernameDuplicado(): void
    {
        $this->auth->registrar(username: 'duplicado', password: 'password123');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('ya esta registrado');

        $this->auth->registrar(username: 'duplicado', password: 'otrapassword');
    }

    // ---------------------------------------------------------------
    //  Tests de login()
    // ---------------------------------------------------------------

    public function testLoginExitoso(): void
    {
        $this->auth->registrar(username: 'loginuser', password: 'secreto123');

        $resultado = $this->auth->login(username: 'loginuser', password: 'secreto123');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('token', $resultado);
        $this->assertArrayHasKey('type', $resultado);
        $this->assertArrayHasKey('expires_in', $resultado);
        $this->assertArrayHasKey('user', $resultado);
        $this->assertSame('Bearer', $resultado['type']);
        $this->assertIsString($resultado['token']);
        $this->assertNotEmpty($resultado['token']);
        $this->assertIsInt($resultado['expires_in']);
        $this->assertSame('loginuser', $resultado['user']['username']);
    }

    public function testLoginUsernameInexistente(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Credenciales invalidas');

        $this->auth->login(username: 'noexisto', password: 'password123');
    }

    public function testLoginPasswordIncorrecto(): void
    {
        $this->auth->registrar(username: 'userpass', password: 'correcta123');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Credenciales invalidas');

        $this->auth->login(username: 'userpass', password: 'incorrecta');
    }

    public function testLoginCamposVacios(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('obligatorios');

        $this->auth->login(username: '', password: '');
    }

    public function testLoginUsernameVacioPasswordLleno(): void
    {
        $this->expectException(ValidationException::class);

        $this->auth->login(username: '', password: 'password123');
    }

    public function testLoginUsernameLlenoPasswordVacio(): void
    {
        $this->expectException(ValidationException::class);

        $this->auth->login(username: 'testuser', password: '');
    }

    // ---------------------------------------------------------------
    //  Tests de validarToken()
    // ---------------------------------------------------------------

    public function testValidarTokenValido(): void
    {
        $this->auth->registrar(username: 'tokenuser', password: 'password123');
        $loginResult = $this->auth->login(username: 'tokenuser', password: 'password123');

        $payload = $this->auth->validarToken($loginResult['token']);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('username', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame('tokenuser', $payload['username']);
    }

    public function testValidarTokenFormatoIncorrecto(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('formato incorrecto');

        $this->auth->validarToken('token-sin-puntos');
    }

    public function testValidarTokenFirmaInvalida(): void
    {
        $this->auth->registrar(username: 'firmauser', password: 'password123');
        $loginResult = $this->auth->login(username: 'firmauser', password: 'password123');

        // Modify the signature part of the token
        $partes = explode('.', $loginResult['token']);
        $partes[2] = 'firma_invalida_modificada';
        $tokenModificado = implode('.', $partes);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('firma no coincide');

        $this->auth->validarToken($tokenModificado);
    }

    public function testValidarTokenExpirado(): void
    {
        // Manually craft an expired token by creating the JWT structure
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

        // Sign with the default secret key
        $secret = 'mini_project_api_secret_key_2024';
        $firma = hash_hmac('SHA256', "{$header}.{$payload}", $secret, true);
        $firmaB64 = rtrim(strtr(base64_encode($firma), '+/', '-_'), '=');

        $tokenExpirado = "{$header}.{$payload}.{$firmaB64}";

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('expirado');

        $this->auth->validarToken($tokenExpirado);
    }

    // ---------------------------------------------------------------
    //  Tests de obtenerPerfil()
    // ---------------------------------------------------------------

    public function testObtenerPerfil(): void
    {
        $registrado = $this->auth->registrar(
            username: 'perfiluser',
            password: 'password123',
        );

        $perfil = $this->auth->obtenerPerfil(userId: $registrado['id']);

        $this->assertIsArray($perfil);
        $this->assertSame($registrado['id'], $perfil['id']);
        $this->assertSame('perfiluser', $perfil['username']);
        $this->assertArrayHasKey('created_at', $perfil);
    }

    public function testObtenerPerfilNoExiste(): void
    {
        $this->expectException(NotFoundException::class);

        $this->auth->obtenerPerfil(userId: 99999);
    }

    // ---------------------------------------------------------------
    //  Tests de registro de multiples usuarios
    // ---------------------------------------------------------------

    public function testRegistrarMultiplesUsuarios(): void
    {
        $user1 = $this->auth->registrar(username: 'usuario_uno', password: 'password123');
        $user2 = $this->auth->registrar(username: 'usuario_dos', password: 'password456');
        $user3 = $this->auth->registrar(username: 'usuario_tres', password: 'password789');

        // Verificar que todos tienen IDs unicos
        $this->assertNotSame($user1['id'], $user2['id']);
        $this->assertNotSame($user2['id'], $user3['id']);
        $this->assertNotSame($user1['id'], $user3['id']);

        // Verificar que cada uno tiene su username correcto
        $this->assertSame('usuario_uno', $user1['username']);
        $this->assertSame('usuario_dos', $user2['username']);
        $this->assertSame('usuario_tres', $user3['username']);
    }

    // ---------------------------------------------------------------
    //  Tests adicionales de validacion de registro
    // ---------------------------------------------------------------

    public function testRegistrarUsernameConEspaciosSeRecorta(): void
    {
        $resultado = $this->auth->registrar(
            username: '  trimmed_user  ',
            password: 'password123',
        );

        $this->assertSame('trimmed_user', $resultado['username']);
    }

    public function testRegistrarUsernameConGuionBajo(): void
    {
        $resultado = $this->auth->registrar(
            username: 'user_name_123',
            password: 'password123',
        );

        $this->assertSame('user_name_123', $resultado['username']);
    }

    public function testRegistrarUsernameDe3Caracteres(): void
    {
        $resultado = $this->auth->registrar(
            username: 'abc',
            password: 'password123',
        );

        $this->assertSame('abc', $resultado['username']);
    }

    public function testRegistrarUsernameDe50Caracteres(): void
    {
        $username = str_repeat('a', 50);

        $resultado = $this->auth->registrar(
            username: $username,
            password: 'password123',
        );

        $this->assertSame($username, $resultado['username']);
    }

    public function testRegistrarPasswordDe6Caracteres(): void
    {
        $resultado = $this->auth->registrar(
            username: 'minpass',
            password: '123456',
        );

        $this->assertIsInt($resultado['id']);
    }
}
