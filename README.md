# Magpie

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-777bb4?logo=php)
![Database](https://img.shields.io/badge/database-SQLite3-003b57?logo=sqlite)
![License](https://img.shields.io/badge/license-open%20source-green)
![Dependencies](https://img.shields.io/badge/dependencies-none-brightgreen)

Magpie is a minimalist, Twitter-like social platform designed as a Single Page Application (SPA) with zero external dependencies. It's built using plain PHP for the backend and vanilla JavaScript for the frontend.

## Features

- **Micro-blogging:** Share thoughts in posts with up to 500 characters and 4 images (JPEG, PNG, GIF, WebP — max 5 MB).
- **Replies & Threads:** Reply to any post; thread view shows the full ancestor chain and replies.
- **Quote Posts:** Quote-repost with added commentary.
- **Likes:** Like and unlike posts; view all your liked posts in a dedicated feed.
- **Follow System:** Follow users; filter your home feed to show only people you follow.
- **Authentication:** Secure sign-in with a "Remember Me" option (configurable duration).
- **Email Verification:** Verify your email address (required before you can post).
- **Password Reset:** Recover access to your account with secure reset links.
- **Notifications:** Get notified when someone replies to, quotes, or follows you; unread badge in the sidebar.
- **User Discovery:** Search users by username or display name.
- **Custom Profiles:** Display name, bio, and avatar (JPEG, PNG, GIF, WebP — max 2 MB). Choose from built-in preset avatars or upload your own.
- **Account Deletion:** Users can permanently delete their own account and all associated data.
- **Admin Panel:** Edit user profiles, toggle admin privileges, disable accounts, and delete users.
- **Responsive:** Works on mobile and desktop.

## Tech Stack

- **Backend:** PHP 8.1+ (no framework)
- **Database:** SQLite3 (schema auto-created and versioned)
- **Frontend:** Vanilla JavaScript (ES6+, async/await, fetch)
- **Styling:** CSS3 with CSS Variables

## Getting Started

No build steps, no `npm install`, no `composer install`.

### Prerequisites

PHP 8.1 or higher with the sqlite3 extension.

### Running the App

1. Clone or download the repository.
2. **Local Email (Optional):** To test verification and password resets, use [Mailpit](https://github.com/axllent/mailpit):
   ```bash
   brew install mailpit
   mailpit
   ```
   Configure your `php.ini` (use `php --ini` to find it) to point to Mailpit:
   ```ini
   # For Apple Silicon (M1/M2/M3) Macs
   sendmail_path = /opt/homebrew/bin/mailpit sendmail
   ```
3. Start the PHP built-in server:
   ```bash
   php -S localhost:8000
   ```
4. Open `http://localhost:8000` in your browser.

The first person to register is automatically granted administrator privileges.

## Project Structure

- `index.php` — HTML shell; serves the SPA markup and all modals
- `api.php` — Entire backend: SQLite init, schema migration, and all API endpoints
- `js/app.js` — Entire frontend: state management, routing, API calls, DOM rendering
- `css/style.css` — All application styles
- `logo.png` — App logo (used as favicon)
- `uploads/avatars/` — User avatar storage (created automatically)
- `uploads/avatars/presets/` — Built-in SVG preset avatars
- `uploads/posts/` — Post image storage (created automatically)
- `magpie.db` — SQLite database (auto-generated on first request)

## License

This project is open source and free to use.

## Browser Compatibility

Magpie uses modern JavaScript (ES2020+) and CSS features. To ensure all functionality (including optional chaining) and layout features (like Flexbox gap and aspect-ratio) work correctly, the following minimum browser versions are recommended:

| Browser | Minimum Version | Release Date |
| :--- | :--- | :--- |
| **Google Chrome** | **88** | Jan 2021 |
| **Microsoft Edge** | **88** | Jan 2021 |
| **Mozilla Firefox** | **89** | June 2021 |
| **Apple Safari** | **15** | Sept 2021 |
