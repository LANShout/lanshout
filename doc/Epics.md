# 📋 User Stories – LanShout

## Epic 1: Authentication & User Profiles

### User Registration & Login
- **As a Visitor** I want to register with username, email, and password so that I can create an account.
- **As a User** I want to log in with my credentials so that I can access the chat.
- **As a User** I want to reset my password via email so that I can recover access if I forget it.
- **As a User** I want to optionally enable 2FA so that my account is more secure.

### Profile Management
- **As a User** I want to update my email so that I can keep my contact details up to date.
- **As a User** I want to choose or change my chat name color so that I can personalize my appearance in the chat.
- **As a User** I want to change my password so that I can keep my account secure.
- **As a User** I want to delete my account so that I can stop participating.
- **As a User** I want to view my profile so that I can check my account details.
- **As a User** I want to change my Chat Color so that everyone can see my name in the color I choose.
---

## Epic 2: Chat Wall (Shoutbox)

### Posting & Viewing Messages
- **As a User** I want to post a message to the chat so that others can see it on the wall.
- **As a User** I want to see all new messages appear in real-time so that I don’t need to reload the page.
- **As a Viewer** I want to see usernames in consistent colors so that I can easily distinguish users.

### Stream/Overlay
- **As an Admin** I want an overlay endpoint (URL with token auth) so that I can embed the chat into OBS.
- **As an Admin** I want to configure overlay settings (show/hide timestamps, number of lines, colors) so that the display matches the event style.

---

## Epic 3: Administration

### User Management
- **As an Admin** I want to see a list of all users so that I can manage them.
- **As an Admin** I want to view user details (email, role, verification status) so that I can check their account.
- **As an Admin** I want to manually validate or reject users so that I can control who participates.
- **As an Admin** I want to assign or change roles (user, moderator, admin) so that I can delegate responsibilities.
- **As an Admin** I want to prevent self-demotion (I cannot remove my own admin role) so that we avoid lockouts.

### Chat Settings
- **As an Admin** I want to enable slow-mode (e.g. 1 message per 10s) so that spam is reduced.
- **As an Admin** I want to configure word filters so that inappropriate messages are blocked.
- **As an Admin** I want to clear the chat history so that the wall can be reset during events.

### Audit Logging
- **As an Admin** I want all moderation and admin actions logged so that I can trace changes and decisions.

---

## Epic 4: Moderation

### Moderator Tools
- **As a Moderator** I want to see the moderation menu when opening the chat so that I can access tools.
- **As a Moderator** I want to see a list of all active moderators so that I can review decisions.
- **As a Moderator** I want to see a list of active users in the chat so that I know who is online.
- **As a Moderator** I want to kick a user so that they are removed temporarily from the chat session.
- **As a Moderator** I want to ban a user so that they cannot return to the chat.
- **As a Moderator** I want to timeout a user so that they are blocked from posting for a defined period.
- **As a Moderator** I want to delete specific messages so that I can remove inappropriate content.
- **As a Moderator** I want to provide a reason (e.g. spam, insult) when moderating so that admins can review later.

---

## Epic 5: API & Integrations

### Message API
- **As a Developer** I want to use an API token to post messages programmatically so that external systems can push messages to the wall.
- **As a Developer** I want to retrieve messages via API so that I can integrate them into other tools.

### User API
- **As a Developer** I want to retrieve a list of users via API so that I can integrate user data.
- **As a Developer** I want to fetch user details via API so that I can show account-related information in external apps.

### API Token Management
- **As an Admin** I want to create, view, and revoke API tokens so that I control integrations securely.

---

## Epic 6: Email Communication

### Mail Features
- **As a User** I want to receive a verification email so that my email is confirmed.
- **As a User** I want to receive a password reset email so that I can recover my account.
- **As a User** I want to receive a 2FA email if I activate mail-based 2FA so that I can confirm logins securely.
- **As an Admin** I want the option to send announcements or newsletters so that I can communicate with all users.  
