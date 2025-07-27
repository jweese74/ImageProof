<?php
/**
 * UserDAO.php — Data Access Object for User Records
 *
 * Encapsulates all database operations for user entities, including lookups by e‑mail or ID,
 * password hash updates, and last‑login timestamp management. This abstraction centralizes
 * SQL logic for user data and simplifies testing, security auditing, and future extension.
 *
 * @package    PixlKey
 * @subpackage Core\DAO
 * @author     Jeffrey Weese
 * @license    MIT
 * @version    0.5.1.4-alpha
 */

declare(strict_types=1);

namespace PixlKey\DAO;

use PDO;
use PDOException;

class UserDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Find user by e-mail */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, email, password_hash, display_name, is_admin FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Find user by ID.
     * Now returns password_hash as well for session revalidation or JWT issuance.
     */
    public function findById(string $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT user_id, email, password_hash, display_name, is_admin FROM users WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (PDOException $e) {
            // Log silently in production
            if (defined('DB_DEBUG') && DB_DEBUG) {
                error_log('UserDAO::findById failed: ' . $e->getMessage());
            }
            return null;
        }
    }

    /** Update password hash */
    public function updatePasswordHash(string $userId, string $newHash): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        return $stmt->execute([$newHash, $userId]);
    }

    /** Update last login timestamp */
    public function updateLastLogin(string $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
        return $stmt->execute([$userId]);
    }
}
