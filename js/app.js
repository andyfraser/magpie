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
const loginBtn       = document.getElementById('login-btn');
const signupUsername = document.getElementById('signup-username');
const signupPassword = document.getElementById('signup-password');
const signupConfirm  = document.getElementById('signup-confirm');
const signupError    = document.getElementById('signup-error');
const signupBtn      = document.getElementById('signup-btn');
const toSignup       = document.getElementById('to-signup');
const toLogin        = document.getElementById('to-login');

const composeEl      = document.getElementById('compose');
const postInput      = document.getElementById('post-input');
const charRing       = document.getElementById('char-ring');
const charRingFill   = document.getElementById('char-ring-fill');
const charRemaining  = document.getElementById('char-remaining');
const submitBtn      = document.getElementById('submit-btn');
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

const feed           = document.getElementById('feed');
const loadMoreWrap   = document.getElementById('load-more-wrap');
const loadMoreBtn    = document.getElementById('load-more-btn');
const toastEl        = document.getElementById('toast');

// Following/People view
const followingTabs      = document.getElementById('following-tabs');
const userSearchWrap     = document.getElementById('user-search-wrap');
const userSearchInput    = document.getElementById('user-search-input');
const usersList          = document.getElementById('users-list');
let currentFollowingTab  = 'following';

// Profile view
const profileAvatar  = document.getElementById('profile-avatar');
const avatarUpload   = document.getElementById('avatar-upload');
const avatarFile     = document.getElementById('avatar-file');
const pfDisplayName  = document.getElementById('pf-display-name');
const pfUsername     = document.getElementById('pf-username');
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

// ── State ─────────────────────────────────────────────────
let currentUser  = null;
let currentPage  = 1;
let totalPages   = 1;
let isSubmitting = false;
let currentFeed  = 'for-you';

// ── Boot ──────────────────────────────────────────────────
(async () => {
  try {
    const data = await apiFetch('auth/me');
    if (data.user) onLogin(data.user);
    else           showAuthModal('login');
  } catch {
    showAuthModal('login');
  }
  loadPosts(1, true);
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
  const res  = await fetch('api.php/' + path, { credentials: 'same-origin', ...opts });
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
  if (name === 'profile')   initProfileView();
  if (name === 'liked')     loadLikedPosts(1, true);
  if (name === 'following') loadUsers();
  if (name === 'admin')     loadAdminUsers();
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
      // If we are in the following tab and just unfollowed, remove from list
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
  if (view === 'signup') {
    loginView.style.display  = 'none';
    signupView.style.display = '';
    signupUsername.focus();
  } else {
    loginView.style.display  = '';
    signupView.style.display = 'none';
    loginUsernameI.focus();
  }
}

function hideAuthModal() { authModal.classList.add('hidden'); }

toSignup.addEventListener('click', () => showAuthModal('signup'));
toLogin.addEventListener('click',  () => showAuthModal('login'));
loginPasswordI.addEventListener('keydown', e => { if (e.key === 'Enter') loginBtn.click(); });
signupConfirm.addEventListener('keydown',  e => { if (e.key === 'Enter') signupBtn.click(); });

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
      body:    JSON.stringify({ username, password }),
    });
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
  const password = signupPassword.value;
  const confirm  = signupConfirm.value;
  if (!username || !password) { signupError.textContent = 'Fill in all fields'; return; }
  if (password !== confirm)   { signupError.textContent = 'Passwords do not match'; return; }
  signupBtn.disabled = true;
  try {
    const data = await apiFetch('auth/signup', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ username, password }),
    });
    onLogin(data.user);
    loadPosts(1, true);
  } catch (e) {
    signupError.textContent = e.message;
  } finally {
    signupBtn.disabled = false;
  }
});

logoutBtn.addEventListener('click', async () => {
  await apiFetch('auth/logout', { method: 'POST' }).catch(() => {});
  currentUser = null;
  currentFeed = 'for-you';
  document.querySelectorAll('.feed-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'for-you'));
  composeEl.style.display   = 'none';
  sidebarUser.style.display = 'none';
  logoutBtn.style.display   = 'none';
  navProfile.style.display  = 'none';
  navLiked.style.display    = 'none';
  navFollowing.style.display = 'none';
  navAdmin.style.display    = 'none';
  loginUsernameI.value = loginPasswordI.value = '';
  loginError.textContent = '';
  showAuthModal('login');
  showView('home');
  loadPosts(1, true);
});

// ── On login ──────────────────────────────────────────────
function onLogin(user) {
  currentUser = user;
  hideAuthModal();
  updateAuthUI(user);
  composeEl.style.display   = '';
  sidebarUser.style.display = '';
  logoutBtn.style.display   = '';
  navProfile.style.display  = '';
  navLiked.style.display    = '';
  navFollowing.style.display = '';
  navAdmin.style.display    = user.is_admin ? '' : 'none';
  postInput.focus();
}

function updateAuthUI(user) {
  const name    = user.display_name || user.username;
  const initial = name[0].toUpperCase();
  const color   = avatarColor(user.username);

  // Sidebar
  sidebarDN.textContent     = name;
  sidebarHandle.textContent = '@' + user.username;
  setAvatarEl(sidebarAvatar, user);

  // Compose
  setAvatarEl(composeAvatar, user);
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

// ── Compose ───────────────────────────────────────────────
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

  submitBtn.disabled = len === 0 || left < 0 || isSubmitting;

  charRingFill.setAttribute('stroke-dashoffset', CIRC * (1 - pct));
  charRing.setAttribute('class', 'char-ring' + (left < 0 ? ' danger' : left < 20 ? ' warn' : ''));

  charRemaining.textContent = left <= 20 ? left : '';
  charRemaining.className   = 'char-remaining' + (left < 0 ? ' danger' : '');
}

updateCharCount();

async function submitPost() {
  if (isSubmitting || !currentUser) return;
  const body = postInput.value.trim();
  if (!body) return;

  isSubmitting = submitBtn.disabled = true;
  try {
    const post = await apiFetch('posts', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ body }),
    });
    postInput.value = '';
    updateCharCount();
    const empty = feed.querySelector('.empty-state');
    if (empty) empty.remove();
    feed.insertBefore(renderPost(post), feed.firstChild);
  } catch (e) {
    showToast(e.message, true);
  } finally {
    isSubmitting = false;
    updateCharCount();
  }
}

// ── Feed ──────────────────────────────────────────────────
async function loadPosts(page, replace) {
  loadMoreBtn.disabled = true;
  try {
    const feedParam = currentFeed === 'following' ? '&feed=following' : '';
    const data = await apiFetch(`posts?page=${page}${feedParam}`);
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
  } catch (e) {
    showToast(e.message, true);
  } finally {
    loadMoreBtn.disabled = false;
  }
}

function renderPost(post) {
  const div = document.createElement('div');
  div.className  = 'post';
  div.dataset.id = post.id;

  const displayName = post.display_name || post.username;

  div.innerHTML = `
    ${postAvatarHtml(post)}
    <div class="post-content">
      <div class="post-meta">
        <span class="post-username">${esc(displayName)}</span>
        <span class="post-handle">@${esc(post.username)}</span>
        <span class="post-sep">·</span>
        <span class="post-time" title="${new Date(post.created_at * 1000).toLocaleString()}">${timeAgo(post.created_at)}</span>
      </div>
      <div class="post-body">${esc(post.body)}</div>
      <div class="post-actions">
        <button class="action-btn like-btn ${post.liked ? 'liked' : ''}" data-id="${post.id}">
          ${heartSvg(post.liked)}
          <span class="like-count">${post.likes > 0 ? post.likes : ''}</span>
        </button>
        ${!post.own && currentUser ? `<button class="action-btn follow-btn ${post.following ? 'following' : ''}" data-username="${esc(post.username)}" title="${post.following ? 'Unfollow' : 'Follow'} @${esc(post.username)}">${followSvg(post.following)}</button>` : ''}
        ${post.own ? `<button class="action-btn delete-btn" data-id="${post.id}">${trashSvg()}</button>` : ''}
      </div>
    </div>`;

  div.querySelector('.like-btn').addEventListener('click', () => {
    if (!currentUser) { showToast('Sign in to like posts', true); return; }
    toggleLike(post.id, div);
  });
  div.querySelector('.follow-btn')?.addEventListener('click', () => toggleFollow(post.username));
  div.querySelector('.delete-btn')?.addEventListener('click', () => deletePost(post.id, div));

  return div;
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
    // Update all follow buttons for this user across the feed
    document.querySelectorAll(`.follow-btn[data-username="${username}"]`).forEach(btn => {
      btn.classList.toggle('following', data.following);
      btn.title = (data.following ? 'Unfollow' : 'Follow') + ' @' + username;
      btn.innerHTML = followSvg(data.following);
    });
    // If on following tab and just unfollowed, reload feed
    if (currentFeed === 'following' && !data.following) loadPosts(1, true);
    showToast(data.following ? `Following @${username}` : `Unfollowed @${username}`);
  } catch (e) { showToast(e.message, true); }
}

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

// ── Profile view ──────────────────────────────────────────
function initProfileView() {
  if (!currentUser) return;
  const u = currentUser;
  pfUsername.value     = u.username;
  pfDisplayName.value  = u.display_name || '';
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

avatarUpload.addEventListener('click', () => avatarFile.click());
avatarFile.addEventListener('change', async () => {
  const file = avatarFile.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const data = await apiFetch('users/me/avatar', { method: 'POST', body: fd });
    currentUser = data.user;
    updateAuthUI(data.user);
    setAvatarEl(profileAvatar, data.user);
    profileAvatar.style.width    = '80px';
    profileAvatar.style.height   = '80px';
    profileAvatar.style.fontSize = '32px';
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
        bio:          pfBio.value.trim(),
      }),
    });
    currentUser = data.user;
    updateAuthUI(data.user);
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
    composeEl.style.display   = 'none';
    sidebarUser.style.display = 'none';
    logoutBtn.style.display   = 'none';
    navProfile.style.display  = 'none';
    navAdmin.style.display    = 'none';
    showView('home');
    showAuthModal('login');
    loadPosts(1, true);
  } catch (e) {
    showToast(e.message, true);
  }
});

// ── Admin view ────────────────────────────────────────────
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
      // Replace row in place
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

  // Prevent self-lockout
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
    // Refresh the row
    const row = document.querySelector(`.admin-user[data-uid="${tid}"]`);
    if (row) {
      const updated = data.user;
      // Restore post_count from existing row
      const sub = row.querySelector('.admin-user-sub')?.textContent || '';
      const match = sub.match(/(\d+) post/);
      updated.post_count = match ? parseInt(match[1]) : 0;
      row.replaceWith(renderAdminUserRow(updated));
    }
    // If editing self, update local state
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

// ── Utilities ─────────────────────────────────────────────
function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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

function followSvg(following) {
  return following
    ? `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>`
    : `<svg viewBox="0 0 24 24" style="fill:none;stroke:currentColor;stroke-width:1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>`;
}

function showToast(msg, isError = false) {
  toastEl.textContent = msg;
  toastEl.className   = 'toast' + (isError ? ' error' : '');
  void toastEl.offsetWidth;
  toastEl.classList.add('show');
  setTimeout(() => toastEl.classList.remove('show'), 2800);
}
