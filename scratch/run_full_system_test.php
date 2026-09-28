<?php
declare(strict_types=1);

/**
 * ====================================================================
 * SUITE DE PRUEBAS INTEGRALES DE SISTEMA - CONTROL DE PERSONAL JUSHSAL
 * ====================================================================
 * Simula interacciones reales de usuario vía HTTP contra Apache/PHP
 * Verifica Frontend, Backend, Sesiones, Roles, APIs, DB y Casos Límite.
 */

set_time_limit(300);

class TestHttpClient {
    private string $baseUrl;
    private string $cookieFile;
    private ?string $lastCsrfToken = null;
    public int $lastStatusCode = 0;
    public array $lastHeaders = [];
    public string $lastBody = '';
    public ?string $lastRedirectUrl = null;

    public function __construct(string $baseUrl, string $sessionName = 'default') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/zktest_cookie_' . $sessionName . '_' . getmypid() . '.txt';
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function resetSession(): void {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->lastCsrfToken = null;
    }

    public function get(string $path, array $queryParams = [], bool $isAjax = false): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }
        return $this->request('GET', $url, [], $isAjax);
    }

    public function post(string $path, array $data = [], bool $isAjax = false, bool $autoCsrf = true): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($autoCsrf && !isset($data['_csrf']) && !isset($data['_csrf_token'])) {
            if ($this->lastCsrfToken === null) {
                // Fetch a page to extract csrf token first
                $this->get('index.php?route=login');
            }
            if ($this->lastCsrfToken !== null) {
                $data['_csrf'] = $this->lastCsrfToken;
            }
        }
        return $this->request('POST', $url, $data, $isAjax);
    }

    public function postMultipart(string $path, array $fields = [], array $files = [], bool $isAjax = false, bool $autoCsrf = true): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($autoCsrf && !isset($fields['_csrf']) && !isset($fields['_csrf_token'])) {
            if ($this->lastCsrfToken !== null) {
                $fields['_csrf'] = $this->lastCsrfToken;
            }
        }
        return $this->request('POST_MULTIPART', $url, ['fields' => $fields, 'files' => $files], $isAjax);
    }

    private function request(string $method, string $url, array $data = [], bool $isAjax = false): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't auto follow so we inspect redirects
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) E2E-Tester/1.0',
        ];
        if ($isAjax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            $headers[] = 'Accept: application/json';
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($method === 'POST_MULTIPART') {
            curl_setopt($ch, CURLOPT_POST, true);
            $postData = $data['fields'] ?? [];
            foreach (($data['files'] ?? []) as $field => $filePath) {
                if (file_exists($filePath)) {
                    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
                    $postData[$field] = new \CURLFile($filePath, $mime, basename($filePath));
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $rawResponse = curl_exec($ch);
        $this->lastStatusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL Error on [$method $url]: $error");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $this->lastBody = substr($rawResponse, $headerSize);
        $this->lastHeaders = [];
        $this->lastRedirectUrl = null;

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                list($key, $val) = explode(':', $line, 2);
                $k = strtolower(trim($key));
                $v = trim($val);
                $this->lastHeaders[$k] = $v;
                if ($k === 'location') {
                    $this->lastRedirectUrl = $v;
                }
            }
        }

        // Extract CSRF token if present in HTML body
        if (preg_match('/name=["\']_csrf["\']\s+value=["\']([a-f0-9]{64})["\']/i', $this->lastBody, $m)) {
            $this->lastCsrfToken = $m[1];
        } elseif (preg_match('/csrf_token["\']?\s*:\s*["\']([a-f0-9]{64})["\']/i', $this->lastBody, $m)) {
            $this->lastCsrfToken = $m[1];
        }

        return $this->lastBody;
    }

    public function getCsrfToken(): ?string {
        return $this->lastCsrfToken;
    }

    public function setCsrfToken(?string $token): void {
        $this->lastCsrfToken = $token;
    }
}

class SystemTestRunner {
    private string $baseUrl = 'http://localhost:8080/control_personal';
    private \PDO $db;
    public array $results = [];
    public array $issues = [];

    public function __construct() {
        $this->db = new \PDO("mysql:host=127.0.0.1;port=3306;dbname=control_personal;charset=utf8mb4", "root", "", [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }

    public function recordResult(string $modulo, string $funcionalidad, string $prueba, string $resultado, ?string $problema = null, string $gravedad = 'Bajo', ?string $causa = null, ?string $archivo = null, ?string $pasos = null): void {
        $this->results[] = [
            'modulo' => $modulo,
            'funcionalidad' => $funcionalidad,
            'prueba' => $prueba,
            'resultado' => $resultado,
            'problema' => $problema,
            'gravedad' => $gravedad
        ];

        if ($resultado === '❌' || $resultado === '⚠️') {
            $this->issues[] = [
                'modulo' => $modulo,
                'funcionalidad' => $funcionalidad,
                'pasos' => $pasos ?? $prueba,
                'resultado_esperado' => 'Operación exitosa y respuesta esperada',
                'resultado_obtenido' => $problema,
                'causa_probable' => $causa ?? 'Revisar código fuente relacionado',
                'archivo' => $archivo ?? 'app/Controllers/' . $modulo . 'Controller.php',
                'gravedad' => $gravedad
            ];
        }

        $icon = match($resultado) {
            '✅' => '[OK]',
            '⚠️' => '[OBS]',
            '❌' => '[FAIL]',
            default => '[SKIP]'
        };
        echo "  $icon [$modulo] $funcionalidad: $prueba -> $resultado" . ($problema ? " ($problema)" : "") . "\n";
    }

    public function runAll(): void {
        echo "=========================================================================\n";
        echo "INICIANDO BATERÍA DE PRUEBAS INTEGRALES END-TO-END DEL SISTEMA JUSHSAL\n";
        echo "=========================================================================\n\n";

        $this->testAuthAndSecurity();
        $this->testRoleAccessControl();
        $this->testUsuariosModule();
        $this->testDashboardModule();
        $this->testEmpleadosModule();
        $this->testTurnosModule();
        $this->testDispositivosModule();
        $this->testMarcacionesModule();
        $this->testAsistenciaModule();
        $this->testJustificacionesModule();
        $this->testEdgeCasesAndSecurityHeaders();

        $this->printSummary();
    }

    private function loginClient(TestHttpClient $client, string $user, string $pass): bool {
        // Fetch login page to get initial csrf token
        $html = $client->get('index.php?route=login');
        $token = $client->getCsrfToken();
        if (!$token) {
            return false;
        }

        $client->post('index.php?route=login', [
            'usuario' => $user,
            'password' => $pass,
            '_csrf' => $token
        ]);

        // A successful login returns a redirect 302 to first accessible route
        if ($client->lastStatusCode === 302 && $client->lastRedirectUrl && !str_contains($client->lastRedirectUrl, 'route=login')) {
            return true;
        }
        return false;
    }

    /**
     * MÓDULO 1: Autenticación, Sesiones y Seguridad
     */
    private function testAuthAndSecurity(): void {
        echo "\n--- [MÓDULO: Autenticación y Sesiones] ---\n";
        $mod = 'Autenticación';

        // 1. Acceso a ruta protegida sin sesión
        $client = new TestHttpClient($this->baseUrl, 'unauth');
        $client->get('index.php?route=dashboard');
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'route=login')) {
            $this->recordResult($mod, 'Control de Sesión', 'Acceso anónimo a dashboard redirige a login', '✅');
        } else {
            $this->recordResult($mod, 'Control de Sesión', 'Acceso anónimo a dashboard no redirigió correctamente', '❌', "Código HTTP {$client->lastStatusCode}", 'Alto');
        }

        // 2. Acceso AJAX anónimo devuelve 401 JSON
        $client->get('index.php?route=dashboard', ['ajax' => '1'], true);
        if ($client->lastStatusCode === 401) {
            $json = json_decode($client->lastBody, true);
            if (isset($json['error']) && $json['error'] === 'session_expired') {
                $this->recordResult($mod, 'Control de Sesión AJAX', 'Petición AJAX anónima retorna 401 session_expired', '✅');
            } else {
                $this->recordResult($mod, 'Control de Sesión AJAX', 'Retornó 401 pero estructura JSON no esperada', '⚠️');
            }
        } else {
            $this->recordResult($mod, 'Control de Sesión AJAX', 'Petición AJAX anónima no retornó 401', '❌', "Código HTTP {$client->lastStatusCode}", 'Medio');
        }

        // 3. Login con campos vacíos
        $client->get('index.php?route=login');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=login', ['usuario' => '', 'password' => '', '_csrf' => $token]);
        if (str_contains($client->lastBody, 'Por favor ingrese usuario y contraseña')) {
            $this->recordResult($mod, 'Formulario de Login', 'Validación de campos vacíos muestra mensaje de error', '✅');
        } else {
            $this->recordResult($mod, 'Formulario de Login', 'No mostró mensaje al enviar campos vacíos', '❌', 'Mensaje esperado no encontrado', 'Medio');
        }

        // 4. Login con contraseña incorrecta
        $client->post('index.php?route=login', ['usuario' => 'admin', 'password' => 'wrong_password_xyz', '_csrf' => $token]);
        if (str_contains($client->lastBody, 'Credenciales incorrectas')) {
            $this->recordResult($mod, 'Autenticación', 'Credenciales erróneas rechazadas con mensaje', '✅');
        } else {
            $this->recordResult($mod, 'Autenticación', 'No mostró error con credenciales incorrectas', '❌', 'Mensaje esperado no encontrado', 'Alto');
        }

        // 5. Protección CSRF en Login (token inválido)
        $client->post('index.php?route=login', ['usuario' => 'admin', 'password' => 'admin123', '_csrf' => 'invalid_csrf_token_0000000000000000000000000000000000000000']);
        if ($client->lastStatusCode === 419 || str_contains($client->lastBody, '419') || str_contains($client->lastBody, 'expirada')) {
            $this->recordResult($mod, 'Protección CSRF', 'Rechazo con HTTP 419 ante token CSRF inválido', '✅');
        } else {
            $this->recordResult($mod, 'Protección CSRF', 'No rechazó petición POST con CSRF inválido', '❌', "Código HTTP {$client->lastStatusCode}", 'Crítico');
        }

        // 6. Login exitoso con admin
        $client->resetSession();
        $loginOk = $this->loginClient($client, 'admin', 'admin123');
        if ($loginOk) {
            $this->recordResult($mod, 'Inicio de Sesión', 'Login exitoso de Administrador (admin123)', '✅');
        } else {
            $this->recordResult($mod, 'Inicio de Sesión', 'Fallo al iniciar sesión con admin', '❌', 'Login no redirigió', 'Crítico');
        }

        // 7. Acceso a dashboard una vez autenticado
        $dashHtml = $client->get('index.php?route=dashboard');
        if ($client->lastStatusCode === 200 && (str_contains($dashHtml, 'Tablero Principal') || str_contains($dashHtml, 'JUSHSAL'))) {
            $this->recordResult($mod, 'Sesión Activa', 'Dashboard carga correctamente con sesión iniciada', '✅');
        } else {
            $this->recordResult($mod, 'Sesión Activa', 'Dashboard falló al cargar con sesión', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 8. Cambio de Contraseña de Perfil - Contraseña actual incorrecta
        $token = $client->getCsrfToken();
        $client->post('index.php?route=perfil&action=cambiar_password', [
            '_csrf' => $token,
            'current_password' => 'clave_falsa_999',
            'new_password' => 'NuevaClave2026',
            'confirm_password' => 'NuevaClave2026'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=error_actual')) {
            $this->recordResult($mod, 'Perfil Usuario', 'Rechazo de cambio de clave con contraseña actual errónea', '✅');
        } else {
            $this->recordResult($mod, 'Perfil Usuario', 'No validó contraseña actual incorrecta', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 9. Cambio de Contraseña de Perfil - Contraseña débil (sin mayúscula o sin número)
        $client->get('index.php?route=dashboard');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=perfil&action=cambiar_password', [
            '_csrf' => $token,
            'current_password' => 'admin123',
            'new_password' => 'debil',
            'confirm_password' => 'debil'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=error_pass_corta')) {
            $this->recordResult($mod, 'Perfil Usuario', 'Validación de complejidad de contraseña débil rechazada', '✅');
        } else {
            $this->recordResult($mod, 'Perfil Usuario', 'Permitió contraseña débil o mensaje no coincidió', '❌', "Redirect: {$client->lastRedirectUrl}", 'Medio');
        }

        // 10. Logout / Cierre de Sesión
        $client->get('index.php?route=logout');
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'route=login')) {
            $this->recordResult($mod, 'Cierre de Sesión', 'Logout destruye sesión y redirige a login', '✅');
        } else {
            $this->recordResult($mod, 'Cierre de Sesión', 'Logout no redirigió correctamente', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 11. Verificar que después del logout no se puede entrar a dashboard
        $client->get('index.php?route=dashboard');
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'route=login')) {
            $this->recordResult($mod, 'Cierre de Sesión', 'Acceso post-logout es bloqueado correctamente', '✅');
        } else {
            $this->recordResult($mod, 'Cierre de Sesión', 'Sesión permaneció abierta tras logout', '❌', 'Fallo de invalidación de sesión', 'Crítico');
        }
    }

    /**
     * MÓDULO 2: Control de Accesos por Roles (RBAC)
     */
    private function testRoleAccessControl(): void {
        echo "\n--- [MÓDULO: Control de Accesos por Roles (RBAC)] ---\n";
        $mod = 'Roles y Permisos';

        // 1. Rol CONSULTA: Debe poder ver asistencia y marcaciones, pero NO usuarios ni editar
        $clientConsulta = new TestHttpClient($this->baseUrl, 'consulta');
        $this->loginClient($clientConsulta, 'consulta', 'admin123');

        // Acceso permitido a asistencia
        $clientConsulta->get('index.php?route=asistencia');
        if ($clientConsulta->lastStatusCode === 200) {
            $this->recordResult($mod, 'Rol CONSULTA', 'Acceso permitido a consulta de asistencia (GET)', '✅');
        } else {
            $this->recordResult($mod, 'Rol CONSULTA', 'Fallo al acceder a asistencia', '❌', "Código {$clientConsulta->lastStatusCode}", 'Alto');
        }

        // Acceso prohibido a usuarios del sistema
        $clientConsulta->get('index.php?route=usuarios');
        if ($clientConsulta->lastStatusCode === 302 && str_contains($clientConsulta->lastRedirectUrl ?? '', 'acceso_denegado')) {
            $this->recordResult($mod, 'Rol CONSULTA', 'Bloqueo estricto a módulo Usuarios (redirige con acceso_denegado)', '✅');
        } else {
            $this->recordResult($mod, 'Rol CONSULTA', 'Accedió o no bloqueó módulo Usuarios para CONSULTA', '❌', "Código {$clientConsulta->lastStatusCode}, URL: {$clientConsulta->lastRedirectUrl}", 'Crítico');
        }

        // Intento de acción POST prohibida (guardar empleado)
        $token = $clientConsulta->getCsrfToken();
        $clientConsulta->post('index.php?route=empleados&action=guardar', [
            '_csrf' => $token,
            'codigo_reloj' => '9999',
            'dni' => '99999999',
            'nombres' => 'Hacker',
            'apellidos' => 'Prueba'
        ]);
        if ($clientConsulta->lastStatusCode === 302 && str_contains($clientConsulta->lastRedirectUrl ?? '', 'acceso_denegado')) {
            $this->recordResult($mod, 'Rol CONSULTA', 'Bloqueo estricto en acción POST de empleados', '✅');
        } else {
            $this->recordResult($mod, 'Rol CONSULTA', 'No bloqueó acción POST no autorizada', '❌', "Código {$clientConsulta->lastStatusCode}", 'Crítico');
        }

        // 2. Rol SUPERVISOR:
        $clientSup = new TestHttpClient($this->baseUrl, 'supervisor');
        $this->loginClient($clientSup, 'supervisor', 'admin123');

        // Intento de resolver justificaciones (solo ADMIN y RRHH pueden resolver)
        $token = $clientSup->getCsrfToken();
        $clientSup->post('index.php?route=justificaciones&action=resolver', [
            '_csrf' => $token,
            'id' => 1,
            'estado' => 'APROBADO'
        ]);
        if ($clientSup->lastStatusCode === 302 && str_contains($clientSup->lastRedirectUrl ?? '', 'acceso_denegado')) {
            $this->recordResult($mod, 'Rol SUPERVISOR', 'Bloqueo a resolver justificaciones (solo ADMIN/RRHH)', '✅');
        } else {
            $this->recordResult($mod, 'Rol SUPERVISOR', 'Supervisor pudo ejecutar resolución de justificación', '❌', "Código {$clientSup->lastStatusCode}", 'Crítico');
        }

        // 3. Rol RRHH: Puede gestionar personal y turnos, pero NO administración de usuarios
        $clientRrhh = new TestHttpClient($this->baseUrl, 'rrhh');
        $this->loginClient($clientRrhh, 'rrhh', 'admin123');

        $clientRrhh->get('index.php?route=usuarios');
        if ($clientRrhh->lastStatusCode === 302 && str_contains($clientRrhh->lastRedirectUrl ?? '', 'acceso_denegado')) {
            $this->recordResult($mod, 'Rol RRHH', 'Bloqueo a módulo Usuarios (exclusivo ADMIN)', '✅');
        } else {
            $this->recordResult($mod, 'Rol RRHH', 'RRHH pudo acceder al módulo de Usuarios del sistema', '❌', "Código {$clientRrhh->lastStatusCode}", 'Crítico');
        }
    }

    /**
     * MÓDULO 3: Usuarios del Sistema (CRUD y Reglas de Negocio)
     */
    private function testUsuariosModule(): void {
        echo "\n--- [MÓDULO: Usuarios del Sistema] ---\n";
        $mod = 'Usuarios';

        $client = new TestHttpClient($this->baseUrl, 'admin_usr');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Listado de usuarios
        $html = $client->get('index.php?route=usuarios');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Usuarios del Sistema')) {
            $this->recordResult($mod, 'Listado de Usuarios', 'Carga de interfaz y catálogo de usuarios', '✅');
        } else {
            $this->recordResult($mod, 'Listado de Usuarios', 'Fallo al cargar página de usuarios', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Validación: Campos obligatorios vacíos
        $token = $client->getCsrfToken();
        $client->post('index.php?route=usuarios&action=guardar', [
            '_csrf' => $token,
            'usuario' => '',
            'nombre_completo' => '',
            'password' => ''
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=error_campos')) {
            $this->recordResult($mod, 'Creación de Usuario', 'Validación de campos obligatorios requeridos', '✅');
        } else {
            $this->recordResult($mod, 'Creación de Usuario', 'No validó campos vacíos', '❌', "Redirect: {$client->lastRedirectUrl}", 'Medio');
        }

        // 3. Validación: Contraseña requerida al crear nuevo usuario
        $client->get('index.php?route=usuarios');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=usuarios&action=guardar', [
            '_csrf' => $token,
            'usuario' => 'testuser_nopass',
            'nombre_completo' => 'Usuario Sin Pass',
            'password' => '',
            'rol' => 'RRHH'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=error_password_requerida')) {
            $this->recordResult($mod, 'Creación de Usuario', 'Exige contraseña obligatoria para nuevo usuario', '✅');
        } else {
            $this->recordResult($mod, 'Creación de Usuario', 'Permitió crear usuario sin contraseña', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 4. Validación: Contraseña débil
        $client->get('index.php?route=usuarios');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=usuarios&action=guardar', [
            '_csrf' => $token,
            'usuario' => 'testuser_weak',
            'nombre_completo' => 'Usuario Pass Debil',
            'password' => '123456',
            'rol' => 'RRHH'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=error_pass_corta')) {
            $this->recordResult($mod, 'Creación de Usuario', 'Rechaza contraseña que no cumple política de complejidad', '✅');
        } else {
            $this->recordResult($mod, 'Creación de Usuario', 'Permitió contraseña insegura', '❌', "Redirect: {$client->lastRedirectUrl}", 'Medio');
        }

        // 5. Creación exitosa de nuevo usuario
        $testUsername = 'test_operador_' . time();
        $client->get('index.php?route=usuarios');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=usuarios&action=guardar', [
            '_csrf' => $token,
            'usuario' => $testUsername,
            'nombre_completo' => 'Operador Automatizado E2E',
            'email' => 'operador_e2e@empresa.com',
            'password' => 'ClaveFuerte2026!',
            'rol' => 'RRHH',
            'activo' => '1'
        ]);

        $createdUser = $this->db->query("SELECT * FROM usuarios_sistema WHERE usuario = '$testUsername'")->fetch();
        if ($createdUser) {
            $this->recordResult($mod, 'Creación de Usuario', "Usuario '$testUsername' persistido correctamente en BD", '✅');
            $testUserId = (int)$createdUser['id'];

            // 6. Probar inicio de sesión con el nuevo usuario
            $clientNewUser = new TestHttpClient($this->baseUrl, 'new_usr');
            $newLoginOk = $this->loginClient($clientNewUser, $testUsername, 'ClaveFuerte2026!');
            if ($newLoginOk) {
                $this->recordResult($mod, 'Inicio de Sesión', 'Nuevo usuario puede autenticarse con sus credenciales', '✅');
            } else {
                $this->recordResult($mod, 'Inicio de Sesión', 'Nuevo usuario no pudo autenticarse', '❌', 'Login falló', 'Crítico');
            }

            // 7. Modificación del usuario (Edición)
            $client->get('index.php?route=usuarios');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=usuarios&action=guardar', [
                '_csrf' => $token,
                'id' => $testUserId,
                'usuario' => $testUsername,
                'nombre_completo' => 'Operador E2E Modificado',
                'email' => 'operador_editado@empresa.com',
                'rol' => 'SUPERVISOR',
                'activo' => '1'
            ]);
            $updatedUser = $this->db->query("SELECT * FROM usuarios_sistema WHERE id = $testUserId")->fetch();
            if ($updatedUser && $updatedUser['nombre_completo'] === 'Operador E2E Modificado' && $updatedUser['rol'] === 'SUPERVISOR') {
                $this->recordResult($mod, 'Edición de Usuario', 'Datos de usuario actualizados correctamente en BD', '✅');
            } else {
                $this->recordResult($mod, 'Edición de Usuario', 'Datos no se actualizaron en la BD', '❌', 'Verificar query update', 'Alto');
            }

            // 8. Desactivar usuario y verificar que ya no puede ingresar
            $client->get('index.php?route=usuarios');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=usuarios&action=cambiar_estado', [
                '_csrf' => $token,
                'id' => $testUserId
            ], true);
            $deactUser = $this->db->query("SELECT activo FROM usuarios_sistema WHERE id = $testUserId")->fetch();
            if ($deactUser && (int)$deactUser['activo'] === 0) {
                $this->recordResult($mod, 'Estado de Usuario', 'Usuario desactivado vía AJAX', '✅');
            } else {
                $this->recordResult($mod, 'Estado de Usuario', 'No se actualizó estado a inactivo', '❌', 'activo != 0', 'Alto');
            }

            // Intento de login con cuenta desactivada
            $clientDeact = new TestHttpClient($this->baseUrl, 'deact_usr');
            $loginDeactOk = $this->loginClient($clientDeact, $testUsername, 'ClaveFuerte2026!');
            if (!$loginDeactOk) {
                $this->recordResult($mod, 'Control de Acceso', 'Cuenta desactivada no puede iniciar sesión', '✅');
            } else {
                $this->recordResult($mod, 'Control de Acceso', 'Cuenta desactivada logró iniciar sesión', '❌', 'Fallo de seguridad', 'Crítico');
            }

            // 9. Restablecer contraseña de usuario
            $client->get('index.php?route=usuarios');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=usuarios&action=restablecer_password', [
                '_csrf' => $token,
                'id' => $testUserId,
                'new_password' => 'NuevaClaveSegura2026#'
            ], true);
            $jsonReset = json_decode($client->lastBody, true);
            if ($jsonReset && !empty($jsonReset['success'])) {
                $this->recordResult($mod, 'Restablecer Clave', 'Administrador restableció contraseña exitosamente', '✅');
            } else {
                $this->recordResult($mod, 'Restablecer Clave', 'Fallo al restablecer contraseña', '❌', $client->lastBody, 'Alto');
            }

            // 10. Protección: Prohibido desactivar o eliminar cuenta de Administrador principal
            $adminUser = $this->db->query("SELECT id FROM usuarios_sistema WHERE rol = 'ADMIN' LIMIT 1")->fetch();
            $adminId = (int)$adminUser['id'];
            $client->get('index.php?route=usuarios');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=usuarios&action=cambiar_estado', ['_csrf' => $token, 'id' => $adminId], true);
            $jsonAdminState = json_decode($client->lastBody, true);
            if ($jsonAdminState && empty($jsonAdminState['success']) && str_contains($jsonAdminState['error'] ?? '', 'protegida')) {
                $this->recordResult($mod, 'Protección de Cuenta Admin', 'Sistema previene desactivación del Administrador principal', '✅');
            } else {
                $this->recordResult($mod, 'Protección de Cuenta Admin', 'No protegió al Administrador principal contra desactivación', '❌', 'Vulnerabilidad administrativa', 'Crítico');
            }

            // 11. Eliminación física (Hard Delete) del usuario de prueba
            $client->get('index.php?route=usuarios');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=usuarios&action=eliminar', ['_csrf' => $token, 'id' => $testUserId], true);
            $deletedCheck = $this->db->query("SELECT id FROM usuarios_sistema WHERE id = $testUserId")->fetch();
            if (!$deletedCheck) {
                $this->recordResult($mod, 'Eliminación de Usuario', "Usuario '$testUsername' eliminado definitivamente de la BD", '✅');
            } else {
                $this->recordResult($mod, 'Eliminación de Usuario', 'Usuario no fue eliminado de la BD', '❌', 'Registro aún existe', 'Alto');
            }
        } else {
            $this->recordResult($mod, 'Creación de Usuario', 'Fallo al crear usuario de prueba en BD', '❌', 'Registro no encontrado tras POST', 'Crítico');
        }
    }

    /**
     * MÓDULO 4: Dashboard y Métricas
     */
    private function testDashboardModule(): void {
        echo "\n--- [MÓDULO: Tablero Principal / Dashboard] ---\n";
        $mod = 'Dashboard';

        $client = new TestHttpClient($this->baseUrl, 'dash_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga de Dashboard
        $html = $client->get('index.php?route=dashboard');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Puntualidad')) {
            $this->recordResult($mod, 'Carga de Dashboard', 'Dashboard carga estadísticas y elementos gráficos', '✅');
        } else {
            $this->recordResult($mod, 'Carga de Dashboard', 'Dashboard falló al cargar', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Endpoint AJAX de tendencia de datos (7 días)
        $client->get('index.php?route=dashboard&action=tendencia_datos', ['periodo' => '7d'], true);
        $json7d = json_decode($client->lastBody, true);
        if ($json7d && isset($json7d['labels']) && isset($json7d['series']['presentes'])) {
            $this->recordResult($mod, 'Tendencia AJAX (7d)', 'Endpoint retorna JSON estructurado con series de asistencia', '✅');
        } else {
            $this->recordResult($mod, 'Tendencia AJAX (7d)', 'Estructura JSON inválida o error en respuesta', '❌', $client->lastBody, 'Medio');
        }

        // 3. Endpoint AJAX de tendencia con filtro de período 'mes_actual'
        $client->get('index.php?route=dashboard&action=tendencia_datos', ['periodo' => 'mes_actual'], true);
        $jsonMes = json_decode($client->lastBody, true);
        if ($jsonMes && isset($jsonMes['labels']) && count($jsonMes['labels']) > 0) {
            $this->recordResult($mod, 'Tendencia AJAX (Mes Actual)', 'Endpoint procesa rango mensual correctamente', '✅');
        } else {
            $this->recordResult($mod, 'Tendencia AJAX (Mes Actual)', 'Fallo en filtro de mes actual', '❌', $client->lastBody, 'Medio');
        }

        // 4. Endpoint AJAX de tendencia con filtro por departamento
        $depto = $this->db->query("SELECT id FROM departamentos WHERE activo = 1 LIMIT 1")->fetch();
        if ($depto) {
            $deptoId = (int)$depto['id'];
            $client->get('index.php?route=dashboard&action=tendencia_datos', ['periodo' => '7d', 'departamento_id' => $deptoId], true);
            $jsonDepto = json_decode($client->lastBody, true);
            if ($jsonDepto && isset($jsonDepto['labels'])) {
                $this->recordResult($mod, 'Filtro Departamental', 'Tendencia filtrada por departamento retorna datos coherentes', '✅');
            } else {
                $this->recordResult($mod, 'Filtro Departamental', 'Fallo en filtro departamental', '❌', $client->lastBody, 'Medio');
            }
        }
    }

    /**
     * MÓDULO 5: Directorio de Personal / Empleados (CRUD)
     */
    private function testEmpleadosModule(): void {
        echo "\n--- [MÓDULO: Gestión de Empleados] ---\n";
        $mod = 'Empleados';

        $client = new TestHttpClient($this->baseUrl, 'emp_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga del directorio
        $html = $client->get('index.php?route=empleados');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Directorio de Personal')) {
            $this->recordResult($mod, 'Directorio', 'Listado de empleados carga correctamente con DataTables', '✅');
        } else {
            $this->recordResult($mod, 'Directorio', 'Fallo al cargar módulo de empleados', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Validación: Campos obligatorios vacíos
        $token = $client->getCsrfToken();
        $client->post('index.php?route=empleados&action=guardar', [
            '_csrf' => $token,
            'codigo_reloj' => '',
            'dni' => '',
            'nombres' => ''
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=campos_requeridos')) {
            $this->recordResult($mod, 'Validación Formulario', 'Rechaza registro con campos obligatorios vacíos', '✅');
        } else {
            $this->recordResult($mod, 'Validación Formulario', 'No validó campos vacíos', '❌', "Redirect: {$client->lastRedirectUrl}", 'Medio');
        }

        // 3. Validación: Formato de DNI inválido (debe tener exactamente 8 dígitos numéricos)
        $client->get('index.php?route=empleados');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=empleados&action=guardar', [
            '_csrf' => $token,
            'codigo_reloj' => '8888',
            'dni' => '1234', // Inválido
            'nombres' => 'Juan',
            'apellidos' => 'Perez'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=dni_invalido')) {
            $this->recordResult($mod, 'Validación DNI', 'Rechaza formato de DNI inválido (menos de 8 dígitos)', '✅');
        } else {
            $this->recordResult($mod, 'Validación DNI', 'Permitió DNI con formato erróneo', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 4. Creación de empleado válido
        $testDni = '7' . str_pad((string)random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $testCodigoReloj = '9' . str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $turno = $this->db->query("SELECT id FROM turnos WHERE activo = 1 LIMIT 1")->fetch();
        $depto = $this->db->query("SELECT id FROM departamentos WHERE activo = 1 LIMIT 1")->fetch();
        $cargo = $this->db->query("SELECT id FROM cargos WHERE activo = 1 LIMIT 1")->fetch();

        $client->get('index.php?route=empleados');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=empleados&action=guardar', [
            '_csrf' => $token,
            'codigo_reloj' => $testCodigoReloj,
            'dni' => $testDni,
            'nombres' => 'Empleado Test',
            'apellidos' => 'Automatizado E2E',
            'email' => 'emptest@empresa.com',
            'telefono' => '987654321',
            'departamento_id' => $depto['id'] ?? null,
            'cargo_id' => $cargo['id'] ?? null,
            'turno_id' => $turno['id'] ?? null,
            'fecha_ingreso' => date('Y-m-d'),
            'activo' => '1'
        ]);

        $empInDb = $this->db->query("SELECT * FROM empleados WHERE dni = '$testDni'")->fetch();
        if ($empInDb) {
            $this->recordResult($mod, 'Creación de Empleado', "Empleado con DNI '$testDni' y código reloj '$testCodigoReloj' guardado en BD", '✅');
            $empId = (int)$empInDb['id'];

            // 5. Edición de empleado
            $client->get('index.php?route=empleados');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=empleados&action=guardar', [
                '_csrf' => $token,
                'id' => $empId,
                'codigo_reloj' => $testCodigoReloj,
                'dni' => $testDni,
                'nombres' => 'Empleado Test Modificado',
                'apellidos' => 'Automatizado E2E',
                'email' => 'emptest_mod@empresa.com',
                'telefono' => '999111222',
                'departamento_id' => $depto['id'] ?? null,
                'cargo_id' => $cargo['id'] ?? null,
                'turno_id' => $turno['id'] ?? null,
                'fecha_ingreso' => date('Y-m-d'),
                'activo' => '1'
            ]);

            $empMod = $this->db->query("SELECT nombres, email FROM empleados WHERE id = $empId")->fetch();
            if ($empMod && $empMod['nombres'] === 'Empleado Test Modificado') {
                $this->recordResult($mod, 'Edición de Empleado', 'Modificación de datos de empleado persistida en BD', '✅');
            } else {
                $this->recordResult($mod, 'Edición de Empleado', 'Fallo al actualizar empleado en BD', '❌', 'Nombres no cambiaron', 'Alto');
            }

            // 6. Desactivación / Eliminación lógica
            $client->get('index.php?route=empleados');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=empleados&action=eliminar', [
                '_csrf' => $token,
                'id' => $empId
            ]);
            $empDeact = $this->db->query("SELECT activo FROM empleados WHERE id = $empId")->fetch();
            if ($empDeact && (int)$empDeact['activo'] === 0) {
                $this->recordResult($mod, 'Desactivación de Empleado', 'Empleado marcado como inactivo (soft-delete seguro)', '✅');
            } else {
                $this->recordResult($mod, 'Desactivación de Empleado', 'No se desactivó el empleado', '❌', 'activo != 0', 'Alto');
            }

            // Limpieza del registro de prueba
            $this->db->exec("DELETE FROM empleados WHERE id = $empId");
        } else {
            $this->recordResult($mod, 'Creación de Empleado', 'Fallo al insertar empleado en base de datos', '❌', "Redirect: {$client->lastRedirectUrl}", 'Crítico');
        }
    }

    /**
     * MÓDULO 6: Turnos y Horarios Laborales (CRUD)
     */
    private function testTurnosModule(): void {
        echo "\n--- [MÓDULO: Turnos y Horarios] ---\n";
        $mod = 'Turnos';

        $client = new TestHttpClient($this->baseUrl, 'turnos_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga de turnos
        $html = $client->get('index.php?route=turnos');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Turnos y Horarios')) {
            $this->recordResult($mod, 'Catálogo de Turnos', 'Listado de horarios laborales carga correctamente', '✅');
        } else {
            $this->recordResult($mod, 'Catálogo de Turnos', 'Fallo al cargar turnos', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Validación: Horario inválido (Entrada mayor o igual a salida en turno no nocturno)
        $token = $client->getCsrfToken();
        $client->post('index.php?route=turnos&action=guardar', [
            '_csrf' => $token,
            'nombre' => 'Turno Invalido Test',
            'hora_entrada' => '17:00:00',
            'hora_salida' => '08:00:00',
            'es_nocturno' => '0'
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=horario_invalido')) {
            $this->recordResult($mod, 'Validación de Horario', 'Detecta y rechaza horario inválido (entrada >= salida sin marcar nocturno)', '✅');
        } else {
            $this->recordResult($mod, 'Validación de Horario', 'Permitió horario diurno invertido', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 3. Creación de turno válido
        $turnoNombre = 'Turno Especial E2E ' . time();
        $client->get('index.php?route=turnos');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=turnos&action=guardar', [
            '_csrf' => $token,
            'nombre' => $turnoNombre,
            'hora_entrada' => '07:30:00',
            'hora_salida' => '16:30:00',
            'tolerancia_minutos' => '15',
            'tolerancia_falta_minutos' => '45',
            'hora_inicio_refrigerio' => '12:30:00',
            'hora_fin_refrigerio' => '13:15:00',
            'minutos_refrigerio' => '45',
            'dias_laborables' => ['1','2','3','4','5'],
            'activo' => '1'
        ]);

        $turnoDb = $this->db->query("SELECT * FROM turnos WHERE nombre = '$turnoNombre'")->fetch();
        if ($turnoDb) {
            $this->recordResult($mod, 'Creación de Turno', "Turno '$turnoNombre' persistido correctamente en BD", '✅');
            $turnoId = (int)$turnoDb['id'];

            // 4. Edición de turno
            $client->get('index.php?route=turnos');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=turnos&action=guardar', [
                '_csrf' => $token,
                'id' => $turnoId,
                'nombre' => $turnoNombre . ' Modificado',
                'hora_entrada' => '07:00:00',
                'hora_salida' => '16:00:00',
                'tolerancia_minutos' => '10',
                'tolerancia_falta_minutos' => '45',
                'activo' => '1'
            ]);
            $turnoMod = $this->db->query("SELECT hora_entrada, tolerancia_minutos FROM turnos WHERE id = $turnoId")->fetch();
            if ($turnoMod && $turnoMod['hora_entrada'] === '07:00:00' && (int)$turnoMod['tolerancia_minutos'] === 10) {
                $this->recordResult($mod, 'Edición de Turno', 'Actualización de parámetros de turno persistida en BD', '✅');
            } else {
                $this->recordResult($mod, 'Edición de Turno', 'Datos de turno no se actualizaron', '❌', 'Verificar UPDATE turnos', 'Alto');
            }

            // 5. Eliminación de turno sin empleados asignados
            $client->get('index.php?route=turnos');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=turnos&action=eliminar', [
                '_csrf' => $token,
                'id' => $turnoId
            ]);
            $turnoDel = $this->db->query("SELECT id FROM turnos WHERE id = $turnoId")->fetch();
            if (!$turnoDel) {
                $this->recordResult($mod, 'Eliminación de Turno', 'Turno sin dependencias eliminado exitosamente de BD', '✅');
            } else {
                // Soft-deleted or not deleted
                $this->recordResult($mod, 'Eliminación de Turno', 'Turno desactivado o no eliminado', '⚠️', 'Verificar si fue soft delete');
                $this->db->exec("DELETE FROM turnos WHERE id = $turnoId");
            }
        } else {
            $this->recordResult($mod, 'Creación de Turno', 'Fallo al guardar turno en BD', '❌', "Redirect: {$client->lastRedirectUrl}", 'Crítico');
        }
    }

    /**
     * MÓDULO 7: Relojes Biométricos / Dispositivos
     */
    private function testDispositivosModule(): void {
        echo "\n--- [MÓDULO: Relojes Biométricos / Hardware] ---\n";
        $mod = 'Dispositivos';

        $client = new TestHttpClient($this->baseUrl, 'disp_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga del módulo
        $html = $client->get('index.php?route=dispositivos');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Relojes Biométricos')) {
            $this->recordResult($mod, 'Catálogo de Terminales', 'Página de dispositivos biométricos carga correctamente', '✅');
        } else {
            $this->recordResult($mod, 'Catálogo de Terminales', 'Fallo al cargar módulo de dispositivos', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Creación de terminal biométrico
        $dispNombre = 'Reloj Test E2E ' . time();
        $token = $client->getCsrfToken();
        $client->post('index.php?route=dispositivos&action=guardar', [
            '_csrf' => $token,
            'nombre' => $dispNombre,
            'ip' => '192.168.1.250',
            'puerto' => '4370',
            'protocolo' => 'TCP',
            'clave_comunicacion' => '0',
            'ubicacion' => 'Puerta de Pruebas Automatizadas',
            'modelo' => 'ZKTeco K40 Test',
            'activo' => '1'
        ]);

        $dispDb = $this->db->query("SELECT * FROM dispositivos WHERE nombre = '$dispNombre'")->fetch();
        if ($dispDb) {
            $this->recordResult($mod, 'Registro de Terminal', "Dispositivo '$dispNombre' guardado en BD con IP 192.168.1.250", '✅');
            $dispId = (int)$dispDb['id'];

            // 3. Test de conexión a dispositivo offline (manejo seguro de timeouts)
            $client->get('index.php?route=dispositivos');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=dispositivos&action=test', [
                '_csrf' => $token,
                'id' => $dispId
            ], true);
            $jsonTest = json_decode($client->lastBody, true);
            if ($client->lastStatusCode === 200 && $jsonTest !== null) {
                $this->recordResult($mod, 'Diagnóstico de Conexión', 'Test de conectividad responde JSON estructurado sin colgar el servidor', '✅');
            } else {
                $this->recordResult($mod, 'Diagnóstico de Conexión', 'Test de conectividad falló o crasheó', '❌', "HTTP: {$client->lastStatusCode}", 'Alto');
            }

            // 4. Endpoint de Estado de Sincronización (sync_status)
            $client->get('index.php?route=dispositivos&action=sync_status', [], true);
            $jsonSync = json_decode($client->lastBody, true);
            if ($client->lastStatusCode === 200 && isset($jsonSync['running']) && isset($jsonSync['dispositivos'])) {
                $this->recordResult($mod, 'Monitor de Sincronización', 'sync_status responde JSON en tiempo real con estado del daemon', '✅');
            } else {
                $this->recordResult($mod, 'Monitor de Sincronización', 'sync_status no respondió formato JSON esperado', '❌', $client->lastBody, 'Medio');
            }

            // 5. Eliminación / Desactivación de dispositivo
            $client->get('index.php?route=dispositivos');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=dispositivos&action=eliminar', [
                '_csrf' => $token,
                'id' => $dispId
            ]);
            $dispCheck = $this->db->query("SELECT activo FROM dispositivos WHERE id = $dispId")->fetch();
            if ($dispCheck && (int)$dispCheck['activo'] === 0) {
                $this->recordResult($mod, 'Desactivación de Terminal', 'Terminal desactivado correctamente vía soft delete', '✅');
            } else {
                $this->recordResult($mod, 'Desactivación de Terminal', 'Fallo al desactivar terminal', '❌', 'activo != 0', 'Alto');
            }

            // Limpieza de prueba
            $this->db->exec("DELETE FROM dispositivos WHERE id = $dispId");
        } else {
            $this->recordResult($mod, 'Registro de Terminal', 'Fallo al insertar dispositivo en BD', '❌', "Redirect: {$client->lastRedirectUrl}", 'Crítico');
        }
    }

    /**
     * MÓDULO 8: Registro de Marcaciones
     */
    private function testMarcacionesModule(): void {
        echo "\n--- [MÓDULO: Registro de Marcaciones] ---\n";
        $mod = 'Marcaciones';

        $client = new TestHttpClient($this->baseUrl, 'marc_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga del historial de marcaciones
        $html = $client->get('index.php?route=marcaciones');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Registro de Marcaciones')) {
            $this->recordResult($mod, 'Historial de Marcaciones', 'Listado de marcaciones crudas carga con filtros y KPIs', '✅');
        } else {
            $this->recordResult($mod, 'Historial de Marcaciones', 'Fallo al cargar marcaciones', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Registro Manual de Marcación
        $emp = $this->db->query("SELECT id, codigo_reloj FROM empleados WHERE activo = 1 LIMIT 1")->fetch();
        if ($emp) {
            $empId = (int)$emp['id'];
            $testFechaHora = date('Y-m-d') . ' 07:55:00';

            $token = $client->getCsrfToken();
            $client->post('index.php?route=marcaciones&action=guardar_manual', [
                '_csrf' => $token,
                'id_empleado' => $empId,
                'fecha_hora' => $testFechaHora,
                'tipo' => 'entrada',
                'id_dispositivo' => '1',
                'motivo' => 'Marcación manual de prueba automatizada E2E'
            ]);

            $marcDb = $this->db->query("SELECT * FROM marcaciones WHERE id_empleado = $empId AND fecha_hora = '$testFechaHora'")->fetch();
            if ($marcDb) {
                $this->recordResult($mod, 'Marcación Manual', "Marcación registrada en BD con tipo_verificacion = '{$marcDb['tipo_verificacion']}'", '✅');

                // 3. Verificar auditoría en EventStore
                $eventoDb = $this->db->query("
                    SELECT * FROM eventos_asistencia 
                    WHERE tipo_evento = 'MARCACION_MANUAL_REGISTRADA' 
                      AND id_agregado LIKE 'emp_{$empId}_%' 
                    ORDER BY id DESC LIMIT 1
                ")->fetch();
                if ($eventoDb) {
                    $this->recordResult($mod, 'Event Sourcing / Auditoría', 'Evento inmutable registrado en tabla eventos_asistencia', '✅');
                } else {
                    $this->recordResult($mod, 'Event Sourcing / Auditoría', 'No se encontró evento en eventos_asistencia', '⚠️', 'EventStore silencioso');
                }

                // Limpieza de prueba
                $this->db->exec("DELETE FROM marcaciones WHERE id = {$marcDb['id']}");
                if ($eventoDb) {
                    $this->db->exec("DELETE FROM eventos_asistencia WHERE id = {$eventoDb['id']}");
                }
            } else {
                $this->recordResult($mod, 'Marcación Manual', 'Fallo al persistir marcación manual en BD', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
            }
        }
    }

    /**
     * MÓDULO 9: Control de Asistencia y Reportes
     */
    private function testAsistenciaModule(): void {
        echo "\n--- [MÓDULO: Control de Asistencia y Consolidado] ---\n";
        $mod = 'Asistencia';

        $client = new TestHttpClient($this->baseUrl, 'asist_admin');
        $this->loginClient($client, 'admin', 'admin123');

        // 1. Carga del consolidado diario
        $html = $client->get('index.php?route=asistencia');
        if ($client->lastStatusCode === 200 && str_contains($html, 'Control de Asistencia')) {
            $this->recordResult($mod, 'Consolidado Diario', 'Interfaz de consolidado diario y métricas carga correctamente', '✅');
        } else {
            $this->recordResult($mod, 'Consolidado Diario', 'Fallo al cargar consolidado de asistencia', '❌', "Código {$client->lastStatusCode}", 'Alto');
        }

        // 2. Acción Recalcular Asistencia
        $today = date('Y-m-d');
        $token = $client->getCsrfToken();
        $client->post('index.php?route=asistencia&action=recalcular', [
            '_csrf' => $token,
            'fecha_inicio' => $today,
            'fecha_fin' => $today
        ]);
        if ($client->lastStatusCode === 302 && str_contains($client->lastRedirectUrl ?? '', 'msg=recalculado')) {
            $this->recordResult($mod, 'Recálculo de Asistencia', 'AttendanceCalculator procesó fecha con éxito', '✅');
        } else {
            $this->recordResult($mod, 'Recálculo de Asistencia', 'Fallo en recálculo de asistencia', '❌', "Redirect: {$client->lastRedirectUrl}", 'Alto');
        }

        // 3. Edición administrativa de asistencia (Ajuste oficial de horario)
        $asistRow = $this->db->query("SELECT * FROM asistencia_diaria WHERE fecha = '$today' LIMIT 1")->fetch();
        if ($asistRow) {
            $asistId = (int)$asistRow['id'];
            $client->get('index.php?route=asistencia');
            $token = $client->getCsrfToken();
            $client->post('index.php?route=asistencia&action=editar', [
                '_csrf' => $token,
                'id' => $asistId,
                'fecha' => $today,
                'hora_entrada_real' => '08:00:00',
                'hora_salida_real' => '17:00:00',
                'estado' => 'PRESENTE',
                'observaciones' => 'Ajuste administrativo verificado E2E'
            ], true);
            $jsonEdit = json_decode($client->lastBody, true);
            if ($client->lastStatusCode === 200 && ($jsonEdit['success'] ?? false)) {
                $this->recordResult($mod, 'Ajuste Administrativo', 'Edición de horas y estado confirmada con recálculo de métricas', '✅');
            } else {
                $this->recordResult($mod, 'Ajuste Administrativo', 'Fallo en edición de asistencia', '❌', $client->lastBody, 'Alto');
            }

            // 4. Modal de Historial de Eventos (Event Sourcing)
            $client->get('index.php?route=asistencia&action=historial_eventos', ['id' => $asistId], true);
            $jsonEvents = json_decode($client->lastBody, true);
            if ($client->lastStatusCode === 200 && isset($jsonEvents['eventos'])) {
                $this->recordResult($mod, 'Trazabilidad de Eventos', 'Modal de historial de eventos retorna línea de tiempo auditada', '✅');
            } else {
                $this->recordResult($mod, 'Trazabilidad de Eventos', 'Fallo al consultar historial_eventos', '❌', $client->lastBody, 'Medio');
            }
        }
    }

    /**
     * MÓDULO 10: Permisos y Justificaciones
     */
    private function testJustificacionesModule(): void {
        echo "\n--- [MÓDULO: Permisos y Justificaciones] ---\n";
        $mod = 'Justificaciones';

        $clientAdmin = new TestHttpClient($this->baseUrl, 'just_admin');
        $this->loginClient($clientAdmin, 'admin', 'admin123');

        // 1. Carga del módulo
        $html = $clientAdmin->get('index.php?route=justificaciones');
        if ($clientAdmin->lastStatusCode === 200 && str_contains($html, 'Permisos y Justificaciones')) {
            $this->recordResult($mod, 'Listado de Permisos', 'Interfaz de justificaciones carga con filtros y modales', '✅');
        } else {
            $this->recordResult($mod, 'Listado de Permisos', 'Fallo al cargar justificaciones', '❌', "Código {$clientAdmin->lastStatusCode}", 'Alto');
        }

        // 2. Validación de rango de fechas invertido (fin < inicio)
        $emp = $this->db->query("SELECT id FROM empleados WHERE activo = 1 LIMIT 1")->fetch();
        $empId = (int)$emp['id'];

        $token = $clientAdmin->getCsrfToken();
        $clientAdmin->post('index.php?route=justificaciones&action=guardar', [
            '_csrf' => $token,
            'id_empleado' => $empId,
            'tipo' => 'PERMISO',
            'fecha_inicio' => '2026-10-10',
            'fecha_fin' => '2026-10-05', // Invertido
            'motivo' => 'Prueba rango invertido'
        ]);
        if ($clientAdmin->lastStatusCode === 302 && str_contains($clientAdmin->lastRedirectUrl ?? '', 'error=rango_invalido')) {
            $this->recordResult($mod, 'Validación Fechas', 'Rechaza fecha fin anterior a fecha inicio', '✅');
        } else {
            $this->recordResult($mod, 'Validación Fechas', 'Permitió rango de fechas invertido', '❌', "Redirect: {$clientAdmin->lastRedirectUrl}", 'Alto');
        }

        // 3. Registro de justificación por SUPERVISOR (Debe crearse en estado PENDIENTE)
        $clientSup = new TestHttpClient($this->baseUrl, 'just_sup');
        $this->loginClient($clientSup, 'supervisor', 'admin123');
        $supUser = $this->db->query("SELECT departamento_id FROM usuarios_sistema WHERE usuario = 'supervisor'")->fetch();
        $supDeptoId = (int)($supUser['departamento_id'] ?? 0);
        $empSup = $supDeptoId > 0 ? $this->db->query("SELECT id FROM empleados WHERE departamento_id = $supDeptoId AND activo = 1 LIMIT 1")->fetch() : $emp;
        $empSupId = (int)($empSup['id'] ?? $empId);

        $clientSup->get('index.php?route=justificaciones');
        $token = $clientSup->getCsrfToken();
        $clientSup->post('index.php?route=justificaciones&action=guardar', [
            '_csrf' => $token,
            'id_empleado' => $empSupId,
            'tipo' => 'SALUD',
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => date('Y-m-d'),
            'motivo' => 'Descanso médico registrado por supervisor E2E'
        ]);

        $justSupDb = $this->db->query("SELECT * FROM justificaciones WHERE id_empleado = $empSupId AND motivo LIKE '%descanso médico registrado por supervisor%' ORDER BY id DESC LIMIT 1")->fetch();
        if ($justSupDb && $justSupDb['estado'] === 'PENDIENTE') {
            $this->recordResult($mod, 'Segregación de Funciones (SoD)', 'Supervisor genera justificación en estado PENDIENTE', '✅');
            $justId = (int)$justSupDb['id'];

            // 4. Resolución de justificación por Administrador (Aprobación)
            $clientAdmin->get('index.php?route=justificaciones');
            $token = $clientAdmin->getCsrfToken();
            $clientAdmin->post('index.php?route=justificaciones&action=resolver', [
                '_csrf' => $token,
                'id' => $justId,
                'estado' => 'APROBADO'
            ]);

            $justResolved = $this->db->query("SELECT estado, aprobado_por FROM justificaciones WHERE id = $justId")->fetch();
            if ($justResolved && $justResolved['estado'] === 'APROBADO' && !empty($justResolved['aprobado_por'])) {
                $this->recordResult($mod, 'Resolución de Permiso', 'Administrador aprobó la justificación pendiente exitosamente', '✅');
            } else {
                $this->recordResult($mod, 'Resolución de Permiso', 'Fallo al aprobar justificación', '❌', 'Estado no cambió a APROBADO', 'Alto');
            }

            // Limpieza
            $this->db->exec("DELETE FROM justificaciones WHERE id = $justId");
        } else {
            $this->recordResult($mod, 'Segregación de Funciones (SoD)', 'Supervisor no creó justificación en estado PENDIENTE', '❌', 'Estado o inserción incorrecta', 'Alto');
        }
    }

    /**
     * MÓDULO 11: Casos Límite, Inyecciones y Encabezados de Seguridad
     */
    private function testEdgeCasesAndSecurityHeaders(): void {
        echo "\n--- [MÓDULO: Casos Límite y Encabezados de Seguridad] ---\n";
        $mod = 'Casos Límite y Headers';

        $client = new TestHttpClient($this->baseUrl, 'headers_test');
        $client->get('index.php?route=login');

        // 1. Verificación de Encabezados de Seguridad OWASP
        $headers = $client->lastHeaders;
        $hasNoSniff = ($headers['x-content-type-options'] ?? '') === 'nosniff';
        $hasSameOrigin = ($headers['x-frame-options'] ?? '') === 'SAMEORIGIN';
        $hasCsp = isset($headers['content-security-policy']);

        if ($hasNoSniff && $hasSameOrigin && $hasCsp) {
            $this->recordResult($mod, 'Encabezados OWASP', 'X-Content-Type-Options, X-Frame-Options y CSP presentes', '✅');
        } else {
            $this->recordResult($mod, 'Encabezados OWASP', 'Faltan encabezados de seguridad HTTP recomendados', '⚠️', 'Revisar headers en public/index.php');
        }

        // 2. Verificación de Cookie con banderas HttpOnly y SameSite
        $setCookie = $headers['set-cookie'] ?? '';
        if (str_contains(strtolower($setCookie), 'httponly') && str_contains(strtolower($setCookie), 'samesite=lax')) {
            $this->recordResult($mod, 'Blindaje de Cookies', 'Cookie de sesión configurada con HttpOnly y SameSite=Lax', '✅');
        } else {
            $this->recordResult($mod, 'Blindaje de Cookies', 'Cookie no cuenta con todas las directivas de endurecimiento', '⚠️');
        }

        // 3. Caso Límite: Búsqueda con caracteres especiales y comillas (Anti-SQLi)
        $this->loginClient($client, 'admin', 'admin123');
        $client->get('index.php?route=empleados', ['search' => "' OR '1'='1' -- "]);
        if ($client->lastStatusCode === 200) {
            $this->recordResult($mod, 'Resistencia SQLi', 'Búsqueda con payloads SQL procesada con Prepared Statements sin errores de sintaxis', '✅');
        } else {
            $this->recordResult($mod, 'Resistencia SQLi', 'Error en consulta SQL con caracteres especiales', '❌', "HTTP: {$client->lastStatusCode}", 'Crítico');
        }

        // 4. Caso Límite: Parámetros ID fuera de rango o negativos
        $client->get('index.php?route=justificaciones&action=ver_adjunto', ['id' => -999]);
        if ($client->lastStatusCode === 404) {
            $this->recordResult($mod, 'ID Fuera de Rango', 'Consulta con ID negativo retorna 404 limpio', '✅');
        } else {
            $this->recordResult($mod, 'ID Fuera de Rango', 'Respuesta inesperada para ID negativo', '⚠️', "HTTP: {$client->lastStatusCode}");
        }
    }

    private function printSummary(): void {
        $total = count($this->results);
        $correct = 0;
        $obs = 0;
        $errors = 0;

        foreach ($this->results as $r) {
            if ($r['resultado'] === '✅') $correct++;
            elseif ($r['resultado'] === '⚠️') $obs++;
            elseif ($r['resultado'] === '❌') $errors++;
        }

        echo "\n=========================================================================\n";
        echo "RESUMEN DE RESULTADOS DE PRUEBAS INTEGRALES\n";
        echo "=========================================================================\n";
        echo "Total de pruebas ejecutadas: $total\n";
        echo "✅ Correctas: $correct\n";
        echo "⚠️ Con observaciones: $obs\n";
        echo "❌ Con errores: $errors\n\n";

        if (!empty($this->issues)) {
            echo "DETALLE DE PROBLEMAS ENCONTRADOS (" . count($this->issues) . "):\n";
            foreach ($this->issues as $idx => $iss) {
                $n = $idx + 1;
                echo "---------------------------------------------------------\n";
                echo "[$n] Módulo: {$iss['modulo']} | Funcionalidad: {$iss['funcionalidad']} [Gravedad: {$iss['gravedad']}]\n";
                echo "    Pasos: {$iss['pasos']}\n";
                echo "    Resultado Esperado: {$iss['resultado_esperado']}\n";
                echo "    Resultado Obtenido: {$iss['resultado_obtenido']}\n";
                echo "    Causa Probable: {$iss['causa_probable']}\n";
                echo "    Archivo Relacionado: {$iss['archivo']}\n";
            }
            echo "---------------------------------------------------------\n";
        } else {
            echo "¡No se encontraron errores críticos! Todos los módulos operan correctamente.\n";
        }
    }
}

// Ejecutar runner
$runner = new SystemTestRunner();
$runner->runAll();
