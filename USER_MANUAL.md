# Magpie User Manual

Welcome to **Magpie**, a minimalist, self-contained social media platform. This manual will guide you through setting up, using, and managing your Magpie experience.

---

## 1. Introduction
Magpie is a Single Page Application (SPA) designed for simplicity and speed. It allows you to share short posts, interact with others through likes and replies, and stay updated with a real-time notification system.

---

## 2. Getting Started

### 2.1 System Requirements
- **PHP 8.1 or higher** with the `sqlite3` extension enabled.
- A modern web browser (Chrome 88+, Edge 88+, Firefox 89+, or Safari 15+).

### 2.2 Running Magpie Locally
1. Open your terminal in the project directory.
2. Start the built-in PHP server with **multi-threaded workers** (required for real-time updates):
   ```bash
   PHP_CLI_SERVER_WORKERS=5 php -S localhost:8000
   ```
   *Note: Using multiple workers prevents the server from freezing during real-time data streaming.*
3. Open your browser and navigate to `http://localhost:8000`.

### 2.3 Creating Your Account
- Click **Sign Up** on the landing page.
- Enter a unique username, your email address, and a secure password.
- **Remember Me:** When signing in, check the "Remember me" box to stay logged in even after closing your browser. Administrators can configure how long these sessions last.
- **Note:** The very first person to register on a new Magpie installation is automatically granted **Administrator** privileges.

### 2.4 Email Verification
To prevent spam and ensure account security, Magpie requires email verification before you can create posts.
- After signing up, a verification link will be sent to your email.
- If you don't see the email, check your spam folder or use the **Resend Verification** button in the top banner.
- Once verified, the restricted "unverified" banner will disappear, and you can start posting.

---

## 3. Using Magpie

### 3.1 The Feed
Magpie offers a seamless experience for viewing content:
- **Infinite Scroll:** The feed automatically loads new posts as you scroll to the bottom. No "Load More" button is required.
- **For You:** A global feed of all recent posts from across the platform.
- **Following:** A curated feed containing only posts from users you follow.
- **Liked:** A personal collection of every post you have liked.
- **New Post Alert:** If others create posts while you are viewing the feed, a "New posts available" banner will appear at the top.

### 3.2 Creating Posts
- Click the **Compose** button or use the text area at the top of the feed.
- **Rich Text:** URLs, `@mentions`, and `#hashtags` are automatically converted into interactive links.
- Posts are limited to **500 characters**.
- **Images:** You can attach up to **4 images** to a post. Magpie supports JPEG, PNG, GIF, and WebP. Images are automatically compressed on your device before upload to save data and speed up posting.
- You can post standard updates, **Replies** to existing threads, or **Quote Posts** (reposting someone else's content with your own commentary).
- To edit a post, click its context menu; you can modify the text and manage (add or remove) attached images.

### 3.3 Interactions
- **Liking:** Click the heart icon on any post to show your appreciation.
- **Following:** Visit a user's profile and click **Follow** to see their updates in your "Following" feed.
- **Threads:** Click on any post to open the **Thread View**. This shows the full conversation history, including the parent posts (ancestors) and all direct replies.

### 3.4 Notifications
The sidebar features a notification badge that updates in real-time. You will receive notifications when:
- Someone **replies** to your post.
- Someone **quotes** your post.
- Someone **follows** you.

---

## 4. Managing Your Profile

### 4.1 Customizing Your Identity
Navigate to your profile to update your public information:
- **Theme:** Toggle between **Light** and **Dark Mode** via the sidebar switch. Your preference is saved locally.
- **Display Name:** Choose how you want your name to appear to others.
- **Bio:** Share a short description of yourself.
- **Avatar:** Upload a custom profile picture (JPEG, PNG, GIF, or WebP up to 2MB) or choose from one of the built-in Magpie presets.

### 4.2 Security & Account Recovery
- **Password Reset:** If you forget your password, use the "Forgot Password" link on the login screen to receive a secure reset link via email.
- **Account Deletion:** If you wish to leave Magpie, you can permanently delete your account and all associated data (posts, likes, follows) from your profile settings. **This action is irreversible.**

---

## 5. Administrator Features
If you are an administrator, you have access to additional management tools:
- **User Management:** Edit any user's profile details.
- **Access Control:** Disable accounts to prevent login or delete users entirely.
- **Permissions:** Grant or revoke administrator status for other users.
- **Site Settings:** Configure system-wide defaults, such as the "Remember Me" session duration (default: 30 days).

---

## 6. Troubleshooting

- **Site Slowness:** If the application feels unresponsive, frozen, or extremely slow, ensure you started the server with multi-threaded workers: `PHP_CLI_SERVER_WORKERS=5 php -S localhost:8000`. The real-time notification system requires multiple threads to avoid blocking.
- **Database Issues:** If you see errors related to the database, ensure the `magpie.db` file is writable by the PHP process.
- **Email Not Sending:** If you are running Magpie locally and not receiving emails, ensure your `php.ini` is configured to use a mail server or a tool like [Mailpit](https://github.com/axllent/mailpit).
- **Updates Not Appearing:** Magpie refreshes content instantly via real-time alerts. If you see a "New posts available" banner, click it to refresh the feed.

---

## 7. Security Notes

Magpie is a minimalist demo application. For those hosting it, please be aware:

- **Server Configuration:** The application's database and technical files are in the web-accessible directory. Ensure your web server is configured to block direct access to `.db`, `.md`, and `.gitignore` files.
- **CSRF & Rates:** The system includes basic protections against Cross-Site Request Forgery and automated brute-force attempts on login.
- **Privacy:** As a public demo, consider all data posted to be visible and ensure your server environment is secure.
