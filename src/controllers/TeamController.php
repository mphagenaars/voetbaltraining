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
                $club = $_POST['club'] ?? '';
                $season = $_POST['season'] ?? '';
                $teamModel->create($_POST['name'], Session::get('user_id'), $club, $season);
                Session::flash('success', 'Team succesvol aangemaakt.');
                $this->redirect('/account/teams');
            }
        }

        // GET request: show form
        $clubs = $this->pdo->query("SELECT * FROM clubs ORDER BY name ASC")->fetchAll();
        $seasons = $this->pdo->query("SELECT * FROM seasons ORDER BY name DESC")->fetchAll();

        View::render('teams/create', [
            'pageTitle' => 'Nieuw Team - Trainer Bobby',
            'clubs' => $clubs,
            'seasons' => $seasons
        ]);
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
                $teamDetails = $teamModel->getTeamDetails($teamId);
                
                $roleParts = [];
                if ($roles['is_coach']) $roleParts[] = 'Coach';
                if ($roles['is_trainer']) $roleParts[] = 'Trainer';
                $roleString = implode(' & ', $roleParts);

                Session::set('current_team', [
                    'id' => $teamId,
                    'name' => $teamDetails['name'],
                    'role' => $roleString,
                    'invite_code' => $teamDetails['invite_code'],
                    'club' => $teamDetails['club'],
                    'season' => $teamDetails['season'],
                    'logo_path' => $teamDetails['logo_path']
                ]);
            }
        }
        $this->redirect('/');
    }
}
