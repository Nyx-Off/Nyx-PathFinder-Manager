import { escape as e } from "./api.js";
import { modal, field, select, textarea, submit } from "./modal.js";
import { schemas, conditions } from "./catalog.js";
export function editEntry(collection, entry, save) {
  const data = entry?.data || {};
  let html =
    collection === "conditions"
      ? select(
          "Condition",
          "name",
          Object.fromEntries(conditions.map((x) => [x, x])),
          entry?.name,
        )
      : field(
          "Nom",
          "name",
          entry?.name || "",
          "text",
          'required maxlength="190"',
        );
  for (const [key, [label, type = "text", initial = ""]] of Object.entries(
    schemas[collection],
  )) {
    const value = data[key] ?? initial;
    if (typeof type === "object") html += select(label, key, type, value);
    else if (type === "textarea") html += textarea(label, key, value);
    else if (type === "checkbox")
      html += `<label class="check"><input type="checkbox" name="${key}" ${value ? "checked" : ""}>${e(label)}</label>`;
    else
      html += field(
        label,
        key,
        value,
        type,
        type === "number"
          ? 'step="' + (key === "bulk" ? "0.01" : "1") + '"'
          : "",
      );
  }
  modal(
    entry ? "Modifier : " + entry.name : "Ajouter un élément",
    `<form class="form-grid">${html}${submit}</form>`,
    async (fd) => {
      const values = { ...data };
      for (const [key, [, type = "text"]] of Object.entries(
        schemas[collection],
      ))
        values[key] =
          type === "checkbox"
            ? fd.has(key)
            : type === "number"
              ? Number(fd.get(key))
              : fd.get(key);
      await save("entry", {
        collection,
        name: fd.get("name"),
        data: values,
        ...(entry ? { entry_id: entry.id } : {}),
      });
    },
  );
  if (collection === "items") {
    const form = document.querySelector("#modal form");
    const groups = {
      arme: [
        "rank",
        "ranged",
        "dice",
        "die",
        "damage_type",
        "extra_damage",
        "range",
      ],
      armure: ["dex_cap", "penalty", "speed_penalty", "strength"],
      bouclier: ["hardness", "hp", "hp_max", "broken"],
    };
    const refresh = () => {
      for (const [type, keys] of Object.entries(groups))
        for (const key of keys)
          form.elements[key].closest("label").hidden =
            form.elements.type.value !== type;
      form.elements.capacity.closest("label").hidden =
        !form.elements.extradimensional.checked;
    };
    form.addEventListener("change", refresh);
    refresh();
  }
}
export function collectionRows(c, key) {
  return c[key]?.length
    ? c[key]
        .map((r) => {
          const d = r.data;
          const spellState =
            key === "spells" &&
            d.casting === "prepared" &&
            (d.prepared === 0 || d.prepared === false)
              ? "book"
              : d.used
                ? "used"
                : "ready";
          let description = d.description || d.notes || "";
          let meta =
            key === "items"
              ? `${schemas.items.type[1][d.type] || "Divers"} · Qté ${d.quantity ?? 1} · ${d.bulk ?? 0} Enc.${d.equipped ? " · Équipé" : ""}${d.container ? " · Dans : " + d.container : ""}`
              : key === "spells"
                ? `Rang ${d.spell_rank ?? 0} · ${schemas.spells.tradition[1][d.tradition] || d.tradition || ""} · ${schemas.spells.casting[1][d.casting] || ""}${d.casting === "prepared" ? (d.prepared === 0 || d.prepared === false ? " · Grimoire (à préparer)" : " · Prêt") : ""}${d.used ? " · Utilisé" : ""}`
                : key === "resources" || key === "spell_slots"
                  ? `${d.current ?? 0} / ${d.max ?? 0}`
                  : key === "conditions"
                    ? `Valeur ${d.value ?? 1}`
                    : key === "modifiers"
                      ? `${Number(d.value) >= 0 ? "+" : ""}${d.value} ${schemas.modifiers.type[1][d.type] || d.type} → ${d.target}${d.active ? "" : " · Inactif"}`
                      : schemas[key]?.category?.[1]?.[d.category] ||
                        d.category ||
                        d.date ||
                        "";
          let quick = "";
          if (["items", "resources", "spell_slots", "spells"].includes(key))
            quick = `<button data-consume="${key}" data-id="${r.id}" title="Consommer">${key === "spells" ? (d.used ? "Restaurer" : "Utiliser") : "−1"}</button>`;
          if (["resources", "spell_slots"].includes(key))
            quick += `<button data-restore="${key}" data-id="${r.id}" title="Restaurer">+1</button>`;
          if (key === "spells")
            quick += `<button data-cast="${r.id}" class="primary">Lancer</button>`;
          if (key === "items" && (d.consumable || d.type === "consommable"))
            quick += `<button data-potion="${r.id}">Utiliser / soins</button>`;
          if (key === "items")
            quick += `<button data-equip="${r.id}">${d.equipped ? "Déséquiper" : d.container ? "Sortir et équiper" : "Équiper"}</button>`;
          if (key === "modifiers")
            quick += `<button data-toggle-modifier="${r.id}">${d.active ? "Désactiver" : "Activer"}</button>`;
          if (key === "conditions")
            quick += `<button data-reduce="${r.id}">−1</button>`;
          if (
            key === "spells" &&
            d.casting === "prepared" &&
            (d.prepared === 0 || d.prepared === false)
          )
            quick = `<button data-prepare="${r.id}">Préparer une copie</button>`;
          return `<article class="row" data-spell-state="${spellState}" data-search="${e(r.name.toLocaleLowerCase())}"><div class="row-content"><strong>${e(r.name)}</strong><p class="small muted">${e(meta)}</p>${description ? `<details><summary>Description</summary><p class="prose">${e(description)}</p></details>` : ""}</div><div class="toolbar">${quick}<button data-edit="${key}" data-id="${r.id}" aria-label="Modifier ${e(r.name)}">✎</button><button class="quiet" data-remove="${key}" data-id="${r.id}" aria-label="Supprimer ${e(r.name)}">×</button></div></article>`;
        })
        .join("")
    : '<p class="muted">Aucun élément. Ajoutez vos choix et vos références de règles.</p>';
}
export function section(c, key, title) {
  return `<section class="panel"><div class="section-head"><h3>${e(title)}</h3><button data-add="${key}">+ Ajouter</button></div><label class="no-print small">Rechercher<input type="search" data-filter placeholder="Filtrer par nom…"></label>${key === "spells" ? `<label class="small no-print">Afficher<select data-spell-filter><option value="all">Tous les sorts</option><option value="ready">Prêts à lancer</option><option value="book">Grimoire — à préparer</option><option value="used">Déjà utilisés</option></select></label>` : ""}${collectionRows(c, key)}</section>`;
}
