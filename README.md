# Bloxer - Web Application Marketplace Platform

Bloxer is a comprehensive web application marketplace platform that combines the best features of Roblox (for web apps), Allegro (for projects), and CodePen/StackBlitz (for creators).

## 🚀 Core Concept

- **Developers** create applications online in a built-in Monaco editor with live preview
- **One-click publishing** to the marketplace
- **Users** browse, install, use, and rate applications
- **Complete ecosystem** with analytics, monetization, and community features

## 📋 Features Implemented

### Developer Side - Creator Dashboard
- ✅ **Workspace/Editor**: Monaco editor with live preview and hot reload
- ✅ **Projects**: Complete project management with file system
- ✅ **Publish Center**: One-click publishing with metadata, pricing, and categories
- ✅ **Analytics**: Views, downloads, users, and revenue tracking
- ✅ **Wallet**: Balance, withdrawals, and transaction history
- ✅ **Templates & Snippets**: Ready starters and boilerplates

### User Side - App Marketplace
- ✅ **App Feed**: Popular, new, recommended, and category browsing
- ✅ **App Pages**: Detailed descriptions, screenshots, live demos, ratings, reviews
- ✅ **User Profile**: Installed apps, usage history, favorites, and reviews
- ✅ **Install/Use System**: One-click installation and app runner

### Platform Features
- ✅ **Complete Authentication**: Login, registration, user types (user/developer)
- ✅ **Modern UI**: Glassmorphism design with responsive layout
- ✅ **Database Schema**: Complete relational database with all necessary tables
- ✅ **App Runner**: Full-featured app execution environment
- ✅ **Rating System**: 5-star ratings with reviews
- ✅ **Analytics Tracking**: Real-time usage statistics
- ✅ **Monetization**: Paid apps with wallet and withdrawal system

## 🗂️ File Structure

```
bloxer/
├── index.php                 # Entry point (redirects to login)
├── login.php                 # User authentication
├── register.php              # User registration
├── forgotpassword.php        # Password reset
├── dashboard.php             # Creator Dashboard (developers)
├── profile.php               # User Profile (regular users)
├── marketplace.php           # App marketplace
├── app.php                   # Individual app pages
├── run_app.php               # App runner/execution environment
├── app_actions.php           # App interaction handlers
├── mainlogincore.php         # Authentication core class
├── complete_database_schema.sql  # Complete database schema
├── style.css                 # Styling (glassmorphism theme)
└── README.md                 # This file
```

## 🛠️ Installation

### Prerequisites
- PHP 7.4+ with MySQLi extension
- MySQL/MariaDB database
- Web server (Apache/NginX)

### Setup Steps

1. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE bloxer_db;
   
   -- Import the complete schema
   mysql -u root -p bloxer_db < complete_database_schema.sql
   ```

2. **Configure Database**
   Edit `mainlogincore.php` and update database credentials:
   ```php
   protected $db_host = 'localhost';
   protected $db_user = 'root';
   protected $db_pass = '';
   protected $db_name = 'bloxer_db';
   ```

3. **Web Server Configuration**
   - Place files in web root (e.g., `/var/www/html/bloxer/` or `c:/xampp/htdocs/bloxer/`)
   - Ensure `style.css` is accessible
   - Configure URL rewriting if desired

4. **Access the Platform**
   - Navigate to `http://localhost/bloxer/`
   - Register as a developer or user
   - Start creating or browsing apps!

## 🎯 User Flows

### Developer Flow
1. **Register** as developer → Login to Creator Dashboard
2. **Create Project** in Workspace with Monaco editor
3. **Test** with live preview and hot reload
4. **Publish** to marketplace via Publish Center
5. **Track** performance in Analytics
6. **Earn** revenue and manage Wallet

### User Flow
1. **Register** as user → Login or browse marketplace
2. **Discover** apps in marketplace (browse, search, categories)
3. **Install** apps with one click
4. **Use** apps in dedicated runner environment
5. **Rate & Review** apps and manage profile

## 🎨 Design System

### UI Theme
- **Glassmorphism** with blur effects and transparency
- **Dark theme** with gradient accents
- **Responsive design** for mobile and desktop
- **Modern icons** using Font Awesome

### Color Palette
- Primary: Indigo/Purple gradients
- Success: Green tones
- Warning: Amber/Yellow tones
- Danger: Red tones
- Background: Deep black with subtle gradients

## 📊 Database Schema

### Core Tables
- `users` - User accounts and profiles
- `projects` - Developer projects
- `project_files` - Project file system
- `apps` - Published marketplace apps
- `categories` - App categories
- `user_apps` - User installations
- `app_reviews` - Ratings and reviews
- `developer_analytics` - Usage statistics
- `developer_wallet` - Developer earnings
- `wallet_transactions` - Financial transactions
- `templates` - Project templates

## 🔧 Technical Features

### Monaco Editor Integration
- Syntax highlighting for HTML, CSS, JavaScript
- Auto-save functionality
- Multi-file editing with tabs
- Live preview for HTML files

### App Runner System
- Sandboxed execution environment
- Fullscreen mode support
- Usage tracking
- Responsive app rendering

### Security Features
- Prepared statements for SQL injection prevention
- Session-based authentication
- Input validation and sanitization
- File access controls

## 🚀 Getting Started

### For Developers
1. Register and choose "Developer" account type
2. Access Creator Dashboard
3. Create your first project
4. Use Monaco editor to build your app
5. Test with live preview
6. Publish to marketplace

### For Users
1. Register or browse as guest
2. Explore the marketplace
3. Install apps that interest you
4. Run apps in dedicated environment
5. Leave ratings and reviews

## 🎯 Future Enhancements

### Potential Features
- Real-time collaboration in editor
- App versioning and updates
- Social features (following, sharing)
- Advanced analytics dashboard
- API for external integrations
- Mobile app companion
- App marketplace API
- Subscription-based apps
- Team collaboration features

## 📝 License

This project is developed as a demonstration of a full-stack web application marketplace platform.

## 🤝 Contributing

This is a complete implementation showcasing modern web development practices including:
- Clean PHP architecture with OOP
- Responsive frontend design
- Database design and optimization
- User experience considerations
- Security best practices

---

**Bloxer** - Where web applications come to life! 🚀
