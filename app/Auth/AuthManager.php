<?php
declare(strict_types=1);

final class AuthManager
{
    public static function demoLogin(string $profile): void
    {
        if (!config('app.demo_mode', false)) {
            throw new RuntimeException('El acceso de demostración está desactivado.');
        }

        $profiles = [
            'resident' => [
                'id' => 'demo-resident-001',
                'name' => 'María González',
                'email' => 'residente@demo.local',
                'role' => 'RESIDENT',
                'portal' => 'resident',
                'avatar' => '',
                'membership_status' => 'approved',
            ],
            'admin' => [
                'id' => 'demo-admin-001',
                'name' => 'Administrador General',
                'email' => 'admin@demo.local',
                'role' => 'ADMIN',
                'portal' => 'operations',
                'avatar' => '',
                'membership_status' => 'approved',
            ],
            'security' => [
                'id' => 'demo-security-001',
                'name' => 'Operador de Vigilancia',
                'email' => 'vigilancia@demo.local',
                'role' => 'SECURITY',
                'portal' => 'operations',
                'avatar' => '',
                'membership_status' => 'approved',
            ],
        ];

        if (InstallationMode::isCentral()) {
            $profiles = ['central'=>[
                'id'=>'demo-central-001','name'=>'Administrador Property Hub Central','email'=>'central@demo.local',
                'role'=>'SUPERADMIN','portal'=>'operations','avatar'=>'','membership_status'=>'approved',
            ]];
        }
        if (!isset($profiles[$profile])) {
            throw new InvalidArgumentException('Perfil de demostración no válido para esta modalidad.');
        }

        session_regenerate_id(true);
        $_SESSION['auth_user'] = $profiles[$profile];
        $_SESSION['active_context'] = 0;
    }

    public static function localLogin(string $identifier, string $password): void
    {
        $identifier = trim(strtolower($identifier));
        if ($identifier === '' || $password === '') throw new DomainException('Indique usuario y contraseña.');
        $pdo = Connection::pdo();
        // Cumulative-update safety: a package can replace PHP files before a pending
        // DB migration is executed (or an operator can upload files manually in cPanel).
        // Do not brick local login just because email_verified_at is not present yet.
        $columnStmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='bh_local_users' AND column_name='email_verified_at' LIMIT 1");
        $columnStmt->execute();
        $emailVerifiedSelect = $columnStmt->fetchColumn() !== false ? 'u.email_verified_at' : 'NOW() AS email_verified_at';
        $stmt = $pdo->prepare("SELECT u.id,u.person_id,u.email,u.username,u.password_hash,u.user_type,u.status,".$emailVerifiedSelect.",COALESCE(p.display_name,u.username,u.email,'Usuario') display_name FROM bh_local_users u LEFT JOIN bh_people p ON p.id=u.person_id WHERE LOWER(COALESCE(u.email,''))=? OR LOWER(COALESCE(u.username,''))=? LIMIT 1");
        $stmt->execute([$identifier,$identifier]);
        $row = $stmt->fetch();
        if (!is_array($row) || !is_string($row['password_hash'] ?? null) || !password_verify($password, (string)$row['password_hash'])) {
            throw new DomainException('Usuario o contraseña incorrectos.');
        }
        if (($row['status'] ?? '') !== 'active') throw new DomainException('La cuenta local no está activa.');
        if ((string)$row['user_type']==='resident' && empty($row['email_verified_at'])) throw new DomainException('Debes confirmar tu correo antes de iniciar sesión.');
        $role = match ((string)$row['user_type']) {
            'superadmin' => 'SUPERADMIN', 'admin' => 'ADMIN', 'security' => 'SECURITY', 'operator' => 'ADMIN', 'resident' => 'RESIDENT', default => 'ADMIN',
        };
        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'id'=>(int)$row['id'],
            'name'=>(string)($row['display_name'] ?: $row['username'] ?: $row['email'] ?: 'Usuario'),
            'person_id'=>(int)($row['person_id'] ?? 0) ?: null,
            'email'=>(string)($row['email'] ?? ''),
            'role'=>$role,
            'portal'=>$role === 'RESIDENT' ? 'resident' : 'operations',
            'avatar'=>'',
            'membership_status'=>'approved',
            'auth_provider'=>'local',
        ];
        $_SESSION['active_context']=0;
        try {
            $pdo->prepare('UPDATE bh_local_users SET last_login_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        } catch (Throwable $e) {
            ErrorReporter::runtimeLog('auth_last_login_update_failed', [
                'user_id'=>(int)$row['id'],
                'exception'=>get_class($e),
                'message'=>$e->getMessage(),
            ]);
        }
        ErrorReporter::authSuccess($identifier, (int)$row['id']);
    }

    public static function socialLogin(array $profile): void
    {
        if (($profile['provider_sub'] ?? '') === '') {
            throw new RuntimeException('El proveedor no devolvió un identificador válido.');
        }

        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'id' => 'social-'.sha1((string) $profile['provider'].'|'.(string) $profile['provider_sub']),
            'name' => (string) ($profile['name'] ?: $profile['email'] ?: 'Usuario'),
            'email' => (string) ($profile['email'] ?? ''),
            'role' => 'RESIDENT',
            'portal' => 'resident',
            'avatar' => (string) ($profile['avatar'] ?? ''),
            'provider' => (string) ($profile['provider'] ?? ''),
            'provider_sub' => (string) $profile['provider_sub'],
            'membership_status' => 'pending',
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function landingRoute(): string
    {
        $user = current_user();
        if (!$user) {
            return 'login';
        }
        if (($user['membership_status'] ?? 'approved') !== 'approved') {
            return 'membership-pending';
        }
        if (($user['portal'] ?? '') === 'resident') return 'resident-home';
        if (InstallationMode::isCentral()) return 'repository-dashboard';
        if (InstallationMode::isEdge()) return 'edge-dashboard';
        return 'dashboard';
    }
}
