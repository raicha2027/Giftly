<?php

require_once __DIR__ . '/../config/db.php';

class AuthController
{
    public function registerUser($data)
    {
        global $pdo;

        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (
            $firstName === '' ||
            $lastName === '' ||
            $email === '' ||
            $phone === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            return 'Please complete all fields.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }

        if (strlen($password) < 8) {
            return 'Your password must contain at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            return 'Your passwords do not match.';
        }

        try {
            $check = $pdo->prepare(
                "SELECT user_id FROM giftly_users WHERE email = ? LIMIT 1"
            );

            $check->execute([$email]);

            if ($check->fetch()) {
                return 'An account with this email already exists.';
            }

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO giftly_users
                (role_id, first_name, last_name, email, phone, password_hash, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)"
            );

            $stmt->execute([
                1,
                $firstName,
                $lastName,
                $email,
                $phone,
                $passwordHash
            ]);

            return true;

        } catch (PDOException $e) {
            return 'Registration failed. Please try again.';
        }
    }
}
