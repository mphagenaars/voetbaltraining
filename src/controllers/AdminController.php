<?php
declare(strict_types=1);

class AdminController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        
        View::render('admin/index', [
            'pageTitle' => 'Admin Dashboard - Trainer Bobby'
        ]);
    }

    public function teams(): void {
        $this->requireAdmin();
        
        $clubs = $this->pdo->query("SELECT * FROM clubs ORDER BY name ASC")->fetchAll();
        $seasons = $this->pdo->query("SELECT * FROM seasons ORDER BY name DESC")->fetchAll();
        
        // Fetch all teams with member count
        $teams = $this->pdo->query("
            SELECT t.*, 
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count 
            FROM teams t 
            ORDER BY t.created_at DESC
        ")->fetchAll();

        View::render('admin/teams', [
            'clubs' => $clubs,
            'seasons' => $seasons,
            'teams' => $teams,
            'pageTitle' => 'Team Beheer - Trainer Bobby'
        ]);
    }

    public function addClub(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/teams');
        
        if (!empty($_POST['name'])) {
            $logoPath = null;
            
            // Handle file upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/uploads/clubs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileTmpPath = $_FILES['logo']['tmp_name'];
                $fileName = $_FILES['logo']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'svg', 'webp');
                
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $dest_path = $uploadDir . $newFileName;
                    
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        $logoPath = 'uploads/clubs/' . $newFileName;
                    }
                }
            }

            $stmt = $this->pdo->prepare("INSERT INTO clubs (name, logo_path) VALUES (:name, :logo_path)");
            try {
                $stmt->execute([
                    ':name' => trim($_POST['name']),
                    ':logo_path' => $logoPath
                ]);
                Session::flash('success', 'Club toegevoegd.');
            } catch (PDOException $e) {
                Session::flash('error', 'Kon club niet toevoegen (bestaat deze al?).');
            }
        }
        $this->redirect('/admin/teams');
    }

    public function deleteClub(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/teams');
        
        if (!empty($_POST['id'])) {
            $stmt = $this->pdo->prepare("DELETE FROM clubs WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            Session::flash('success', 'Club verwijderd.');
        }
        $this->redirect('/admin/teams');
    }

    public function addSeason(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/teams');
        
        if (!empty($_POST['name'])) {
            $stmt = $this->pdo->prepare("INSERT INTO seasons (name) VALUES (:name)");
            try {
                $stmt->execute([':name' => trim($_POST['name'])]);
                Session::flash('success', 'Seizoen toegevoegd.');
            } catch (PDOException $e) {
                Session::flash('error', 'Kon seizoen niet toevoegen (bestaat deze al?).');
            }
        }
        $this->redirect('/admin/teams');
    }

    public function deleteSeason(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/teams');
        
        if (!empty($_POST['id'])) {
            $stmt = $this->pdo->prepare("DELETE FROM seasons WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            Session::flash('success', 'Seizoen verwijderd.');
        }
        $this->redirect('/admin/teams');
    }

    public function deleteTeam(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/teams');
        
        if (!empty($_POST['id'])) {
            $stmt = $this->pdo->prepare("DELETE FROM teams WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            Session::flash('success', 'Team verwijderd.');
        }
        $this->redirect('/admin/teams');
    }

    public function editTeam(): void {
        $this->requireAdmin();
        
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $team = $stmt->fetch();
        
        if (!$team) {
            Session::flash('error', 'Team niet gevonden.');
            $this->redirect('/admin/teams');
        }
        
        $clubs = $this->pdo->query("SELECT * FROM clubs ORDER BY name ASC")->fetchAll();
        $seasons = $this->pdo->query("SELECT * FROM seasons ORDER BY name DESC")->fetchAll();
        
        View::render('admin/edit_team', [
            'team' => $team,
            'clubs' => $clubs,
            'seasons' => $seasons,
            'competitionCategories' => Team::competitionCategoryOptions(),
            'pageTitle' => 'Team Bewerken - Trainer Bobby'
        ]);
    }

    public function updateTeam(): void {
        $this->requireAdmin();
        $this->verifyCsrf();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $club = trim($_POST['club'] ?? '');
            $season = trim($_POST['season'] ?? '');
            $rawCompetitionCategory = trim((string)($_POST['competition_category'] ?? ''));
            $competitionCategory = Team::normalizeCompetitionCategory($rawCompetitionCategory);
            if ($rawCompetitionCategory !== '' && $competitionCategory === '') {
                Session::flash('error', 'Leeftijdscategorie is ongeldig.');
                $this->redirect('/admin/teams/edit?id=' . $id);
            }
            
            if (empty($name)) {
                Session::flash('error', 'Team naam is verplicht.');
                $this->redirect('/admin/teams/edit?id=' . $id);
            }
            
            if ($competitionCategory === '') {
                $competitionCategory = Team::inferCompetitionCategoryFromTeamName($name);
            }

            $stmt = $this->pdo->prepare("UPDATE teams SET name = :name, club = :club, season = :season, competition_category = :competition_category WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':club' => $club,
                ':season' => $season,
                ':competition_category' => $competitionCategory,
                ':id' => $id
            ]);
            
            Session::flash('success', 'Team bijgewerkt.');
            $this->redirect('/admin/teams');
        }
    }

    public function users(): void {
        $this->requireAdmin();

        $userModel = new User($this->pdo);
        $users = $userModel->getAll('name ASC');

        View::render('admin/users', [
            'users' => $users,
            'pageTitle' => 'Gebruikersbeheer - Trainer Bobby'
        ]);
    }

    public function createUser(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/users');
        }

        $this->verifyCsrf('/admin/users');

        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($name) || empty($password)) {
            Session::flash('error', 'Alle velden zijn verplicht.');
            $this->redirect('/admin/users');
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Wachtwoord moet minimaal 8 tekens zijn.');
            $this->redirect('/admin/users');
        }

        $userModel = new User($this->pdo);

        if ($userModel->getByUsername($username) !== null) {
            Session::flash('error', 'Gebruikersnaam is al in gebruik.');
            $this->redirect('/admin/users');
        }

        $userModel->create($username, $password, $name);

        Session::flash('success', 'Gebruiker "' . htmlspecialchars($username, ENT_QUOTES) . '" aangemaakt.');
        $this->redirect('/admin/users');
    }

    public function resetUserPassword(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/users');
        }

        $this->verifyCsrf('/admin/users');

        $userId      = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($userId <= 0) {
            Session::flash('error', 'Ongeldige gebruiker.');
            $this->redirect('/admin/users');
        }

        if (strlen($newPassword) < 8) {
            Session::flash('error', 'Nieuw wachtwoord moet minimaal 8 tekens zijn.');
            $this->redirect('/admin/users');
        }

        $userModel = new User($this->pdo);
        $user = $userModel->getById($userId);

        if (!$user) {
            Session::flash('error', 'Gebruiker niet gevonden.');
            $this->redirect('/admin/users');
        }

        $userModel->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));

        Session::flash('success', 'Wachtwoord van "' . htmlspecialchars($user['name'], ENT_QUOTES) . '" is opnieuw ingesteld.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/users');

            $userId = (int)($_POST['user_id'] ?? 0);

            // Prevent deleting yourself
            if ($userId === Session::get('user_id')) {
                Session::flash('error', 'Je kunt jezelf niet verwijderen.');
                $this->redirect('/admin/users');
            }

            $userModel = new User($this->pdo);
            $userModel->delete($userId);

            Session::flash('success', 'Gebruiker verwijderd.');
            $this->redirect('/admin/users');
        }
    }

    public function toggleAdmin(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/users');

            $userId = (int)($_POST['user_id'] ?? 0);
            $isAdmin = (bool)($_POST['is_admin'] ?? false);

            // Prevent removing your own admin rights
            if ($userId === Session::get('user_id')) {
                Session::flash('error', 'Je kunt je eigen admin-rechten niet wijzigen.');
                $this->redirect('/admin/users');
            }

            $userModel = new User($this->pdo);
            $userModel->setAdminStatus($userId, $isAdmin);

            Session::flash('success', 'Rechten aangepast.');
            $this->redirect('/admin/users');
        }
    }

    public function updateUserAiAccessEnabled(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/users');
        }

        $this->verifyCsrf('/admin/users');

        $userId = (int)($_POST['user_id'] ?? 0);
        $aiAccessEnabled = !empty($_POST['ai_access_enabled']) ? 1 : 0;

        if ($userId <= 0) {
            Session::flash('error', 'Ongeldige gebruiker.');
            $this->redirect('/admin/users');
        }

        $stmt = $this->pdo->prepare('UPDATE users SET ai_access_enabled = :ai_access_enabled WHERE id = :id');
        $stmt->execute([
            ':ai_access_enabled' => $aiAccessEnabled,
            ':id' => $userId,
        ]);

        Session::flash('success', 'AI toegang bijgewerkt.');
        $this->redirect('/admin/users');
    }

    public function manageTeamMembers(): void {
        $this->requireAdmin();

        $teamId = (int)($_GET['team_id'] ?? 0);
        if (!$teamId) {
            $this->redirect('/admin/teams');
        }

        $teamModel = new Team($this->pdo);
        $userModel = new User($this->pdo);

        $team = $teamModel->getById($teamId);
        if (!$team) {
            Session::flash('error', 'Team niet gevonden.');
            $this->redirect('/admin/teams');
        }

        $teamMembers   = $teamModel->getTeamMembers($teamId);
        $allUsers      = $userModel->getAll('name ASC');
        $memberUserIds = array_column($teamMembers, 'id');

        $availableUsers = array_filter($allUsers, fn($u) => !in_array($u['id'], $memberUserIds));

        View::render('admin/team_members', [
            'team'           => $team,
            'teamMembers'    => $teamMembers,
            'availableUsers' => $availableUsers,
            'pageTitle'      => 'Leden van ' . $team['name'],
        ]);
    }

    public function manageTeams(): void {
        $this->requireAdmin();
        
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            $this->redirect('/admin');
        }

        $userModel = new User($this->pdo);
        $teamModel = new Team($this->pdo);

        $user = $userModel->getById($userId);
        if (!$user) {
            Session::flash('error', 'Gebruiker niet gevonden.');
            $this->redirect('/admin');
        }

        $userTeams = $teamModel->getTeamsForUser($userId);
        $allTeams = $teamModel->getAll('name ASC');
        
        // Filter teams where user is NOT a member
        $availableTeams = array_filter($allTeams, function($team) use ($userTeams) {
            foreach ($userTeams as $ut) {
                if ($ut['id'] === $team['id']) return false;
            }
            return true;
        });

        View::render('admin/user_teams', [
            'user' => $user,
            'userTeams' => $userTeams,
            'availableTeams' => $availableTeams,
            'pageTitle' => 'Teams Beheren - ' . $user['name']
        ]);
    }

    private function safeRedirectTo(string $fallback): string {
        $to = $_POST['redirect_to'] ?? '';
        if ($to !== '' && str_starts_with($to, '/admin/')) {
            return $to;
        }
        return $fallback;
    }

    public function addTeamMember(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin');

            $userId    = (int)$_POST['user_id'];
            $teamId    = (int)$_POST['team_id'];
            $isCoach   = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->addMember($teamId, $userId, $isCoach, $isTrainer);

            Session::flash('success', 'Toegevoegd aan team.');
            $this->redirect($this->safeRedirectTo('/admin/user-teams?user_id=' . $userId));
        }
    }

    public function updateTeamRole(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin');

            $userId    = (int)$_POST['user_id'];
            $teamId    = (int)$_POST['team_id'];
            $isCoach   = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->updateMemberRoles($teamId, $userId, $isCoach, $isTrainer);

            // Update sessie als de admin zijn eigen rollen aanpast voor het actieve team
            if ($userId === Session::get('user_id') && Session::has('current_team') && Session::get('current_team')['id'] === $teamId) {
                $roleParts = [];
                if ($isCoach) $roleParts[] = 'Coach';
                if ($isTrainer) $roleParts[] = 'Trainer';
                $currentTeam = Session::get('current_team');
                $currentTeam['role'] = implode(' & ', $roleParts);
                Session::set('current_team', $currentTeam);
            }

            Session::flash('success', 'Rollen bijgewerkt.');
            $this->redirect($this->safeRedirectTo('/admin/user-teams?user_id=' . $userId));
        }
    }

    public function removeTeamMember(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin');

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];

            $teamModel = new Team($this->pdo);
            $teamModel->removeMember($teamId, $userId);

            // Update sessie als de admin zichzelf uit het actieve team verwijdert
            if ($userId === Session::get('user_id') && Session::has('current_team') && Session::get('current_team')['id'] === $teamId) {
                Session::remove('current_team');
            }

            Session::flash('success', 'Verwijderd uit team.');
            $this->redirect($this->safeRedirectTo('/admin/user-teams?user_id=' . $userId));
        }
    }

    public function manageOptions(): void {
        $this->requireAdmin();
        
        $db = $this->pdo;
        $stmt = $db->prepare("SELECT * FROM exercise_options ORDER BY category, sort_order ASC");
        $stmt->execute();
        $allOptions = $stmt->fetchAll();
        
        $options = [
            'team_task' => [],
            'objective' => [],
            'football_action' => []
        ];
        
        foreach ($allOptions as $opt) {
            if (isset($options[$opt['category']])) {
                $options[$opt['category']][] = $opt;
            }
        }
        
        View::render('admin/options', [
            'options' => $options,
            'pageTitle' => 'Oefenstof Opties Beheren'
        ]);
    }

    public function createOption(): void {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/options');
            
            $category = $_POST['category'] ?? '';
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name) || !in_array($category, ['team_task', 'objective', 'football_action'])) {
                Session::flash('error', 'Ongeldige invoer.');
                $this->redirect('/admin/options');
            }
            
            $db = $this->pdo;
            
            // Get max sort order
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM exercise_options WHERE category = :category");
            $stmt->execute([':category' => $category]);
            $maxSort = (int)$stmt->fetchColumn();
            
            $stmt = $db->prepare("INSERT INTO exercise_options (category, name, sort_order) VALUES (:category, :name, :sort_order)");
            $stmt->execute([
                ':category' => $category,
                ':name' => $name,
                ':sort_order' => $maxSort + 1
            ]);
            
            Session::flash('success', 'Optie toegevoegd.');
            $this->redirect('/admin/options');
        }
    }

    public function updateOption(): void {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/options');
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name) || $id <= 0) {
                Session::flash('error', 'Ongeldige invoer.');
                $this->redirect('/admin/options');
            }
            
            $db = $this->pdo;
            $stmt = $db->prepare("UPDATE exercise_options SET name = :name WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':id' => $id
            ]);
            
            Session::flash('success', 'Optie bijgewerkt.');
            $this->redirect('/admin/options');
        }
    }

    public function deleteOption(): void {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/options');
            
            $id = (int)($_POST['id'] ?? 0);
            
            $db = $this->pdo;
            $stmt = $db->prepare("DELETE FROM exercise_options WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            Session::flash('success', 'Optie verwijderd.');
            $this->redirect('/admin/options');
        }
    }

    public function reorderOptions(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin/options');

            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }

            $db = $this->pdo;
            $stmt = $db->prepare("UPDATE exercise_options SET sort_order = :sort_order WHERE id = :id");

            $db->beginTransaction();
            try {
                foreach ($ids as $index => $id) {
                    $stmt->execute([
                        ':sort_order' => $index,
                        ':id' => (int)$id
                    ]);
                }
                $db->commit();
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $db->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }

    public function system(): void {
        $this->requireAdmin();
        
        $activityModel = new \ActivityLog($this->pdo);
        
        $stats = [
            'recent_activity' => $activityModel->getRecent(50),
            'popular_exercises' => $activityModel->getPopularExercises(10)
        ];
        
        View::render('admin/system', ['stats' => $stats, 'pageTitle' => 'Systeem Logs - Trainer Bobby']);
    }

    public function dashboard() {
        $this->requireAdmin();
        
        $userModel = new \User($this->pdo);
        $exerciseModel = new \Exercise($this->pdo);
        $trainingModel = new \Training($this->pdo);
        $activityModel = new \ActivityLog($this->pdo);

        // Fetch counts for the dashboard
        $stats = [
            'users' => $userModel->count(),
            'exercises' => $exerciseModel->count(),
            'trainings' => $trainingModel->count(),
            'recent_activity' => $activityModel->getRecent(10),
            'popular_exercises' => $activityModel->getPopularExercises()
        ];

        View::render('admin/dashboard', ['stats' => $stats]);
    }
}
