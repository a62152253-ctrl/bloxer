<?php
/**
 * Static Analysis Tool for Bloxer
 * Performs basic static code analysis to find potential issues
 */

require_once '../controllers/core/mainlogincore.php';
require_once '../../config/security.php';

$auth = new AuthCore();

// Only allow developers
if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit('Access denied. Developer access required.', 403, 'warning');
}

echo "<h1>🔍 Static Analysis - Bloxer Code Quality</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .issue { margin: 10px 0; padding: 10px; border-radius: 5px; }
    .error { background: #ffebee; border-left: 4px solid #f44336; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; }
    .info { background: #e3f2fd; border-left: 4px solid #2196f3; }
    .success { background: #e8f5e8; border-left: 4px solid #4caf50; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
    .stat-card { background: #f5f5f5; padding: 15px; border-radius: 8px; text-align: center; }
    .file-list { max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 5px; }
</style>";

class StaticAnalyzer {
    private $rootPath;
    private $issues = [];
    private $stats = [
        'files_analyzed' => 0,
        'total_lines' => 0,
        'errors' => 0,
        'warnings' => 0,
        'info' => 0
    ];

    public function __construct($rootPath) {
        $this->rootPath = $rootPath;
    }

    public function analyze() {
        $this->analyzePhpFiles();
        $this->analyzeJsFiles();
        return $this->generateReport();
    }

    private function analyzePhpFiles() {
        $phpFiles = $this->findFiles('*.php');
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $this->stats['files_analyzed']++;
            $this->stats['total_lines'] += count($lines);

            // Check for security issues
            $this->checkSecurityIssues($file, $content, $lines);
            
            // Check for code quality issues
            $this->checkCodeQuality($file, $content, $lines);
            
            // Check for deprecated functions
            $this->checkDeprecatedFunctions($file, $content, $lines);
        }
    }

    private function analyzeJsFiles() {
        $jsFiles = $this->findFiles('*.js');
        
        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $this->stats['files_analyzed']++;
            $this->stats['total_lines'] += count($lines);

            // Check for JavaScript security issues
            $this->checkJsSecurityIssues($file, $content, $lines);
        }
    }

    private function checkSecurityIssues($file, $content, $lines) {
        // Check for eval()
        if (preg_match('/eval\s*\(/', $content)) {
            $this->addIssue('error', $file, 'Use of eval() function detected', $this->findLine($lines, 'eval'));
        }

        // Check for system calls
        if (preg_match('/(system|exec|shell_exec|passthru|proc_open)\s*\(/', $content)) {
            $this->addIssue('warning', $file, 'System function call detected', $this->findLine($lines, 'system'));
        }

        // Check for SQL injection risks (basic heuristic)
        if (preg_match('/\$\w+\s*=\s*["\'].*\$\w+.*["\']/', $content) && 
            preg_match('/(mysql_query|mysqli_query|pg_query)/', $content)) {
            $this->addIssue('warning', $file, 'Potential SQL injection vulnerability (heuristic)', $this->findLine($lines, 'mysql_query'));
        }

        // Check for XSS risks
        if (preg_match('/echo\s*\$\w+/', $content) && !preg_match('/htmlspecialchars|htmlentities/', $content)) {
            $this->addIssue('warning', $file, 'Potential XSS vulnerability - unescaped output', $this->findLine($lines, 'echo'));
        }

        // Check for file inclusion
        if (preg_match('/\b(include|require|include_once|require_once)\s*\(\s*\$/', $content)) {
            $this->addIssue('error', $file, 'Dynamic file inclusion detected', $this->findLine($lines, 'include|require'));
        }
    }

    private function checkCodeQuality($file, $content, $lines) {
        // Check for TODO comments
        if (preg_match('/TODO|FIXME|XXX|HACK/', $content)) {
            $this->addIssue('info', $file, 'TODO/FIXME comment found', $this->findLine($lines, 'TODO|FIXME|XXX|HACK'));
        }

        // Check for long functions (>50 lines)
        if (preg_match_all('/function\s+\w+\s*\([^)]*\)\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $startPos = $match[1];
                $bracketCount = 0;
                $lineCount = 0;
                $inFunction = false;

                for ($i = $startPos; $i < strlen($content); $i++) {
                    if ($content[$i] === '{') {
                        $bracketCount++;
                        $inFunction = true;
                    } elseif ($content[$i] === '}') {
                        $bracketCount--;
                        if ($bracketCount === 0 && $inFunction) {
                            break;
                        }
                    } elseif ($content[$i] === "\n" && $inFunction) {
                        $lineCount++;
                    }
                }

                if ($lineCount > 50) {
                    $this->addIssue('warning', $file, 'Long function detected (' . $lineCount . ' lines)', $this->findLine($lines, substr($match[0], 0, 20)));
                }
            }
        }

        // Check for deep nesting (>4 levels)
        $linesWithNesting = [];
        foreach ($lines as $lineNum => $line) {
            $indent = strlen($line) - strlen(ltrim($line));
            $nestingLevel = $indent / 4; // Assuming 4 spaces per level
            if ($nestingLevel > 4) {
                $linesWithNesting[] = $lineNum + 1;
            }
        }

        if (!empty($linesWithNesting)) {
            $this->addIssue('warning', $file, 'Deep nesting detected (>4 levels) on lines: ' . implode(', ', $linesWithNesting));
        }
    }

    private function checkDeprecatedFunctions($file, $content, $lines) {
        $deprecated = [
            'mysql_connect' => 'Use mysqli or PDO instead',
            'ereg' => 'Use preg_match instead',
            'split' => 'Use explode or preg_split instead',
            'create_function' => 'Use anonymous functions instead'
        ];

        foreach ($deprecated as $func => $alternative) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/', $content)) {
                $this->addIssue('warning', $file, "Deprecated function '$func' - $alternative", $this->findLine($lines, $func));
            }
        }
    }

    private function checkJsSecurityIssues($file, $content, $lines) {
        // Check for eval()
        if (preg_match('/eval\s*\(/', $content)) {
            $this->addIssue('error', $file, 'JavaScript eval() detected', $this->findLine($lines, 'eval'));
        }

        // Check for innerHTML without sanitization
        if (preg_match('/innerHTML\s*=/', $content) && !preg_match('/sanitize|escape/', $content)) {
            $this->addIssue('warning', $file, 'Potential XSS - innerHTML assignment', $this->findLine($lines, 'innerHTML'));
        }

        // Check for document.write
        if (preg_match('/document\.write\s*\(/', $content)) {
            $this->addIssue('warning', $file, 'document.write() detected', $this->findLine($lines, 'document.write'));
        }
    }

    private function findLine($lines, $pattern) {
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/', $line)) {
                return $lineNum + 1;
            }
        }
        return 'unknown';
    }

    private function addIssue($severity, $file, $message, $line = null) {
        $this->issues[] = [
            'severity' => $severity,
            'file' => str_replace($this->rootPath . '/', '', $file),
            'message' => $message,
            'line' => $line
        ];
        
        $this->stats[$severity === 'error' ? 'errors' : ($severity === 'warning' ? 'warnings' : 'info')]++;
    }

    private function findFiles($pattern) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->rootPath));
        
        // Exclude common directories that don't need analysis
        $excludeDirs = ['vendor', 'node_modules', '.git', 'cache', 'temp', 'logs'];
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $path = $file->getPathname();
                
                // Skip excluded directories
                $skip = false;
                foreach ($excludeDirs as $excludeDir) {
                    if (strpos($path, '/' . $excludeDir . '/') !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }

    private function generateReport() {
        $html = '<div class="stats">';
        
        $html .= '<div class="stat-card">';
        $html .= '<h3>' . $this->stats['files_analyzed'] . '</h3>';
        $html .= '<p>Files Analyzed</p>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<h3>' . $this->stats['total_lines'] . '</h3>';
        $html .= '<p>Total Lines</p>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<h3>' . $this->stats['errors'] . '</h3>';
        $html .= '<p>Errors</p>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<h3>' . $this->stats['warnings'] . '</h3>';
        $html .= '<p>Warnings</p>';
        $html .= '</div>';
        
        $html .= '</div>';

        if (empty($this->issues)) {
            $html .= '<div class="issue success">';
            $html .= '<h3>🎉 Excellent!</h3>';
            $html .= '<p>No issues found in the codebase.</p>';
            $html .= '</div>';
        } else {
            usort($this->issues, function($a, $b) {
                $severityOrder = ['error' => 0, 'warning' => 1, 'info' => 2];
                return $severityOrder[$a['severity']] - $severityOrder[$b['severity']];
            });

            foreach ($this->issues as $issue) {
                $html .= '<div class="issue ' . $issue['severity'] . '">';
                $html .= '<strong>' . ucfirst($issue['severity']) . '</strong>';
                $html .= ' in <code>' . htmlspecialchars($issue['file'], ENT_QUOTES, 'UTF-8') . '</code>';
                if ($issue['line'] && $issue['line'] !== 'unknown') {
                    $html .= ' at line ' . htmlspecialchars($issue['line'], ENT_QUOTES, 'UTF-8');
                }
                $html .= '<br>';
                $html .= htmlspecialchars($issue['message'], ENT_QUOTES, 'UTF-8');
                $html .= '</div>';
            }
        }

        return $html;
    }
}

// Run analysis
$analyzer = new StaticAnalyzer(__DIR__ . '/..');
echo $analyzer->analyze();

echo "<p><a href='../index.php'>← Back to Bloxer</a></p>";
?>
