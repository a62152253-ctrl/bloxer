# Bloxer Application Structure

## Directory Organization

```
bloxer/
├── assets/                     # Static assets
│   ├── css/                   # Stylesheets
│   │   ├── app.css
│   │   ├── editor.css
│   │   ├── marketplace.css
│   │   ├── marketplace-components.css
│   │   ├── marketplace-enhanced.css
│   │   ├── projects.css
│   │   ├── publish.css
│   │   ├── profile.css
│   │   └── style.css
│   ├── js/                    # JavaScript files
│   │   ├── marketplace-enhanced.js
│   │   ├── marketplace-functions.js
│   │   └── project-wizard.js
│   └── images/                # Image assets
├── config/                    # Configuration files
│   ├── security.php          # Security utilities
│   └── validation.php        # Validation patterns
├── controllers/              # Application controllers
│   ├── Authentication/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   ├── forgotpassword.php
│   │   └── mainlogincore.php
│   ├── User/
│   │   ├── dashboard.php
│   │   ├── profile.php
│   │   └── messages.php
│   ├── Marketplace/
│   │   ├── marketplace.php
│   │   ├── app.php
│   │   ├── app-details.php
│   │   └── app_actions.php
│   ├── Projects/
│   │   ├── projects.php
│   │   ├── project-templates.php
│   │   ├── project-templates-complete.php
│   │   ├── project-import.php
│   │   └── project-import-export.php
│   └── Editor/
│       ├── editor.php
│       ├── editor-simple.php
│       ├── publish.php
│       └── run_app.php
├── database/                  # Database related files
│   ├── migrations/            # Database migration files
│   ├── seeds/                 # Database seed files
│   ├── chat_schema.sql
│   ├── comments_schema.sql
│   ├── complete_database_schema.sql
│   ├── create_login_attempts_table.sql
│   ├── create_offers_table.sql
│   ├── create_table.sql
│   ├── database_schema.sql
│   ├── ratings_schema.sql
│   └── version_control_schema.sql
├── api/                       # API endpoints
│   ├── marketplace-api.php
│   ├── get_file.php
│   ├── get_project_files.php
│   └── get_projects.php
├── includes/                  # Utility files and includes
│   ├── chat.php
│   ├── comments.php
│   ├── ratings.php
│   ├── version-control.php
│   ├── clear_cookies.php
│   ├── setup.php
│   ├── setup_database.php
│   ├── create_test_accounts.php
│   └── workspace.php
├── helpers/                   # Helper functions
├── public/                    # Public accessible files
├── logs/                      # Log files
│   └── .gitkeep
├── .env.example              # Environment configuration template
├── .htaccess                 # Apache configuration
├── index.php                 # Application router
└── README.md                 # Project documentation
```

## Key Improvements

1. **Separation of Concerns**: Files are organized by functionality
2. **MVC Pattern**: Controllers, Models (to be created), and Views (to be organized)
3. **Asset Management**: All CSS and JS files in dedicated directories
4. **Database Organization**: All SQL files in database directory
5. **API Separation**: API endpoints in dedicated directory
6. **Security**: Proper .htaccess configuration with security headers
7. **Environment Support**: .env.example for configuration management

## Next Steps

1. Create model classes for database operations
2. Organize view files in proper directories
3. Implement proper autoloading
4. Add composer for dependency management
5. Create proper routing system
