<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class AuthAdminController
{
    public function showLogin(): void
    {
        if (is_admin()) {
            redirect('/admin');
        }
        view('admin/login', ['title' => 'Login administrativo']);
    }

    public function login(): void
    {
        csrf_check();

        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        $stmt = Database::pdo()->prepare('SELECT id, name, password_hash, active, role, email_verified_at FROM admins WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        $admin = $stmt->fetch();

        // Sempre executa password_verify para evitar diferença de tempo
        $hash = $admin['password_hash'] ?? '$2y$10$usesomesillystringforsaleuQ5rQ3kK0Yq0m0x0x0x0x0x0x0x0x0x0';
        if ($admin && password_verify($pass, $hash)) {
            // Professor que ainda não confirmou o e-mail: leva para a confirmação do código.
            if ((int) $admin['active'] !== 1 && $admin['role'] === 'teacher' && $admin['email_verified_at'] === null) {
                $_SESSION['verify_email'] = $email;
                flash('error', 'Confirme seu e-mail para entrar. Digite o código ou peça um novo.');
                redirect('/cadastro/verificar');
            }
            if ((int) $admin['active'] !== 1) {
                flash('error', 'E-mail ou senha inválidos.');
                redirect('/admin/login');
            }
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'] === 'teacher' ? 'teacher' : 'admin';
            redirect('/admin');
        }

        flash('error', 'E-mail ou senha inválidos.');
        redirect('/admin/login');
    }

    public function logout(): void
    {
        csrf_check();
        $_SESSION = [];
        session_destroy();
        redirect('/admin/login');
    }
}
