/* global document */

if (!window.getToken || !getToken()) {
  location.href = "login.html";
}


document.getElementById("btnFixAuth")?.addEventListener("click", () => {
  // ouvre login dans le même onglet, pour recréer token
  location.href = "login.html";
});


document.addEventListener("DOMContentLoaded", async () => {
  const list = document.getElementById("messagesList");
  const msgBox = document.getElementById("msgBox");

  const show = (text) => {
    msgBox.textContent = text;
  };

  try {
    // Utilise ta fonction api() de app.js (avec token)
    const res = await api("/api/admin_contact_list.php");
    const items = res.items || [];

    if (!items.length) {
      list.innerHTML = "<p>Aucun message.</p>";
      return;
    }

    list.innerHTML = items.map((m) => {
      const safeMsg = (m.message || "").replace(/</g, "&lt;").replace(/>/g, "&gt;");
      return `
        <div style="background:#fff;border-radius:12px;padding:14px;margin:12px 0;box-shadow:0 8px 18px rgba(0,0,0,.08)">
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
              <strong>#${m.id}</strong> — ${m.name} — <em>${m.email}</em><br/>
              <small>${m.created_at}</small>
            </div>
            <button data-id="${m.id}" class="btnDel" style="border:none;border-radius:10px;padding:10px 12px;cursor:pointer">
              Supprimer
            </button>
          </div>
          <div style="margin-top:10px;white-space:pre-wrap">${safeMsg}</div>
        </div>
      `;
    }).join("");

    // delete
    document.querySelectorAll(".btnDel").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = Number(btn.dataset.id);
        if (!id) return;

        show("Suppression...");
        try {
          await api("/api/admin_contact_delete.php", {
            method: "POST",
            body: JSON.stringify({ id }),
          });
          show("Supprimé ✅");
          // refresh simple
          btn.closest("div").parentElement.remove();
        } catch (e) {
          show("Erreur suppression ❌");
        }
      });
    });
  } catch (e) {
    show("Accès refusé (admin) ou erreur API.");
  }
});
