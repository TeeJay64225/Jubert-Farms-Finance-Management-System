<?php
// router.php

class Router {
    private $routes = [];

    public function addRoute($url, $file, $role = null) {
        $this->routes[$url] = [
            'file' => $file,
            'role' => $role
        ];
    }

    public function dispatch($url) {
        if (isset($this->routes[$url])) {
            $route = $this->routes[$url];
            $requiredRole = $route['role'];

            // Role check
            if ($requiredRole && isset($_SESSION['user_type'])) {
                if (strtolower($_SESSION['user_type']) !== strtolower($requiredRole) && $requiredRole !== 'Any') {
                    echo "Access Denied";
                    return;
                }
            } elseif ($requiredRole && $requiredRole !== 'Any') {
                echo "Access Denied";
                return;
            }

            require_once $route['file'];
        } else {
            echo "404 - Page not found";
        }
    }
}
         