/* global window, document, localStorage, fetch */

console.log("✅ ECO RIDE APP.JS chargé =>", location.href);
window.__APPJS_LOADED__ = true;

// =====================
// Config API base URL
// =====================
const API_BASE = "/ecoride/backend/public";

// =====================
// Auth helper functions
// =====================
function getToken() {
  try {
    return (
      localStorage.getItem("access_token") ||
      sessionStorage.getItem("access_token") ||
      ""
    );
  } catch {
    return "";
  }
}


function setToken(token) {
  try {
    if (!token) return;
    localStorage.setItem("access_token", token);
    sessionStorage.setItem("access_token", token);
  } catch {}
}


function clearAuth() {
  try {
    localStorage.removeItem("access_token");
    localStorage.removeItem("user");
    sessionStorage.removeItem("access_token");
    sessionStorage.removeItem("user");
  } catch {}
}


function getCurrentUser() {
  try {
    const raw = localStorage.getItem("user");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function setCurrentUser(user) {
  try {
    if (user) localStorage.setItem("user", JSON.stringify(user));
  } catch {}
}

function isLoggedIn() {
  return !!getToken();
}

// Normalise le rôle utilisateur en string minuscule
function getUserRole(user) {
  if (!user) return null;

  const role =
    (typeof user.role === "string" && user.role) ||
    (Array.isArray(user.roles) && user.roles[0]) ||
    (typeof user.roles === "string" && user.roles) ||
    "";

  return role ? String(role).toLowerCase() : null;
}

// =====================
// API helper 
// =====================
async function api(path, opts = {}) {
  const url = `${API_BASE}${path}`;
  const token = getToken();

  // Headers
  const headers = new Headers(opts.headers || {});
  headers.set("Accept", "application/json");

  // Content-Type
  if (!(opts.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }

  if (token) headers.set("Authorization", `Bearer ${token}`);

  console.log("API CALL =>", url);
  console.log("TOKEN PRESENT ?", !!token);
  console.log("AUTH HEADER =>", headers.get("Authorization") || "(none)");

  const res = await fetch(url, {
    method: opts.method || "GET",
    body: opts.body,
    headers, // override headers
  });

  const text = await res.text();
  let data = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { ok: false, error: "Réponse non-JSON", raw: text };
  }

  // ⚠️ Gestion 401 (Unauthorized) spécifique
  if (res.status === 401) {
    console.warn("401 => token conservé (pas de clearAuth automatique)");
  }

  if (!res.ok) {
    const msg = data?.error || data?.message || `Erreur HTTP ${res.status} sur ${path}`;
    throw new Error(msg);
  }

  return data;
}


// =====================
// Navbar active link
// =====================
function setActiveNav() {
  document.querySelectorAll("header nav a").forEach((a) => {
    const href = a.getAttribute("href");
    if (!href) return;

    if (location.pathname.endsWith(href)) a.setAttribute("data-active", "");
    else a.removeAttribute("data-active");
  });
}

// =====================
// Auth links visibility
// =====================
function refreshAuthUI() {
  const loggedIn = isLoggedIn();
  const user = getCurrentUser();
  const role = getUserRole(user);

  // body classes
  document.body.classList.toggle("is-admin", loggedIn && role === "admin");
  document.body.classList.toggle(
    "is-employee",
    loggedIn && role === "employee"
  );

  const loginLink = document.getElementById("loginLink");
  const logoutLink = document.getElementById("logoutLink");
  const adminLink = document.getElementById("adminLink");
  const moderationLink = document.getElementById("moderationLink");
  const profileLink = document.getElementById("profileLink");

  if (loginLink) loginLink.style.display = loggedIn ? "none" : "";
  if (logoutLink) logoutLink.style.display = loggedIn ? "" : "none";
  if (profileLink) profileLink.style.display = loggedIn ? "" : "none";

  if (adminLink)
    adminLink.style.display = loggedIn && role === "admin" ? "" : "none";
  if (moderationLink) {
    moderationLink.style.display =
      loggedIn && (role === "admin" || role === "employee") ? "" : "none";
  }

  // Afficher l'email utilisateur
  const adminEmail = document.getElementById("adminEmail");
  if (adminEmail) {
    adminEmail.textContent =
      loggedIn && user?.email ? user.email : "Non connecté";
  }

  // Bind logout link (1 seule fois)
  if (logoutLink && !logoutLink.dataset.bound) {
    logoutLink.dataset.bound = "1";
    logoutLink.addEventListener("click", (e) => {
      e.preventDefault();
      clearAuth();
      alert("Vous avez été déconnecté(e).");
      location.href = "index.html";
    });
  }

  // Bind logout button (1 seule fois)
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn && !logoutBtn.dataset.bound) {
    logoutBtn.dataset.bound = "1";
    logoutBtn.addEventListener("click", () => {
      clearAuth();
      alert("Vous avez été déconnecté(e).");
      location.href = "index.html";
    });
  }
}

// =====================
// Toggle Login / Signup
// =====================
(function bindAuthToggle() {
  const loginForm = document.getElementById("loginForm");
  const signupForm = document.getElementById("signupForm");
  const showSignup = document.getElementById("showSignup");
  const showLogin = document.getElementById("showLogin");
  const title = document.getElementById("formTitle");

  if (!loginForm || !signupForm) return;

  const toSignup = (e) => {
    if (e) e.preventDefault();
    loginForm.style.display = "none";
    signupForm.style.display = "flex";
    if (title) title.textContent = "Créer un compte";
    const msg = document.getElementById("loginMsg");
    if (msg) msg.textContent = "";
  };

  const toLogin = (e) => {
    if (e) e.preventDefault();
    signupForm.style.display = "none";
    loginForm.style.display = "block";
    if (title) title.textContent = "Connexion";
    const msg = document.getElementById("signupMsg");
    if (msg) msg.textContent = "";
  };

  if (showSignup) showSignup.addEventListener("click", toSignup);
  if (showLogin) showLogin.addEventListener("click", toLogin);
})();

// =====================
// SIGNUP handler
// =====================
(function bindSignupForm() {
  const form = document.getElementById("signupForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const msg = document.getElementById("signupMsg");
    if (msg) msg.textContent = "Création du compte...";

    const data = Object.fromEntries(new FormData(form).entries());

    // Vérif mot de passe côté front (en plus de l’API)
    if ((data.password || "") !== (data.password_confirm || "")) {
      if (msg) msg.textContent = "Les mots de passe ne correspondent pas.";
      return;
    }

    try {
      const res = await fetch("/ecoride/backend/public/api/register.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const out = await res.json().catch(() => ({}));

      if (!res.ok || out.ok === false || out.success === false) {
        if (msg) msg.textContent = out.message || "Erreur lors de la création du compte.";
        return;
      }

      if (msg) msg.textContent = out.message || "Compte créé !";

      // Retour auto vers connexion
      setTimeout(() => {
        const loginForm = document.getElementById("loginForm");
        const signupForm = document.getElementById("signupForm");
        const title = document.getElementById("formTitle");
        if (signupForm) signupForm.style.display = "none";
        if (loginForm) loginForm.style.display = "block";
        if (title) title.textContent = "Connexion";
        form.reset();
      }, 700);

    } catch (err) {
      console.error(err);
      if (msg) msg.textContent = "Erreur réseau. Vérifie que l’API répond.";
    }
  });
})();


// =====================
// On DOM ready
// =====================
document.addEventListener("DOMContentLoaded", () => {
  setActiveNav();
  refreshAuthUI();
});

// =====================
// LOGIN form submission (login.html)
// =====================
(function bindLoginForm() {
  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  if (loginForm.dataset.bound) return;
  loginForm.dataset.bound = "1";

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const msg = document.getElementById("loginMsg");
    if (msg) msg.textContent = "Connexion...";

    try {
      const data = Object.fromEntries(new FormData(loginForm).entries());

      const res = await api("/api/login.php", {
        method: "POST",
        body: JSON.stringify(data),
      });

      const token = res.access_token || res.token || res.accessToken || "";
if (token) setToken(token);

if (res.user) setCurrentUser(res.user);


      refreshAuthUI();

      if (msg) msg.textContent = res.message || "Connecté.";

      setTimeout(() => {
        const role = getUserRole(res.user);
        if (role === "admin") location.href = "admin.html";
        else if (role === "employee") location.href = "moderation.html";
        else location.href = "index.html";
      }, 300);
    } catch (err) {
      console.error(err);
      if (msg) msg.textContent = err.message || "Erreur de connexion";
    }
  });
})();

// =============================
// Create Employee form submission (admin.html)
// =============================
document.addEventListener("click", async (e) => {
  const btn = e.target.closest("#btnCreateEmployee");
  if (!btn) return;

  const msg = document.getElementById("createEmployeeMsg");

  const full_name = document.getElementById("empName")?.value.trim() || "";
  const email = document.getElementById("empEmail")?.value.trim() || "";
  const password = document.getElementById("empPassword")?.value || "";

  if (msg) msg.textContent = "";

  if (!full_name || !email || !password) {
    if (msg) msg.textContent = "❌ Remplis tous les champs.";
    return;
  }

  try {
    if (msg) msg.textContent = "⏳ Création en cours…";

    const data = await api("/api/admin_create_employee.php", {
      method: "POST",
      body: JSON.stringify({ full_name, email, password }),
    });

    if (!data || data.ok === false) {
      if (msg)
        msg.textContent =
          "❌ " + (data?.error || data?.message || "Erreur inconnue");
      return;
    }

    if (msg) msg.textContent = "✅ Employé créé.";

    // reset champs
    const n = document.getElementById("empName");
    const em = document.getElementById("empEmail");
    const pw = document.getElementById("empPassword");
    if (n) n.value = "";
    if (em) em.value = "";
    if (pw) pw.value = "";

    // recharger la liste des utilisateurs (si présente) 
    if (typeof window.loadUsers === "function") window.loadUsers();
  } catch (err) {
    console.error(err);
    if (msg) msg.textContent = "❌ " + (err.message || "Erreur réseau");
  }
});

// =====================
// Expose functions to global scope
// =====================
window.api = api;
window.isLoggedIn = isLoggedIn;
window.getCurrentUser = getCurrentUser;
window.getUserRole = getUserRole;
window.refreshAuthUI = refreshAuthUI;
window.getToken = getToken;
window.clearAuth = clearAuth;
