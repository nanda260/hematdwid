document.addEventListener('DOMContentLoaded', function () {
  initWebauthnRegister();
  initWebauthnLogin();
});

function bufferToBase64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  for (const b of bytes) str += String.fromCharCode(b);
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function base64urlToBuffer(base64url) {
  const padding = '='.repeat((4 - (base64url.length % 4)) % 4);
  const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const buffer = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) buffer[i] = raw.charCodeAt(i);
  return buffer.buffer;
}

function initWebauthnRegister() {
  var btn = document.getElementById('btnRegisterFingerprint');
  var statusEl = document.getElementById('webauthnRegisterStatus');
  if (!btn) return;

  if (!window.PublicKeyCredential) {
    btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Perangkat/browser ini tidak mendukung WebAuthn.';
    return;
  }

  btn.addEventListener('click', async function () {
    btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Menunggu Biometrik...';

    try {
      const optionsRes = await fetch('api/webauthn-register-options.php');
      const options = await optionsRes.json();

      options.challenge = base64urlToBuffer(options.challenge);
      options.user.id = base64urlToBuffer(options.user.id);
      options.excludeCredentials = (options.excludeCredentials || []).map(function (c) {
        return { ...c, id: base64urlToBuffer(c.id) };
      });

      const credential = await navigator.credentials.create({ publicKey: options });

      const payload = {
        id: credential.id,
        response: {
          clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
          attestationObject: bufferToBase64url(credential.response.attestationObject),
        },
      };

      const verifyRes = await fetch('api/webauthn-register-verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const result = await verifyRes.json();

      if (statusEl) statusEl.textContent = result.message;
      if (result.success) {
        setTimeout(function () { window.location.reload(); }, 1000);
      } else {
        btn.disabled = false;
      }
    } catch (err) {
      if (statusEl) statusEl.textContent = 'Dibatalkan atau gagal: ' + err.message;
      btn.disabled = false;
    }
  });
}

function initWebauthnLogin() {
  var btn = document.getElementById('btnLoginFingerprint');
  var statusEl = document.getElementById('webauthnLoginStatus');
  if (!btn) return;

  if (!window.PublicKeyCredential) {
    btn.style.display = 'none';
    return;
  }

  btn.addEventListener('click', async function () {
    btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Menunggu Biometrik...';

    try {
      const optionsRes = await fetch('api/webauthn-login-options.php', {
        method: 'POST',
      });
      const options = await optionsRes.json();

      if (!optionsRes.ok) {
        throw new Error(options.message || 'Gagal memuat opsi login.');
      }

      options.challenge = base64urlToBuffer(options.challenge);
      // Usernameless: jangan kirim allowCredentials kosong, biarkan browser
      // menampilkan akun yang tersimpan di sensor (discoverable credential)
      delete options.allowCredentials;

      const assertion = await navigator.credentials.get({ publicKey: options });

      const payload = {
        id: assertion.id,
        response: {
          clientDataJSON: bufferToBase64url(assertion.response.clientDataJSON),
          authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
          signature: bufferToBase64url(assertion.response.signature),
        },
      };

      const verifyRes = await fetch('api/webauthn-login-verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const result = await verifyRes.json();

      if (result.success) {
        if (statusEl) statusEl.textContent = 'Berhasil, mengalihkan...';
        window.location.href = result.redirect || 'dashboard.php';
      } else {
        if (statusEl) statusEl.textContent = result.message;
        btn.disabled = false;
      }
    } catch (err) {
      if (statusEl) statusEl.textContent = 'Dibatalkan atau gagal: ' + err.message;
      btn.disabled = false;
    }
  });
}