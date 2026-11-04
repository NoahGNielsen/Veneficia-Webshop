<?php
// Database-backed sessions. Every session is a row in VeneficiaSessions, so we know which customers (and employees)
// are logged in, on which devices, and can log a user out everywhere.
require_once __DIR__ . '/db.php';

const SESSION_COOKIE_NAME = 'VeneficiaSession';
const SESSION_DEFAULT_LIFETIME = 7200;      // 2 hours since last activity
const SESSION_REMEMBER_LIFETIME = 31536000; // 1 year when "Husk mig" is ticked

class VeneficiaSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public int $lifetime = SESSION_DEFAULT_LIFETIME;

    // Only a hash of the session id is stored, so a leaked database can't be used to take over sessions
    public static function hashId(string $id): string
    {
        return hash('sha256', $id);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = getDb()->prepare('SELECT sessionData FROM VeneficiaSessions WHERE sessionID = ? AND ValidUntil > NOW()');
        $stmt->execute([self::hashId($id)]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = getDb()->prepare(
            'INSERT INTO VeneficiaSessions
                (sessionID, customerId, employeeId, sessionData, ValidUntil, lastActivityAt, ipAddress, userAgent)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), ?, ?)
             ON DUPLICATE KEY UPDATE
                customerId = VALUES(customerId),
                employeeId = VALUES(employeeId),
                sessionData = VALUES(sessionData),
                ValidUntil = VALUES(ValidUntil),
                lastActivityAt = VALUES(lastActivityAt),
                ipAddress = VALUES(ipAddress),
                userAgent = VALUES(userAgent)'
        );
        return $stmt->execute([
            self::hashId($id),
            $_SESSION['customerId'] ?? null,
            $_SESSION['employeeId'] ?? null,
            $data,
            $this->lifetime,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255) ?: null,
        ]);
    }

    public function destroy(string $id): bool
    {
        return getDb()->prepare('DELETE FROM VeneficiaSessions WHERE sessionID = ?')->execute([self::hashId($id)]);
    }

    public function gc(int $max_lifetime): int|false
    {
        return getDb()->exec('DELETE FROM VeneficiaSessions WHERE ValidUntil <= NOW()');
    }

    // Used by session.use_strict_mode, so the browser can't pick its own session id
    public function validateId(string $id): bool
    {
        $stmt = getDb()->prepare('SELECT 1 FROM VeneficiaSessions WHERE sessionID = ? AND ValidUntil > NOW()');
        $stmt->execute([self::hashId($id)]);
        return $stmt->fetchColumn() !== false;
    }

    // Called instead of write() when the session data didn't change, to keep the session alive
    public function updateTimestamp(string $id, string $data): bool
    {
        return getDb()
            ->prepare('UPDATE VeneficiaSessions SET ValidUntil = DATE_ADD(NOW(), INTERVAL ? SECOND), lastActivityAt = NOW() WHERE sessionID = ?')
            ->execute([$this->lifetime, self::hashId($id)]);
    }
}

function sessionHandler(): VeneficiaSessionHandler
{
    static $handler = null;
    return $handler ??= new VeneficiaSessionHandler();
}

// Must be called before any output. With $create = false a session is only resumed if the browser already has one,
// so ordinary visitors browsing the shop don't each get a row in the database.
function startSession(bool $create = true): void
{
    if (session_status() === PHP_SESSION_ACTIVE || (!$create && !isset($_COOKIE[SESSION_COOKIE_NAME]))) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_save_handler(sessionHandler(), true);
    session_name(SESSION_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (!empty($_SESSION['rememberMe'])) {
        sessionHandler()->lifetime = SESSION_REMEMBER_LIFETIME;
        sendSessionCookie(time() + SESSION_REMEMBER_LIFETIME);
    }

    // Every page visit by a logged-in customer counts as a login, so lastLoginAt shows when they were last seen
    if (isset($_SESSION['customerId'])) {
        getDb()->prepare('UPDATE VeneficiaCustomers SET lastLoginAt = NOW() WHERE VeneficiaCustomerId = ?')->execute([$_SESSION['customerId']]);
    }
}

function sendSessionCookie(int $expires): void
{
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}

function loginCustomer(int $customerId, bool $rememberMe): void
{
    startSession();
    // New id on login, so a session id planted before login is worthless afterwards
    session_regenerate_id(true);
    $_SESSION = [
        'customerId' => $customerId,
        'rememberMe' => $rememberMe,
        'csrfToken' => bin2hex(random_bytes(32)),
    ];
    if ($rememberMe) {
        sessionHandler()->lifetime = SESSION_REMEMBER_LIFETIME;
        sendSessionCookie(time() + SESSION_REMEMBER_LIFETIME);
    }
    getDb()->prepare('UPDATE VeneficiaCustomers SET lastLoginAt = NOW() WHERE VeneficiaCustomerId = ?')->execute([$customerId]);
}

// Employees get a fresh session of their own (no "Husk mig"), so an admin login never lasts longer than 2 hours idle
function loginEmployee(int $employeeId): void
{
    startSession();
    session_regenerate_id(true);
    $_SESSION = [
        'employeeId' => $employeeId,
        'csrfToken' => bin2hex(random_bytes(32)),
    ];
    sessionHandler()->lifetime = SESSION_DEFAULT_LIFETIME;
}

function logout(): void
{
    startSession(false);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    session_destroy();
    sendSessionCookie(time() - 3600);
}

// The logged-in customer (id, email, names), or null. Only works once startSession() has run.
function currentCustomer(): ?array
{
    static $customer = false;
    if ($customer === false) {
        $customer = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['customerId'])) {
            $stmt = getDb()->prepare('SELECT VeneficiaCustomerId, email, firstName, lastName FROM VeneficiaCustomers WHERE VeneficiaCustomerId = ?');
            $stmt->execute([$_SESSION['customerId']]);
            $customer = $stmt->fetch() ?: null;
        }
    }
    return $customer;
}

// Sends visitors who aren't logged in to the login page
function requireCustomer(): array
{
    startSession(false);
    $customer = currentCustomer();
    if ($customer === null) {
        header('Location: /userMgmt/login');
        exit;
    }
    return $customer;
}

// The logged-in employee (id, username), or null. Only works once startSession() has run.
function currentEmployee(): ?array
{
    static $employee = false;
    if ($employee === false) {
        $employee = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['employeeId'])) {
            $stmt = getDb()->prepare('SELECT employeeID, employeeUsername FROM VeneficiaEmployees WHERE employeeID = ?');
            $stmt->execute([$_SESSION['employeeId']]);
            $employee = $stmt->fetch() ?: null;
        }
    }
    return $employee;
}

// Sends visitors who aren't logged in as an employee to the admin login page
function requireEmployee(): array
{
    startSession(false);
    $employee = currentEmployee();
    if ($employee === null) {
        header('Location: /admin/login');
        exit;
    }
    return $employee;
}

// All active sessions (devices) for a customer, newest activity first
function customerSessions(int $customerId): array
{
    $stmt = getDb()->prepare(
        'SELECT sessionID, createdAt, lastActivityAt, ValidUntil, ipAddress, userAgent FROM VeneficiaSessions
         WHERE customerId = ? AND ValidUntil > NOW() ORDER BY lastActivityAt DESC'
    );
    $stmt->execute([$customerId]);
    $sessions = $stmt->fetchAll();
    foreach ($sessions as &$session) {
        $session['isCurrent'] = session_status() === PHP_SESSION_ACTIVE
            && hash_equals($session['sessionID'], VeneficiaSessionHandler::hashId(session_id()));
    }
    unset($session);
    return $sessions;
}

// Logs the customer out on every device except this one
function logoutOtherSessions(int $customerId): void
{
    getDb()
        ->prepare('DELETE FROM VeneficiaSessions WHERE customerId = ? AND sessionID <> ?')
        ->execute([$customerId, VeneficiaSessionHandler::hashId(session_id())]);
}

function csrfToken(): string
{
    startSession();
    return $_SESSION['csrfToken'] ??= bin2hex(random_bytes(32));
}

function checkCsrf(?string $token): bool
{
    return session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['csrfToken'])
        && is_string($token)
        && hash_equals($_SESSION['csrfToken'], $token);
}
