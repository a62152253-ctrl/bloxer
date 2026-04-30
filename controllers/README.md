# Controllers Structure

## Organized Folder Structure

### `/auth/`
Authentication related controllers
- `login.php` - User login page
- `register.php` - User registration page  
- `forgotpassword.php` - Password recovery
- `logout.php` - User logout

### `/apps/`
Application management controllers
- `app.php` - Main app display page
- `app-details.php` - Detailed app information
- `app_actions.php` - App actions (install, rate, etc.)
- `app_old.php` - Legacy app display
- `app_updates.php` - App update management

### `/projects/`
Project development controllers
- `projects.php` - Project list and management
- `projects_old.php` - Legacy project management
- `project-import.php` - Project import functionality
- `project-import-export.php` - Project import/export
- `project-templates.php` - Project templates
- `project-templates-complete.php` - Complete project templates

### `/marketplace/`
Marketplace and publishing controllers
- `marketplace.php` - Main marketplace page
- `marketplace-settings.php` - Marketplace configuration
- `marketplace_backup.php` - Marketplace backup
- `publish.php` - App publishing

### `/user/`
User profile and social features
- `profile.php` - User profile page
- `developer_profile.php` - Developer profile
- `user-appview.php` - User's app view
- `messages.php` - Private messages
- `notifications.php` - User notifications
- `follow_feed.php` - Follow feed
- `personalized_feed.php` - Personalized content feed
- `report.php` - Report content/users

### `/core/`
Core system controllers
- `mainlogincore.php` - Core authentication system
- `dashboard.php` - Main developer dashboard
- `workspace.php` - Development workspace
- `run_app.php` - App execution environment
- `sandbox.php` - Secure sandbox environment
- `tools.php` - Development tools

### `/api/`
API and service endpoints
- `version-control.php` - Version control API
- `download_version.php` - File download API
- `download_version_file.php` - Version file download
- `websocket_server.php` - WebSocket server

### `/admin/`
Administrative controllers
- `reports.php` - System reports

### `/tools/`
Development and analytics tools
- `activity.php` - Activity monitoring
- `overview.php` - System overview
- `performance.php` - Performance metrics
- `preview.php` - Preview functionality
- `visitors.php` - Visitor analytics

## Benefits of This Structure

1. **Better Organization**: Related files grouped by functionality
2. **Easier Maintenance**: Clear separation of concerns
3. **Scalability**: Easy to add new controllers in appropriate folders
4. **Team Development**: Different developers can work on different modules
5. **Code Reusability**: Shared functionality easier to identify

## Migration Notes

- All file paths in includes and requires need to be updated
- Routes in .htaccess may need adjustment
- Any hardcoded paths in templates should be updated
- Test all functionality after migration
