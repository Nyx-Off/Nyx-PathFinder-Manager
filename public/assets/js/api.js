export const csrf = document.querySelector('meta[name="csrf-token"]').content;
export async function api(action = "", id = 0, data = null, method = null) {
  const status = document.querySelector("#save-status");
  if (data) status.textContent = "Enregistrement…";
  try {
    const response = await fetch(
      `api.php?action=${encodeURIComponent(action)}&id=${id}`,
      {
        method: method || (data ? "PATCH" : "GET"),
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: data ? JSON.stringify(data) : null,
      },
    );
    const result = await response.json();
    if (!response.ok || !result.ok)
      throw Error(result.error || "Erreur de connexion");
    if (data) status.textContent = "✓ Enregistré";
    return result.data;
  } catch (error) {
    if (data) status.textContent = "Non enregistré";
    throw error;
  }
}
export const escape = (value) =>
  String(value ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
export function toast(message) {
  const el = document.querySelector("#toast");
  el.textContent = message;
  el.classList.add("visible");
  setTimeout(() => el.classList.remove("visible"), 4500);
}
