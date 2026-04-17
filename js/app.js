'use strict';

const MAX_CHARS = 500;
const CIRC      = 2 * Math.PI * 9; // r=9 circumference ≈ 56.55

// ── DOM refs ──────────────────────────────────────────────
const authModal      = document.getElementById('auth-modal');
const loginView      = document.getElementById('login-view');
const signupView     = document.getElementById('signup-view');
const loginUsernameI = document.getElementById('login-username');
const loginPasswordI = document.getElementById('login-password');
const loginError     = document.getElementById('login-error');
const loginRememberMe = document.getElementById('login-remember-me');
const loginBtn       = document.getElementById('login-btn');
const signupUsername = document.getElementById('signup-username');
const signupEmail    = document.getElementById('signup-email');
const signupPassword = document.getElementById('signup-password');
const signupConfirm  = document.getElementById('signup-confirm');
const signupError    = document.getElementById('signup-error');
const signupBtn      = document.getElementById('signup-btn');
const toSignup       = document.getElementById('to-signup');
const toLogin        = document.getElementById('to-login');

const forgotView     = document.getElementById('forgot-view');
const forgotEmail    = document.getElementById('forgot-email');
const forgotError    = document.getElementById('forgot-error');
const forgotBtn      = document.getElementById('forgot-btn');
const toForgot       = document.getElementById('to-forgot');
const forgotToLogin  = document.getElementById('forgot-to-login');

const resetView      = document.getElementById('reset-view');
const resetPassword  = document.getElementById('reset-password');
const resetConfirm   = document.getElementById('reset-confirm');
const resetError     = document.getElementById('reset-error');
const resetBtn       = document.getElementById('reset-btn');

const verifyBanner   = document.getElementById('verify-banner');
const resendVerifyBtn = document.getElementById('resend-verify-btn');

const composeEl      = document.getElementById('compose');
const postInput      = document.getElementById('post-input');
const charRingWrap   = document.getElementById('char-ring-wrap');
const charRing       = document.getElementById('char-ring');
const charRingFill   = document.getElementById('char-ring-fill');
const charRemaining  = document.getElementById('char-remaining');
const submitBtn      = document.getElementById('submit-btn');
const cancelBtn      = document.getElementById('compose-cancel-btn');
const composeAvatar  = document.getElementById('compose-avatar');

const sidebarUser    = document.getElementById('sidebar-user');
const sidebarAvatar  = document.getElementById('sidebar-avatar');
const sidebarDN      = document.getElementById('sidebar-display-name');
const sidebarHandle  = document.getElementById('sidebar-handle');
const logoutBtn      = document.getElementById('logout-btn');
const navProfile     = document.getElementById('nav-profile');
const navLiked       = document.getElementById('nav-liked');
const navFollowing   = document.getElementById('nav-following');
const navAdmin       = document.getElementById('nav-admin');
const navNotifications = document.getElementById('nav-notifications');
const notifBadge     = document.getElementById('notif-badge');

const feed           = document.getElementById('feed');
const loadMoreWrap   = document.getElementById('load-more-wrap');
const loadMoreBtn    = document.getElementById('load-more-btn');
const themeToggle    = document.getElementById('theme-toggle');
const feedSentinel   = document.getElementById('feed-sentinel');
const toastEl        = document.getElementById('toast');

// Infinite scroll observer
const feedObserver = new IntersectionObserver((entries) => {
  if (entries[0].isIntersecting && !loadMoreBtn.disabled && !loadMoreWrap.hidden) {
    loadPosts(currentPage + 1, false);
  }
}, { threshold: 0.1 });

if (feedSentinel) feedObserver.observe(feedSentinel);

// Initialize theme
if (localStorage.getItem('magpie-theme') === 'dark') {
  document.body.classList.add('dark-mode');
  if (themeToggle) {
    themeToggle.querySelector('span').textContent = 'Light Mode';
    themeToggle.querySelector('svg').innerHTML = '<path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM2 12h2M20 12h2M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M17.66 4.93l-1.41 1.41M4.93 17.66l-1.41 1.41"/>';
  }
}

if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('magpie-theme', isDark ? 'dark' : 'light');
    themeToggle.querySelector('span').textContent = isDark ? 'Light Mode' : 'Dark Mode';
    themeToggle.querySelector('svg').innerHTML = isDark
      ? '<path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM2 12h2M20 12h2M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M17.66 4.93l-1.41 1.41M4.93 17.66l-1.41 1.41"/>'
      : '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
  });
}

// Following/People view
const followingTabs      = document.getElementById('following-tabs');
const userSearchWrap     = document.getElementById('user-search-wrap');
const userSearchInput    = document.getElementById('user-search-input');
const usersList          = document.getElementById('users-list');
let currentFollowingTab  = 'following';

// Profile view
const profileAvatar  = document.getElementById('profile-avatar');
const avatarUpload   = document.getElementById('avatar-upload');
const avatarPicker   = document.getElementById('avatar-picker');
const avatarFile     = document.getElementById('avatar-file');
const avatarUploadBtn = document.getElementById('avatar-upload-btn');
const pfDisplayName  = document.getElementById('pf-display-name');
const pfUsername     = document.getElementById('pf-username');
const pfEmail        = document.getElementById('pf-email');
const pfBio          = document.getElementById('pf-bio');
const bioChars       = document.getElementById('bio-chars');
const profileError   = document.getElementById('profile-error');
const saveProfileBtn = document.getElementById('save-profile-btn');
const deleteAcctBtn  = document.getElementById('delete-account-btn');

// Admin modal
const adminEditModal = document.getElementById('admin-edit-modal');
const adminEditTitle = document.getElementById('admin-edit-title');
const adminEditUid   = document.getElementById('admin-edit-uid');
const adminEditDN    = document.getElementById('admin-edit-display-name');
const adminEditBio   = document.getElementById('admin-edit-bio');
const adminEditAdmin = document.getElementById('admin-edit-is-admin');
const adminEditDis   = document.getElementById('admin-edit-disabled');
const adminEditErr   = document.getElementById('admin-edit-error');
const adminEditCancel= document.getElementById('admin-edit-cancel');
const adminEditSave  = document.getElementById('admin-edit-save');

const adminTabs          = document.getElementById('admin-tabs');
const adminSettingsPanel = document.getElementById('admin-settings-panel');
const adminRmbDays       = document.getElementById('admin-rmb-days');
const adminSettingsErr   = document.getElementById('admin-settings-error');
const adminSettingsSave  = document.getElementById('admin-settings-save');

// Thread modal
const threadModal    = document.getElementById('thread-modal');
const threadContent  = document.getElementById('thread-content');
const threadClose    = document.getElementById('thread-close');

// Compose modal (reply / quote)
const composeModal            = document.getElementById('compose-modal');
const composeModalCtx         = document.getElementById('compose-modal-context');
const composeModalAvatar      = document.getElementById('compose-modal-avatar');
const composeModalLabel       = document.getElementById('compose-modal-label');
const composeModalInput       = document.getElementById('compose-modal-input');
const composeModalQuote       = document.getElementById('compose-modal-quote-wrap');
const composeModalRing        = document.getElementById('compose-modal-ring');
const composeModalFill        = document.getElementById('compose-modal-ring-fill');
const composeModalLeft        = document.getElementById('compose-modal-remaining');
const composeModalCancel      = document.getElementById('compose-modal-cancel');
const composeModalSubmit      = document.getElementById('compose-modal-submit');
const composeModalAttachBtn   = document.getElementById('compose-modal-attach-btn');
const composeModalImageFile   = document.getElementById('compose-modal-image-file');
const composeModalImagePreview= document.getElementById('compose-modal-image-preview');

// Home compose image
const composeAttachBtn   = document.getElementById('compose-attach-btn');
const composeImageFile   = document.getElementById('compose-image-file');
const composeImagePreview= document.getElementById('compose-image-preview');

// Lightbox
const lightboxEl      = document.getElementById('lightbox');
const lightboxImg     = document.getElementById('lightbox-img');
const lightboxClose   = document.getElementById('lightbox-close');
const lightboxPrev    = document.getElementById('lightbox-prev');
const lightboxNext    = document.getElementById('lightbox-next');
const lightboxCounter = document.getElementById('lightbox-counter');

// ── State ─────────────────────────────────────────────────
let currentUser  = null;
let csrfToken    = null;
let currentPage  = 1;
let totalPages   = 1;
let isSubmitting = false;
let currentFeed  = 'for-you';
let currentQuery = '';
let currentUsername = '';
let feedRefreshTimer = null;

// Compose modal state
let composeMode     = null; // 'reply' | 'quote'
let composeTargetId = null; // post id
let composeIsSubmitting = false;

// Image state
let postImageFiles        = []; // selected Files for home compose (max 4)
let composeModalImageFiles = []; // selected Files for compose modal (max 4)

// Lightbox state
let lightboxImages = [];
let lightboxIndex  = 0;

// ── Boot ──────────────────────────────────────────────────
(async () => {
  // Always fetch session info and CSRF token first
  try {
    const data = await apiFetch('auth/me');
    if (data.csrf_token) csrfToken = data.csrf_token;
    if (data.user) {
      onLogin(data.user);
    }
  } catch (e) {
    console.error('Failed to fetch session:', e);
    showToast('Failed to connect to Magpie. Please refresh the page.', true);
  }

  // Handle verify/reset tokens in URL hash
  const hash = window.location.hash.substring(1);
  const params = new URLSearchParams(hash);
  const verifyToken = params.get('verify');
  const resetToken  = params.get('reset');

  if (verifyToken) {
    try {
      await apiFetch('auth/verify-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: verifyToken }),
      });
      showToast('Email verified! You can now post.');
      if (currentUser) {
        currentUser.email_verified = true;
        verifyBanner.style.display = 'none';
      }
      window.location.hash = '';
    } catch (e) {
      showToast(e.message, true);
    }
  } else if (resetToken) {
    showAuthModal('reset');
    window.location.hash = '';
    resetBtn.dataset.token = resetToken;
  }

  if (!currentUser && !resetToken && !hash) {
    showAuthModal('login');
  }

  const handleHash = () => {
    const hash = window.location.hash.substring(1);
    if (hash.startsWith('/user/')) {
      currentUsername = hash.substring(6);
      currentQuery    = '';
      currentFeed     = 'user';
      showView('home'); 
      document.querySelector('.view-title').textContent = `@${currentUsername}`;
      document.getElementById('feed-tabs').style.display = 'none';
      document.getElementById('compose').style.display = 'none';
      loadPosts(1, true);
    } else if (hash.startsWith('/search')) {
      const h_params = new URLSearchParams(hash.substring(7));
      currentQuery    = h_params.get('q') || '';
      currentUsername = '';
      currentFeed     = 'search';
      showView('home');
      document.querySelector('.view-title').textContent = `Search: ${currentQuery}`;
      document.getElementById('feed-tabs').style.display = 'none';
      document.getElementById('compose').style.display = 'none';
      loadPosts(1, true);
    } else {
      // Home / For You
      currentFeed = 'for-you';
      document.querySelector('.view-title').textContent = 'Home';
      document.getElementById('feed-tabs').style.display = '';
      document.getElementById('compose').style.display = currentUser ? '' : 'none';
      loadPosts(1, true);
    }
  };

  window.addEventListener('hashchange', handleHash);
  handleHash();
})();

// ── Feed tabs ─────────────────────────────────────────────
document.getElementById('feed-tabs').addEventListener('click', e => {
  const tab = e.target.closest('.feed-tab');
  if (!tab) return;
  const name = tab.dataset.tab;
  if (name === currentFeed) return;
  if (name === 'following' && !currentUser) { showAuthModal('login'); return; }
  currentFeed = name;
  document.querySelectorAll('.feed-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
  loadPosts(1, true);
});

// ── API helper ────────────────────────────────────────────
async function apiFetch(path, opts = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const headers = { ...opts.headers };
  if (csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
    headers['X-CSRF-Token'] = csrfToken;
  }
  const res  = await fetch('api.php/' + path, { ...opts, headers });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
}

// ── View switching ────────────────────────────────────────
function showView(name) {
  document.querySelectorAll('.view').forEach(v => v.style.display = 'none');
  document.getElementById('view-' + name).style.display = '';
  document.querySelectorAll('.nav-link').forEach(l =>
    l.classList.toggle('active', l.dataset.view === name)
  );
  if (name === 'profile')       initProfileView();
  if (name === 'liked')         loadLikedPosts(1, true);
  if (name === 'following')     loadUsers();
  if (name === 'admin')         loadAdminView('users');
  if (name === 'notifications') loadNotifications();
}

// ── People (Following) view logic ─────────────────────────
followingTabs.addEventListener('click', e => {
  const tab = e.target.closest('.feed-tab');
  if (!tab) return;
  const name = tab.dataset.tab;
  if (name === currentFollowingTab) return;
  currentFollowingTab = name;
  document.querySelectorAll('#following-tabs .feed-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
  userSearchWrap.style.display = (name === 'search') ? '' : 'none';
  if (name === 'search') userSearchInput.focus();
  loadUsers();
});

let searchTimeout = null;
userSearchInput.addEventListener('input', () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(loadUsers, 300);
});

async function loadUsers() {
  usersList.innerHTML = '<div class="empty-state"><p>Loading…</p></div>';
  try {
    const isSearch = currentFollowingTab === 'search';
    const q        = userSearchInput.value.trim();
    const params   = new URLSearchParams();
    if (isSearch && q) params.append('q', q);
    if (!isSearch)     params.append('following', '1');

    const data  = await apiFetch('users?' + params.toString());
    renderUsers(data.users);
  } catch (e) {
    usersList.innerHTML = `<div class="empty-state"><p>${esc(e.message)}</p></div>`;
  }
}

function renderUsers(users) {
  if (users.length === 0) {
    const msg = currentFollowingTab === 'search' ? 'No users found' : 'You are not following anyone yet';
    usersList.innerHTML = `<div class="empty-state"><p>${msg}</p></div>`;
    return;
  }
  usersList.innerHTML = '';
  users.forEach(u => {
    const div = document.createElement('div');
    div.className = 'user-item';

    const avatar = document.createElement('div');
    avatar.className = 'avatar';
    setAvatarEl(avatar, u);

    const info = document.createElement('div');
    info.className = 'user-item-info';
    info.innerHTML = `
      <div class="user-item-name">${esc(u.display_name || u.username)}</div>
      <div class="user-item-handle">@${esc(u.username)}</div>
    `;

    const actions = document.createElement('div');
    actions.className = 'user-item-actions';
    const followBtn = document.createElement('button');
    followBtn.className = 'btn-follow' + (u.following ? ' following' : '');
    followBtn.addEventListener('click', async () => {
      await toggleFollow(u.username);
      if (currentFollowingTab === 'following') {
        div.remove();
        if (usersList.children.length === 0) {
          usersList.innerHTML = '<div class="empty-state"><p>You are not following anyone yet</p></div>';
        }
      } else {
        followBtn.classList.toggle('following');
      }
    });
    actions.appendChild(followBtn);

    div.appendChild(avatar);
    div.appendChild(info);
    div.appendChild(actions);
    usersList.appendChild(div);
  });
}

document.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    if (link.dataset.view) showView(link.dataset.view);
  });
});

// ── Auth modal ────────────────────────────────────────────
function showAuthModal(view) {
  authModal.classList.remove('hidden');
  loginView.style.display  = view === 'login'  ? '' : 'none';
  signupView.style.display = view === 'signup' ? '' : 'none';
  forgotView.style.display = view === 'forgot' ? '' : 'none';
  resetView.style.display  = view === 'reset'  ? '' : 'none';

  if (view === 'signup') signupUsername.focus();
  if (view === 'login')  loginUsernameI.focus();
  if (view === 'forgot') forgotEmail.focus();
  if (view === 'reset')  resetPassword.focus();
}

function hideAuthModal() { authModal.classList.add('hidden'); }

toSignup.addEventListener('click', () => showAuthModal('signup'));
toLogin.addEventListener('click',  () => showAuthModal('login'));
toForgot.addEventListener('click', () => showAuthModal('forgot'));
forgotToLogin.addEventListener('click', () => showAuthModal('login'));

loginPasswordI.addEventListener('keydown', e => { if (e.key === 'Enter') loginBtn.click(); });
signupConfirm.addEventListener('keydown',  e => { if (e.key === 'Enter') signupBtn.click(); });
forgotEmail.addEventListener('keydown',    e => { if (e.key === 'Enter') forgotBtn.click(); });
resetConfirm.addEventListener('keydown',   e => { if (e.key === 'Enter') resetBtn.click(); });

loginBtn.addEventListener('click', async () => {
  loginError.textContent = '';
  const username = loginUsernameI.value.trim();
  const password = loginPasswordI.value;
  if (!username || !password) { loginError.textContent = 'Fill in all fields'; return; }
  loginBtn.disabled = true;
  try {
    const data = await apiFetch('auth/login', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ username, password, remember_me: loginRememberMe.checked }),
    });
    if (data.csrf_token) csrfToken = data.csrf_token;
    onLogin(data.user);
    loadPosts(1, true);
  } catch (e) {
    loginError.textContent = e.message;
  } finally {
    loginBtn.disabled = false;
  }
});

signupBtn.addEventListener('click', async () => {
  signupError.textContent = '';
  const username = signupUsername.value.trim();
  const email    = signupEmail.value.trim();
  const password = signupPassword.value;
  const confirm  = signupConfirm.value;
  if (!username || !email || !password) { signupError.textContent = 'Fill in all fields'; return; }
  if (password !== confirm)              { signupError.textContent = 'Passwords do not match'; return; }
  signupBtn.disabled = true;
  try {
    const data = await apiFetch('auth/signup', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ username, email, password }),
    });
    if (data.csrf_token) csrfToken = data.csrf_token;
    onLogin(data.user);
    loadPosts(1, true);
  } catch (e) {
    signupError.textContent = e.message;
  } finally {
    signupBtn.disabled = false;
  }
});

forgotBtn.addEventListener('click', async () => {
  forgotError.textContent = '';
  const email = forgotEmail.value.trim();
  if (!email) { forgotError.textContent = 'Email is required'; return; }
  forgotBtn.disabled = true;
  try {
    await apiFetch('auth/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email }),
    });
    showToast('If an account exists, a reset link has been sent!');
    showAuthModal('login');
  } catch (e) {
    forgotError.textContent = e.message;
  } finally {
    forgotBtn.disabled = false;
  }
});

resetBtn.addEventListener('click', async () => {
  resetError.textContent = '';
  const password = resetPassword.value;
  const confirm  = resetConfirm.value;
  const token    = resetBtn.dataset.token;
  if (password.length < 6) { resetError.textContent = 'Password must be at least 6 characters'; return; }
  if (password !== confirm) { resetError.textContent = 'Passwords do not match'; return; }
  resetBtn.disabled = true;
  try {
    await apiFetch('auth/reset-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, password }),
    });
    showToast('Password reset successful! You can now log in.');
    showAuthModal('login');
  } catch (e) {
    resetError.textContent = e.message;
  } finally {
    resetBtn.disabled = false;
  }
});

logoutBtn.addEventListener('click', async () => {
  try {
    const data = await apiFetch('auth/logout', { method: 'POST' });
    if (data.csrf_token) csrfToken = data.csrf_token;
  } catch (e) {
    console.warn('Logout API failed', e);
    csrfToken = null; // Fallback
  }
  currentUser = null;
  currentFeed = 'for-you';
  document.querySelectorAll('.feed-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'for-you'));
  composeEl.style.display        = 'none';
  sidebarUser.style.display      = 'none';
  logoutBtn.style.display        = 'none';
  navProfile.style.display       = 'none';
  navLiked.style.display         = 'none';
  navFollowing.style.display     = 'none';
  navAdmin.style.display         = 'none';
  navNotifications.style.display = 'none';
  notifBadge.classList.add('hidden');
  verifyBanner.style.display = 'none';
  loginUsernameI.value = loginPasswordI.value = '';
  signupUsername.value = signupEmail.value = signupPassword.value = signupConfirm.value = '';
  loginError.textContent = '';

  showAuthModal('login');
  showView('home');
  loadPosts(1, true);
});

// ── On login ──────────────────────────────────────────────
function onLogin(user, token = null) {
  currentUser = user;
  if (token) csrfToken = token;
  hideAuthModal();
  updateAuthUI(user);
  composeEl.style.display        = '';
  sidebarUser.style.display      = '';
  logoutBtn.style.display        = '';
  navProfile.style.display       = '';
  navLiked.style.display         = '';
  navFollowing.style.display     = '';
  navNotifications.style.display = '';
  navAdmin.style.display         = user.is_admin ? '' : 'none';
  verifyBanner.style.display     = user.email_verified ? 'none' : 'block';
  postInput.focus();
  refreshNotifCount();
  initSSE();
}

resendVerifyBtn.addEventListener('click', async () => {
  resendVerifyBtn.disabled = true;
  try {
    await apiFetch('auth/resend-verification', { method: 'POST' });
    showToast('Verification email sent!');
  } catch (e) {
    showToast(e.message, true);
  } finally {
    setTimeout(() => { resendVerifyBtn.disabled = false; }, 5000);
  }
});

function updateAuthUI(user) {
  if (!user) return;
  sidebarDN.textContent     = user.display_name || user.username;
  sidebarHandle.textContent = '@' + user.username;
  setAvatarEl(sidebarAvatar, user);
  setAvatarEl(composeAvatar, user);
  if (currentUser) {
    setAvatarEl(composeModalAvatar, currentUser);
  }
  if (user.username) {
    document.querySelectorAll(`.post[data-username="${CSS.escape(user.username)}"] .avatar`)
      .forEach(el => setAvatarEl(el, user));
  }
}

function setAvatarEl(el, user) {
  const name    = user.display_name || user.username;
  const initial = name[0].toUpperCase();
  if (user.avatar) {
    el.innerHTML = `<img src="${esc(user.avatar)}" alt="">`;
    el.style.background = '';
  } else {
    el.innerHTML = initial;
    el.style.background = avatarColor(user.username);
  }
}

// ── Compose (home) ────────────────────────────────────────
postInput.addEventListener('input', updateCharCount);
postInput.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') submitPost();
});
submitBtn.addEventListener('click', submitPost);
loadMoreBtn.addEventListener('click', () => loadPosts(currentPage + 1, false));

function updateCharCount() {
  const len  = postInput.value.length;
  const left = MAX_CHARS - len;
  const pct  = Math.min(len / MAX_CHARS, 1);

  const hasContent = len > 0 || postImageFiles.length > 0;
  submitBtn.disabled        = !hasContent || left < 0 || isSubmitting;
  cancelBtn.style.display   = hasContent ? '' : 'none';
  charRingWrap.style.display = hasContent ? '' : 'none';

  charRingFill.setAttribute('stroke-dashoffset', CIRC * (1 - pct));
  charRing.setAttribute('class', 'char-ring' + (left < 0 ? ' danger' : left < 20 ? ' warn' : ''));

  charRemaining.textContent = left <= 20 ? left : '';
  charRemaining.className   = 'char-remaining' + (left < 0 ? ' danger' : '');
}

updateCharCount();

async function submitPost() {
  if (isSubmitting || !currentUser) return;
  const body = postInput.value.trim();
  if (!body && postImageFiles.length === 0) return;

  isSubmitting = submitBtn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('body', body);
    for (const f of postImageFiles) {
      const compressed = await compressImage(f);
      fd.append('images[]', compressed);
    }

    await apiFetch('posts', { method: 'POST', body: fd });
    postInput.value = '';
    clearComposeImages(postImageFiles, composeImagePreview, updateCharCount);
    await loadPosts(1, true);
  } catch (e) {
    showToast(e.message, true);
  } finally {
    isSubmitting = false;
    updateCharCount();
  }
}

cancelBtn.addEventListener('click', () => {
  postInput.value = '';
  clearComposeImages(postImageFiles, composeImagePreview, updateCharCount);
  postInput.focus();
});

// ── Compose image attach (home) ───────────────────────────
composeAttachBtn.addEventListener('click', () => composeImageFile.click());
composeImageFile.addEventListener('change', () => {
  addFilesToCompose(Array.from(composeImageFile.files), postImageFiles, composeImagePreview, updateCharCount);
  composeImageFile.value = '';
});

// ── Feed ──────────────────────────────────────────────────
function scheduleFeedRefresh() {
  clearTimeout(feedRefreshTimer);
  feedRefreshTimer = setTimeout(() => {
    if (currentUser && document.getElementById('view-home').style.display !== 'none') {
      loadPosts(1, true);
    }
  }, 2 * 60 * 1000);
}

async function loadPosts(page, replace) {
  loadMoreBtn.disabled = true;
  try {
    const feedParam = currentFeed === 'following' ? '&feed=following' : (currentFeed === 'liked' ? '&feed=liked' : '');
    const qParam    = currentQuery ? `&q=${encodeURIComponent(currentQuery)}` : '';
    const uParam    = currentUsername ? `&username=${encodeURIComponent(currentUsername)}` : '';
    
    const data = await apiFetch(`posts?page=${page}${feedParam}${qParam}${uParam}`);
    currentPage = data.page;
    totalPages  = data.pages;

    if (replace) feed.innerHTML = '';

    if (data.posts.length === 0 && replace) {
      const emptyMsg = currentFeed === 'following'
        ? '<h2>No posts yet</h2><p>Follow people to see their posts here.</p>'
        : '<h2>No posts yet</h2><p>Be the first to post something.</p>';
      feed.innerHTML = `<div class="empty-state">${emptyMsg}</div>`;
    } else {
      data.posts.forEach(p => feed.appendChild(renderPost(p)));
    }
    loadMoreWrap.hidden = currentPage >= totalPages;
    scheduleFeedRefresh();
  } catch (e) {
    showToast(e.message, true);
  } finally {
    loadMoreBtn.disabled = false;
  }
}

function renderPost(post, opts = {}) {
  const { inThread = false, isHighlight = false } = opts;

  const div = document.createElement('div');
  div.className        = 'post' + (isHighlight ? ' post-highlight' : '');
  div.dataset.id       = post.id;
  div.dataset.username = post.username;

  const displayName = post.display_name || post.username;

  const replyingTo = post.parent_id && post.parent_username
    ? `<div class="reply-label">Replying to <span class="reply-to-handle">@${esc(post.parent_display_name || post.parent_username)}</span></div>`
    : '';

  const quoteCard = post.quote ? renderQuoteCard(post.quote) : '';

  const replyCountLabel = post.reply_count > 0 ? post.reply_count : '';
  const editedLabel     = post.edited_at ? `<span class="post-edited" title="Edited ${new Date(post.edited_at * 1000).toLocaleString()}">· edited</span>` : '';
  const imageHtml       = renderPhotoGrid(post.image_urls);

  div.innerHTML = `
    ${postAvatarHtml(post)}
    <div class="post-content">
      <div class="post-meta">
        <span class="post-username">${esc(displayName)}</span>
        <span class="post-handle">@${esc(post.username)}</span>
        <span class="post-sep">·</span>
        <span class="post-time" title="${new Date(post.created_at * 1000).toLocaleString()}">${timeAgo(post.created_at)}</span>
        ${editedLabel}
      </div>
      ${replyingTo}
      ${post.body ? `<div class="post-body post-body-clickable">${parseRichText(post.body)}</div>` : ''}
      ${imageHtml}
      ${quoteCard}
      <div class="post-actions">
        <button class="action-btn reply-btn" data-id="${post.id}" title="Reply">
          ${replySvg()}
          <span class="reply-count">${replyCountLabel}</span>
        </button>
        <button class="action-btn repost-btn ${post.reposted ? 'reposted' : ''}" data-id="${post.id}" title="Repost">
          ${repostSvg()}
          <span class="repost-count">${post.repost_count > 0 ? post.repost_count : ''}</span>
        </button>
        <button class="action-btn quote-btn" data-id="${post.id}" title="Quote">
          ${quoteSvg()}
        </button>
        <button class="action-btn like-btn ${post.liked ? 'liked' : ''}" data-id="${post.id}">
          ${heartSvg(post.liked)}
          <span class="like-count">${post.likes > 0 ? post.likes : ''}</span>
        </button>
        ${!post.own && currentUser ? `<button class="action-btn follow-btn ${post.following ? 'following' : ''}" data-username="${esc(post.username)}" title="${post.following ? 'Unfollow' : 'Follow'} @${esc(post.username)}">${followSvg(post.following)}</button>` : ''}
        ${post.own ? `<button class="action-btn edit-btn" data-id="${post.id}" title="Edit">${editSvg()}</button>` : ''}
        ${post.own ? `<button class="action-btn delete-btn" data-id="${post.id}" title="Delete">${trashSvg()}</button>` : ''}
      </div>
    </div>`;

  // Click post body/meta to open thread (not action buttons)
  div.querySelector('.post-body-clickable')?.addEventListener('click', () => openThread(post.id));
  div.querySelector('.post-meta').addEventListener('click', () => openThread(post.id));

  // Photo grid lightbox
  div.querySelectorAll('.photo-grid-item').forEach((item, idx) => {
    item.addEventListener('click', e => {
      e.stopPropagation();
      openLightbox(post.image_urls, idx);
    });
  });

  div.querySelector('.reply-btn').addEventListener('click', e => {
    e.stopPropagation();
    if (!currentUser) { showToast('Sign in to reply', true); return; }
    openComposeModal('reply', post);
  });

  div.querySelector('.repost-btn').addEventListener('click', e => {
    e.stopPropagation();
    if (!currentUser) { showToast('Sign in to repost', true); return; }
    toggleRepost(post.id, div);
  });

  div.querySelector('.quote-btn').addEventListener('click', e => {
    e.stopPropagation();
    if (!currentUser) { showToast('Sign in to quote', true); return; }
    openComposeModal('quote', post);
  });

  div.querySelector('.like-btn').addEventListener('click', e => {
    e.stopPropagation();
    if (!currentUser) { showToast('Sign in to like posts', true); return; }
    toggleLike(post.id, div);
  });

  div.querySelector('.follow-btn')?.addEventListener('click', e => {
    e.stopPropagation();
    toggleFollow(post.username);
  });

  div.querySelector('.edit-btn')?.addEventListener('click', e => {
    e.stopPropagation();
    startEditPost(post, div);
  });

  div.querySelector('.delete-btn')?.addEventListener('click', e => {
    e.stopPropagation();
    deletePost(post.id, div);
  });

  return div;
}

function startEditPost(post, postEl) {
  const bodyEl    = postEl.querySelector('.post-body-clickable');
  const actionsEl = postEl.querySelector('.post-actions');
  const gridEl    = postEl.querySelector('.photo-grid'); // may be null

  const textarea = document.createElement('textarea');
  textarea.className = 'post-edit-textarea';
  textarea.value = post.body;
  textarea.rows  = 3;

  // Image state for this edit session
  let keepImageUrls  = [...(post.image_urls || [])];
  let editImageFiles = [];

  const imageEditArea = document.createElement('div');
  imageEditArea.className = 'compose-images-preview';

  function renderEditImages() {
    // Revoke any previous blob URLs before wiping innerHTML
    imageEditArea.querySelectorAll('img').forEach(img => {
      if (img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);
    });
    imageEditArea.innerHTML = '';

    keepImageUrls.forEach((url, idx) => {
      imageEditArea.appendChild(buildEditThumb(url, () => {
        keepImageUrls.splice(idx, 1);
        renderEditImages();
      }));
    });

    editImageFiles.forEach((file, idx) => {
      const blobUrl = URL.createObjectURL(file);
      imageEditArea.appendChild(buildEditThumb(blobUrl, () => {
        URL.revokeObjectURL(blobUrl);
        editImageFiles.splice(idx, 1);
        renderEditImages();
      }));
    });

    if (keepImageUrls.length + editImageFiles.length < 4) {
      const addBtn = document.createElement('button');
      addBtn.type      = 'button';
      addBtn.className = 'compose-img-add';
      addBtn.title     = 'Add image';
      addBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>`;
      addBtn.addEventListener('click', () => {
        const fi = document.createElement('input');
        fi.type = 'file';
        fi.accept = 'image/jpeg,image/png,image/gif,image/webp';
        fi.multiple = true;
        fi.style.display = 'none';
        fi.addEventListener('change', () => {
          const remaining = 4 - keepImageUrls.length - editImageFiles.length;
          Array.from(fi.files).slice(0, remaining).forEach(f => editImageFiles.push(f));
          fi.remove();
          renderEditImages();
        });
        document.body.appendChild(fi);
        fi.click();
      });
      imageEditArea.appendChild(addBtn);
    }
  }

  renderEditImages();

  const editBar   = document.createElement('div');
  editBar.className = 'post-edit-bar';
  const cancelBtn = document.createElement('button');
  cancelBtn.className   = 'btn btn-ghost btn-sm';
  cancelBtn.textContent = 'Cancel';
  const saveBtn   = document.createElement('button');
  saveBtn.className   = 'btn btn-primary btn-sm';
  saveBtn.textContent = 'Save';
  editBar.appendChild(cancelBtn);
  editBar.appendChild(saveBtn);

  bodyEl.replaceWith(textarea);
  if (gridEl) {
    gridEl.replaceWith(imageEditArea);
  } else {
    textarea.after(imageEditArea);
  }
  actionsEl.replaceWith(editBar);
  textarea.focus();
  textarea.setSelectionRange(textarea.value.length, textarea.value.length);

  const restore = () => {
    imageEditArea.querySelectorAll('img').forEach(img => {
      if (img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);
    });
    textarea.replaceWith(bodyEl);
    if (gridEl) {
      imageEditArea.replaceWith(gridEl);
    } else {
      imageEditArea.remove();
    }
    editBar.replaceWith(actionsEl);
  };

  cancelBtn.addEventListener('click', restore);

  saveBtn.addEventListener('click', async () => {
    const newBody     = textarea.value.trim();
    const totalImages = keepImageUrls.length + editImageFiles.length;
    if (!newBody && totalImages === 0) { showToast('Post cannot be empty', true); return; }
    if (newBody.length > MAX_CHARS)    { showToast('Post too long', true); return; }
    saveBtn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('body', newBody);
      keepImageUrls.forEach(url => fd.append('keep_images[]', url.split('/').pop()));
      editImageFiles.forEach(f  => fd.append('images[]', f));
      const updated  = await apiFetch(`posts/${post.id}`, { method: 'PUT', body: fd });
      const newPostEl = renderPost(updated);
      postEl.replaceWith(newPostEl);
      showToast('Post updated');
    } catch (e) {
      showToast(e.message, true);
      saveBtn.disabled = false;
    }
  });
}

function buildEditThumb(src, onRemove) {
  const thumb = document.createElement('div');
  thumb.className = 'compose-img-thumb';
  const img = document.createElement('img');
  img.src = src;
  img.alt = '';
  const btn = document.createElement('button');
  btn.type      = 'button';
  btn.className = 'compose-img-thumb-remove';
  btn.title     = 'Remove';
  btn.textContent = '×';
  btn.addEventListener('click', onRemove);
  thumb.appendChild(img);
  thumb.appendChild(btn);
  return thumb;
}

function renderQuoteCard(quote) {
  const dn = quote.display_name || quote.username;
  const avatarHtml = quote.avatar_url
    ? `<img src="${esc(quote.avatar_url)}" alt="">`
    : `<span style="background:${avatarColor(quote.username)};width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;color:#fff;flex-shrink:0">${esc(quote.username[0].toUpperCase())}</span>`;
  return `
    <div class="quote-card">
      <div class="quote-card-meta">
        <span class="quote-avatar">${avatarHtml}</span>
        <span class="quote-username">${esc(dn)}</span>
        <span class="quote-handle">@${esc(quote.username)}</span>
        <span class="post-sep">·</span>
        <span class="quote-time">${timeAgo(quote.created_at)}</span>
      </div>
      <div class="quote-body">${parseRichText(quote.body)}</div>
    </div>`;
}

function postAvatarHtml(post) {
  if (post.avatar_url) {
    return `<div class="avatar"><img src="${esc(post.avatar_url)}" alt=""></div>`;
  }
  return `<div class="avatar" style="background:${avatarColor(post.username)}">${post.username[0].toUpperCase()}</div>`;
}

async function toggleLike(id, postEl) {
  try {
    const post = await apiFetch(`posts/${id}/like`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
    });
    const btn = postEl.querySelector('.like-btn');
    btn.classList.toggle('liked', post.liked);
    btn.innerHTML = `${heartSvg(post.liked)}<span class="like-count">${post.likes > 0 ? post.likes : ''}</span>`;
  } catch (e) { showToast(e.message, true); }
}

async function toggleRepost(id, postEl) {
  try {
    const post = await apiFetch(`posts/${id}/repost`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
    });
    const btn = postEl.querySelector('.repost-btn');
    if (btn) {
      btn.classList.toggle('reposted', post.reposted);
      btn.innerHTML = `${repostSvg()}<span class="repost-count">${post.repost_count > 0 ? post.repost_count : ''}</span>`;
    }
    showToast(post.reposted ? 'Reposted' : 'Repost removed');
    if (!threadModal.classList.contains('hidden')) refreshThread();
    loadPosts(1, true);
  } catch (e) { showToast(e.message, true); }
}

async function deletePost(id, postEl) {
  try {
    await apiFetch(`posts/${id}`, { method: 'DELETE' });
    postEl.style.transition = 'opacity 0.2s';
    postEl.style.opacity    = '0';
    setTimeout(() => {
      postEl.remove();
      if (!feed.querySelector('.post'))
        feed.innerHTML = `<div class="empty-state"><h2>No posts yet</h2><p>Be the first to post something.</p></div>`;
    }, 200);
  } catch (e) { showToast(e.message, true); }
}

async function toggleFollow(username) {
  try {
    const data = await apiFetch(`users/${username}/follow`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
    });
    document.querySelectorAll(`.follow-btn[data-username="${username}"]`).forEach(btn => {
      btn.classList.toggle('following', data.following);
      btn.title = (data.following ? 'Unfollow' : 'Follow') + ' @' + username;
      btn.innerHTML = followSvg(data.following);
    });
    if (currentFeed === 'following' && !data.following) loadPosts(1, true);
    showToast(data.following ? `Following @${username}` : `Unfollowed @${username}`);
    if (data.following) refreshNotifCount();
  } catch (e) { showToast(e.message, true); }
}

// ── Thread modal ──────────────────────────────────────────
let currentThreadPostId = null;

async function openThread(postId) {
  currentThreadPostId = postId;
  threadContent.innerHTML = '<div class="empty-state"><p>Loading…</p></div>';
  threadModal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  try {
    const data = await apiFetch(`posts/${postId}/thread`);
    renderThread(data);
  } catch (e) {
    threadContent.innerHTML = `<div class="empty-state"><p>${esc(e.message)}</p></div>`;
  }
}

async function refreshThread() {
  if (!currentThreadPostId) return;
  try {
    const data = await apiFetch(`posts/${currentThreadPostId}/thread`);
    renderThread(data);
  } catch (e) { /* silently ignore refresh errors */ }
}

function closeThread() {
  currentThreadPostId = null;
  threadModal.classList.add('hidden');
  document.body.style.overflow = '';
}

threadClose.addEventListener('click', closeThread);
threadModal.addEventListener('click', e => {
  if (e.target === threadModal) closeThread();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (!lightboxEl.classList.contains('hidden'))    closeLightbox();
    else if (!threadModal.classList.contains('hidden'))   closeThread();
    else if (!composeModal.classList.contains('hidden'))  closeComposeModal();
  }
  if (e.key === 'ArrowLeft'  && !lightboxEl.classList.contains('hidden')) lightboxNav(-1);
  if (e.key === 'ArrowRight' && !lightboxEl.classList.contains('hidden')) lightboxNav(1);
});

function renderThread(data) {
  threadContent.innerHTML = '';
  const { ancestors, post, replies } = data;

  ancestors.forEach((ancestor, i) => {
    const el = renderPost(ancestor, { inThread: true });
    el.classList.add('thread-ancestor');
    if (i < ancestors.length - 1 || true) el.classList.add('thread-connected');
    threadContent.appendChild(el);
  });

  const mainEl = renderPost(post, { inThread: true, isHighlight: true });
  threadContent.appendChild(mainEl);

  if (replies.length > 0) {
    const sep = document.createElement('div');
    sep.className = 'thread-sep';
    sep.textContent = `${replies.length} ${replies.length === 1 ? 'reply' : 'replies'}`;
    threadContent.appendChild(sep);

    replies.forEach(r => {
      const el = renderPost(r, { inThread: true });
      const indent = (r.depth - 1) * 24;
      if (indent > 0) {
        el.style.marginLeft = indent + 'px';
        el.style.borderLeft = '2px solid var(--border-dk)';
      }
      threadContent.appendChild(el);
    });
  } else {
    const none = document.createElement('div');
    none.className = 'thread-no-replies';
    none.textContent = 'No replies yet';
    threadContent.appendChild(none);
  }

  // Scroll highlighted post into view
  setTimeout(() => {
    const highlight = threadContent.querySelector('.post-highlight');
    if (highlight) highlight.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }, 50);
}

// ── Compose modal (reply / quote) ─────────────────────────
function openComposeModal(mode, post) {
  composeMode     = mode;
  composeTargetId = post.id;

  if (currentUser) setAvatarEl(composeModalAvatar, currentUser);

  if (mode === 'reply') {
    composeModalInput.placeholder = 'Post your reply…';
    composeModalLabel.style.display = '';
    composeModalLabel.innerHTML = `Replying to <span class="reply-to-handle">@${esc(post.display_name || post.username)}</span>`;
    composeModalCtx.innerHTML = '';
    // Show original post as context
    const contextPost = document.createElement('div');
    contextPost.className = 'compose-context-post';
    contextPost.innerHTML = `
      ${postAvatarHtml(post)}
      <div class="post-content">
        <div class="post-meta">
          <span class="post-username">${esc(post.display_name || post.username)}</span>
          <span class="post-handle">@${esc(post.username)}</span>
        </div>
        <div class="post-body">${parseRichText(post.body)}</div>
      </div>`;
    composeModalCtx.appendChild(contextPost);
    composeModalQuote.style.display = 'none';
    composeModalQuote.innerHTML = '';
  } else {
    // quote
    composeModalInput.placeholder = 'Add a comment…';
    composeModalLabel.style.display = 'none';
    composeModalCtx.innerHTML = '';
    composeModalQuote.style.display = '';
    composeModalQuote.innerHTML = renderQuoteCard(post);
  }

  composeModalInput.value = '';
  clearComposeImages(composeModalImageFiles, composeModalImagePreview, updateComposeModalCount);
  composeModal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  setTimeout(() => composeModalInput.focus(), 50);
}

function closeComposeModal() {
  clearComposeImages(composeModalImageFiles, composeModalImagePreview, updateComposeModalCount);
  composeModal.classList.add('hidden');
  document.body.style.overflow = '';
  composeMode = null;
  composeTargetId = null;
}

// ── Compose modal image attach ────────────────────────────
composeModalAttachBtn.addEventListener('click', () => composeModalImageFile.click());
composeModalImageFile.addEventListener('change', () => {
  addFilesToCompose(Array.from(composeModalImageFile.files), composeModalImageFiles, composeModalImagePreview, updateComposeModalCount);
  composeModalImageFile.value = '';
});

composeModalCancel.addEventListener('click', closeComposeModal);
composeModal.addEventListener('click', e => {
  if (e.target === composeModal) closeComposeModal();
});

composeModalInput.addEventListener('input', updateComposeModalCount);
composeModalInput.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') composeModalSubmit.click();
});

function updateComposeModalCount() {
  const len  = composeModalInput.value.length;
  const left = MAX_CHARS - len;
  const pct  = Math.min(len / MAX_CHARS, 1);

  composeModalSubmit.disabled = (len === 0 && composeModalImageFiles.length === 0) || left < 0 || composeIsSubmitting;
  composeModalFill.setAttribute('stroke-dashoffset', CIRC * (1 - pct));
  composeModalRing.setAttribute('class', 'char-ring' + (left < 0 ? ' danger' : left < 20 ? ' warn' : ''));
  composeModalLeft.textContent = left <= 20 ? left : '';
  composeModalLeft.className   = 'char-remaining' + (left < 0 ? ' danger' : '');
}

composeModalSubmit.addEventListener('click', async () => {
  if (composeIsSubmitting || !currentUser || !composeTargetId) return;
  const body = composeModalInput.value.trim();
  if (!body && composeModalImageFiles.length === 0) return;

  const fd = new FormData();
  fd.append('body', body);
  if (composeMode === 'reply') fd.append('parent_id', composeTargetId);
  if (composeMode === 'quote') fd.append('quote_id',  composeTargetId);
  
  for (const f of composeModalImageFiles) {
    const compressed = await compressImage(f);
    fd.append('images[]', compressed);
  }

  const wasReply = composeMode === 'reply';
  const threadWasOpen = !threadModal.classList.contains('hidden');
  composeIsSubmitting = composeModalSubmit.disabled = true;
  try {
    await apiFetch('posts', { method: 'POST', body: fd });
    closeComposeModal();
    showToast(wasReply ? 'Reply posted' : 'Quote posted');
    if (wasReply && threadWasOpen) {
      refreshThread();
    }
    loadPosts(1, true);
  } catch (e) {
    showToast(e.message, true);
  } finally {
    composeIsSubmitting = false;
    updateComposeModalCount();
  }
});

// ── Liked view ────────────────────────────────────────────
const likedFeed        = document.getElementById('liked-feed');
const likedLoadMoreWrap = document.getElementById('liked-load-more-wrap');
const likedLoadMoreBtn  = document.getElementById('liked-load-more-btn');
let likedPage = 1;
let likedTotalPages = 1;

likedLoadMoreBtn.addEventListener('click', () => loadLikedPosts(likedPage + 1, false));

async function loadLikedPosts(page, replace) {
  likedLoadMoreBtn.disabled = true;
  try {
    const data = await apiFetch(`posts?page=${page}&feed=liked`);
    likedPage       = data.page;
    likedTotalPages = data.pages;

    if (replace) likedFeed.innerHTML = '';

    if (data.posts.length === 0 && replace) {
      likedFeed.innerHTML = '<div class="empty-state"><h2>No liked posts yet</h2><p>Posts you like will appear here.</p></div>';
    } else {
      data.posts.forEach(p => likedFeed.appendChild(renderPost(p)));
    }
    likedLoadMoreWrap.hidden = likedPage >= likedTotalPages;
  } catch (e) {
    showToast(e.message, true);
  } finally {
    likedLoadMoreBtn.disabled = false;
  }
}

// ── Notifications ─────────────────────────────────────────
let sseSource = null;

function initSSE() {
  if (sseSource) sseSource.close();
  sseSource = new EventSource('api.php/stream');
  sseSource.onmessage = (e) => {
    const data = JSON.parse(e.data);
    if (data.type === 'notification') {
      updateNotifBadge(data.unread);
    } else if (data.type === 'new_post') {
      showNewPostAlert();
    }
  };
  sseSource.onerror = () => {
    sseSource.close();
    // Exponential backoff or simple delay
    setTimeout(initSSE, 10000);
  };
}

function showNewPostAlert() {
  // Only show on the home views and if we are near the top
  const isHomeView = document.getElementById('view-home').style.display !== 'none';
  if (!isHomeView || window.scrollY > 400) return;
  if (currentFeed !== 'for-you' && currentFeed !== 'following') return;

  // Prevent duplicate alerts
  if (document.querySelector('.new-post-alert')) return;

  const alert = document.createElement('div');
  alert.className = 'new-post-alert';
  alert.innerHTML = '<span>New posts available</span>';
  alert.onclick = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    loadPosts(1, true);
    alert.remove();
  };
  
  document.getElementById('view-home').prepend(alert);
  setTimeout(() => alert.remove(), 15000);
}

async function refreshNotifCount() {
  if (!currentUser) return;
  try {
    const data = await apiFetch('notifications');
    updateNotifBadge(data.unread);
  } catch {}
}

function updateNotifBadge(count) {
  if (count > 0) {
    notifBadge.textContent = count > 99 ? '99+' : count;
    notifBadge.classList.remove('hidden');
  } else {
    notifBadge.classList.add('hidden');
  }
}

// EventSource handles updates now

async function loadNotifications() {
  const list = document.getElementById('notifications-list');
  list.innerHTML = '<div class="empty-state"><p>Loading…</p></div>';
  try {
    const data = await apiFetch('notifications');
    updateNotifBadge(data.unread);
    renderNotifications(data.notifications);
    // Mark all as read
    if (data.unread > 0) {
      await apiFetch('notifications/read', { method: 'POST' }).catch(() => {});
      updateNotifBadge(0);
    }
  } catch (e) {
    list.innerHTML = `<div class="empty-state"><p>${esc(e.message)}</p></div>`;
  }
}

function renderNotifications(notifs) {
  const list = document.getElementById('notifications-list');
  if (notifs.length === 0) {
    list.innerHTML = '<div class="empty-state"><h2>No notifications yet</h2><p>You\'ll see activity here when someone follows or replies to you.</p></div>';
    return;
  }
  list.innerHTML = '';
  notifs.forEach(n => {
    const item = document.createElement('div');
    item.className = 'notif-item' + (n.read ? '' : ' notif-unread');

    const actor = n.actor;
    const avatarHtml = actor.avatar
      ? `<div class="avatar notif-avatar"><img src="${esc(actor.avatar)}" alt=""></div>`
      : `<div class="avatar notif-avatar" style="background:${avatarColor(actor.username)}">${actor.display_name[0].toUpperCase()}</div>`;

    let icon = '', text = '';
    if (n.type === 'follow') {
      icon = `<span class="notif-icon notif-icon-follow">${followNotifSvg()}</span>`;
      text = `<strong>${esc(actor.display_name)}</strong> followed you`;
    } else if (n.type === 'reply') {
      icon = `<span class="notif-icon notif-icon-reply">${replySvg()}</span>`;
      text = `<strong>${esc(actor.display_name)}</strong> replied to your post`;
    } else if (n.type === 'quote') {
      icon = `<span class="notif-icon notif-icon-quote">${quoteSvg()}</span>`;
      text = `<strong>${esc(actor.display_name)}</strong> quoted your post`;
    } else if (n.type === 'repost') {
      icon = `<span class="notif-icon notif-icon-repost">${repostSvg()}</span>`;
      text = `<strong>${esc(actor.display_name)}</strong> reposted your post`;
    }

    const snippet = n.post_body ? `<div class="notif-snippet">${parseRichText(n.post_body)}</div>` : '';

    item.innerHTML = `
      <div class="notif-icon-col">${icon}</div>
      <div class="notif-body">
        ${avatarHtml}
        <div class="notif-text">${text}</div>
        ${snippet}
        <div class="notif-time">${timeAgo(n.created_at)}</div>
      </div>`;

    if (n.post_id) {
      item.style.cursor = 'pointer';
      const threadId = n.parent_post_id || n.post_id;
      item.addEventListener('click', () => openThread(threadId));
    }

    list.appendChild(item);
  });
}

// ── Profile view ──────────────────────────────────────────
function initProfileView() {
  if (!currentUser) return;
  const u = currentUser;
  pfUsername.value     = u.username;
  pfDisplayName.value  = u.display_name || '';
  pfEmail.value        = u.email || '';
  pfBio.value          = u.bio || '';
  profileError.textContent = '';
  updateBioCounter();
  setAvatarEl(profileAvatar, u);
  profileAvatar.style.width  = '80px';
  profileAvatar.style.height = '80px';
  profileAvatar.style.fontSize = '32px';
}

pfBio.addEventListener('input', updateBioCounter);
function updateBioCounter() {
  bioChars.textContent = 160 - pfBio.value.length;
}

// Toggle avatar picker open/closed
avatarUpload.addEventListener('click', () => {
  avatarPicker.classList.toggle('open');
  if (avatarPicker.classList.contains('open')) syncPresetHighlight();
});

// Highlight whichever preset matches the current avatar
function syncPresetHighlight() {
  const current = currentUser?.avatar || '';
  document.querySelectorAll('.avatar-preset').forEach(img => {
    const match = current.endsWith('presets/' + img.dataset.preset);
    img.classList.toggle('active', match);
  });
}

// Preset click — select a magpie avatar
document.querySelectorAll('.avatar-preset').forEach(img => {
  img.addEventListener('click', async () => {
    try {
      const data = await apiFetch('users/me/avatar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ preset: img.dataset.preset }),
      });
      currentUser = data.user;
      updateAuthUI(data.user);
      setAvatarEl(profileAvatar, data.user);
      profileAvatar.style.width    = '80px';
      profileAvatar.style.height   = '80px';
      profileAvatar.style.fontSize = '32px';
      syncPresetHighlight();
      avatarPicker.classList.remove('open');
      showToast('Avatar updated');
    } catch (e) {
      showToast(e.message, true);
    }
  });
});

// Upload custom file
avatarUploadBtn.addEventListener('click', () => avatarFile.click());
avatarFile.addEventListener('change', async () => {
  const file = avatarFile.files[0];
  if (!file) return;
  const compressed = await compressImage(file, 400, 400, 0.9);
  const fd = new FormData();
  fd.append('avatar', compressed);
  try {
    const data = await apiFetch('users/me/avatar', { method: 'POST', body: fd });
    currentUser = data.user;
    updateAuthUI(data.user);
    setAvatarEl(profileAvatar, data.user);
    profileAvatar.style.width    = '80px';
    profileAvatar.style.height   = '80px';
    profileAvatar.style.fontSize = '32px';
    avatarPicker.classList.remove('open');
    showToast('Avatar updated');
  } catch (e) {
    showToast(e.message, true);
  } finally {
    avatarFile.value = '';
  }
});

saveProfileBtn.addEventListener('click', async () => {
  profileError.textContent = '';
  saveProfileBtn.disabled  = true;
  try {
    const data = await apiFetch('users/me', {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        display_name: pfDisplayName.value.trim(),
        email:        pfEmail.value.trim(),
        bio:          pfBio.value.trim(),
      }),
    });
    if (data.csrf_token) csrfToken = data.csrf_token;
    currentUser = data.user;
    updateAuthUI(data.user);
    verifyBanner.style.display = data.user.email_verified ? 'none' : 'block';
    showToast('Profile saved');
  } catch (e) {
    profileError.textContent = e.message;
  } finally {
    saveProfileBtn.disabled = false;
  }
});

deleteAcctBtn.addEventListener('click', async () => {
  if (!confirm('Delete your account and all your posts permanently? This cannot be undone.')) return;
  try {
    await apiFetch('users/me', { method: 'DELETE' });
    currentUser = null;
    composeEl.style.display        = 'none';
    sidebarUser.style.display      = 'none';
    logoutBtn.style.display        = 'none';
    navProfile.style.display       = 'none';
    navAdmin.style.display         = 'none';
    navNotifications.style.display = 'none';
    showView('home');
    showAuthModal('login');
    loadPosts(1, true);
  } catch (e) {
    showToast(e.message, true);
  }
});

// ── Admin view ────────────────────────────────────────────
let currentAdminTab = 'users';

function loadAdminView(tab) {
  currentAdminTab = tab;
  adminTabs.querySelectorAll('.feed-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  const userList = document.getElementById('admin-user-list');
  if (tab === 'users') {
    userList.style.display = '';
    adminSettingsPanel.style.display = 'none';
    loadAdminUsers();
  } else {
    userList.style.display = 'none';
    adminSettingsPanel.style.display = '';
    loadAdminSettings();
  }
}

adminTabs.addEventListener('click', e => {
  const tab = e.target.closest('.feed-tab');
  if (!tab || tab.dataset.tab === currentAdminTab) return;
  loadAdminView(tab.dataset.tab);
});

async function loadAdminSettings() {
  try {
    const data = await apiFetch('admin/settings');
    adminRmbDays.value = data.settings.remember_me_days;
    adminSettingsErr.textContent = '';
  } catch (e) {
    adminSettingsErr.textContent = e.message;
  }
}

adminSettingsSave.addEventListener('click', async () => {
  adminSettingsErr.textContent = '';
  adminSettingsSave.disabled = true;
  try {
    const data = await apiFetch('admin/settings', {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ remember_me_days: parseInt(adminRmbDays.value, 10) }),
    });
    adminRmbDays.value = data.settings.remember_me_days;
    showToast('Settings saved');
  } catch (e) {
    adminSettingsErr.textContent = e.message;
  } finally {
    adminSettingsSave.disabled = false;
  }
});

async function loadAdminUsers() {
  const list = document.getElementById('admin-user-list');
  list.innerHTML = '<div class="empty-state"><p>Loading…</p></div>';
  try {
    const data = await apiFetch('admin/users');
    renderAdminUsers(data.users);
  } catch (e) {
    list.innerHTML = `<div class="empty-state"><p>${esc(e.message)}</p></div>`;
  }
}

function renderAdminUsers(users) {
  const list = document.getElementById('admin-user-list');
  if (!users.length) {
    list.innerHTML = '<div class="empty-state"><p>No users found.</p></div>';
    return;
  }
  list.innerHTML = '';
  users.forEach(u => list.appendChild(renderAdminUserRow(u)));
}

function renderAdminUserRow(u) {
  const row = document.createElement('div');
  row.className   = 'admin-user';
  row.dataset.uid = u.id;

  const name    = u.display_name || u.username;
  const initial = name[0].toUpperCase();
  const avatarHtml = u.avatar
    ? `<div class="avatar"><img src="${esc(u.avatar)}" alt=""></div>`
    : `<div class="avatar" style="background:${avatarColor(u.username)}">${initial}</div>`;

  const badges = [
    u.is_admin  ? '<span class="badge badge-admin">Admin</span>'    : '',
    u.disabled  ? '<span class="badge badge-disabled">Disabled</span>' : '',
  ].join('');

  row.innerHTML = `
    ${avatarHtml}
    <div class="admin-user-info">
      <div class="admin-user-name">${esc(name)} ${badges}</div>
      <div class="admin-user-sub">@${esc(u.username)} · ${u.post_count} post${u.post_count !== 1 ? 's' : ''}</div>
    </div>
    <div class="admin-actions">
      <button class="admin-btn btn-edit">Edit</button>
      <button class="admin-btn ${u.disabled ? 'btn-enable' : 'btn-disable'}">${u.disabled ? 'Enable' : 'Disable'}</button>
      <button class="admin-btn btn-del" ${u.id === currentUser?.id ? 'disabled title="Cannot delete yourself"' : ''}>Delete</button>
    </div>`;

  row.querySelector('.btn-edit').addEventListener('click', () => openAdminEdit(u));

  row.querySelector(u.disabled ? '.btn-enable' : '.btn-disable').addEventListener('click', async () => {
    try {
      const data = await apiFetch(`admin/users/${u.id}`, {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ disabled: !u.disabled }),
      });
      const updated = data.user;
      updated.post_count = u.post_count;
      row.replaceWith(renderAdminUserRow(updated));
      showToast(updated.disabled ? 'User disabled' : 'User enabled');
    } catch (e) { showToast(e.message, true); }
  });

  row.querySelector('.btn-del').addEventListener('click', async () => {
    if (!confirm(`Delete @${u.username} and all their posts? This cannot be undone.`)) return;
    try {
      await apiFetch(`admin/users/${u.id}`, { method: 'DELETE' });
      row.style.transition = 'opacity 0.2s';
      row.style.opacity    = '0';
      setTimeout(() => row.remove(), 200);
      showToast('User deleted');
    } catch (e) { showToast(e.message, true); }
  });

  return row;
}

// Admin edit modal
function openAdminEdit(u) {
  adminEditUid.value   = u.id;
  adminEditTitle.textContent = `Edit @${u.username}`;
  adminEditDN.value    = u.display_name || '';
  adminEditBio.value   = u.bio || '';
  adminEditAdmin.checked = u.is_admin;
  adminEditDis.checked   = u.disabled;
  adminEditErr.textContent = '';

  const isSelf = u.id === currentUser?.id;
  adminEditAdmin.disabled = isSelf;
  adminEditDis.disabled   = isSelf;

  adminEditModal.classList.remove('hidden');
  adminEditDN.focus();
}

adminEditCancel.addEventListener('click', () => adminEditModal.classList.add('hidden'));
adminEditModal.addEventListener('click', e => { if (e.target === adminEditModal) adminEditModal.classList.add('hidden'); });

adminEditSave.addEventListener('click', async () => {
  adminEditErr.textContent = '';
  adminEditSave.disabled   = true;
  const tid = parseInt(adminEditUid.value);
  try {
    const data = await apiFetch(`admin/users/${tid}`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        display_name: adminEditDN.value.trim(),
        bio:          adminEditBio.value.trim(),
        is_admin:     adminEditAdmin.checked,
        disabled:     adminEditDis.checked,
      }),
    });
    adminEditModal.classList.add('hidden');
    showToast('User updated');
    const row = document.querySelector(`.admin-user[data-uid="${tid}"]`);
    if (row) {
      const updated = data.user;
      const sub = row.querySelector('.admin-user-sub')?.textContent || '';
      const match = sub.match(/(\d+) post/);
      updated.post_count = match ? parseInt(match[1]) : 0;
      row.replaceWith(renderAdminUserRow(updated));
    }
    if (tid === currentUser?.id) {
      currentUser = { ...currentUser, ...data.user };
      updateAuthUI(currentUser);
    }
  } catch (e) {
    adminEditErr.textContent = e.message;
  } finally {
    adminEditSave.disabled = false;
  }
});

// ── Multi-image compose helpers ───────────────────────────
function addFilesToCompose(files, imageFilesArr, previewEl, updateFn) {
  for (const file of files) {
    if (imageFilesArr.length >= 4) { showToast('Maximum 4 images per post', true); break; }
    imageFilesArr.push(file);
  }
  renderComposePreviews(imageFilesArr, previewEl, updateFn);
}

function clearComposeImages(imageFilesArr, previewEl, updateFn) {
  previewEl.querySelectorAll('img').forEach(img => URL.revokeObjectURL(img.src));
  imageFilesArr.length = 0;
  previewEl.innerHTML = '';
  previewEl.classList.add('hidden');
  if (updateFn) updateFn();
}

function renderComposePreviews(imageFilesArr, previewEl, updateFn) {
  previewEl.querySelectorAll('img').forEach(img => URL.revokeObjectURL(img.src));
  previewEl.innerHTML = '';
  if (imageFilesArr.length === 0) {
    previewEl.classList.add('hidden');
  } else {
    previewEl.classList.remove('hidden');
    imageFilesArr.forEach((file, idx) => {
      const url   = URL.createObjectURL(file);
      const thumb = document.createElement('div');
      thumb.className = 'compose-img-thumb';
      const img   = document.createElement('img');
      img.src = url;
      img.alt = '';
      const btn   = document.createElement('button');
      btn.className   = 'compose-img-thumb-remove';
      btn.title       = 'Remove';
      btn.textContent = '×';
      btn.addEventListener('click', () => {
        URL.revokeObjectURL(url);
        imageFilesArr.splice(idx, 1);
        renderComposePreviews(imageFilesArr, previewEl, updateFn);
      });
      thumb.appendChild(img);
      thumb.appendChild(btn);
      previewEl.appendChild(thumb);
    });
  }
  if (updateFn) updateFn();
}

// ── Photo grid (post image display) ──────────────────────
function renderPhotoGrid(imageUrls) {
  if (!imageUrls || imageUrls.length === 0) return '';
  const n = imageUrls.length;
  const items = imageUrls.map((url, i) =>
    `<div class="photo-grid-item" data-index="${i}"><img src="${esc(url)}" alt="Post image" loading="lazy"></div>`
  ).join('');
  return `<div class="photo-grid photo-grid-${n}">${items}</div>`;
}

// ── Lightbox ──────────────────────────────────────────────
function openLightbox(images, startIndex) {
  lightboxImages = images;
  lightboxIndex  = startIndex;
  updateLightbox();
  lightboxEl.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lightboxEl.classList.add('hidden');
  document.body.style.overflow = '';
  lightboxImg.src = '';
}

function lightboxNav(dir) {
  lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
  updateLightbox();
}

function updateLightbox() {
  lightboxImg.src = lightboxImages[lightboxIndex];
  const multi = lightboxImages.length > 1;
  lightboxCounter.textContent = multi ? `${lightboxIndex + 1} / ${lightboxImages.length}` : '';
  lightboxPrev.style.display  = multi ? '' : 'none';
  lightboxNext.style.display  = multi ? '' : 'none';
}

lightboxClose.addEventListener('click', closeLightbox);
lightboxEl.addEventListener('click', e => { if (e.target === lightboxEl) closeLightbox(); });
lightboxPrev.addEventListener('click', e => { e.stopPropagation(); lightboxNav(-1); });
lightboxNext.addEventListener('click', e => { e.stopPropagation(); lightboxNav(1); });

// ── Utilities ─────────────────────────────────────────────
async function compressImage(file, maxW = 1200, maxH = 1200, quality = 0.8) {
  if (!file || !file.type.startsWith('image/')) return file;
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        let w = img.width;
        let h = img.height;
        if (w > maxW || h > maxH) {
          if (w > h) { h *= maxW / w; w = maxW; }
          else { w *= maxH / h; h = maxH; }
        }
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        canvas.toBlob((blob) => {
          resolve(new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: 'image/jpeg' }));
        }, 'image/jpeg', quality);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

function parseRichText(text) {
  if (!text) return '';
  const s = esc(text);
  return s
    .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" class="post-link" target="_blank" rel="noopener">$1</a>')
    .replace(/@([a-zA-Z0-9_]+)/g, '<a href="#/user/$1" class="post-mention" onclick="event.stopPropagation()">@$1</a>')
    .replace(/#([a-zA-Z0-9_]+)/g, '<a href="#/search?q=%23$1" class="post-hashtag" onclick="event.stopPropagation()">#$1</a>');
}

function esc(str) {
  if (typeof str !== 'string') return str;
  const s = str
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
  return /^\s*javascript:/i.test(s) ? '#invalid' : s;
}

function timeAgo(ts) {
  const diff = Math.floor(Date.now() / 1000) - ts;
  if (diff < 60)        return `${diff}s`;
  if (diff < 3600)      return `${Math.floor(diff / 60)}m`;
  if (diff < 86400)     return `${Math.floor(diff / 3600)}h`;
  if (diff < 604800)    return `${Math.floor(diff / 86400)}d`;
  if (diff < 2592000)   return `${Math.floor(diff / 604800)}w`;
  if (diff < 31536000)  return `${Math.floor(diff / 2592000)}mo`;
  return `${Math.floor(diff / 31536000)}y`;
}

function avatarColor(username) {
  const colours = ['#1d9bf0','#7856ff','#ff7a00','#00ba7c','#f91880','#ffad1f'];
  let h = 0;
  for (let i = 0; i < username.length; i++) h = (h * 31 + username.charCodeAt(i)) & 0xffff;
  return colours[h % colours.length];
}

function heartSvg(filled) {
  return filled
    ? `<svg viewBox="0 0 24 24"><path fill="#f91880" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>`
    : `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>`;
}

function trashSvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;
}

function editSvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`;
}

function followSvg(following) {
  return following
    ? `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>`
    : `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>`;
}

function replySvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
}

function repostSvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`;
}

function quoteSvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M3 21l1-6 7-7 5 5-7 7-6 1zm4.5-8.5l4 4"/><path d="M13 3l5 5 2-2-5-5-2 2z"/><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>`;
}

function followNotifSvg() {
  return `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>`;
}

function showToast(msg, isError = false) {
  toastEl.textContent = msg;
  toastEl.className   = 'toast' + (isError ? ' error' : '');
  void toastEl.offsetWidth;
  toastEl.classList.add('show');
  setTimeout(() => toastEl.classList.remove('show'), 2800);
}
