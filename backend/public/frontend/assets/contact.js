/* global document, fetch */

const API_BASE = "/ecoride/backend/public"; 

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("contactForm");
  const message = document.getElementById("message");
  const charCount = document.getElementById("charCount");
  const msgBox = document.getElementById("contactMsg");

  if (!form || !message || !charCount || !msgBox) return;

  // compteur de caractères
  const updateCount = () => {
    charCount.textContent = String(message.value.length);
  };
  updateCount();
  message.addEventListener("input", updateCount);

  // gestion de l’envoi du formulaire
  const showMsg = (text, type) => {
    msgBox.textContent = text;
    msgBox.classList.remove("success", "error");
    msgBox.classList.add(type);
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    showMsg("Envoi en cours...", "success");

    const payload = {
      name: form.name.value.trim(),
      email: form.email.value.trim(),
      message: form.message.value.trim(),
    };

    // validation basique
    if (!payload.name || !payload.email || !payload.message) {
      showMsg("Merci de remplir tous les champs.", "error");
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/api/contact.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok || data.error) {
        showMsg(data.error || "Erreur lors de l’envoi. Réessaie.", "error");
        return;
      }

      showMsg(data.message || "Message envoyé ✅ Merci !", "success");
      form.reset();
      updateCount();
    } catch (err) {
      showMsg("Impossible de contacter le serveur. Vérifie XAMPP / URL API.", "error");
    }
  });
});
