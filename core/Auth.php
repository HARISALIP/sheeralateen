<?php
/**
 * Auth
 * ---------------------------------------------------------
 * Handles login/logout and role checks. The unified login page
 * uses this class without passing a role to allow anyone to log in,
 * then checks their role. Protected pages use requireRole().
 */
class Auth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Attempt to log a user in, restricted to a specific role.
     * Returns the user row on success, or false on failure.
     */
    public function attemptLogin(string $email, string $password, ?string $requiredRole = null): array|false
    {
        $sql = "SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1";
        $params = [':email' => $email];

        if ($requiredRole !== null) {
            $sql = "SELECT * FROM users WHERE email = :email AND role = :role AND deleted_at IS NULL LIMIT 1";
            $params[':role'] = $requiredRole;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Mark last login time
        $update = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);

        // Establish session (regenerate ID to prevent session fixation)
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['branch_id'] = $user['branch_id'] !== null ? (int) $user['branch_id'] : null;

        ActivityLogger::log(
            (int) $user['id'],
            'login',
            $user['name'] . ' logged in as ' . $user['role'] . '.',
            $_SESSION['branch_id']
        );

        return $user;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }

    public static function hasRole(string $role): bool
    {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }

    /**
     * Guard for the top of any protected page. Redirects to the
     * given login page if the current session doesn't match the
     * required role.
     */
    public static function requireRole(string $role, string $redirectTo): void
    {
        if (!self::hasRole($role)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'logout',
                ($_SESSION['name'] ?? 'User') . ' logged out.',
                $_SESSION['branch_id'] ?? null
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
