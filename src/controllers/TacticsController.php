<?php
declare(strict_types=1);

class TacticsController extends BaseController {
    private MatchTactic $matchTacticModel;
    private Team $teamModel;
    private FormationTemplate $formationTemplateModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->matchTacticModel = new MatchTactic($pdo);
        $this->teamModel = new Team($pdo);
        $this->formationTemplateModel = new FormationTemplate($pdo);
    }

    public function index(): void {
        $this->requireTeamContext();

        $team = Session::get('current_team');
        $teamId = (int)($team['id'] ?? 0);
        $teamMatchFormat = $this->resolveCurrentTeamMatchFormat($teamId);
        if (!$this->canAccessCurrentTeam($teamId)) {
            $this->redirect('/');
        }

        $tactics = $this->matchTacticModel->getForTeam($teamId);
        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId, $teamMatchFormat);
        if (is_array($team)) {
            $team['match_format'] = $teamMatchFormat;
        }

        View::render('tactics/index', [
            'team' => $team,
            'tactics' => $tactics,
            'speelwijzen' => $speelwijzen,
            'pageTitle' => 'Tactiekstudio - Trainer Bobby',
        ]);
    }

    /**
     * JSON API: save (create/update) a speelwijze.
     */
    public function saveSpeelwijze(): void {
        $this->requireTeamContext();
        $team = Session::get('current_team');
        $teamId = (int)($team['id'] ?? 0);
        $teamMatchFormat = $this->resolveCurrentTeamMatchFormat($teamId);
        $isAdmin = (bool)Session::get('is_admin');
        if (!$this->canAccessCurrentTeam($teamId)) {
            $this->jsonError('Geen toegang.', 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->jsonError('Ongeldige invoer.');
            return;
        }

        if (!Csrf::verifyToken((string)($input['csrf_token'] ?? ''))) {
            $this->jsonError('Ongeldig CSRF token.', 403);
            return;
        }

        $id = isset($input['id']) ? (int)$input['id'] : null;
        $name = trim((string)($input['name'] ?? ''));
        $isShared = !empty($input['is_shared']);
        $rawPositions = is_array($input['positions'] ?? null) ? $input['positions'] : [];

        // Validate name
        $nameError = FormationTemplate::validateName($name);
        if ($nameError !== null) {
            $this->jsonError($nameError);
            return;
        }

        // Sanitize and validate positions
        $positions = FormationTemplate::sanitizePositions($rawPositions);
        $posError = FormationTemplate::validatePositions($positions);
        if ($posError !== null) {
            $this->jsonError($posError);
            return;
        }
        $templateFormat = FormationTemplate::deriveFormat(count($positions));
        if ($teamMatchFormat !== '' && $templateFormat !== $teamMatchFormat) {
            $this->jsonError('Alleen speelwijzen voor ' . Team::matchFormatLabel($teamMatchFormat) . ' zijn toegestaan voor dit team.');
            return;
        }

        if ($id !== null) {
            // Update: verify ownership (admins may also update global shared templates)
            $existing = $this->formationTemplateModel->getById($id);
            $existingTeamId = null;
            if (is_array($existing) && array_key_exists('team_id', $existing) && $existing['team_id'] !== null) {
                $existingTeamId = (int)$existing['team_id'];
            }
            $isTeamOwned = $existingTeamId !== null && $existingTeamId === $teamId;
            $isGlobalShared = is_array($existing)
                && $existingTeamId === null
                && (int)($existing['is_shared'] ?? 0) === 1;

            if (!$existing || (!$isTeamOwned && !($isAdmin && $isGlobalShared))) {
                $this->jsonError('Speelwijze niet gevonden of geen eigenaar.', 404);
                return;
            }

            // Keep global shared templates shared-only.
            if ($isGlobalShared) {
                $isShared = true;
            }

            $this->formationTemplateModel->update($id, $name, $positions, $isShared);
        } else {
            // Create
            $storeTeamId = $isShared ? null : $teamId;
            $id = $this->formationTemplateModel->create($storeTeamId, $name, $positions, $isShared, (int)Session::get('user_id'));
        }

        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId, $teamMatchFormat);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $id, 'speelwijzen' => $speelwijzen]);
    }

    /**
     * JSON API: delete a speelwijze.
     */
    public function deleteSpeelwijze(): void {
        $this->requireTeamContext();
        $team = Session::get('current_team');
        $teamId = (int)($team['id'] ?? 0);
        $teamMatchFormat = $this->resolveCurrentTeamMatchFormat($teamId);
        $isAdmin = (bool)Session::get('is_admin');
        if (!$this->canAccessCurrentTeam($teamId)) {
            $this->jsonError('Geen toegang.', 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->jsonError('Ongeldige invoer.');
            return;
        }

        if (!Csrf::verifyToken((string)($input['csrf_token'] ?? ''))) {
            $this->jsonError('Ongeldig CSRF token.', 403);
            return;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('Ongeldig ID.');
            return;
        }

        $deleted = $this->formationTemplateModel->deleteForTeam($id, $teamId, $isAdmin);
        if (!$deleted) {
            $this->jsonError('Speelwijze niet gevonden of is een gedeelde standaard.', 404);
            return;
        }

        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId, $teamMatchFormat);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'speelwijzen' => $speelwijzen]);
    }

    /**
     * JSON API: list speelwijzen available to the current team.
     */
    public function listSpeelwijzen(): void {
        $this->requireTeamContext();
        $team = Session::get('current_team');
        $teamId = (int)($team['id'] ?? 0);
        $teamMatchFormat = $this->resolveCurrentTeamMatchFormat($teamId);
        if (!$this->canAccessCurrentTeam($teamId)) {
            $this->jsonError('Geen toegang.', 403);
            return;
        }

        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId, $teamMatchFormat);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'speelwijzen' => $speelwijzen]);
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

    private function resolveCurrentTeamMatchFormat(int $teamId): string {
        if ($teamId <= 0) {
            return '';
        }

        $currentTeam = Session::get('current_team');
        if (is_array($currentTeam) && (int)($currentTeam['id'] ?? 0) === $teamId) {
            $sessionFormat = Team::normalizeMatchFormat((string)($currentTeam['match_format'] ?? ''));
            if ($sessionFormat !== '') {
                return $sessionFormat;
            }
        }

        $teamDetails = $this->teamModel->getTeamDetails($teamId);
        if (is_array($teamDetails)) {
            return Team::resolveMatchFormatForTeam($teamDetails);
        }

        return '';
    }

    private function jsonError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
    }
}
