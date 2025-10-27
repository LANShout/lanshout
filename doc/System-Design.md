System Design Documentation
# Overview
The LanShout system design documentation covers the system architecture and design decisions.

# Components
 - Laravel Application
 - PostgreSQL Database
 - Redis Cache

# Architecture
- Laravel Application: Handles user authentication, chat functionality, and API endpoints.
- PostgreSQL Database: Stores user data, chat messages, and system configurations.
- Redis Cache: Caches frequently accessed data for improved performance.

# Design Decisions
The Design Decisions section covers the design decisions made in the system design.
## Database Design
- Normalized tables for users, chat messages, and system configurations.
- Indexes on frequently queried columns for performance.
- Foreign key constraints for data integrity.

## Cache Design
- Redis cache for frequently accessed data.
- Expiration of cached data to reduce memory usage.
- Session caching for improved performance and restart safe user sessions.

## Real-Time Communication
- WebSockets with Laravel Echo for real-time communication between users.


## Roles and Permissions
- Roles and permissions are managed using Laravel's built-in authorization system.
- Roles are assigned to users based on their permissions.
- Permissions are defined in the `Permission` model.
- Permissions are assigned to roles in the `RolePermission` model.
- There are following Roles: 
  - Super Admin
  - Admin
  - Moderator
  - User
- There are Following Permissions:
  - View Chat
  - Send Chat Message
  - Delete Chat Message
  - Edit User
  - Delete User
  - Edit Chat Configuration
  - Edit System Configuration

## Content Moderation
- Content moderation is handled using a custom content filter.
- Content is filtered using the `ContentModeration` class.
- The Content Moderation Class uses several filters to remove unwanted content.
- Filters include:
  - Spam
  - Bad Words
  - URLs
  - Emails
  - Phone Numbers
  - IP Addresses
  - Passwords
- The Content Moderation Class can be extended to add more filters.
- The Content Moderation Class can be used to filter chat messages before the messages are saved to the database and send to the chat.

## Overlay/OBS Endpoint
- The Overlay/OBS endpoint is used by OBS as an webbrowser source
- The Overlay/OBS Endpoint is protected by a token in the URL
- The Overlay/OBS Endpoint is configured in the `SystemConfiguration` model
  - Amount of Lines
  - Font Size
  - Font Color
- The Overlay/OBS Endpoint can be used to display the chat in OBS
