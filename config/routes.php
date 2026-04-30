<?php
/**
 * Routes Configuration for Bloxer Platform
 * Centralized route management and URL routing
 */

class RouteConfig {
    private static $routes = [];
    private static $currentNamespace = '';
    
    public static function get($path, $handler, $middleware = []) {
        self::addRoute('GET', $path, $handler, $middleware);
    }
    
    public static function post($path, $handler, $middleware = []) {
        self::addRoute('POST', $path, $handler, $middleware);
    }
    
    public static function put($path, $handler, $middleware = []) {
        self::addRoute('PUT', $path, $handler, $middleware);
    }
    
    public static function delete($path, $handler, $middleware = []) {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }
    
    public static function any($path, $handler, $middleware = []) {
        self::addRoute(['GET', 'POST', 'PUT', 'DELETE'], $path, $handler, $middleware);
    }
    
    public static function group($namespace, $callback) {
        $previousNamespace = self::$currentNamespace;
        self::$currentNamespace = trim($previousNamespace . '/' . $namespace, '/');
        $callback();
        self::$currentNamespace = $previousNamespace;
    }
    
    private static function addRoute($methods, $path, $handler, $middleware = []) {
        if (is_string($methods)) {
            $methods = [$methods];
        }
        
        $fullPath = trim(self::$currentNamespace . '/' . $path, '/');
        
        foreach ($methods as $method) {
            self::$routes[$method][$fullPath] = [
                'handler' => $handler,
                'middleware' => $middleware,
                'namespace' => self::$currentNamespace
            ];
        }
    }
    
    public static function dispatch($method, $uri) {
        $uri = trim($uri, '/');
        $uri = $uri === '' ? '/' : $uri;
        
        // Check for exact match
        if (isset(self::$routes[$method][$uri])) {
            return self::executeRoute(self::$routes[$method][$uri]);
        }
        
        // Check for parameterized routes
        foreach (self::$routes[$method] as $route => $routeInfo) {
            if (self::matchesRoute($route, $uri, $params)) {
                return self::executeRoute($routeInfo, $params);
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        include 'controllers/errors/404.php';
        return false;
    }
    
    private static function matchesRoute($route, $uri, &$params) {
        $routeParts = explode('/', $route);
        $uriParts = explode('/', $uri);
        
        if (count($routeParts) !== count($uriParts)) {
            return false;
        }
        
        $params = [];
        
        foreach ($routeParts as $i => $part) {
            if (strpos($part, '{') === 0 && strpos($part, '}') === strlen($part) - 1) {
                $paramName = substr($part, 1, -1);
                $params[$paramName] = $uriParts[$i];
            } elseif ($part !== $uriParts[$i]) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function executeRoute($routeInfo, $params = []) {
        $handler = $routeInfo['handler'];
        $middleware = $routeInfo['middleware'];
        
        // Execute middleware
        foreach ($middleware as $mw) {
            if (!self::executeMiddleware($mw)) {
                return false;
            }
        }
        
        // Execute handler
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        } elseif (is_string($handler)) {
            return self::executeController($handler, $params);
        }
        
        throw new Exception("Invalid route handler");
    }
    
    private static function executeMiddleware($middleware) {
        if (is_callable($middleware)) {
            return $middleware();
        } elseif (is_string($middleware)) {
            $middlewareFile = "middleware/$middleware.php";
            if (file_exists($middlewareFile)) {
                require_once $middlewareFile;
                $class = ucfirst($middleware) . 'Middleware';
                if (class_exists($class)) {
                    $instance = new $class();
                    return $instance->handle();
                }
            }
        }
        return true;
    }
    
    private static function executeController($handler, $params) {
        list($controller, $method) = explode('@', $handler);
        
        $controllerFile = "controllers/$controller.php";
        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: $controllerFile");
        }
        
        require_once $controllerFile;
        $controllerClass = ucfirst($controller) . 'Controller';
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: $controllerClass");
        }
        
        $instance = new $controllerClass();
        
        if (!method_exists($instance, $method)) {
            throw new Exception("Method not found: $method in $controllerClass");
        }
        
        return call_user_func_array([$instance, $method], $params);
    }
    
    public static function getRoutes() {
        return self::$routes;
    }
    
    public static function url($routeName, $params = []) {
        // This would need to be implemented for named routes
        // For now, return basic URL construction
        return app_url($routeName);
    }
}

// Define application routes
RouteConfig::group('auth', function() {
    RouteConfig::get('login', 'auth/login.php');
    RouteConfig::post('login', 'auth/login.php@login');
    RouteConfig::get('register', 'auth/register.php');
    RouteConfig::post('register', 'auth/register.php@register');
    RouteConfig::get('logout', 'auth/logout.php');
    RouteConfig::get('forgot-password', 'auth/forgotpassword.php');
    RouteConfig::post('forgot-password', 'auth/forgotpassword.php@sendReset');
});

RouteConfig::group('user', function() {
    RouteConfig::get('profile', 'user/profile.php');
    RouteConfig::post('profile', 'user/profile.php@update');
    RouteConfig::get('notifications', 'user/notifications.php');
    RouteConfig::post('notifications', 'user/notifications.php@update');
    RouteConfig::get('messages', 'user/messages.php');
    RouteConfig::get('personalized-feed', 'user/personalized_feed.php');
    RouteConfig::get('follow-feed', 'user/follow_feed.php');
    RouteConfig::get('developer-profile', 'user/developer_profile.php');
    RouteConfig::post('developer-profile', 'user/developer_profile.php@update');
    RouteConfig::get('user-appview', 'user/user-appview.php');
    RouteConfig::get('report', 'user/report.php');
    RouteConfig::post('report', 'user/report.php@submit');
});

RouteConfig::group('core', function() {
    RouteConfig::get('dashboard', 'core/dashboard.php');
    RouteConfig::post('dashboard', 'core/dashboard.php@handleAction');
    RouteConfig::get('workspace', 'core/workspace.php');
    RouteConfig::post('workspace', 'core/workspace.php@save');
    RouteConfig::get('tools', 'core/tools.php');
    RouteConfig::get('run-app', 'core/run_app.php');
    RouteConfig::get('sandbox', 'core/sandbox.php');
});

RouteConfig::group('projects', function() {
    RouteConfig::get('projects', 'projects/projects.php');
    RouteConfig::post('projects', 'projects/projects.php@handleAction');
    RouteConfig::get('project-templates', 'projects/project-templates.php');
    RouteConfig::get('project-templates-complete', 'projects/project-templates-complete.php');
    RouteConfig::post('project-import', 'projects/project-import.php@import');
    RouteConfig::get('project-import-export', 'projects/project-import-export.php');
});

RouteConfig::group('marketplace', function() {
    RouteConfig::get('marketplace', 'marketplace/marketplace.php');
    RouteConfig::get('marketplace-settings', 'marketplace/marketplace-settings.php');
    RouteConfig::post('marketplace-settings', 'marketplace/marketplace-settings.php@update');
    RouteConfig::get('publish', 'marketplace/publish.php');
    RouteConfig::post('publish', 'marketplace/publish.php@submit');
});

RouteConfig::group('apps', function() {
    RouteConfig::get('app', 'apps/app.php');
    RouteConfig::get('app-details', 'apps/app-details.php');
    RouteConfig::get('app-actions', 'apps/app_actions.php');
    RouteConfig::post('app-actions', 'apps/app_actions.php@handleAction');
    RouteConfig::get('app-updates', 'apps/app_updates.php');
});

RouteConfig::group('api', function() {
    RouteConfig::get('api', 'api/api_base.php');
    RouteConfig::post('api', 'api/api_base.php@handle');
    RouteConfig::get('apps-api', 'api/apps_api.php');
    RouteConfig::get('follow-developer', 'api/follow_developer.php');
    RouteConfig::post('follow-developer', 'api/follow_developer.php@toggle');
    RouteConfig::get('get-file', 'api/get_file.php');
    RouteConfig::post('version-control', 'api/version-control.php@handle');
    RouteConfig::get('download-version', 'api/download_version.php');
    RouteConfig::get('download-version-file', 'api/download_version_file.php');
    RouteConfig::post('websocket-server', 'api/websocket_server.php@handle');
});

RouteConfig::group('admin', function() {
    RouteConfig::get('admin', 'admin.php');
    RouteConfig::group('tools', function() {
        RouteConfig::get('overview', 'tools/overview.php');
        RouteConfig::get('activity', 'tools/activity.php');
        RouteConfig::get('performance', 'tools/performance.php');
        RouteConfig::get('reports', 'admin/reports.php');
    });
});

// Root routes
RouteConfig::get('', 'index.php');
RouteConfig::get('index.php', 'index.php');
RouteConfig::get('check-projects', 'check_projects.php');

// WebSocket
RouteConfig::get('start-websocket', 'start_websocket.php');

// Helper function to dispatch current request
function dispatchRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    
    // Remove query string from URI
    $uri = strtok($uri, '?');
    
    return RouteConfig::dispatch($method, $uri);
}
?>
