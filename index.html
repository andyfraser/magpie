<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Magpie</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ── Auth modal ───────────────────────────────────────── -->
<div class="modal-overlay hidden" id="auth-modal">
  <div class="modal">
    <div class="modal-logo">
      <svg viewBox="0 0 36 36" fill="none">
        <ellipse cx="18" cy="20" rx="10" ry="8" fill="#0f1419"/>
        <ellipse cx="18" cy="20" rx="5"  ry="7" fill="white"/>
        <circle  cx="24" cy="13" r="6"   fill="#0f1419"/>
        <circle  cx="26" cy="12" r="2"   fill="white"/>
        <circle  cx="27" cy="11.5" r="1" fill="#0f1419"/>
        <polygon points="30,13 36,12 30,15" fill="#f59e0b"/>
        <polygon points="8,22 2,28 10,26"   fill="#0f1419"/>
        <polygon points="8,22 4,32 12,27"   fill="#0f1419"/>
      </svg>
    </div>
    <!-- Login -->
    <div id="login-view">
      <h2>Sign in to Magpie</h2>
      <div class="form-group">
        <label for="login-username">Username or Email</label>
        <input id="login-username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false">
      </div>
      <div class="form-group">
        <label for="login-password">Password</label>
        <input id="login-password" type="password" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="toggle-label">
          <input type="checkbox" id="login-remember-me" checked>
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
          <span>Remember me</span>
        </label>
      </div>
      <div class="form-error" id="login-error"></div>
      <button class="btn btn-primary" id="login-btn">Sign in</button>
      <p class="modal-switch"><a id="to-forgot">Forgot password?</a></p>
      <p class="modal-switch">Don't have an account? <a id="to-signup">Sign up</a></p>
    </div>
    <!-- Signup -->
    <div id="signup-view" style="display:none">
      <h2>Create your account</h2>
      <div class="form-group">
        <label for="signup-username">Username</label>
        <input id="signup-username" type="text" autocomplete="username" autocapitalize="none"
               spellcheck="false" placeholder="letters, numbers, underscores">
      </div>
      <div class="form-group">
        <label for="signup-email">Email</label>
        <input id="signup-email" type="email" autocomplete="email" placeholder="you@example.com">
      </div>
      <div class="form-group">
        <label for="signup-password">Password</label>
        <input id="signup-password" type="password" autocomplete="new-password" placeholder="at least 6 characters">
      </div>
      <div class="form-group">
        <label for="signup-confirm">Confirm password</label>
        <input id="signup-confirm" type="password" autocomplete="new-password">
      </div>
      <div class="form-error" id="signup-error"></div>
      <button class="btn btn-primary" id="signup-btn">Create account</button>
      <p class="modal-switch">Already have an account? <a id="to-login">Sign in</a></p>
    </div>
    <!-- Forgot Password -->
    <div id="forgot-view" style="display:none">
      <h2>Find your Magpie account</h2>
      <p style="margin-bottom:16px; font-size:14px; color:var(--text-sub);">Enter the email associated with your account to change your password.</p>
      <div class="form-group">
        <label for="forgot-email">Email</label>
        <input id="forgot-email" type="email" autocomplete="email">
      </div>
      <div class="form-error" id="forgot-error"></div>
      <button class="btn btn-primary" id="forgot-btn">Send reset link</button>
      <p class="modal-switch"><a id="forgot-to-login">Back to login</a></p>
    </div>
    <!-- Reset Password -->
    <div id="reset-view" style="display:none">
      <h2>Reset your password</h2>
      <div class="form-group">
        <label for="reset-password">New password</label>
        <input id="reset-password" type="password" autocomplete="new-password" placeholder="at least 6 characters">
      </div>
      <div class="form-group">
        <label for="reset-confirm">Confirm new password</label>
        <input id="reset-confirm" type="password" autocomplete="new-password">
      </div>
      <div class="form-error" id="reset-error"></div>
      <button class="btn btn-primary" id="reset-btn">Reset password</button>
    </div>
  </div>
</div>

<!-- ── Admin edit modal ──────────────────────────────────── -->
<div class="modal-overlay hidden" id="admin-edit-modal">
  <div class="modal">
    <h2 id="admin-edit-title">Edit User</h2>
    <input type="hidden" id="admin-edit-uid">
    <div class="form-group">
      <label for="admin-edit-display-name">Display name</label>
      <input id="admin-edit-display-name" type="text" maxlength="50">
    </div>
    <div class="form-group">
      <label for="admin-edit-bio">Bio</label>
      <textarea id="admin-edit-bio" maxlength="160" rows="3"></textarea>
    </div>
    <div class="form-group">
      <label class="checkbox-label">
        <input type="checkbox" id="admin-edit-is-admin">
        <span>Admin privileges</span>
      </label>
    </div>
    <div class="form-group">
      <label class="checkbox-label">
        <input type="checkbox" id="admin-edit-disabled">
        <span>Disabled</span>
      </label>
    </div>
    <div class="form-error" id="admin-edit-error"></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" id="admin-edit-cancel">Cancel</button>
      <button class="btn btn-primary" id="admin-edit-save">Save changes</button>
    </div>
  </div>
</div>

<!-- ── Reply / Quote compose modal ──────────────────────── -->
<div class="modal-overlay hidden" id="compose-modal">
  <div class="modal compose-modal">
    <div id="compose-modal-context"></div>
    <div class="compose-inner" style="padding-top:12px">
      <div class="avatar" id="compose-modal-avatar">?</div>
      <div class="compose-body">
        <div id="compose-modal-label" class="reply-label" style="display:none"></div>
        <textarea id="compose-modal-input" placeholder="Post your reply" rows="3"></textarea>
        <div id="compose-modal-quote-wrap" style="display:none"></div>
        <div class="compose-images-preview hidden" id="compose-modal-image-preview"></div>
        <div class="compose-footer">
          <button class="compose-attach-btn" id="compose-modal-attach-btn" title="Add images">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </button>
          <input type="file" id="compose-modal-image-file" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none">
          <div class="char-ring-wrap" style="margin-left:auto">
            <svg class="char-ring" id="compose-modal-ring" viewBox="0 0 22 22">
              <circle class="track" cx="11" cy="11" r="9"/>
              <circle class="fill"  cx="11" cy="11" r="9" stroke-dashoffset="0" id="compose-modal-ring-fill"/>
            </svg>
            <span class="char-remaining" id="compose-modal-remaining"></span>
          </div>
          <button class="btn btn-ghost" id="compose-modal-cancel">Cancel</button>
          <button class="btn btn-primary" id="compose-modal-submit" disabled>Post</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Thread modal ──────────────────────────────────────── -->
<div class="modal-overlay hidden" id="thread-modal">
  <div class="thread-panel">
    <div class="thread-panel-header">
      <button class="thread-back-btn" id="thread-close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      </button>
      <span class="thread-panel-title">Thread</span>
    </div>
    <div class="thread-panel-body" id="thread-content"></div>
  </div>
</div>

<!-- ── App ──────────────────────────────────────────────── -->
<div class="layout">

  <nav class="sidebar">
    <a class="logo" href="#">
      <svg class="logo-icon" viewBox="0 0 36 36" fill="none">
        <ellipse cx="18" cy="20" rx="10" ry="8" fill="#0f1419"/>
        <ellipse cx="18" cy="20" rx="5"  ry="7" fill="white"/>
        <circle  cx="24" cy="13" r="6"   fill="#0f1419"/>
        <circle  cx="26" cy="12" r="2"   fill="white"/>
        <circle  cx="27" cy="11.5" r="1" fill="#0f1419"/>
        <polygon points="30,13 36,12 30,15" fill="#f59e0b"/>
        <polygon points="8,22 2,28 10,26"   fill="#0f1419"/>
        <polygon points="8,22 4,32 12,27"   fill="#0f1419"/>
      </svg>
      <span class="logo-text">Magpie</span>
    </a>

    <a class="nav-link active" data-view="home" href="#">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" fill="none"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      <span>Home</span>
    </a>

    <a class="nav-link" data-view="notifications" href="#" id="nav-notifications" style="display:none">
      <div class="nav-icon-wrap">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        <span class="notif-badge hidden" id="notif-badge"></span>
      </div>
      <span>Notifications</span>
    </a>

    <a class="nav-link" data-view="liked" href="#" id="nav-liked" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      <span>Liked</span>
    </a>

    <a class="nav-link" data-view="following" href="#" id="nav-following" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" fill="none"/><path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      <span>Following</span>
    </a>

    <a class="nav-link" data-view="profile" href="#" id="nav-profile" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      <span>Profile</span>
    </a>

    <a class="nav-link" data-view="admin" href="#" id="nav-admin" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
      <span>Admin</span>
    </a>

    <button class="theme-toggle" id="theme-toggle">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <span>Dark Mode</span>
    </button>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-user" id="sidebar-user" style="display:none">
      <div class="avatar" id="sidebar-avatar">?</div>
      <div class="sidebar-user-info">
        <div class="sidebar-username" id="sidebar-display-name"></div>
        <div class="sidebar-handle"   id="sidebar-handle"></div>
      </div>
    </div>
    <button class="logout-btn" id="logout-btn" style="display:none">Log out</button>
  </nav>

  <main class="main">

    <!-- ── Verification Banner ────────────────────────── -->
    <div id="verify-banner" class="verify-banner" style="display:none">
      <div class="verify-banner-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <span>Please verify your email address.</span>
        <button id="resend-verify-btn" class="btn-resend">Resend email</button>
      </div>
    </div>

    <!-- ── Home view ──────────────────────────────────── -->
    <div id="view-home" class="view">
      <div class="view-header">
        <div class="view-title">Home</div>
        <div class="feed-tabs" id="feed-tabs">
          <button class="feed-tab active" data-tab="for-you">For you</button>
          <button class="feed-tab" data-tab="following">Following</button>
        </div>
      </div>

      <section class="compose" id="compose" style="display:none">
        <div class="compose-inner">
          <div class="avatar" id="compose-avatar">?</div>
          <div class="compose-body">
            <textarea id="post-input" placeholder="What's happening?" rows="3"></textarea>
            <div class="compose-images-preview hidden" id="compose-image-preview"></div>
            <div class="compose-footer">
              <button class="compose-attach-btn" id="compose-attach-btn" title="Add images">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              </button>
              <input type="file" id="compose-image-file" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none">
              <div class="compose-footer-right">
                <div class="char-ring-wrap" id="char-ring-wrap" style="display:none">
                  <svg class="char-ring" id="char-ring" viewBox="0 0 22 22">
                    <circle class="track" cx="11" cy="11" r="9"/>
                    <circle class="fill"  cx="11" cy="11" r="9" stroke-dashoffset="0" id="char-ring-fill"/>
                  </svg>
                  <span class="char-remaining" id="char-remaining"></span>
                </div>
                <button class="btn btn-ghost btn-sm" id="compose-cancel-btn" style="display:none">Cancel</button>
                <button class="btn btn-primary" id="submit-btn" disabled>Post</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div id="feed"></div>
      <div id="load-more-wrap" hidden>
        <button class="btn btn-ghost" id="load-more-btn">Load more</button>
        <div id="feed-sentinel" style="height:20px;"></div>
      </div>
    </div>

    <!-- ── Notifications view ─────────────────────────── -->
    <div id="view-notifications" class="view" style="display:none">
      <div class="view-header">
        <div class="view-title">Notifications</div>
      </div>
      <div id="notifications-list"></div>
    </div>

    <!-- ── Liked view ───────────────────────────────────── -->
    <div id="view-liked" class="view" style="display:none">
      <div class="view-header">
        <div class="view-title">Liked</div>
        <div class="feed-tabs">
          <button class="feed-tab active">Posts</button>
        </div>
      </div>
      <div id="liked-feed"></div>
      <div id="liked-load-more-wrap" hidden>
        <button class="btn btn-ghost" id="liked-load-more-btn">Load more</button>
      </div>
    </div>

    <!-- ── Following view ───────────────────────────────── -->
    <div id="view-following" class="view" style="display:none">
      <div class="view-header">
        <div class="view-title">Following</div>
        <div class="feed-tabs" id="following-tabs">
          <button class="feed-tab active" data-tab="following">Following</button>
          <button class="feed-tab" data-tab="search">Search</button>
        </div>
      </div>
      <div class="search-bar-wrap" id="user-search-wrap" style="display:none; padding: 12px 16px; border-bottom: 1px solid var(--border);">
        <input type="text" id="user-search-input" placeholder="Search users..." style="width:100%; padding: 8px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
      </div>
      <div id="users-list"></div>
    </div>

    <!-- ── Profile view ───────────────────────────────── -->
    <div id="view-profile" class="view" style="display:none">
      <div class="view-header">
        <div class="view-title">Profile</div>
        <div class="feed-tabs">
          <button class="feed-tab active">Edit Profile</button>
        </div>
      </div>

      <div class="profile-editor">

        <div class="avatar-section">
          <div class="avatar-upload" id="avatar-upload" title="Change photo">
            <div class="avatar avatar-lg" id="profile-avatar">?</div>
            <div class="avatar-upload-overlay">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
            </div>
          </div>

          <div class="avatar-picker" id="avatar-picker">
            <p class="avatar-picker-label">Choose a magpie</p>
            <div class="avatar-presets-grid">
              <img src="/uploads/avatars/presets/magpie_01.svg" class="avatar-preset" data-preset="magpie_01.svg" title="Classic">
              <img src="/uploads/avatars/presets/magpie_02.svg" class="avatar-preset" data-preset="magpie_02.svg" title="Cool Shades">
              <img src="/uploads/avatars/presets/magpie_03.svg" class="avatar-preset" data-preset="magpie_03.svg" title="Bow Tie">
              <img src="/uploads/avatars/presets/magpie_04.svg" class="avatar-preset" data-preset="magpie_04.svg" title="Crown">
              <img src="/uploads/avatars/presets/magpie_05.svg" class="avatar-preset" data-preset="magpie_05.svg" title="Party Hat">
              <img src="/uploads/avatars/presets/magpie_06.svg" class="avatar-preset" data-preset="magpie_06.svg" title="Pirate">
              <img src="/uploads/avatars/presets/magpie_07.svg" class="avatar-preset" data-preset="magpie_07.svg" title="Winking">
              <img src="/uploads/avatars/presets/magpie_08.svg" class="avatar-preset" data-preset="magpie_08.svg" title="Sleepy">
              <img src="/uploads/avatars/presets/magpie_09.svg" class="avatar-preset" data-preset="magpie_09.svg" title="Surprised">
              <img src="/uploads/avatars/presets/magpie_10.svg" class="avatar-preset" data-preset="magpie_10.svg" title="Nerd">
            </div>
            <div class="avatar-picker-upload-row">
              <span class="avatar-picker-or">or</span>
              <button class="btn btn-ghost btn-sm" id="avatar-upload-btn">Upload your own</button>
              <input type="file" id="avatar-file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="pf-display-name">Display name</label>
          <input id="pf-display-name" type="text" maxlength="50" placeholder="Your name">
          <div class="field-hint">Shown instead of your username. Max 50 characters.</div>
        </div>

        <div class="form-group">
          <label>Username</label>
          <input id="pf-username" type="text" disabled class="input-disabled">
          <div class="field-hint">Usernames cannot be changed.</div>
        </div>

        <div class="form-group">
          <label for="pf-email">Email address</label>
          <input id="pf-email" type="email" maxlength="254" placeholder="you@example.com">
          <div class="field-hint">Changing your email will require re-verification.</div>
        </div>

        <div class="form-group">
          <label for="pf-bio">Bio</label>
          <textarea id="pf-bio" maxlength="160" rows="3" placeholder="Tell people about yourself"></textarea>
          <div class="field-hint"><span id="bio-chars">160</span> characters remaining</div>
        </div>

        <div class="form-error" id="profile-error"></div>
        <button class="btn btn-primary" id="save-profile-btn">Save changes</button>

        <div class="danger-zone">
          <h3>Danger zone</h3>
          <p>Permanently delete your account and all your posts. This action cannot be undone.</p>
          <button class="btn btn-danger" id="delete-account-btn">Delete account</button>
        </div>

      </div>
    </div>

    <!-- ── Admin view ─────────────────────────────────── -->
    <div id="view-admin" class="view" style="display:none">
      <div class="view-header">
        <div class="view-title">Admin</div>
        <div class="feed-tabs" id="admin-tabs">
          <button class="feed-tab active" data-tab="users">Users</button>
          <button class="feed-tab" data-tab="settings">Settings</button>
        </div>
      </div>
      <div id="admin-user-list"></div>
      <div id="admin-settings-panel" style="display:none; padding:24px 16px; max-width:480px;">
        <h3 style="margin:0 0 20px; font-size:16px;">Site Settings</h3>
        <div class="form-group">
          <label for="admin-rmb-days">Remember me duration (days)</label>
          <input id="admin-rmb-days" type="number" min="1" max="365" value="30" style="width:120px;">
          <div class="field-hint">How long a "remember me" session lasts after login.</div>
        </div>
        <div class="form-error" id="admin-settings-error"></div>
        <button class="btn btn-primary" id="admin-settings-save">Save settings</button>
      </div>
    </div>

  </main>

  <nav class="mobile-nav">
    <a class="nav-link active" data-view="home" href="#">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" fill="none"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </a>
    <a class="nav-link" data-view="notifications" href="#" id="mobile-nav-notifications" style="display:none">
      <div class="nav-icon-wrap">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        <span class="notif-badge hidden" id="mobile-notif-badge"></span>
      </div>
    </a>
    <a class="nav-link" data-view="liked" href="#" id="mobile-nav-liked" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </a>
    <a class="nav-link" data-view="following" href="#" id="mobile-nav-following" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" fill="none"/><path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </a>
    <a class="nav-link" data-view="profile" href="#" id="mobile-nav-profile" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/></svg>
    </a>
    </nav>

    <button class="mobile-compose-fab" id="mobile-compose-fab" style="display:none">
    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
    </button>

    </div>


<div class="toast" id="toast"></div>

<!-- ── Lightbox ──────────────────────────────────────────── -->
<div class="lightbox hidden" id="lightbox">
  <button class="lightbox-close" id="lightbox-close" title="Close">&times;</button>
  <button class="lightbox-prev" id="lightbox-prev" title="Previous">&#8249;</button>
  <img class="lightbox-img" id="lightbox-img" src="" alt="">
  <button class="lightbox-next" id="lightbox-next" title="Next">&#8250;</button>
  <span class="lightbox-counter" id="lightbox-counter"></span>
</div>

<script src="js/app.js"></script>
</body>
</html>
