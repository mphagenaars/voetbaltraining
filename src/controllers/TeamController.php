<?php
declare(strict_types=1);

class TeamController {
    public function __construct(private PDO $pdo) {}

    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /');
                exit;
            }
            $name = $_POST['name'] ?? '';
            if (!empty($name)) {
                $teamModel = new Team($this->pdo);
                $teamModel->create($name, $_SESSION['user_id']);
            }
            header('Location: /');
            exit;
        }
        header('Location: /');
        exit;
    }

    public function select(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /');
                exit;
            }
            $teamId = (int)($_POST['team_id'] ?? 0);
            $teamModel = new Team($this->pdo);
            
            // Verifieer dat de user lid is van dit team
            if ($teamModel->isMember($teamId, $_SESSION['user_id'])) {
                $roles = $teamModel->getMemberRoles($teamId, $_SESSION['user_id']);
                
                $roleParts = [];
                if ($roles['is_coach']) $roleParts[] = 'Coach';
                if ($roles['is_trainer']) $roleParts[] = 'Trainer';
                $roleString = implode(' & ', $roleParts);

                $_SESSION['current_team'] = [
                    'id' => $teamId,
                    'name' => $_POST['team_name'],
                    'role' => $roleString,
                    'invite_code' => $_POST['team_invite_code']
                ];
            }
        }
        header('Location: /');
        exit;
    }
}
