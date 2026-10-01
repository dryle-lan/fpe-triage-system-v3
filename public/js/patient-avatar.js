// patient-avatar.js — a colored initials circle for a patient name.
// Colors are drawn from a palette that intentionally avoids red/orange/
// yellow/green, so an avatar's color is never mistaken for a triage color.
// Shared by dashboard.js (encounter cards) and intake.js (patient search
// and the selected-patient summary).

const AVATAR_PALETTE = ['#3949AB', '#00897B', '#7B1FA2', '#AD1457', '#6D4C41', '#455A64'];

function avatarColorFor(name) {
  const str = name || '?';
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
  }
  return AVATAR_PALETTE[hash % AVATAR_PALETTE.length];
}

function initialsFor(name) {
  if (!name) return '?';

  // Names are stored "Last, First" — prefer First+Last initials.
  const parts = name.split(',').map((s) => s.trim()).filter(Boolean);
  let first = '';
  let last = '';

  if (parts.length >= 2) {
    last = parts[0];
    first = parts[1];
  } else {
    const words = name.trim().split(/\s+/);
    first = words[0] || '';
    last = words[1] || '';
  }

  const a = first.charAt(0) || '';
  const b = last.charAt(0) || first.charAt(1) || '';
  const initials = (a + b).toUpperCase();
  return initials || '?';
}

function avatarHtml(name, sizeClass) {
  const cls = sizeClass ? `avatar ${sizeClass}` : 'avatar';
  return `<span class="${cls}" style="background:${avatarColorFor(name)}">${initialsFor(name)}</span>`;
}
