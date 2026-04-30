<?php
// Project Templates Handler
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get available templates
function getProjectTemplates() {
    return [
        [
            'id' => 'blank',
            'name' => 'Blank Project',
            'description' => 'Start with a clean slate',
            'icon' => 'fa-file',
            'category' => 'basic',
            'files' => [
                'index.html' => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>My Project</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <h1>Welcome to your project!</h1>\n    <script src=\"script.js\"></script>\n</body>\n</html>",
                'style.css' => "/* Add your styles here */\nbody {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 20px;\n}\n\nh1 {\n    color: #333;\n}",
                'script.js' => "// Add your JavaScript here"
            ]
        ],
        [
            'id' => 'portfolio',
            'name' => 'Portfolio Website',
            'description' => 'Personal portfolio template',
            'icon' => 'fa-user',
            'category' => 'portfolio',
            'files' => [
                'index.html' => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>My Portfolio</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <header>\n        <nav>\n            <h1>My Name</h1>\n            <ul>\n                <li><a href=\"#about\">About</a></li>\n                <li><a href=\"#projects\">Projects</a></li>\n                <li><a href=\"#contact\">Contact</a></li>\n            </ul>\n        </nav>\n    </header>\n    \n    <main>\n        <section id=\"hero\">\n            <h2>Web Developer & Designer</h2>\n            <p>Creating beautiful and functional web experiences</p>\n        </section>\n        \n        <section id=\"projects\">\n            <h2>My Projects</h2>\n            <div class=\"project-grid\">\n                <div class=\"project-card\">\n                    <h3>Project 1</h3>\n                    <p>Description of project</p>\n                </div>\n            </div>\n        </section>\n    </main>\n    \n    <script src=\"script.js\"></script>\n</body>\n</html>",
                'style.css' => "* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}\n\nbody {\n    font-family: 'Arial', sans-serif;\n    line-height: 1.6;\n    color: #333;\n}\n\nheader {\n    background: #333;\n    color: white;\n    padding: 1rem 0;\n    position: fixed;\n    width: 100%;\n    top: 0;\n    z-index: 1000;\n}\n\nnav {\n    display: flex;\n    justify-content: space-between;\n    align-items: center;\n    max-width: 1200px;\n    margin: 0 auto;\n    padding: 0 2rem;\n}\n\nnav ul {\n    display: flex;\n    list-style: none;\n    gap: 2rem;\n}\n\nnav a {\n    color: white;\n    text-decoration: none;\n}\n\n#hero {\n    margin-top: 80px;\n    padding: 4rem 2rem;\n    text-align: center;\n    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n    color: white;\n}\n\n#projects {\n    padding: 4rem 2rem;\n    max-width: 1200px;\n    margin: 0 auto;\n}\n\n.project-grid {\n    display: grid;\n    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));\n    gap: 2rem;\n    margin-top: 2rem;\n}\n\n.project-card {\n    background: white;\n    padding: 2rem;\n    border-radius: 8px;\n    box-shadow: 0 4px 6px rgba(0,0,0,0.1);\n}",
                'script.js' => "// Smooth scrolling\ndocument.querySelectorAll('a[href^=\"#\"]').forEach(anchor => {\n    anchor.addEventListener('click', function (e) {\n        e.preventDefault();\n        document.querySelector(this.getAttribute('href')).scrollIntoView({\n            behavior: 'smooth'\n        });\n    });\n});\n\n// Simple scroll animations\nwindow.addEventListener('scroll', () => {\n    const scrollY = window.pageYOffset;\n    const hero = document.querySelector('#hero');\n    if (hero) {\n        hero.style.transform = `translateY(${scrollY * 0.5}px)`;\n    }\n});"
            ]
        ],
        [
            'id' => 'blog',
            'name' => 'Blog Template',
            'description' => 'Simple blog layout',
            'icon' => 'fa-blog',
            'category' => 'content',
            'files' => [
                'index.html' => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>My Blog</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <header>\n        <div class=\"container\">\n            <h1>My Blog</h1>\n            <p>Sharing thoughts and ideas</p>\n        </div>\n    </header>\n    \n    <main class=\"container\">\n        <section class=\"posts\">\n            <article class=\"post\">\n                <h2>Welcome to My Blog</h2>\n                <p class=\"meta\">Posted on January 1, 2024</p>\n                <p>This is my first blog post. I'm excited to share my thoughts and experiences with you!</p>\n                <a href=\"#\" class=\"read-more\">Read More</a>\n            </article>\n            \n            <article class=\"post\">\n                <h2>Another Post</h2>\n                <p class=\"meta\">Posted on January 2, 2024</p>\n                <p>Here's another interesting topic I'd like to discuss...</p>\n                <a href=\"#\" class=\"read-more\">Read More</a>\n            </article>\n        </section>\n        \n        <aside class=\"sidebar\">\n            <div class=\"widget\">\n                <h3>About Me</h3>\n                <p>I'm a developer who loves to write about technology and life.</p>\n            </div>\n            \n            <div class=\"widget\">\n                <h3>Categories</h3>\n                <ul>\n                    <li><a href=\"#\">Technology</a></li>\n                    <li><a href=\"#\">Life</a></li>\n                    <li><a href=\"#\">Tutorials</a></li>\n                </ul>\n            </div>\n        </aside>\n    </main>\n    \n    <script src=\"script.js\"></script>\n</body>\n</html>",
                'style.css' => ".container {\n    max-width: 1200px;\n    margin: 0 auto;\n    padding: 0 20px;\n}\n\nheader {\n    background: #2c3e50;\n    color: white;\n    padding: 2rem 0;\n    text-align: center;\n}\n\nmain {\n    display: grid;\n    grid-template-columns: 2fr 1fr;\n    gap: 2rem;\n    margin: 2rem 0;\n}\n\n.post {\n    background: white;\n    padding: 2rem;\n    margin-bottom: 2rem;\n    border-radius: 8px;\n    box-shadow: 0 2px 4px rgba(0,0,0,0.1);\n}\n\n.post h2 {\n    color: #2c3e50;\n    margin-bottom: 0.5rem;\n}\n\n.meta {\n    color: #7f8c8d;\n    font-size: 0.9rem;\n    margin-bottom: 1rem;\n}\n\n.read-more {\n    color: #3498db;\n    text-decoration: none;\n    font-weight: bold;\n}\n\n.read-more:hover {\n    text-decoration: underline;\n}\n\n.sidebar .widget {\n    background: white;\n    padding: 1.5rem;\n    margin-bottom: 1rem;\n    border-radius: 8px;\n    box-shadow: 0 2px 4px rgba(0,0,0,0.1);\n}\n\n.sidebar h3 {\n    color: #2c3e50;\n    margin-bottom: 1rem;\n}\n\n.sidebar ul {\n    list-style: none;\n    padding: 0;\n}\n\n.sidebar li {\n    margin-bottom: 0.5rem;\n}\n\n.sidebar a {\n    color: #3498db;\n    text-decoration: none;\n}\n\n.sidebar a:hover {\n    text-decoration: underline;\n}\n\n@media (max-width: 768px) {\n    main {\n        grid-template-columns: 1fr;\n    }\n}",
                'script.js' => "// Blog functionality\ndocument.addEventListener('DOMContentLoaded', function() {\n    // Add some interactivity to posts\n    const posts = document.querySelectorAll('.post');\n    \n    posts.forEach(post => {\n        post.addEventListener('click', function() {\n            // Could expand post or navigate to full article\n            console.log('Post clicked');\n        });\n    });\n    \n    // Simple search functionality (placeholder)\n    const searchInput = document.createElement('input');\n    searchInput.type = 'text';\n    searchInput.placeholder = 'Search posts...';\n    searchInput.style.cssText = 'width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;';\n    \n    const postsSection = document.querySelector('.posts');\n    if (postsSection) {\n        postsSection.parentNode.insertBefore(searchInput, postsSection);\n    }\n});"
            ]
        ],
        [
            'id' => 'landing',
            'name' => 'Landing Page',
            'description' => 'Marketing landing page',
            'icon' => 'fa-rocket',
            'category' => 'business',
            'files' => [
                'index.html' => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>Amazing Product</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <nav class=\"navbar\">\n        <div class=\"nav-container\">\n            <div class=\"logo\">ProductLogo</div>\n            <ul class=\"nav-menu\">\n                <li><a href=\"#features\">Features</a></li>\n                <li><a href=\"#pricing\">Pricing</a></li>\n                <li><a href=\"#contact\">Contact</a></li>\n                <li><button class=\"btn btn-primary\">Get Started</button></li>\n            </ul>\n        </div>\n    </nav>\n    \n    <section class=\"hero\">\n        <div class=\"hero-content\">\n            <h1 class=\"hero-title\">Transform Your Business</h1>\n            <p class=\"hero-subtitle\">The most innovative solution for modern companies</p>\n            <div class=\"hero-actions\">\n                <button class=\"btn btn-primary btn-large\">Start Free Trial</button>\n                <button class=\"btn btn-secondary btn-large\">Watch Demo</button>\n            </div>\n        </div>\n    </section>\n    \n    <section id=\"features\" class=\"features\">\n        <div class=\"container\">\n            <h2>Powerful Features</h2>\n            <div class=\"features-grid\">\n                <div class=\"feature\">\n                    <div class=\"feature-icon\">⚡</div>\n                    <h3>Lightning Fast</h3>\n                    <p>Optimized performance for the best user experience</p>\n                </div>\n                <div class=\"feature\">\n                    <div class=\"feature-icon\">🔒</div>\n                    <h3>Secure</h3>\n                    <p>Enterprise-grade security to protect your data</p>\n                </div>\n                <div class=\"feature\">\n                    <div class=\"feature-icon\">�</div>\n                    <h3>Scalable</h3>\n                    <p>Grows with your business needs</p>\n                </div>\n            </div>\n        </div>\n    </section>\n    \n    <script src=\"script.js\"></script>\n</body>\n</html>",
                'style.css' => "* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}\n\nbody {\n    font-family: Arial, sans-serif;\n    line-height: 1.6;\n    color: #333;\n}\n\n.navbar {\n    background: #fff;\n    box-shadow: 0 2px 10px rgba(0,0,0,0.1);\n    position: fixed;\n    width: 100%;\n    top: 0;\n    z-index: 1000;\n}\n\n.nav-container {\n    max-width: 1200px;\n    margin: 0 auto;\n    padding: 1rem 2rem;\n    display: flex;\n    justify-content: space-between;\n    align-items: center;\n}\n\n.logo {\n    font-size: 1.5rem;\n    font-weight: bold;\n    color: #333;\n}\n\n.nav-menu {\n    display: flex;\n    list-style: none;\n    gap: 2rem;\n    align-items: center;\n}\n\n.nav-menu a {\n    text-decoration: none;\n    color: #666;\n    transition: color 0.3s;\n}\n\n.nav-menu a:hover {\n    color: #007bff;\n}\n\n.btn {\n    padding: 0.5rem 1rem;\n    border: none;\n    border-radius: 5px;\n    cursor: pointer;\n    text-decoration: none;\n    transition: all 0.3s;\n}\n\n.btn-primary {\n    background: #007bff;\n    color: white;\n}\n\n.btn-primary:hover {\n    background: #0056b3;\n}\n\n.btn-secondary {\n    background: transparent;\n    color: #007bff;\n    border: 1px solid #007bff;\n}\n\n.btn-secondary:hover {\n    background: #007bff;\n    color: white;\n}\n\n.btn-large {\n    padding: 1rem 2rem;\n    font-size: 1.1rem;\n}\n\n.hero {\n    margin-top: 80px;\n    padding: 6rem 2rem;\n    text-align: center;\n    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n    color: white;\n}\n\n.hero-title {\n    font-size: 3rem;\n    margin-bottom: 1rem;\n}\n\n.hero-subtitle {\n    font-size: 1.2rem;\n    margin-bottom: 2rem;\n    opacity: 0.9;\n}\n\n.hero-actions {\n    display: flex;\n    gap: 1rem;\n    justify-content: center;\n}\n\n.features {\n    padding: 4rem 2rem;\n}\n\n.container {\n    max-width: 1200px;\n    margin: 0 auto;\n}\n\n.features-grid {\n    display: grid;\n    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));\n    gap: 2rem;\n    margin-top: 2rem;\n}\n\n.feature {\n    text-align: center;\n    padding: 2rem;\n    background: white;\n    border-radius: 10px;\n    box-shadow: 0 5px 15px rgba(0,0,0,0.1);\n}\n\n.feature-icon {\n    font-size: 3rem;\n    margin-bottom: 1rem;\n}\n\n.feature h3 {\n    margin-bottom: 1rem;\n    color: #333;\n}",
                'script.js' => "// Smooth scrolling\ndocument.querySelectorAll('a[href^=\"#\"]').forEach(anchor => {\n    anchor.addEventListener('click', function (e) {\n        e.preventDefault();\n        document.querySelector(this.getAttribute('href')).scrollIntoView({\n            behavior: 'smooth'\n        });\n    });\n});\n\n// Button interactions\ndocument.querySelectorAll('.btn').forEach(button => {\n    button.addEventListener('click', function() {\n        console.log('Button clicked:', this.textContent);\n    });\n});"
            ]
        ]
    ];
}

// Handle API requests
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'templates' => getProjectTemplates()]);
        break;
        
    case 'get':
        $templateId = $_GET['id'] ?? '';
        $templates = getProjectTemplates();
        $template = null;
        
        foreach ($templates as $t) {
            if ($t['id'] === $templateId) {
                $template = $t;
                break;
            }
        }
        
        if ($template) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'template' => $template]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Template not found']);
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
