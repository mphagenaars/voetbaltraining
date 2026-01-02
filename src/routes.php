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
    '/matches/delete' => ['GameController', 'delete'],
    '/matches/view' => ['GameController', 'view'],
    '/matches/add-event' => ['GameController', 'addEvent'],
    '/matches/update-score' => ['GameController', 'updateScore'],
    '/matches/update-details' => ['GameController', 'updateDetails'],
    '/matches/save-lineup' => ['GameController', 'saveLineup'],

    // Account
    '/account' => ['AccountController', 'index'],
    '/account/update-profile' => ['AccountController', 'updateProfile'],
    '/account/update-password' => ['AccountController', 'updatePassword'],

    // Admin
    '/admin' => ['AdminController', 'index'],
    '/admin/delete-user' => ['AdminController', 'deleteUser'],
    '/admin/toggle-admin' => ['AdminController', 'toggleAdmin'],
    '/admin/user-teams' => ['AdminController', 'manageTeams'],
    '/admin/add-team-member' => ['AdminController', 'addTeamMember'],
    '/admin/update-team-role' => ['AdminController', 'updateTeamRole'],
    '/admin/remove-team-member' => ['AdminController', 'removeTeamMember'],
    '/admin/options' => ['AdminController', 'manageOptions'],
    '/admin/options/create' => ['AdminController', 'createOption'],
    '/admin/options/update' => ['AdminController', 'updateOption'],
    '/admin/options/delete' => ['AdminController', 'deleteOption'],
    '/admin/options/reorder' => ['AdminController', 'reorderOptions'],
    
    // Lineups
    '/lineups' => ['LineupController', 'index'],
    '/lineups/create' => ['LineupController', 'create'],
    '/lineups/view' => ['LineupController', 'view'],
    '/lineups/save-positions' => ['LineupController', 'savePositions'],
    '/lineups/delete' => ['LineupController', 'delete'],
];
