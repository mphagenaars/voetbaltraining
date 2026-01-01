<?php
declare(strict_types=1);

class TeamController extends BaseController {

    public function create(): void {
        $this->requireAuth();
        $this->verifyCsrf();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            if (!empty($name)) {
                $teamModel = new Team($this->pdo);
                $teamModel->create($name, $_SESSION['user_id']);
            }
            $this->redirect('/');
        }

        // GET request: show form
        View::render('teams/create', ['pageTitle' => 'Nieuw Team - Trainer Bobby']);
    }

    public function select(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
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
