# Vulnerability Assessment - Phase 1: Reconnaissance

## Application Information
- **Framework**: Laravel 11.x
- **Frontend**: Filament v3 (Livewire)
- **Database**: SQLite
- **Web Server**: PHP Built-in Server
- **Authentication**: Session-based
- **Encryption**: AES-256

## Technology Stack Analysis
```
Frontend: Livewire + Alpine.js + Tailwind CSS
Backend: Laravel 11 + Eloquent ORM
Database: SQLite (file-based)
Session: File-based sessions
Cache: File-based cache
```

## Attack Surface Mapping

### 1. Authentication Endpoints
- `/adminn/login` - Login form
- `/adminn/logout` - Logout
- `/livewire/update` - Livewire AJAX endpoints

### 2. Main Application Endpoints
- `/adminn` - Dashboard
- `/adminn/password-entries` - Password CRUD
- `/adminn/categories` - Category management
- `/adminn/shared-passwords` - Password sharing
- `/adminn/export-import` - Data export/import
- `/adminn/audit-logs` - Audit logging (admin only)
- `/adminn/security-status` - Security monitoring (admin only)

### 3. File Upload Endpoints
- Export/Import functionality
- Profile picture uploads (if any)

### 4. API Endpoints
- Livewire components (AJAX)
- Real-time updates

## User Roles & Permissions
- **Admin**: Full access to all features + system monitoring
- **User**: Limited to own data + sharing features

## Data Flow Analysis
```
User Input → Validation → Business Logic → Database → Encryption → Storage
```

## Security Controls Identified
- CSRF protection (Laravel built-in)
- SQL injection protection (Eloquent ORM)
- XSS protection (Blade templating)
- Rate limiting
- Account lockout
- Audit logging
- Data encryption (AES-256)
- Role-based access control
