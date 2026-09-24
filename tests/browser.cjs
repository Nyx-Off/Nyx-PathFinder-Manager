const {
  chromium,
} = require("../storage/browser-tools/node_modules/playwright");
const fs = require("fs");
const assert = require("assert");
(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-dev-shm-usage"],
  });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
  });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  const base =
    process.env.TEST_BASE_URL || "https://nyx-off.dev/others/pathfinder/";
  const credentials = JSON.parse(
    fs.readFileSync("storage/test-credentials.json"),
  )[0];
  await page.goto(base);
  await page.getByLabel("Adresse e-mail").fill(credentials.email);
  await page
    .getByLabel("Mot de passe", { exact: true })
    .fill(credentials.password);
  await page.getByRole("button", { name: "Se connecter" }).click();
  await page.getByRole("heading", { name: "Mes personnages" }).waitFor();
  await page.locator("[data-create]").first().click();
  await page.locator("[name=name]").fill("Aldren — test navigateur");
  await page.locator("[name=player]").fill("Test");
  await page.locator("[name=campaign]").fill("Les Chroniques du crépuscule");
  await page.locator("#next").click();
  await page.locator("[name=ancestry]").fill("Humain");
  await page.locator("#next").click();
  await page.locator("[name=heritage]").fill("Polyvalent");
  await page.locator("#next").click();
  await page.locator("[name=background]").fill("Érudit");
  await page.locator("#next").click();
  await page.locator("[name=class]").fill("Magicien");
  await page.locator("[name=class_hp]").fill("6");
  await page.locator("[name=key_attribute]").selectOption("int");
  await page.locator("#next").click();
  await page.locator("[name=int]").fill("4");
  await page.locator("[name=con]").fill("2");
  await page.locator("#next").click();
  await page.locator('[name="skill_Arcanes"]').selectOption("1");
  await page.locator('[name="skill_Sorts"]').selectOption("1");
  await page.locator("#next").click();
  await page.locator("[name=feat_name_0]").fill("Don personnel");
  await page.locator("#next").click();
  await page.locator("[name=item_name_0]").fill("Potion de soin");
  await page.locator("[name=item_type_0]").selectOption("consommable");
  await page.locator("[name=item_quantity_0]").fill("2");
  await page.locator("#next").click();
  await page.locator("[name=spell_name_0]").fill("Sort personnel");
  await page.locator("[name=slots]").fill("2");
  await page.locator("#next").click();
  await page.locator("#finish").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  await page
    .locator("#app")
    .getByRole("heading", { name: "Aldren — test navigateur" })
    .waitFor();
  console.log("PASS browser creation wizard");
  await page.locator("[data-hp=damage]").click();
  await page.locator("[name=amount]").fill("3");
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  console.log("PASS browser HP dialog");
  await page.locator("[data-add=conditions]").first().click();
  await page.locator("[name=name]").selectOption("Effrayé");
  await page.locator("[name=value]").fill("2");
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  await page.getByText("Valeur 2", { exact: true }).waitFor();
  await page.screenshot({
    path: "storage/dashboard-desktop.png",
    fullPage: true,
  });
  await page.locator(".tabs [data-tab=items]").click();
  await page.getByText("Potion de soin", { exact: true }).waitFor();
  await page.locator('[data-edit="items"]').first().click();
  assert(
    await page.locator('[name="rank"]').isHidden(),
    "weapon fields hidden for potion",
  );
  await page.locator('[name="type"]').selectOption("arme");
  assert(
    await page.locator('[name="rank"]').isVisible(),
    "weapon fields available for weapon",
  );
  await page.locator('[name="extradimensional"]').check();
  assert(
    await page.locator('[name="capacity"]').isVisible(),
    "container capacity available",
  );
  await page.locator("#close-modal").click();
  console.log("PASS category-specific inventory fields");
  await page.locator("[data-potion]").click();
  await page.locator("[name=amount]").fill("2");
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  console.log("PASS browser inventory and potion");
  await page.locator(".tabs [data-tab=spells]").click();
  await page.locator("[data-cast]").click();
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  console.log("PASS browser cast spell");
  await page.locator('[data-add="spells"]').click();
  await page.locator('[name="name"]').fill("Sort du livre test");
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  await page.locator("[data-spell-filter]").selectOption("book");
  await page.getByRole("button", { name: "Préparer une copie" }).click();
  await page
    .getByText("Sort du livre test — préparé", { exact: true })
    .waitFor();
  await page.locator("[data-spell-filter]").selectOption("ready");
  assert(
    await page.locator("[data-prepare]").isHidden(),
    "book filtered from ready spells",
  );
  await page.locator("[data-filter]").first().fill("absent test");
  assert.equal(
    await page
      .locator("[data-spell-filter]")
      .locator("..")
      .locator("..")
      .locator("[data-search]:visible")
      .count(),
    0,
    "combined search and preparation filter",
  );
  console.log("PASS spell preparation and filters");
  await page.locator(".tabs [data-tab=progression]").click();
  await page.locator("[data-level]").click();
  await page.locator("[name=feat_name]").fill("Don niveau 2");
  await page.locator("#modal button[type=submit]").click();
  await page.locator("#modal").waitFor({ state: "hidden" });
  await page.getByRole("heading", { name: "Niveau 2", exact: true }).waitFor();
  console.log("PASS browser level up");
  for (const tab of [
    "skills",
    "combat",
    "actions",
    "feats",
    "abilities",
    "journal_entries",
    "notes",
    "summary",
  ])
    await page.locator(`.tabs [data-tab=${tab}]`).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({
    path: "storage/dashboard-mobile.png",
    fullPage: true,
  });
  assert(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth + 1,
    ),
    "mobile overflow",
  );
  console.log("PASS mobile layout");
  const cid = Number(new URL(page.url()).hash.split("/")[1]);
  const token = await page
    .locator("meta[name=csrf-token]")
    .getAttribute("content");
  const response = await context.request.get(base + "api.php?id=" + cid);
  const c = (await response.json()).data;
  await context.request.delete(base + "api.php?action=delete&id=" + cid, {
    headers: { "X-CSRF-Token": token },
    data: { revision: c.revision },
  });
  assert.deepStrictEqual(errors, []);
  console.log("PASS no browser JavaScript errors");
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
