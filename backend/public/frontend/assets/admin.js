/* global isLoggedIn, getCurrentUser, getUserRole, api, Chart */
/* global window, document, localStorage */

(function guardAdminPage() {
  const logged =
    typeof isLoggedIn === "function"
      ? isLoggedIn()
      : !!localStorage.getItem("access_token");

  const user = typeof getCurrentUser === "function" ? getCurrentUser() : null;
  const role =
    typeof getUserRole === "function"
      ? getUserRole(user)
      : String(user?.role || "").toLowerCase().trim();

  const allowed = logged && user && (role === "admin" || role === "employee");


  if (!allowed) {
    alert("Vous devez être connecté(e) en tant qu’administrateur ou employé.");

    window.location.replace("login.html");
  }
})();

(function adminMain() {
  console.log("✅ ADMIN.JS chargé (clean)");

  // =============================
  // Helpers UI
  // =============================
  function setMsg(text, isError = false) {
    const el = document.getElementById("createEmployeeMsg");
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

  // =============================
  // Credits table (admin)
  // =============================
  async function loadCreditsTable() {
    const tbody = document.querySelector("#creditsTable tbody");
    if (!tbody) return;

    // filtre optionnel via User ID
    const uid = Number(document.getElementById("creditUserId")?.value || 0);

    tbody.innerHTML = `<tr><td colspan="6">Chargement…</td></tr>`;

    try {
      setCreditTableMsg("⏳ Chargement…");
      const qs =
        uid > 0 ? `?user_id=${encodeURIComponent(uid)}&limit=50` : `?limit=50`;

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

  // =============================
  // Credits tool (admin)
  // =============================
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

        // reset champs
        const a = document.getElementById("creditAmount");
        const n = document.getElementById("creditNote");
        if (a) a.value = "";
        if (n) n.value = "";

        // refresh table
        await loadCreditsTable();

        setTimeout(() => setCreditMsg(""), 1500);
      } catch (e) {
        setCreditMsg("❌ " + (e.message || "Erreur réseau"), true);
      }
    });
  }

  // =============================
  // Load users table
  // =============================
  const usersTbody = document.querySelector("#usersTable tbody");

  window.loadUsers = async function loadUsers() {
    if (!usersTbody) return;

    usersTbody.innerHTML = `<tr><td colspan="6">Chargement…</td></tr>`;

    try {
      const res = await api("/api/admin_users.php", { method: "GET" });

      if (!res || res.ok === false) {
        usersTbody.innerHTML = `<tr><td colspan="6">${res?.error || "Erreur"}</td></tr>`;
        return;
      }

      const users = Array.isArray(res.users) ? res.users : [];

      if (!users.length) {
        usersTbody.innerHTML = `<tr><td colspan="6">Aucun utilisateur.</td></tr>`;
        return;
      }

      usersTbody.innerHTML = "";
      users.forEach((u) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${u.id ?? ""}</td>
          <td>${u.full_name ?? ""}</td>
          <td>${u.email ?? ""}</td>
          <td>${u.role ?? ""}</td>
          <td>${u.rating ?? ""}</td>
          <td>${u.created_at ?? ""}</td>
        `;
        usersTbody.appendChild(tr);
      });
    } catch (e) {
      usersTbody.innerHTML = `<tr><td colspan="6">Erreur chargement users</td></tr>`;
      console.error(e);
    }
  };

  // =============================
  // DOMContentLoaded
  // =============================
  document.addEventListener("DOMContentLoaded", async () => {
    const adminEmailEl = document.getElementById("adminEmail");
    const logoutBtn = document.getElementById("logoutBtn");

    const user = getCurrentUser();
    const role = String(getUserRole(user) || "").toLowerCase();

    if (adminEmailEl) adminEmailEl.textContent = user?.email || "Non connecté";

    // logout
    if (logoutBtn && !logoutBtn.dataset.bound) {
      logoutBtn.dataset.bound = "1";
      logoutBtn.addEventListener("click", (e) => {
        e.preventDefault();
        localStorage.removeItem("access_token");
        localStorage.removeItem("user");
        alert("Vous avez été déconnecté(e).");
        location.href = "index.html";
      });
    }

    // Sécurité: vérifier rôle admin ou employee
  const isStaff = role === "admin" || role === "employee";
  if (!isLoggedIn() || !user || !isStaff) {
  alert("Accès réservé à l’administrateur ou à l’employé.");
  location.href = "login.html";
  return;
}

    // ✅ Bind credits tool
    bindCreditsTool();

    // ✅ Bind refresh credits table button
    const btnRefreshCredits = document.getElementById("btnRefreshCredits");
    if (btnRefreshCredits && !btnRefreshCredits.dataset.bound) {
      btnRefreshCredits.dataset.bound = "1";
      btnRefreshCredits.addEventListener("click", loadCreditsTable);
    }

    // ==== show admin sections =====
    const creditsSection = document.getElementById("creditsSection");
    if (creditsSection) creditsSection.style.display = ""; // admin => visible

    // --------------- Load admin stats --------------
    const tripsKpiEl = document.getElementById("statTotalTrips");
    const creditsKpiEl = document.getElementById("statTotalCredits");
    const tableBody = document.querySelector("#tripsTable tbody");
    const ctxTrips = document.getElementById("chartTrips");
    const ctxCreds = document.getElementById("chartCredits");

    let tripsChart = null;
    let creditsChart = null;

    try {
      const stats = await api("/api/admin_stats.php", { method: "GET" });

      if (!stats || stats.ok === false) {
        alert(stats?.error || stats?.message || "Erreur chargement stats");
        return;
      }

      const tripsPerDay = Array.isArray(stats.trips_per_day) ? stats.trips_per_day : [];
      const creditsPerDay = Array.isArray(stats.credits_per_day) ? stats.credits_per_day : [];

      const totalCredits = Number(stats.total_credits ?? 0);
      const totalTripsFromApi = Number(stats.total_trips ?? NaN);
      const totalTripsFallback = tripsPerDay.reduce((sum, d) => sum + Number(d.count || 0), 0);
      const totalTrips = Number.isFinite(totalTripsFromApi) ? totalTripsFromApi : totalTripsFallback;

      if (tripsKpiEl) tripsKpiEl.textContent = String(totalTrips);
      if (creditsKpiEl) creditsKpiEl.textContent = totalCredits.toFixed(2);

      // trips table
      if (tableBody) {
        tableBody.innerHTML = "";
        if (!tripsPerDay.length) {
          tableBody.innerHTML = `<tr><td colspan="2">Aucun trajet sur cette période.</td></tr>`;
        } else {
          tripsPerDay.forEach((row) => {
            const day = row.day ?? "-";
            const count = row.count ?? 0;
            tableBody.innerHTML += `<tr><td>${day}</td><td>${count}</td></tr>`;
          });
        }
      }

      // charts
      if (ctxTrips) {
        const daysTrips = tripsPerDay.map((r) => r.day);
        const valuesTrips = tripsPerDay.map((r) => Number(r.count || 0));

        if (tripsChart) tripsChart.destroy();
        tripsChart = new Chart(ctxTrips, {
          type: "bar",
          data: {
            labels: daysTrips.length ? daysTrips : ["(aucune donnée)"],
            datasets: [{ label: "Trajets", data: valuesTrips.length ? valuesTrips : [0] }],
          },
          options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
          },
        });
      }

      if (ctxCreds) {
        const daysCredits = creditsPerDay.map((r) => r.day);
        const valuesCredits = creditsPerDay.map((r) => Number(r.credits || 0));

        if (creditsChart) creditsChart.destroy();
        creditsChart = new Chart(ctxCreds, {
          type: "bar",
          data: {
            labels: daysCredits.length ? daysCredits : ["(aucune donnée)"],
            datasets: [{ label: "Crédits gagnés", data: valuesCredits.length ? valuesCredits : [0] }],
          },
          options: { responsive: true, scales: { y: { beginAtZero: true } } },
        });
      }
    } catch (err) {
      console.error(err);
      alert("Erreur lors du chargement des statistiques admin.");
    }

    // ✅ Load users table
    if (typeof window.loadUsers === "function") window.loadUsers();
    loadCreditsTable();
    loadCreditsAudit();
  });

  async function loadCreditsAudit() {
  const msg = document.getElementById("creditAuditMsg");
  const tbody = document.querySelector("#creditAuditTable tbody");
  if (!tbody) return;

  if (msg) msg.textContent = "Chargement…";
  if (msg) msg.style.color = "";


  try {
    const res = await api("/api/admin_credits_audit.php", { method: "GET" });

    const items = res.items || [];
    tbody.innerHTML = "";

    let bad = 0;

    for (const it of items) {
      const ecart = Number(it.ecart || 0);
      if (ecart !== 0) bad++;

      const tr = document.createElement("tr");
if (ecart !== 0) {
  tr.style.backgroundColor = "#fff3f3"; // rouge très léger
}


      tr.innerHTML = `
        <td>${it.id}</td>
        <td>${escapeHtml(it.full_name || "")}</td>
        <td>${escapeHtml(it.email || "")}</td>
        <td>${it.role}</td>
        <td>${Number(it.credits_enregistres).toFixed(2)}</td>
        <td>${Number(it.credits_calcules).toFixed(2)}</td>
        <td><strong>${ecart.toFixed(2)}</strong></td>
      `;
      tbody.appendChild(tr);
    }

   if (msg) {
  msg.textContent = bad === 0
    ? `✅ OK — ${items.length} utilisateur(s), aucune incohérence`
    : `⚠️ ${bad} incohérence(s) sur ${items.length} utilisateur(s)`;

  msg.style.color = bad === 0 ? "green" : "crimson";
}


  } catch (e) {
    if (msg) msg.textContent = "Erreur chargement audit crédits.";
    console.error(e);
  }
}

// mini helper escape HTML
function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}


  // =============================
  // CREATE EMPLOYEE 
  // =============================
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("#btnCreateEmployee");
    if (!btn) return;

    const full_name = document.getElementById("empName")?.value.trim() || "";
    const email = document.getElementById("empEmail")?.value.trim() || "";
    const password = document.getElementById("empPassword")?.value || "";

    if (!full_name || !email || !password) {
      setMsg("❌ Remplis tous les champs.", true);
      return;
    }

    try {
      setMsg("⏳ Création en cours…");

      const data = await api("/api/admin_create_employee.php", {
        method: "POST",
        body: JSON.stringify({ full_name, email, password }),
      });

      if (!data || data.ok === false) {
        setMsg("❌ " + (data?.error || data?.message || "Erreur inconnue"), true);
        return;
      }

      setMsg("✅ Employé créé.");

      // reset fields
      const n = document.getElementById("empName");
      const em = document.getElementById("empEmail");
      const pw = document.getElementById("empPassword");
      if (n) n.value = "";
      if (em) em.value = "";
      if (pw) pw.value = "";

      // refresh users table
      if (typeof window.loadUsers === "function") window.loadUsers();
    } catch (err) {
      console.error(err);
      setMsg("❌ " + (err.message || "Erreur réseau"), true);
    }
  });
})();
