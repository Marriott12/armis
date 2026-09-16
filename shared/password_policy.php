<?php
/** ARMIS Password Policy & History helpers. */

if (!function_exists('armisEnsurePasswordHistoryTable')) {
    function armisEnsurePasswordHistoryTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_password_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            svcNo VARCHAR(10) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            changedBy VARCHAR(25) NULL,
            changeReason VARCHAR(100) NULL,
            INDEX idx_staff_password_history_svcNo_createdAt (svcNo, createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function armisPasswordPolicy(): array
    {
        return [
            'min_length' => 12,
            'history_count' => 5,
        ];
    }

    function armisPasswordValidationErrors(string $password, string $confirm = ''): array
    {
        $policy = armisPasswordPolicy();
        $errors = [];
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < $policy['min_length']) {
            $errors[] = 'Password must be at least ' . $policy['min_length'] . ' characters long.';
        }
        if ($password !== '' && !preg_match('/[a-z]/', $password)) $errors[] = 'Include at least one lowercase letter.';
        if ($password !== '' && !preg_match('/[A-Z]/', $password)) $errors[] = 'Include at least one uppercase letter.';
        if ($password !== '' && !preg_match('/\d/', $password)) $errors[] = 'Include at least one number.';
        if ($password !== '' && !preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Include at least one special character.';
        if ($confirm !== '' && $password !== $confirm) $errors[] = 'Passwords do not match.';
        return $errors;
    }

    function armisPasswordWasUsedBefore(PDO $pdo, string $svcNo, string $password): bool
    {
        armisEnsurePasswordHistoryTable($pdo);
        $limit = (int) armisPasswordPolicy()['history_count'];
        $stmt = $pdo->prepare('SELECT password_hash FROM staff_password_history WHERE svcNo = ? ORDER BY createdAt DESC, id DESC LIMIT ' . $limit);
        $stmt->execute([$svcNo]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hash) {
            if (password_verify($password, $hash)) return true;
        }
        return false;
    }

    function armisArchiveCurrentPassword(PDO $pdo, string $svcNo, ?string $currentHash, ?string $changedBy = null, string $reason = 'password_change'): void
    {
        if (!$currentHash) return;
        armisEnsurePasswordHistoryTable($pdo);
        $stmt = $pdo->prepare('INSERT INTO staff_password_history (svcNo, password_hash, changedBy, changeReason) VALUES (?, ?, ?, ?)');
        $stmt->execute([$svcNo, $currentHash, $changedBy, $reason]);
        // Retain only the configured number of historical hashes.
        $limit = (int) armisPasswordPolicy()['history_count'];
        $pdo->prepare('DELETE FROM staff_password_history WHERE svcNo = ? AND id NOT IN (SELECT id FROM (SELECT id FROM staff_password_history WHERE svcNo = ? ORDER BY createdAt DESC, id DESC LIMIT ' . $limit . ') x)')
            ->execute([$svcNo, $svcNo]);
    }

    function armisPasswordStrength(string $password): array
    {
        $score = 0;
        if (strlen($password) >= 12) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/\d/', $password)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;
        if (strlen($password) >= 16) $score++;
        $levels = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
        return ['score' => min($score, 5), 'label' => $levels[min($score, 5)]];
    }
}
