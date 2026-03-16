<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Core/Auth.php';

class TestController
{
    public function index()
    {
        Auth::requireLogin();

        $testsDir = __DIR__ . '/../../tests_e2e';
        $testFiles = glob($testsDir . '/*.spec.js') ?: [];

        $tests = array_map(function ($file) {
            return basename($file);
        }, $testFiles);

        View::render('tests/index', ['tests' => $tests]);
    }

    public function run()
    {
        Auth::requireLogin();

        header('Content-Type: application/json');

        $testFile = $_POST['test_file'] ?? '';
        if (!$testFile) {
            echo json_encode(['success' => false, 'output' => 'No test file provided']);
            return;
        }

        // Sanitize: only allow *.spec.js filenames, no path traversal
        $testFile = basename($testFile);
        if (!preg_match('/^[\w\-]+\.spec\.js$/', $testFile)) {
            echo json_encode(['success' => false, 'output' => 'Invalid test file name']);
            return;
        }

        $testsDir = realpath(__DIR__ . '/../../tests_e2e');
        $fullPath = $testsDir . DIRECTORY_SEPARATOR . $testFile;

        if (!$testsDir || !file_exists($fullPath)) {
            echo json_encode(['success' => false, 'output' => 'Test file not found']);
            return;
        }

        // Execute Playwright (requires npx and @playwright/test installed in the project)
        $command = 'cd ' . escapeshellarg(realpath(__DIR__ . '/../../'))
                 . ' && npx playwright test ' . escapeshellarg($testFile) . ' 2>&1';

        $output = shell_exec($command);

        echo json_encode(['success' => true, 'output' => $output ?? '(sin salida)']);
    }
}
