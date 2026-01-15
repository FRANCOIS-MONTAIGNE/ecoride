/* global window, document, localStorage, api, isLoggedIn, getCurrentUser, getUserRole */

// Guard page access (only admin + employee) 
(function guardModerationPage() {
  const logged =
    typeof isLoggedIn === "function"
      ? isLoggedIn()
      : !!localStorage.getItem("access_token");

  const user = typeof getCurrentUser === "function" ? getCurrentUser() : null;
  const role =
    typeof getUserRole === "function"
      ? getUserRole(user)
      : String(user?.role || "").toLowerCase().trim();

  const allowed = role === "admin" || role === "employee";

  if (!logged || !user || !allowed) {
    alert("Accès réservé aux employés et à l’administrateur.");
    window.location.replace("login.html");
  }
})();

// =====================
// Message helpers
// =====================
function setMsg(text, isError = false) {
  const el = document.getElementById("modMsg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "crimson" : "";
}

function setCreditMsg(text, isError = false) {
  const el = document.getElementById("creditMsg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "crimson" : "";
}

function setCreditTableMsg(text, isError = false) {
  const el = document.getElementById("creditTableMsg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "crimson" : "";
}

// =====================
// Feedbacks table
// =====================
async function loadFeedbacks() {
  const tbody = document.querySelector("#feedbacksTable tbody");
  const status = document.getElementById("statusFilter")?.value || "pending";
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="10">Chargement…</td></tr>`;

  try {
    setMsg("⏳ Chargement…");
    const res = await api(
      `/api/mod_feedbacks.php?status=${encodeURIComponent(status)}`,
      { method: "GET" }
    );

    if (!res || res.ok === false) {
      tbody.innerHTML = `<tr><td colspan="10">${res?.error || "Erreur"}</td></tr>`;
      setMsg(res?.error || "Erreur", true);
      return;
    }

    const items = Array.isArray(res.feedbacks) ? res.feedbacks : [];
    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="10">Aucun avis.</td></tr>`;
      setMsg("");
      return;
    }

    tbody.innerHTML = "";
    items.forEach((f) => {
      const tr = document.createElement("tr");
      const canModerate = f.status === "pending";

      tr.innerHTML = `
        <td>${f.id ?? ""}</td>
        <td>#${f.trip_id ?? ""}</td>
        <td>${f.user_name ?? ""}</td>
        <td>${f.user_email ?? ""}</td>
        <td>${(f.comment ?? "").toString().slice(0, 200)}</td>
        <td>${f.status ?? ""}</td>
        <td>${f.created_at ?? ""}</td>
        <td>${f.moderated_by_name ?? ""}</td>
        <td>${f.moderated_at ?? ""}</td>
        <td>
          ${
            canModerate
              ? `
            <button type="button" data-action="approve" data-id="${f.id}">Valider</button>
            <button type="button" data-action="reject" data-id="${f.id}">Rejeter</button>
          `
              : `<span style="opacity:.7;">—</span>`
          }
        </td>
      `;
      tbody.appendChild(tr);
    });

    setMsg("✅ OK");
    setTimeout(() => setMsg(""), 800);
  } catch (err) {
    console.error(err);
    tbody.innerHTML = `<tr><td colspan="10">Erreur chargement</td></tr>`;
    setMsg("❌ " + (err.message || "Erreur réseau"), true);
  }
}

// Approve / Reject buttons
document.addEventListener("click", async (e) => {
  const btn = e.target.closest("button[data-action]");
  if (!btn) return;

  const action = btn.dataset.action;
  const id = Number(btn.dataset.id || 0);
  if (!id) return;

  const newStatus =
    action === "approve" ? "ok" : action === "reject" ? "issue" : null;
  if (!newStatus) return;

  const confirmText =
    newStatus === "ok"
      ? `Valider l'avis #${id} ?`
      : `Rejeter / signaler l'avis #${id} ?`;

  if (!confirm(confirmText)) return;

  try {
    setMsg("⏳ Mise à jour…");
    const res = await api("/api/mod_feedback_update.php", {
      method: "POST",
      body: JSON.stringify({ id, status: newStatus }),
    });

    if (!res || res.ok === false) {
      setMsg("❌ " + (res?.error || "Erreur"), true);
      return;
    }

    setMsg("✅ Modération enregistrée");
    await loadFeedbacks();
  } catch (err) {
    console.error(err);
    setMsg("❌ " + (err.message || "Erreur réseau"), true);
  }
});

// =====================
// Credits table
// =====================
async function loadCreditsTable() {
  const tbody = document.querySelector("#creditsTable tbody");
  if (!tbody) return;

  // get user filter
  const uid = Number(document.getElementById("creditUserId")?.value || 0);

  tbody.innerHTML = `<tr><td colspan="6">Chargement…</td></tr>`;

  try {
    setCreditTableMsg("⏳ Chargement…");
    const qs = uid > 0
      ? `?user_id=${encodeURIComponent(uid)}&limit=50`
      : `?limit=50`;

    const res = await api(`/api/admin_list_credits.php${qs}`, { method: "GET" });

    if (!res || res.ok === false) {
      tbody.innerHTML = `<tr><td colspan="6">${res?.error || "Erreur"}</td></tr>`;
      setCreditTableMsg(res?.error || "Erreur", true);
      return;
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="6">Aucun mouvement.</td></tr>`;
      setCreditTableMsg("");
      return;
    }

    tbody.innerHTML = "";
    for (const c of items) {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${c.id ?? ""}</td>
        <td>#${c.user_id ?? ""} ${c.user_email ?? ""}</td>
        <td>${c.amount ?? ""}</td>
        <td>${String(c.note ?? "").slice(0, 120)}</td>
        <td>${c.by_name ?? ""}</td>
        <td>${c.created_at ?? ""}</td>
      `;
      tbody.appendChild(tr);
    }

    setCreditTableMsg("✅ OK");
    setTimeout(() => setCreditTableMsg(""), 800);
  } catch (err) {
    console.error(err);
    tbody.innerHTML = `<tr><td colspan="6">Erreur chargement crédits</td></tr>`;
    setCreditTableMsg("❌ " + (err.message || "Erreur réseau"), true);
  }
}

// =====================
// Credits adjustment tool
// =====================
function bindCreditsTool() {
  const btn = document.getElementById("btnAdjustCredits");
  if (!btn || btn.dataset.bound) return;
  btn.dataset.bound = "1";

  btn.addEventListener("click", async () => {
    const userId = Number(document.getElementById("creditUserId")?.value || 0);
    const amount = Number(document.getElementById("creditAmount")?.value || 0);
    const note = String(document.getElementById("creditNote")?.value || "").trim();

    if (!userId || !amount || !note) {
      setCreditMsg("⚠️ userId, montant et raison obligatoires", true);
      return;
    }

    try {
      setCreditMsg("⏳ Traitement...");
      const res = await api("/api/admin_adjust_credits.php", {
        method: "POST",
        body: JSON.stringify({ user_id: userId, amount, note }),
      });

      if (!res || res.ok === false) {
        setCreditMsg("❌ " + (res?.error || res?.details || "Erreur"), true);
        return;
      }

      setCreditMsg("✅ Nouveau solde: " + (res.new_balance ?? "?"));

      // clear form fields 
      const a = document.getElementById("creditAmount");
      const n = document.getElementById("creditNote");
      if (a) a.value = "";
      if (n) n.value = "";

      //  reload table 
      await loadCreditsTable();

      setTimeout(() => setCreditMsg(""), 1500);
    } catch (e2) {
      setCreditMsg("❌ " + (e2.message || "Erreur réseau"), true);
    }
  });
}

// =====================
// Initialization 
// =====================
document.addEventListener("DOMContentLoaded", () => {
  const u = typeof getCurrentUser === "function" ? getCurrentUser() : null;

  // display user email
  const emailEl = document.getElementById("modUserEmail");
  if (emailEl) emailEl.textContent = u?.email || "Non connecté";

  // bind feedbacks refresh + filter
  document.getElementById("btnRefresh")?.addEventListener("click", loadFeedbacks);
  document.getElementById("statusFilter")?.addEventListener("change", loadFeedbacks);

  // bind credits tool
  bindCreditsTool();

  // bind credits table refresh
  const btnRefreshCredits = document.getElementById("btnRefreshCredits");
  if (btnRefreshCredits && !btnRefreshCredits.dataset.bound) {
    btnRefreshCredits.dataset.bound = "1";
    btnRefreshCredits.addEventListener("click", loadCreditsTable);
  }

  // hide credits section for non-admin/employee
  const role = getUserRole(u);
  const section = document.getElementById("creditsSection");
  if (section && role !== "admin" && role !== "employee") {
    section.style.display = "none";
  }

  // initial load
  loadFeedbacks();
  loadCreditsTable();
});
