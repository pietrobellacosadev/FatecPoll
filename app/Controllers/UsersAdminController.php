<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use PDO;

/** Gerenciamento de professores, exclusivo do administrador global. */
final class UsersAdminController
{
    public function index(): void
    {
        require_super_admin();
        $pdo = Database::pdo();
        $teachers = $pdo->query("SELECT id, name, email, active, email_verified_at, created_at FROM admins WHERE role = 'teacher' ORDER BY name, id")->fetchAll();

        $polls = $pdo->query("SELECT p.id, p.owner_id, p.title, p.status, p.created_at,
            (SELECT COUNT(*) FROM participations v WHERE v.poll_id = p.id) AS participants
            FROM polls p JOIN admins a ON a.id = p.owner_id AND a.role = 'teacher'
            ORDER BY p.created_at DESC, p.id DESC")->fetchAll();

        $byTeacher = [];
        foreach ($polls as $poll) {
            $byTeacher[(int) $poll['owner_id']][] = $poll;
        }
        foreach ($teachers as &$teacher) {
            $teacher['polls'] = $byTeacher[(int) $teacher['id']] ?? [];
            $teacher['open_count'] = count(array_filter($teacher['polls'], static fn(array $p): bool => $p['status'] === 'published'));
            $teacher['state'] = (int) $teacher['active'] === 1 ? 'active' : ($teacher['email_verified_at'] ? 'inactive' : 'pending');
        }
        unset($teacher);

        view('admin/teachers', ['title' => 'Professores', 'teachers' => $teachers]);
    }

    /** POST somente; não altera administradores nem libera e-mail não verificado. */
    public function status(string $id): void
    {
        require_super_admin();
        csrf_check();
        $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['activate', 'deactivate'], true)) {
            http_response_code(422);
            flash('error', 'Ação inválida.');
            redirect('/admin/professores');
        }
        if (!ctype_digit($id) || (int) $id < 1) {
            $this->notFound();
            return;
        }
        $activate = $action === 'activate';

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, name, active, email_verified_at FROM admins WHERE id = ? AND role = 'teacher' FOR UPDATE");
            $stmt->execute([(int) $id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$teacher) {
                $pdo->rollBack();
                $this->notFound();
                return;
            }
            if ($activate && !$teacher['email_verified_at']) {
                $pdo->rollBack();
                flash('error', 'O professor precisa confirmar o e-mail antes de ser ativado.');
                redirect('/admin/professores');
            }
            $pdo->prepare("UPDATE admins SET active = ? WHERE id = ? AND role = 'teacher'")
                ->execute([$activate ? 1 : 0, (int) $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        flash('success', $activate ? 'Professor reativado.' : 'Professor inativado. As enquetes dele foram preservadas.');
        redirect('/admin/professores');
    }

    private function notFound(): void
    {
        http_response_code(404);
        view('public/404', ['title' => 'Professor não encontrado']);
    }
}
