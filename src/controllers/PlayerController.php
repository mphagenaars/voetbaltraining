<?php

declare(strict_types=1);

class PlayerController {
    private Player $playerModel;

    public function __construct(private PDO $pdo) {
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        $players = $this->playerModel->getAllForTeam($_SESSION['current_team']['id']);
        require __DIR__ . '/../../src/views/players/index.php';
    }

    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $this->playerModel->create($_SESSION['current_team']['id'], $name);
            }
        }
        header('Location: /players');
        exit;
    }

    public function edit(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        $player = $this->playerModel->getById($id);

        if (!$player || $player['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /players');
            exit;
        }

        require __DIR__ . '/../../src/views/players/edit.php';
    }

    public function update(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if ($id > 0 && !empty($name)) {
                // Verify ownership
                $player = $this->playerModel->getById($id);
                if ($player && $player['team_id'] === $_SESSION['current_team']['id']) {
                    $this->playerModel->update($id, $name);
                }
            }
        }
        header('Location: /players');
        exit;
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // TODO: Check if player belongs to current team (Model should probably handle this or we check here)
                // For now, following existing logic which didn't explicitly check ownership in the delete block in index.php (it had a TODO comment)
                // But let's be safe and check it if we can.
                $player = $this->playerModel->getById($id);
                if ($player && $player['team_id'] === $_SESSION['current_team']['id']) {
                    $this->playerModel->delete($id);
                }
            }
        }
        header('Location: /players');
        exit;
    }
}
