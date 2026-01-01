<?php
declare(strict_types=1);

class AdminController extends BaseController {

    private function requireAdmin(): void {
        $this->requireAuth();
        
        // Check DB voor zekerheid (sessie kan verouderd zijn)
        $userModel = new User($this->pdo);
        $user = $userModel->getById($_SESSION['user_id']);
        
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
        
        $success = $_GET['success'] ?? null;
        $error = $_GET['error'] ?? null;

        View::render('admin/index', [
            'users' => $users,
            'success' => $success,
            'error' => $error,
            'pageTitle' => 'Gebruikersbeheer - Trainer Bobby'
        ]);
    }
    
    public function deleteUser(): void {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $this->verifyCsrf('/admin?error=' . urlencode('Ongeldige sessie.'));
            
            $userId = (int)($_POST['user_id'] ?? 0);
            
            // Prevent deleting yourself
            if ($userId === $_SESSION['user_id']) {
                 header('Location: /admin?error=' . urlencode('Je kunt jezelf niet verwijderen.'));
                 exit;
            }

            $userModel = new User($this->pdo);
            $userModel->delete($userId);
            
            header('Location: /admin?success=' . urlencode('Gebruiker verwijderd.'));
            exit;
        }
    }
    
    public function toggleAdmin(): void {
        $this->requireAdmin();
         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /admin?error=' . urlencode('Ongeldige sessie.'));
                exit;
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $isAdmin = (bool)($_POST['is_admin'] ?? false);
            
             // Prevent removing your own admin rights
            if ($userId === $_SESSION['user_id']) {
                 header('Location: /admin?error=' . urlencode('Je kunt je eigen admin-rechten niet wijzigen.'));
                 exit;
            }
            
            $userModel = new User($this->pdo);
            $userModel->setAdminStatus($userId, $isAdmin);
            
            header('Location: /admin?success=' . urlencode('Rechten aangepast.'));
            exit;
         }
    }

    public function manageTeams(): void {
        $this->requireAdmin();
        
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            header('Location: /admin');
            exit;
        }

        $userModel = new User($this->pdo);
        $teamModel = new Team($this->pdo);

        $user = $userModel->getById($userId);
        if (!$user) {
            header('Location: /admin?error=' . urlencode('Gebruiker niet gevonden.'));
            exit;
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

        $success = $_GET['success'] ?? null;
        $error = $_GET['error'] ?? null;

        View::render('admin/user_teams', [
            'user' => $user,
            'userTeams' => $userTeams,
            'availableTeams' => $availableTeams,
            'success' => $success,
            'error' => $error,
            'pageTitle' => 'Teams Beheren - ' . $user['name']
        ]);
    }

    public function addTeamMember(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin?error=' . urlencode('Ongeldige sessie.'));

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];
            $isCoach = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->addMember($teamId, $userId, $isCoach, $isTrainer);

            $this->redirect('/admin/user-teams?user_id=' . $userId . '&success=' . urlencode('Toegevoegd aan team.'));
        }
    }

    public function updateTeamRole(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/admin?error=' . urlencode('Ongeldige sessie.'));

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];
            $isCoach = isset($_POST['is_coach']);
            $isTrainer = isset($_POST['is_trainer']);

            $teamModel = new Team($this->pdo);
            $teamModel->updateMemberRoles($teamId, $userId, $isCoach, $isTrainer);

            // Update sessie als de admin zijn eigen rollen aanpast voor het actieve team
            if ($userId === $_SESSION['user_id'] && isset($_SESSION['current_team']) && $_SESSION['current_team']['id'] === $teamId) {
                $roleParts = [];
                if ($isCoach) $roleParts[] = 'Coach';
                if ($isTrainer) $roleParts[] = 'Trainer';
                $roleString = implode(' & ', $roleParts);
                $_SESSION['current_team']['role'] = $roleString;
            }

            header('Location: /admin/user-teams?user_id=' . $userId . '&success=' . urlencode('Rollen bijgewerkt.'));
            exit;
        }
    }

    public function removeTeamMember(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /admin?error=' . urlencode('Ongeldige sessie.'));
                exit;
            }

            $userId = (int)$_POST['user_id'];
            $teamId = (int)$_POST['team_id'];

            $teamModel = new Team($this->pdo);
            $teamModel->removeMember($teamId, $userId);

            // Update sessie als de admin zichzelf uit het actieve team verwijdert
            if ($userId === $_SESSION['user_id'] && isset($_SESSION['current_team']) && $_SESSION['current_team']['id'] === $teamId) {
                unset($_SESSION['current_team']);
            }

            header('Location: /admin/user-teams?user_id=' . $userId . '&success=' . urlencode('Verwijderd uit team.'));
            exit;
        }
    }
}
