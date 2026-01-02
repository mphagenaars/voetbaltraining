<?php
declare(strict_types=1);

class AdminController extends BaseController {

    private function requireAdmin(): void {
        $this->requireAuth();
        
        // Check DB voor zekerheid (sessie kan verouderd zijn)
        $userModel = new User($this->pdo);
        $user = $userModel->getById(Session::get('user_id'));
        
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            View::render('404', ['pageTitle' => 'Geen toegang']); // Of een specifieke 403 pagina
            exit;
        }
    }

    public function index(): void {
        $this->requireAdmin();
        
        $userModel = new User($this->pdo);
        $users = $userModel->getAll('name ASC');
        
        View::render('admin/index', [
            'users' => $users,
            'pageTitle' => 'Gebruikersbeheer - Trainer Bobby'
        ]);
    }
    
    public function deleteUser(): void {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $this->verifyCsrf('/admin');
            
            $userId = (int)($_POST['user_id'] ?? 0);
            
            // Prevent deleting yourself
            if ($userId === Session::get('user_id')) {
                 Session::flash('error', 'Je kunt jezelf niet verwijderen.');
                 $this->redirect('/admin');
            }

            $userModel = new User($this->pdo);
            $userModel->delete($userId);
            
            Session::flash('success', 'Gebruiker verwijderd.');
            $this->redirect('/admin');
        }
    }
    
    public function toggleAdmin(): void {
        $this->requireAdmin();
         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $this->verifyCsrf('/admin');
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $isAdmin = (bool)($_POST['is_admin'] ?? false);
            
             // Prevent removing your own admin rights
            if ($userId === Session::get('user_id')) {
                 Session::flash('error', 'Je kunt je eigen admin-rechten niet wijzigen.');
                 $this->redirect('/admin');
            }
            
            $userModel = new User($this->pdo);
            $userModel->setAdminStatus($userId, $isAdmin);
            
            Session::flash('success', 'Rechten aangepast.');
            $this->redirect('/admin');
         }
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

    public function addTeamMember(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin');

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];
            $isCoach = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->addMember($teamId, $userId, $isCoach, $isTrainer);

            Session::flash('success', 'Toegevoegd aan team.');
            $this->redirect('/admin/user-teams?user_id=' . $userId);
        }
    }

    public function updateTeamRole(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin');

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];
            $isCoach = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->updateMemberRoles($teamId, $userId, $isCoach, $isTrainer);

            // Update sessie als de admin zijn eigen rollen aanpast voor het actieve team
            if ($userId === Session::get('user_id') && Session::has('current_team') && Session::get('current_team')['id'] === $teamId) {
                $roleParts = [];
                if ($isCoach) $roleParts[] = 'Coach';
                if ($isTrainer) $roleParts[] = 'Trainer';
                $roleString = implode(' & ', $roleParts);
                
                $currentTeam = Session::get('current_team');
                $currentTeam['role'] = $roleString;
                Session::set('current_team', $currentTeam);
            }

            Session::flash('success', 'Rollen bijgewerkt.');
            $this->redirect('/admin/user-teams?user_id=' . $userId);
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
            $this->redirect('/admin/user-teams?user_id=' . $userId);
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
}
