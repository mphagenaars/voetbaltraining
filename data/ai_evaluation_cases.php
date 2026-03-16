<?php
declare(strict_types=1);

return [
    'version' => '1',
    'updated_at' => '2026-03-15',
    'description' => 'Handmatige evaluatieset voor Fase J: availability, viability en recoverability.',
    'cases' => [
        [
            'case_id' => 'public_downloadable_reference',
            'label' => 'Publiek downloadbare referentievideo',
            'bucket' => 'public_downloadable',
            'video_id' => 'OXXKkwBkpEQ',
            'enabled' => true,
            'run_frame_download' => true,
            'notes' => 'Referentie voor een technisch bereikbare video die eerder end-to-end frame-extractie haalde.',
            'expect' => [
                'downloadable_via_ytdlp' => true,
                'technical_selectable' => true,
                'availability_mode' => 'anonymous_ok',
                'frame_download_ok' => true,
                'frame_attempt_count_min' => 1,
            ],
        ],
        [
            'case_id' => 'browser_playable_auth_reference',
            'label' => 'Auth-required browserreferentie',
            'bucket' => 'auth_gated_browser_playable',
            'video_id' => 'KbrHmRU-IfU',
            'enabled' => true,
            'run_frame_download' => true,
            'notes' => 'Age-restricted referentie uit recente yt-dlp issue-triage; bedoeld als browser-playable maar backend-auth-required case.',
            'expect' => [
                'availability_mode_in' => ['auth_required', 'cookie_recovered', 'cookies_invalid'],
                'error_code_not_in' => ['unavailable'],
                'frame_attempt_count_min' => 1,
            ],
        ],
        [
            'case_id' => 'short_clip_reference',
            'label' => 'Korte publieke referentieclip',
            'bucket' => 'short_clip',
            'video_id' => 'jNQXAC9IVRw',
            'enabled' => true,
            'run_frame_download' => false,
            'notes' => 'Stabiele korte publieke clip om short-clip-penalty en viability-ranking te blijven valideren.',
            'expect' => [
                'duration_max_seconds' => 30,
                'technical_recommended' => false,
            ],
        ],
        [
            'case_id' => 'metadata_only_reference',
            'label' => 'Metadata-only tekstbron',
            'bucket' => 'metadata_only',
            'video_id' => 'N_w6d6y4i58',
            'enabled' => true,
            'run_frame_download' => false,
            'notes' => 'Referentie voor een video die in lokale cache eerder alleen metadata_fallback had.',
            'expect' => [
                'transcript_source_in' => ['metadata_fallback', 'none'],
                'chapter_count_max' => 0,
                'metadata_only' => true,
                'source_evidence_sufficient' => false,
            ],
        ],
    ],
];
