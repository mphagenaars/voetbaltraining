<?php
declare(strict_types=1);

class TeamController extends BaseController {

    public function create(): void {
        $this->requireAuth();
        $this->verifyCsrf();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = new Validator($_POST);
            $validator->required('name');

            if ($validator->isValid()) {
                $teamModel = new Team($this->pdo);
                $teamModel->create($_POST['name'], Session::get('user_id'));
                Session::flash('success', 'Team succesvol aangemaakt.');
                $this->redirect('/');
            }
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
            
            $userId = Session::get('user_id');
            // Verifieer dat de user lid is van dit team
            if ($teamModel->isMember($teamId, $userId)) {
                $roles = $teamModel->getMemberRoles($teamId, $userId);
                
                $roleParts = [];
                if ($roles['is_coach']) $roleParts[] = 'Coach';
                if ($roles['is_trainer']) $roleParts[] = 'Trainer';
                $roleString = implode(' & ', $roleParts);

                Session::set('current_team', [
                    'id' => $teamId,
                    'name' => $_POST['team_name'],
                    'role' => $roleString,
                    'invite_code' => $_POST['team_invite_code']
                ]);
            }
        }
        $this->redirect('/');
    }
}
