<?php
declare(strict_types=1);

class TacticsController extends BaseController {
    private MatchTactic $matchTacticModel;
    private Team $teamModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->matchTacticModel = new MatchTactic($pdo);
        $this->teamModel = new Team($pdo);
    }

    public function index(): void {
        $this->requireTeamContext();

        $team = Session::get('current_team');
        $teamId = (int)($team['id'] ?? 0);
        if (!$this->canAccessCurrentTeam($teamId)) {
            $this->redirect('/');
        }

        $tactics = $this->matchTacticModel->getForTeam($teamId);

        View::render('tactics/index', [
            'team' => $team,
            'tactics' => $tactics,
            'pageTitle' => 'Tactiekstudio - Trainer Bobby',
        ]);
    }

    private function canAccessCurrentTeam(int $teamId): bool {
        if (!Session::has('user_id') || !Session::has('current_team')) {
            return false;
        }

        $currentTeamId = (int)(Session::get('current_team')['id'] ?? 0);
        if ($teamId <= 0 || $teamId !== $currentTeamId) {
            return false;
        }

        return $this->teamModel->canAccessTeam(
            $teamId,
            (int)Session::get('user_id'),
            (bool)Session::get('is_admin')
        );
    }
}
