# Magpie

Magpie is a minimalist, Twitter-like social platform designed as a Single Page Application (SPA) with zero external dependencies. It's built using plain PHP for the backend and vanilla JavaScript for the frontend.

## Features

- **Standardized UI:** Clean, minimalist design with a consistent layout across all views.
- **Micro-blogging:** Share your thoughts in short posts with character limits.
- **Interactions:** Like posts, follow users, and see their posts in your following feed.
- **User Discovery:** A dedicated section to search for users and manage who you follow.
- **Custom Profiles:** Personalize your account with display names, bios, and profile pictures.
- **Admin Panel:** Powerful tools for user management and content control.
- **Responsive:** Designed to work smoothly on mobile and desktop devices.

## Tech Stack

- **Backend:** PHP 8.0+
- **Database:** SQLite3
- **Frontend:** Vanilla JavaScript (ES6+)
- **Styling:** CSS3 with CSS Variables

## Getting Started

Magpie is designed to be extremely easy to run. No build steps, no `npm install`, and no `composer install`.

### Prerequisites

- PHP 8.0 or higher with the `sqlite3` extension.

### Running the App

1. Clone or download the repository.
2. Start the PHP built-in server from the root directory:

```bash
php -S localhost:8000
```

3. Open your browser and navigate to `http://localhost:8000`.

The first person to register will automatically be granted administrator privileges.

## Project Structure

- `index.php`: The main entry point and single HTML page.
- `api.php`: The backend API handling all logic and database operations.
- `js/app.js`: The frontend SPA logic, routing, and rendering.
- `css/style.css`: All application styles.
- `uploads/`: Directory for user profile pictures.
- `magpie.db`: The SQLite database (auto-generated).

## License

This project is open source and free to use.
