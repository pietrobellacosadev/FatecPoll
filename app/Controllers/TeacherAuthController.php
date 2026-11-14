<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\TeacherRegistration as Reg;

/** Cadastro de professores: formulário, confirmação do código enviado por e-mail e reenvio. */
final class TeacherAuthController
{
    public function showRegister(): void
    {
        if (is_admin()) {
            redirect('/admin');
        }
        view('auth/register', ['title' => 'Cadastro de professores', 'errors' => [], 'old' => ['name' => '', 'email' => '']]);
    }

    public function register(): void
    {
        csrf_check();
        $name  = (string) ($_POST['name'] ?? '');
        $email = (string) ($_POST['email'] ?? '');

        $res = Reg::register($name, $email, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
        if ($res['errors']) {
            http_response_code(422);
            view('auth/register', [
                'title' => 'Cadastro de professores', 'errors' => $res['errors'],
                'old' => ['name' => trim($name), 'email' => trim($email)],
            ]);
            return;
        }

        $_SESSION['verify_email'] = $res['email'];
        if (!empty($res['mail_failed'])) {
            flash('error', $res['notice'] . ' Seu cadastro foi salvo: use "Reenviar código".');
        } else {
            flash('success', 'Enviamos um código de 6 dígitos para ' . Reg::maskEmail($res['email']) . '.');
        }
        redirect('/cadastro/verificar');
    }

    public function showVerify(): void
    {
        $email = $this->pendingEmail();
        view('auth/verify', ['title' => 'Confirmar e-mail', 'email' => $email, 'errors' => []]);
    }

    public function verify(): void
    {
        csrf_check();
        $email = $this->pendingEmail();

        $errors = Reg::verify($email, (string) ($_POST['code'] ?? ''));
        if ($errors) {
            http_response_code(422);
            view('auth/verify', ['title' => 'Confirmar e-mail', 'email' => $email, 'errors' => $errors]);
            return;
        }
        unset($_SESSION['verify_email']);
        flash('success', 'E-mail confirmado! Entre com seu e-mail e senha.');
        redirect('/admin/login');
    }

    public function resend(): void
    {
        csrf_check();
        $email = $this->pendingEmail();
        $id = Reg::pendingId($email);
        if ($id === null) {
            unset($_SESSION['verify_email']);
            redirect('/admin/login');
        }
        $err = Reg::sendCode($id, $email);
        if ($err === null) {
            flash('success', 'Novo código enviado para ' . Reg::maskEmail($email) . '.');
        } else {
            flash('error', $err);
        }
        redirect('/cadastro/verificar');
    }

    /** E-mail em confirmação nesta sessão; sem ele, volta ao cadastro. */
    private function pendingEmail(): string
    {
        $email = $_SESSION['verify_email'] ?? '';
        if (!is_string($email) || $email === '') {
            redirect('/cadastro');
        }
        return $email;
    }
}
