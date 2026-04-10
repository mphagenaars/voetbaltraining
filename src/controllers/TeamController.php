<?php
declare(strict_types=1);

class TeamController extends BaseController {

    public function create(): void {
        $this->requireAuth();
        $this->verifyCsrf();

        $competitionCategories = Team::competitionCategoryOptions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = new Validator($_POST);
            $validator->required('name');

            if ($validator->isValid()) {
                $teamModel = new Team($this->pdo);
                $name = trim((string)($_POST['name'] ?? ''));
                $club = trim((string)($_POST['club'] ?? ''));
                $season = trim((string)($_POST['season'] ?? ''));
                $rawCompetitionCategory = trim((string)($_POST['competition_category'] ?? ''));
                $normalizedCompetitionCategory = Team::normalizeCompetitionCategory($rawCompetitionCategory);
                if ($rawCompetitionCategory !== '' && $normalizedCompetitionCategory === '') {
                    Session::flash('error', 'Ongeldige leeftijdscategorie.');
                    $this->redirect('/team/create');
                }

                $teamModel->create(
                    $name,
                    (int)Session::get('user_id'),
                    $club,
                    $season,
                    $normalizedCompetitionCategory
                );
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
            'seasons' => $seasons,
            'competitionCategories' => $competitionCategories,
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
            if ($teamModel->canAccessTeam($teamId, (int)$userId)) {
                $roles = $teamModel->getMemberRoles($teamId, $userId);
                $teamDetails = $teamModel->getTeamDetails($teamId);
                
                $roleString = Team::roleLabelFromRoles($roles);
                $competitionCategory = Team::resolveCompetitionCategory($teamDetails);
                $matchFormat = Team::resolveMatchFormatForTeam($teamDetails);

                Session::set('current_team', [
                    'id' => $teamId,
                    'name' => $teamDetails['name'],
                    'role' => $roleString,
                    'invite_code' => $teamDetails['invite_code'],
                    'club' => $teamDetails['club'],
                    'season' => $teamDetails['season'],
                    'logo_path' => $teamDetails['logo_path'],
                    'competition_category' => $competitionCategory,
                    'match_format' => $matchFormat,
                ]);
            }
        }
        $this->redirect('/');
    }
}
