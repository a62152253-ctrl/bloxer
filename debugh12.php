<?php
/**
 * DEBUGH12 - Hardcore Error Scanner
 * Skanuje błędy PHP/CSS/JS w całej aplikacji
 */

// MAX DEBUG MODE
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('html_errors', 1);
ini_set('track_errors', 1);

// Ustawienia
define('SCAN_ROOT', __DIR__);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_EXECUTION_TIME', 600); // 10 minut
define('MEMORY_LIMIT', '512M');

// Ustawienia limity
ini_set('memory_limit', MEMORY_LIMIT);
set_time_limit(MAX_EXECUTION_TIME);

class DebugH12 {
    // Ignorowane ścieżki
    private $ignore_paths = [
        '.git',
        'node_modules',
        'vendor',
        '.DS_Store',
        'debugh12.php',
        'logs',
        'uploads',
        'temp'
    ];

    // Ignorowane rozszerzenia
    private $ignore_extensions = [
        'log',
        'tmp',
        'cache'
    ];
    private $errors = [];
    private $warnings = [];
    private $stats = [
        'files_scanned' => 0,
        'php_files' => 0,
        'css_files' => 0,
        'js_files' => 0,
        'html_files' => 0,
        'skipped_files' => 0,
        'scan_time' => 0
    ];
    private $start_time;
    private $max_errors = 1000; // Limit błędów
    
    public function scan() {
        $this->start_time = microtime(true);
        
        echo "<!DOCTYPE html><html><head>";
        echo "<title>DEBUGH12 - Hardcore Scanner</title>";
        echo "<style>
            body { font-family: 'Courier New', monospace; background: #000; color: #0f0; margin: 20px; }
            .header { color: #ff0; font-size: 24px; margin-bottom: 20px; }
            .section { color: #0ff; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #0ff; padding-bottom: 5px; }
            .error { color: #f00; background: #300; padding: 5px; margin: 5px 0; }
            .warning { color: #ff0; background: #330; padding: 5px; margin: 5px 0; }
            .info { color: #0f0; background: #030; padding: 5px; margin: 5px 0; }
            .file-path { color: #f0f; font-weight: bold; }
            .line-number { color: #ff0; }
            .code { background: #111; padding: 10px; margin: 5px 0; border-left: 3px solid #0f0; white-space: pre-wrap; font-size: 12px; max-height: 200px; overflow-y: auto; }
            .stats { background: #003; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .progress { color: #0ff; }
            .memory-usage { color: #f0f; font-size: 14px; }
        </style>";
        echo "</head><body>";
        
        echo "<div class='header'>🔥 DEBUGH12 - HARDCORE SCANNER v2 🔥</div>";
        echo "<div class='info'>Scanning entire application for PHP/CSS/JS errors...</div>";
        echo "<div class='memory-usage'>Memory limit: " . MEMORY_LIMIT . " | Max file size: " . (MAX_FILE_SIZE / 1024 / 1024) . "MB</div>";
        
        // Sprawdź dostępność PHP lint
        $php_version = phpversion();
        echo "<div class='info'>PHP Version: $php_version</div>";
        
        $this->scanDirectory(SCAN_ROOT);
        $this->displayResults();
        
        echo "</body></html>";
    }
    
    private function scanDirectory($dir) {
        try {
            $items = scandir($dir);
        } catch (Exception $e) {
            $this->addError($dir, 0, "Cannot read directory: " . $e->getMessage());
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            // Sprawdź czy ignorować
            if ($this->shouldIgnore($path)) {
                $this->stats['skipped_files']++;
                continue;
            }
            
            try {
                if (is_dir($path)) {
                    $this->scanDirectory($path);
                } elseif (is_file($path)) {
                    $this->scanFile($path);
                }
            } catch (Exception $e) {
                $this->addError($path, 0, "Error scanning file: " . $e->getMessage());
            }
            
            // Przerwij jeśli za dużo błędów
            if (count($this->errors) > $this->max_errors) {
                $this->addError($dir, 0, "Maximum error limit reached ($this->max_errors). Stopping scan.");
                break;
            }
        }
    }
    
    private function shouldIgnore($path) {
        $path_parts = explode(DIRECTORY_SEPARATOR, $path);
        
        foreach ($path_parts as $part) {
            if (in_array($part, $this->ignore_paths)) return true;
        }
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, $this->ignore_extensions)) return true;
        
        try {
            if (filesize($path) > MAX_FILE_SIZE) return true;
        } catch (Exception $e) {
            return true; // Ignoruj pliki których nie można odczytać
        }
        
        return false;
    }
    
    private function scanFile($file) {
        $this->stats['files_scanned']++;
        
        // Progress co 100 plików
        if ($this->stats['files_scanned'] % 100 === 0) {
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
            echo "<div class='progress'>Scanned: {$this->stats['files_scanned']} files | Memory: {$memory}MB</div>";
            flush(); // Wymuś wyświetlenie
        }
        
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        try {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->addError($file, 0, "Cannot read file content");
                return;
            }
        } catch (Exception $e) {
            $this->addError($file, 0, "Error reading file: " . $e->getMessage());
            return;
        }
        
        switch ($extension) {
            case 'php':
                $this->stats['php_files']++;
                $this->scanPHP($file, $content);
                break;
            case 'css':
                $this->stats['css_files']++;
                $this->scanCSS($file, $content);
                break;
            case 'js':
                $this->stats['js_files']++;
                $this->scanJS($file, $content);
                break;
            case 'html':
            case 'htm':
                $this->stats['html_files']++;
                $this->scanHTML($file, $content);
                break;
        }
    }
    
    private function scanPHP($file, $content) {
        $lines = explode("\n", $content);
        $total_braces = 0;
        $total_square = 0;
        $total_paren = 0;
        
        foreach ($lines as $line_num => $line) {
            $line_num++;
            
            // Sprawdź składnię PHP tylko w liniach z PHP
            if (strpos($line, '<?php') !== false || strpos($line, '?>') !== false) {
                // Licz nawiasy globalnie
                $total_braces += substr_count($line, '{') - substr_count($line, '}');
                $total_square += substr_count($line, '[') - substr_count($line, ']');
                $total_paren += substr_count($line, '(') - substr_count($line, ')');
                
                // Sprawdź nie zakończone instrukcje (lepszy regex)
                if (trim($line) && !preg_match('/[;{}\s]\s*$/', trim($line)) && 
                    !preg_match('/^\s*(if|else|elseif|for|foreach|while|switch|case|function|class|try|catch|finally|declare|namespace|use)\b/', trim($line)) &&
                    !preg_match('/\/\/.*$/', trim($line)) && // ignoruj komentarze
                    !preg_match('/\/\*.*\*\//', trim($line))) { // ignoruj bloki komentarzy
                    $this->addWarning($file, $line_num, "Possible missing semicolon", $line);
                }
                
                // Sprawdź niebezpieczne funkcje
                $dangerous_functions = ['eval', 'exec', 'system', 'shell_exec', 'passthru', 'file_get_contents', 'fopen', 'fwrite'];
                foreach ($dangerous_functions as $func) {
                    if (preg_match('/\b' . $func . '\s*\(/', $line)) {
                        $this->addWarning($file, $line_num, "Potentially dangerous function: $func", $line);
                    }
                }
                
                // Sprawdź SQL Injection patterns
                if (preg_match('/\$\w+\s*\.\s*["\'].*\$\w+["\']/', $line)) {
                    $this->addWarning($file, $line_num, "Possible SQL injection vulnerability", $line);
                }
                
                // Sprawdź XSS patterns
                if (preg_match('/echo\s+\$\w+/', $line) && !preg_match('/htmlspecialchars|htmlentities/', $line)) {
                    $this->addWarning($file, $line_num, "Unsanitized output - possible XSS", $line);
                }
            }
        }
        
        // Sprawdź globalne nawiasy na końcu pliku
        if ($total_braces !== 0) {
            $this->addError($file, 0, "Unmatched braces in file: $total_braces " . ($total_braces > 0 ? "open" : "close"));
        }
        if ($total_square !== 0) {
            $this->addError($file, 0, "Unmatched square brackets in file: $total_square " . ($total_square > 0 ? "open" : "close"));
        }
        if ($total_paren !== 0) {
            $this->addError($file, 0, "Unmatched parentheses in file: $total_paren " . ($total_paren > 0 ? "open" : "close"));
        }
        
        // Sprawdź składnię całego pliku (tylko dla małych plików)
        if (strlen($content) < 50000) { // 50KB limit
            $temp_file = tempnam(sys_get_temp_dir(), 'php_syntax_check_');
            if ($temp_file) {
                file_put_contents($temp_file, $content);
                $output = [];
                $return_code = 0;
                
                // Validate temp file path to prevent command injection
                $safe_temp_file = escapeshellarg($temp_file);
                SecurityUtils::safeExec("php -l $safe_temp_file 2>&1", $output, $return_code);
                unlink($temp_file);
                
                if ($return_code !== 0) {
                    foreach ($output as $error_line) {
                        if (strpos($error_line, 'Parse error') !== false || strpos($error_line, 'Fatal error') !== false) {
                            $this->addError($file, 0, "PHP Syntax Error: " . $error_line);
                        }
                    }
                }
            }
        }
    }
    
    private function scanCSS($file, $content) {
        $lines = explode("\n", $content);
        $total_braces = 0;
        
        foreach ($lines as $line_num => $line) {
            $line_num++;
            
            // Ignoruj komentarze
            if (preg_match('/\/\*.*\*\//', $line) || trim($line) === '') continue;
            
            // Licz nawiasy globalnie
            $total_braces += substr_count($line, '{') - substr_count($line, '}');
            
            // Sprawdź nie zakończone właściwości (lepszy regex)
            if (preg_match('/^[^{}]*:\s*[^;{}]*$/m', trim($line)) && !preg_match('/\/\*.*$/', $line)) {
                $this->addWarning($file, $line_num, "Missing semicolon in CSS property", $line);
            }
            
            // Sprawdź nieprawidłowe kolory
            if (preg_match('/#[0-9a-fA-F]{0,5}(?![0-9a-fA-F])/', $line, $matches) && !preg_match('/#[0-9a-fA-F]{6}/', $line)) {
                $this->addWarning($file, $line_num, "Invalid hex color: " . $matches[0], $line);
            }
            
            // Sprawdź jednostki bez wartości
            if (preg_match('/^\s*[a-zA-Z-]+\s*:\s*(px|em|rem|%|vh|vw|pt|pc|in|cm|mm|ex|ch|vmin|vmax)\s*;?$/', trim($line))) {
                $this->addWarning($file, $line_num, "CSS unit without value", $line);
            }
        }
        
        // Sprawdź globalne nawiasy na końcu pliku
        if ($total_braces !== 0) {
            $this->addError($file, 0, "Unmatched CSS braces in file: $total_braces " . ($total_braces > 0 ? "open" : "close"));
        }
    }
    
    private function scanJS($file, $content) {
        $lines = explode("\n", $content);
        $total_braces = 0;
        
        foreach ($lines as $line_num => $line) {
            $line_num++;
            
            // Ignoruj komentarze
            if (preg_match('/\/\/.*$/', $line) || preg_match('/\/\*.*\*\//', $line) || trim($line) === '') continue;
            
            // Licz nawiasy globalnie
            $total_braces += substr_count($line, '{') - substr_count($line, '}');
            
            // Sprawdź nie zakończone instrukcje (lepszy regex)
            if (trim($line) && !preg_match('/[;}\s]\s*$/', trim($line)) && 
                !preg_match('/^\s*(if|else|for|while|function|try|catch|finally|switch|case|break|continue|return|var|let|const)\b/', trim($line))) {
                $this->addWarning($file, $line_num, "Possible missing semicolon in JavaScript", $line);
            }
            
            // Sprawdź console.log w produkcji
            if (strpos($line, 'console.log') !== false) {
                $this->addWarning($file, $line_num, "console.log found - remove for production", $line);
            }
            
            // Sprawdź używanie == zamiast ===
            if (preg_match('/==(?!=)/', $line) && !preg_match('/===/', $line)) {
                $this->addWarning($file, $line_num, "Use === instead of = =", $line);
            }
            
            // Sprawdź nieużywane zmienne (prosta heurystyka)
            if (preg_match('/(var|let|const)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)/', $line, $matches)) {
                $var_name = $matches[2];
                $var_count = substr_count($content, $var_name);
                if ($var_count === 1) { // tylko deklaracja
                    $this->addWarning($file, $line_num, "Variable '$var_name' declared but never used", $line);
                }
            }
        }
        
        // Sprawdź globalne nawiasy na końcu pliku
        if ($total_braces !== 0) {
            $this->addError($file, 0, "Unmatched JS braces in file: $total_braces " . ($total_braces > 0 ? "open" : "close"));
        }
    }
    
    private function scanHTML($file, $content) {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line_num => $line) {
            $line_num++;
            
            // Sprawdź nie zamknięte tagi
            if (preg_match_all('/<(\w+)[^>]*>/', $line, $open_matches)) {
                foreach ($open_matches[1] as $tag) {
                    if (!in_array($tag, ['img', 'br', 'hr', 'meta', 'link', 'input'])) {
                        if (!preg_match('/<\/' . $tag . '>/', $line)) {
                            $this->addWarning($file, $line_num, "Unclosed HTML tag: <$tag>", $line);
                        }
                    }
                }
            }
            
            // Sprawdź nie zamknięte atrybuty
            if (preg_match('/<[^>]*\w+\s*=\s*["\'][^"\']*$/m', $line)) {
                $this->addError($file, $line_num, "Unclosed HTML attribute", $line);
            }
        }
    }
    
    private function addError($file, $line, $message, $code = '') {
        $this->errors[] = [
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'code' => $code
        ];
    }
    
    private function addWarning($file, $line, $message, $code = '') {
        $this->warnings[] = [
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'code' => $code
        ];
    }
    
    private function displayResults() {
        $this->stats['scan_time'] = round(microtime(true) - $this->start_time, 2);
        $memory_peak = round(memory_get_peak_usage() / 1024 / 1024, 2);
        
        echo "<div class='section'>📊 SCAN STATISTICS</div>";
        echo "<div class='stats'>";
        echo "<div>Files scanned: <span class='file-path'>{$this->stats['files_scanned']}</span></div>";
        echo "<div>Files skipped: <span class='file-path'>{$this->stats['skipped_files']}</span></div>";
        echo "<div>PHP files: <span class='file-path'>{$this->stats['php_files']}</span></div>";
        echo "<div>CSS files: <span class='file-path'>{$this->stats['css_files']}</span></div>";
        echo "<div>JS files: <span class='file-path'>{$this->stats['js_files']}</span></div>";
        echo "<div>HTML files: <span class='file-path'>{$this->stats['html_files']}</span></div>";
        echo "<div>Scan time: <span class='file-path'>{$this->stats['scan_time']}s</span></div>";
        echo "<div>Peak memory: <span class='file-path'>{$memory_peak}MB</span></div>";
        echo "</div>";
        
        echo "<div class='section'>🔥 CRITICAL ERRORS (" . count($this->errors) . ")</div>";
        if (empty($this->errors)) {
            echo "<div class='info'>✅ No critical errors found!</div>";
        } else {
            $error_count = 0;
            foreach ($this->errors as $error) {
                $error_count++;
                if ($error_count > 50) { // Limit wyświetlania
                    echo "<div class='warning'>... and " . (count($this->errors) - 50) . " more errors (display limited)</div>";
                    break;
                }
                echo "<div class='error'>";
                echo "<div class='file-path'>" . str_replace(SCAN_ROOT, '', $error['file']) . "</div>";
                echo "<div class='line-number'>Line: {$error['line']}</div>";
                echo "<div>{$error['message']}</div>";
                if ($error['code']) {
                    $code_preview = substr($error['code'], 0, 200);
                    echo "<div class='code'>" . htmlspecialchars($code_preview) . (strlen($error['code']) > 200 ? "..." : "") . "</div>";
                }
                echo "</div>";
            }
        }
        
        echo "<div class='section'>⚠️ WARNINGS (" . count($this->warnings) . ")</div>";
        if (empty($this->warnings)) {
            echo "<div class='info'>✅ No warnings found!</div>";
        } else {
            $warning_count = 0;
            foreach ($this->warnings as $warning) {
                $warning_count++;
                if ($warning_count > 50) { // Limit wyświetlania
                    echo "<div class='warning'>... and " . (count($this->warnings) - 50) . " more warnings (display limited)</div>";
                    break;
                }
                echo "<div class='warning'>";
                echo "<div class='file-path'>" . str_replace(SCAN_ROOT, '', $warning['file']) . "</div>";
                echo "<div class='line-number'>Line: {$warning['line']}</div>";
                echo "<div>{$warning['message']}</div>";
                if ($warning['code']) {
                    $code_preview = substr($warning['code'], 0, 200);
                    echo "<div class='code'>" . htmlspecialchars($code_preview) . (strlen($warning['code']) > 200 ? "..." : "") . "</div>";
                }
                echo "</div>";
            }
        }
        
        echo "<div class='section'>🎯 SCAN COMPLETE</div>";
        echo "<div class='info'>";
        echo "<div>Total issues found: <span class='file-path'>" . (count($this->errors) + count($this->warnings)) . "</span></div>";
        echo "<div>Errors: <span class='error'>" . count($this->errors) . "</span></div>";
        echo "<div>Warnings: <span class='warning'>" . count($this->warnings) . "</span></div>";
        echo "<div class='memory-usage'>Scan completed in {$this->stats['scan_time']}s with peak memory {$memory_peak}MB</div>";
        echo "</div>";
    }
}

// Uruchom skaner
$scanner = new DebugH12();
$scanner->scan();
?>
