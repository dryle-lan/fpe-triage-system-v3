// users.js — admin Users page: list accounts, create, activate/deactivate

const grid = document.getElementById('accounts-grid');
const overlay = document.getElementById('new-user-overlay');
const form = document.getElementById('new-user-form');
const formError = document.getElementById('new-user-error');
const cancelBtn = document.getElementById('cancel-new-user-btn');

const ROLE_LABELS = { health_worker: 'Health worker', doctor: 'Doctor', admin: 'Admin' };

async function loadAccounts() {
  grid.innerHTML = '<p class="empty-state">Loading accounts…</p>';

  try {
    const res = await fetch('api/users.php');
    const users = await res.json();

    if (!res.ok) {
      grid.innerHTML = '<p class="empty-state">Failed to load accounts.</p>';
      return;
    }

    const createCard = `
      <button type="button" class="create-mission-card" id="open-new-user-btn">
        <span class="plus">+</span>
        <span>Create account</span>
      </button>`;

    grid.innerHTML = createCard + users.map(renderAccountCard).join('');

    document.getElementById('open-new-user-btn').addEventListener('click', openModal);
    wireCardEvents();
  } catch (err) {
    grid.innerHTML = '<p class="empty-state">Could not reach the server.</p>';
  }
}

function renderAccountCard(u) {
  const isSelf = window.CURRENT_USER && Number(window.CURRENT_USER.id) === Number(u.id);
  const toggleLabel = u.is_active ? 'Deactivate account' : 'Activate account';
  const toggleItem = isSelf
    ? ''
    : `<button type="button" class="${u.is_active ? 'danger' : ''}" data-toggle-id="${u.id}" data-next-active="${!u.is_active}">${toggleLabel}</button>`;

  return `
    <div class="account-card ${u.is_active ? '' : 'is-inactive'}">
      <div class="account-card-top">
        <div>
          <p class="account-card-name">${escapeHtml(u.name)}${isSelf ? ' (you)' : ''}</p>
          <p class="account-card-email">${escapeHtml(u.email)}</p>
        </div>
      </div>
      <div class="account-card-badges">
        <span class="badge badge-role">${ROLE_LABELS[u.role] || u.role}</span>
        <span class="badge ${u.is_active ? 'badge-active' : 'badge-inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span>
      </div>
      <div class="account-card-actions">
        <div class="mission-menu">
          <button type="button" class="btn-icon" aria-label="More actions" data-menu-toggle>&#8942;</button>
          <div class="mission-menu-list">
            ${toggleItem || '<span style="display:block;padding:0.65rem 0.9rem;color:var(--color-text-muted);font-size:0.85rem;">No actions</span>'}
          </div>
        </div>
      </div>
    </div>
  `;
}

function wireCardEvents() {
  document.querySelectorAll('[data-menu-toggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = btn.closest('.mission-menu');
      document.querySelectorAll('.mission-menu.is-open').forEach((m) => {
        if (m !== menu) m.classList.remove('is-open');
      });
      menu.classList.toggle('is-open');
    });
  });

  document.querySelectorAll('[data-toggle-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const nextActive = btn.dataset.nextActive === 'true';
      toggleActive(btn.dataset.toggleId, nextActive);
    });
  });
}

document.addEventListener('click', () => {
  document.querySelectorAll('.mission-menu.is-open').forEach((m) => m.classList.remove('is-open'));
});

async function toggleActive(id, nextActive) {
  const verb = nextActive ? 'Activate' : 'Deactivate';
  if (!confirm(`${verb} this account?`)) return;

  try {
    const res = await fetch(`api/users.php?id=${encodeURIComponent(id)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ is_active: nextActive }),
    });
    const data = await res.json();

    if (!res.ok) {
      alert(data.error || (data.errors && Object.values(data.errors).join(', ')) || 'Failed to update account.');
      return;
    }

    loadAccounts();
  } catch (err) {
    alert('Could not reach the server.');
  }
}

// ---------------------------------------------------------------------
// Create account modal
// ---------------------------------------------------------------------
function openModal() {
  formError.hidden = true;
  form.reset();
  overlay.hidden = false;
  document.getElementById('u_name').focus();
}

function closeModal() {
  overlay.hidden = true;
}

cancelBtn.addEventListener('click', closeModal);
overlay.addEventListener('click', (e) => {
  if (e.target === overlay) closeModal();
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  formError.hidden = true;

  const payload = {
    name: document.getElementById('u_name').value,
    email: document.getElementById('u_email').value,
    password: document.getElementById('u_password').value,
    role: document.querySelector('input[name="u_role"]:checked').value,
  };

  try {
    const res = await fetch('api/users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) {
      const message = data.errors ? Object.values(data.errors).join(', ') : data.error || 'Failed to create account.';
      formError.textContent = message;
      formError.hidden = false;
      return;
    }

    closeModal();
    loadAccounts();
  } catch (err) {
    formError.textContent = 'Could not reach the server.';
    formError.hidden = false;
  }
});

document.getElementById('logout-btn').addEventListener('click', async () => {
  await fetch('api/auth.php', { method: 'DELETE' });
  window.location.href = 'login.html';
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

loadAccounts();
