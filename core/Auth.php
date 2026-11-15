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
    public function attemptLogin(string $email, string $password, ?string $requiredRole = null, bool $remember = true): array|false
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

        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        } else {
            self::clearRememberCookie();
        }

        ActivityLogger::log(
            (int) $user['id'],
            'login',
            $user['name'] . ' logged in as ' . $user['role'] . '.',
            $_SESSION['branch_id']
        );

        return $user;
    }

    /**
     * Issue a secure 30-day Remember Me token stored in DB & encrypted cookie.
     */
    public function issueRememberToken(int $userId): void
    {
        self::ensureRememberTable($this->db);

        // Housekeeping: remove expired tokens
        try {
            $this->db->exec("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");
        } catch (\Throwable $e) {}

        // 16 bytes = 32 hex chars selector, 32 bytes = 64 hex chars validator
        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresTs = time() + (30 * 86400); // 30 days
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);

        $stmt = $this->db->prepare("
            INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at)
            VALUES (:user_id, :selector, :token_hash, :expires_at)
        ");
        $stmt->execute([
            ':user_id'    => $userId,
            ':selector'   => $selector,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);

        self::setRememberCookie($selector, $validator, $expiresTs);
    }

    /**
     * Check if a valid remember-me cookie exists and re-establish the session.
     */
    public static function checkRememberMe(PDO $db): bool
    {
        if (self::isLoggedIn()) {
            return true;
        }

        if (empty($_COOKIE['bms_remember'])) {
            return false;
        }

        $cookieVal = urldecode($_COOKIE['bms_remember']);
        $parts = explode(':', $cookieVal, 2);
        if (count($parts) !== 2) {
            self::clearRememberCookie();
            return false;
        }

        [$selector, $validator] = $parts;
        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            self::clearRememberCookie();
            return false;
        }

        self::ensureRememberTable($db);

        try {
            $stmt = $db->prepare("
                SELECT t.id AS token_id, t.user_id, t.token_hash, t.expires_at,
                       u.id AS uid, u.name, u.email, u.role, u.branch_id, u.status, u.deleted_at
                FROM user_remember_tokens t
                JOIN users u ON u.id = t.user_id
                WHERE t.selector = :selector
                LIMIT 1
            ");
            $stmt->execute([':selector' => $selector]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                self::clearRememberCookie();
                return false;
            }

            // Check if expired
            if (strtotime($row['expires_at']) < time()) {
                $del = $db->prepare("DELETE FROM user_remember_tokens WHERE id = :id");
                $del->execute([':id' => $row['token_id']]);
                self::clearRememberCookie();
                return false;
            }

            // Validate token hash (timing-safe comparison)
            if (!hash_equals($row['token_hash'], hash('sha256', $validator))) {
                $del = $db->prepare("DELETE FROM user_remember_tokens WHERE id = :id");
                $del->execute([':id' => $row['token_id']]);
                self::clearRememberCookie();
                return false;
            }

            // Verify user is still active
            if ($row['status'] !== 'active' || $row['deleted_at'] !== null) {
                $del = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = :uid");
                $del->execute([':uid' => $row['uid']]);
                self::clearRememberCookie();
                return false;
            }

            // Re-establish session
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['user_id']   = (int) $row['uid'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['name']      = $row['name'];
            $_SESSION['branch_id'] = $row['branch_id'] !== null ? (int) $row['branch_id'] : null;

            // Rolling token refresh (sliding 30-day window)
            $newValidator = bin2hex(random_bytes(32));
            $newTokenHash = hash('sha256', $newValidator);
            $newExpiresTs = time() + (30 * 86400);
            $newExpiresAt = date('Y-m-d H:i:s', $newExpiresTs);

            $upd = $db->prepare("UPDATE user_remember_tokens SET token_hash = :hash, expires_at = :expires WHERE id = :id");
            $upd->execute([
                ':hash'    => $newTokenHash,
                ':expires' => $newExpiresAt,
                ':id'      => $row['token_id'],
            ]);

            self::setRememberCookie($selector, $newValidator, $newExpiresTs);

            return true;
        } catch (\Throwable $e) {
            error_log('checkRememberMe error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set the Remember Me HTTP cookie with secure flags.
     */
    public static function setRememberCookie(string $selector, string $validator, int $expires): void
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $cookieValue = $selector . ':' . $validator;

        setcookie('bms_remember', $cookieValue, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['bms_remember'] = $cookieValue;
    }

    /**
     * Clear the Remember Me cookie from the client.
     */
    public static function clearRememberCookie(): void
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('bms_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['bms_remember']);
    }

    /**
     * Ensure the user_remember_tokens table exists (self-healing migration).
     */
    public static function ensureRememberTable(PDO $db): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
                    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT UNSIGNED NOT NULL,
                    `selector`    VARCHAR(64) NOT NULL,
                    `token_hash`  VARCHAR(64) NOT NULL,
                    `expires_at`  DATETIME NOT NULL,
                    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_remember_selector` (`selector`),
                    INDEX `idx_remember_user_id` (`user_id`),
                    INDEX `idx_remember_expires_at` (`expires_at`),
                    CONSTRAINT `fk_remember_tokens_user`
                        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('ensureRememberTable notice: ' . $e->getMessage());
        }
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
        // Invalidate Remember Me token in DB and clear cookie
        if (!empty($_COOKIE['bms_remember'])) {
            $parts = explode(':', $_COOKIE['bms_remember'], 2);
            if (count($parts) === 2) {
                try {
                    self::ensureRememberTable($this->db);
                    $stmt = $this->db->prepare("DELETE FROM user_remember_tokens WHERE selector = :selector");
                    $stmt->execute([':selector' => $parts[0]]);
                } catch (\Throwable $e) {}
            }
            self::clearRememberCookie();
        }

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

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
