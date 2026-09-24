import { escape as e } from "./api.js";
const dialog = document.querySelector("#modal");
let dirty = false;
window.addEventListener("beforeunload", (event) => {
  if (dirty && dialog.open) {
    event.preventDefault();
    event.returnValue = "";
  }
});
dialog.addEventListener("close", () => {
  dirty = false;
});
function draft(form, title) {
  const key =
    "pf2-draft:" +
    document.querySelector("meta[name=csrf-token]").content +
    location.hash +
    ":" +
    title;
  try {
    const stored = JSON.parse(sessionStorage.getItem(key) || "null");
    if (stored) {
      for (const input of form.elements) {
        if (!input.name || input.type === "file" || !(input.name in stored))
          continue;
        if (input.type === "checkbox")
          input.checked = stored[input.name].includes(input.value);
        else input.value = stored[input.name][0] ?? "";
      }
      const note = document.createElement("p");
      note.className = "muted small wide";
      note.textContent =
        "Brouillon local restauré. Vérifiez les champs avant d’enregistrer.";
      form.prepend(note);
      dirty = true;
    }
  } catch {}
  form.addEventListener("input", () => {
    dirty = true;
    const values = {};
    for (const input of form.elements) {
      if (!input.name || input.type === "file" || input.type === "submit")
        continue;
      if (input.type === "checkbox") {
        values[input.name] ??= [];
        if (input.checked) values[input.name].push(input.value);
      } else values[input.name] = [input.value];
    }
    try {
      sessionStorage.setItem(key, JSON.stringify(values));
    } catch {}
  });
  return () => {
    dirty = false;
    sessionStorage.removeItem(key);
  };
}

document.querySelector("#close-modal").onclick = () => dialog.close();
export function modal(title, html, submit) {
  document.querySelector("#modal-title").textContent = title;
  document.querySelector("#modal-body").innerHTML = html;
  if (!dialog.open) dialog.showModal();
  const form = dialog.querySelector("form");
  const clearDraft = form ? draft(form, title) : () => {};
  if (form && submit)
    form.onsubmit = async (event) => {
      event.preventDefault();
      const button = form.querySelector("[type=submit]");
      if (button) button.disabled = true;
      try {
        await submit(new FormData(form), form);
        clearDraft();
        dialog.close();
      } catch (error) {
        let alert = form.querySelector("[role=alert]");
        if (!alert) {
          alert = document.createElement("p");
          alert.setAttribute("role", "alert");
          form.prepend(alert);
        }
        alert.className = "error";
        alert.textContent = error.message;
      } finally {
        if (button) button.disabled = false;
      }
    };
}
export function field(label, name, value = "", type = "text", extra = "") {
  return `<label>${e(label)}<input name="${e(name)}" type="${type}" value="${e(value)}" ${extra}></label>`;
}
export function select(label, name, options, value = "") {
  return `<label>${e(label)}<select name="${e(name)}">${Object.entries(options)
    .map(
      ([key, text]) =>
        `<option value="${e(key)}" ${String(key) === String(value) ? "selected" : ""}>${e(text)}</option>`,
    )
    .join("")}</select></label>`;
}
export function textarea(label, name, value = "") {
  return `<label class="wide">${e(label)}<textarea name="${e(name)}" rows="4">${e(value)}</textarea></label>`;
}
export const submit =
  '<div class="form-actions wide"><button class="primary" type="submit">Enregistrer</button></div>';
