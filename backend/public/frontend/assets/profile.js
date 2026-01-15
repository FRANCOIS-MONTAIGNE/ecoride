/* global isLoggedIn, getCurrentUser */

// ===============================
// PROFILE USER
// ===============================
(function () {
  if (!isLoggedIn() || !getCurrentUser()) {
    alert("Session invalide, veuillez vous reconnecter.");
    localStorage.removeItem("access_token");
    localStorage.removeItem("user");
    location.href = "login.html";
    return;
  }

  const user = getCurrentUser();
  console.log("Profil - user récupéré :", user);

  if (!user) {
    alert("Session invalide, veuillez vous reconnecter.");
    location.href = "login.html";
    return;
  }

  const nameEl    = document.getElementById("profileFullName");
  const emailEl   = document.getElementById("profileEmail");
  const roleEl    = document.getElementById("profileRole");
  const ratingEl  = document.getElementById("profileRating");
  const createdEl = document.getElementById("profileCreatedAt");
  const logoutBtn = document.getElementById("profileLogoutBtn");

  if (nameEl)   nameEl.textContent   = user.full_name || "—";
  if (emailEl)  emailEl.textContent  = user.email || "—";
  if (roleEl)   roleEl.textContent   = user.role || "—";
  if (ratingEl) ratingEl.textContent = user.rating ?? "—";
  if (createdEl) createdEl.textContent = user.created_at || "—";

  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      localStorage.removeItem("access_token");
      localStorage.removeItem("user");
      location.href = "login.html";
    });
  }
})();


// ===============================
// MESSAGERIE 
// ===============================
(function bindMessaging() {
  // éléments DOM importants 
  const convList = document.getElementById("convList");
  if (!convList) return;

  const chatBox   = document.getElementById("chatBox");
  const chatForm  = document.getElementById("chatForm");
  const chatInput = document.getElementById("chatInput");
  const chatMsg   = document.getElementById("chatMsg");

  let currentConvId = 0;
  let pollTimer = null;

  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function loadConversations() {
    try {
      const res = await api("/api/messages_list.php", { method: "GET" });
      const items = Array.isArray(res.items) ? res.items : [];

      convList.innerHTML = "";
      if (!items.length) {
        convList.innerHTML = `<div class="muted">Aucune conversation (réservations acceptées uniquement).</div>`;
        return;
      }

      items.forEach((c) => {
        const me = getCurrentUser();
        const otherName =
          String(c.driver_id) === String(me?.id)
            ? c.passenger_name
            : c.driver_name;

        const div = document.createElement("div");
        div.className = "conv-item";
        div.innerHTML = `
          <strong>${escapeHtml(otherName || "Conversation")}</strong>
          <div class="muted">${escapeHtml(c.origin_city)} → ${escapeHtml(c.dest_city)}</div>
        `;
        div.addEventListener("click", () => selectConversation(c.id));
        convList.appendChild(div);
      });

    } catch (e) {
      console.error(e);
    }
  }

  async function selectConversation(convId) {
    currentConvId = convId;
    await loadMessages();

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);
  }

  async function loadMessages() {
    if (!currentConvId) return;

    try {
      const res = await api(`/api/messages_get.php?conversation_id=${currentConvId}`, { method: "GET" });
      const msgs = Array.isArray(res.messages) ? res.messages : [];
      const meId = Number(getCurrentUser()?.id || 0);

      chatBox.innerHTML = "";
      msgs.forEach((m) => {
        const div = document.createElement("div");
        div.className = "msg" + (Number(m.sender_id) === meId ? " me" : "");
        div.innerHTML = `
          <div>${escapeHtml(m.body)}</div>
          <span class="meta">${escapeHtml(m.created_at)}</span>
        `;
        chatBox.appendChild(div);
      });

      chatBox.scrollTop = chatBox.scrollHeight;

    } catch (e) {
      console.error(e);
    }
  }

  if (chatForm) {
    chatForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!currentConvId) return;

      const body = chatInput.value.trim();
      if (!body) return;

      chatInput.value = "";
      await api("/api/messages_send.php", {
        method: "POST",
        body: JSON.stringify({ conversation_id: currentConvId, body }),
      });

      await loadMessages();
    });
  }

  // Chargement initial des conversations
  document.addEventListener("DOMContentLoaded", loadConversations);
})();
