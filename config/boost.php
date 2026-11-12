<?php

declare(strict_types=1);

return [
    // Docker 越しの実行・自動整形・XServer 配備と食い違う指示を、生成ガイドラインから除く。
    'guidelines' => [
        'exclude' => ['deployments', 'pest/core', 'pint/core'],
    ],

    'rules' => [
        'enabled' => false,
    ],

    'agents' => [
        'antigravity' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'codex' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'claude_code' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'gemini' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'cursor' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'copilot' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'opencode' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
        'junie' => [
            'guidelines_path' => 'docs/generated/boost.md',
        ],
    ],
];
