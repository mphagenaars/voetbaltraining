<?php
declare(strict_types=1);

return [
    '/' => ['DashboardController', 'index'],
    
    // Team
    '/team/create' => ['TeamController', 'create'],
    '/team/select' => ['TeamController', 'select'],

    // Exercises
    '/exercises' => ['ExerciseController', 'index'],
    '/exercises/create' => ['ExerciseController', 'create'],
    '/exercises/edit' => ['ExerciseController', 'edit'],
    '/exercises/view' => ['ExerciseController', 'view'],
    '/exercises/delete' => ['ExerciseController', 'delete'],
    '/exercises/comment' => ['ExerciseController', 'storeComment'],
    '/exercises/react' => ['ExerciseController', 'toggleReaction'],
    '/exercises/add-to-training' => ['ExerciseController', 'addToTraining'],

    // Trainings
    '/trainings' => ['TrainingController', 'index'],
    '/trainings/create' => ['TrainingController', 'create'],
    '/trainings/edit' => ['TrainingController', 'edit'],
    '/trainings/view' => ['TrainingController', 'view'],
    '/trainings/delete' => ['TrainingController', 'delete'],

    // Auth
    '/login' => ['AuthController', 'login'],
    '/register' => ['AuthController', 'register'],
    '/logout' => ['AuthController', 'logout'],

    // Players
    '/players' => ['PlayerController', 'index'],
    '/players/create' => ['PlayerController', 'create'],
    '/players/delete' => ['PlayerController', 'delete'],
    '/players/edit' => ['PlayerController', 'edit'],
    '/players/update' => ['PlayerController', 'update'],

    // Matches
    '/matches' => ['GameController', 'index'],
    '/matches/create' => ['GameController', 'create'],
    '/matches/edit' => ['GameController', 'edit'],
    '/matches/delete' => ['GameController', 'delete'],
    '/matches/view' => ['GameController', 'view'],
    '/matches/live' => ['GameController', 'live'],
    '/matches/reports' => ['GameController', 'reports'],
    '/matches/timer-action' => ['GameController', 'timerAction'],
    '/matches/add-event' => ['GameController', 'addEvent'],
    '/matches/update-score' => ['GameController', 'updateScore'],
    '/matches/update-details' => ['GameController', 'updateDetails'],
    '/matches/save-lineup' => ['GameController', 'saveLineup'],
    '/matches/tactics/save' => ['GameController', 'saveTactic'],
    '/matches/tactics/delete' => ['GameController', 'deleteTactic'],
    '/matches/tactics/export-video' => ['GameController', 'exportTacticVideo'],

    // Tactics Studio
    '/tactics' => ['TacticsController', 'index'],
    '/tactics/save' => ['GameController', 'saveTactic'],
    '/tactics/delete' => ['GameController', 'deleteTactic'],
    '/tactics/export-video' => ['GameController', 'exportTacticVideo'],

    // Account
    '/account' => ['AccountController', 'index'],
    '/account/teams' => ['AccountController', 'teams'],
    '/account/teams/toggle-visibility' => ['AccountController', 'toggleTeamVisibility'],
    '/account/update-profile' => ['AccountController', 'updateProfile'],
    '/account/update-password' => ['AccountController', 'updatePassword'],

    // Admin
    '/admin' => ['AdminController', 'index'],
    '/admin/teams' => ['AdminController', 'teams'],
    '/admin/teams/add-club' => ['AdminController', 'addClub'],
    '/admin/teams/delete-club' => ['AdminController', 'deleteClub'],
    '/admin/teams/add-season' => ['AdminController', 'addSeason'],
    '/admin/teams/delete-season' => ['AdminController', 'deleteSeason'],
    '/admin/teams/delete-team' => ['AdminController', 'deleteTeam'],
    '/admin/teams/edit' => ['AdminController', 'editTeam'],
    '/admin/teams/update' => ['AdminController', 'updateTeam'],
    '/admin/users' => ['AdminController', 'users'],
    '/admin/delete-user' => ['AdminController', 'deleteUser'],
    '/admin/toggle-admin' => ['AdminController', 'toggleAdmin'],
    '/admin/update-user-ai-access' => ['AdminController', 'updateUserAiAccessEnabled'],
    '/admin/user-teams' => ['AdminController', 'manageTeams'],
    '/admin/add-team-member' => ['AdminController', 'addTeamMember'],
    '/admin/update-team-role' => ['AdminController', 'updateTeamRole'],
    '/admin/remove-team-member' => ['AdminController', 'removeTeamMember'],
    '/admin/options' => ['AdminController', 'manageOptions'],
    '/admin/options/create' => ['AdminController', 'createOption'],
    '/admin/options/update' => ['AdminController', 'updateOption'],
    '/admin/options/delete' => ['AdminController', 'deleteOption'],
    '/admin/options/reorder' => ['AdminController', 'reorderOptions'],
    '/admin/system' => ['AdminController', 'system'],

    // Admin AI Module
    '/admin/ai/settings' => ['AiAdminController', 'manageAiSettings'],
    '/admin/ai/access-mode' => ['AiAdminController', 'updateAiAccessMode'],
    '/admin/ai/api-key' => ['AiAdminController', 'saveOpenRouterApiKey'],
    '/admin/ai/api-key/delete' => ['AiAdminController', 'deleteOpenRouterApiKey'],
    '/admin/ai/management-key' => ['AiAdminController', 'saveManagementApiKey'],
    '/admin/ai/management-key/delete' => ['AiAdminController', 'deleteManagementApiKey'],
    '/admin/ai/youtube-key' => ['AiAdminController', 'saveYouTubeApiKey'],
    '/admin/ai/youtube-key/delete' => ['AiAdminController', 'deleteYouTubeApiKey'],
    '/admin/ai/models/create' => ['AiAdminController', 'createAiModel'],
    '/admin/ai/models/update' => ['AiAdminController', 'updateAiModel'],
    '/admin/ai/models/delete' => ['AiAdminController', 'deleteAiModel'],
    '/admin/ai/models/reorder' => ['AiAdminController', 'reorderAiModels'],
    '/admin/ai/pricing/update' => ['AiAdminController', 'updateAiModelPricing'],
    '/admin/ai/budget' => ['AiAdminController', 'updateAiBudgetSettings'],
    '/admin/ai/retrieval' => ['AiAdminController', 'updateAiRetrievalSettings'],
    '/admin/ai/usage' => ['AiAdminController', 'usageReport'],

    // AI User endpoints
    '/ai/chat/message' => ['AiController', 'sendMessage'],
    '/ai/chat/apply-text' => ['AiController', 'applyText'],
    '/ai/chat/apply-drawing' => ['AiController', 'applyDrawing'],
    '/ai/models' => ['AiController', 'listModels'],
    '/ai/chat/sessions' => ['AiController', 'listSessions'],
    '/ai/chat/session' => ['AiController', 'getSession'],
    '/ai/usage/summary' => ['AiController', 'usageSummary'],
    '/ai/usage/history' => ['AiController', 'usageHistory'],
];
