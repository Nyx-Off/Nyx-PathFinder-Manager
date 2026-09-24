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
  const base =
    process.env.TEST_BASE_URL || "https://nyx-off.dev/others/pathfinder/";
  const users = JSON.parse(fs.readFileSync("storage/test-credentials.json"));
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(base);
  await page.getByLabel("Adresse e-mail").fill(users[0].email);
  await page
    .getByLabel("Mot de passe", { exact: true })
    .fill(users[0].password);
  await page.getByRole("button", { name: "Se connecter" }).click();
  await page.locator("#app").waitFor();
  const csrf = await page
    .locator("meta[name=csrf-token]")
    .getAttribute("content");
  const response = await context.request.post(base + "api.php?action=create", {
    headers: { "X-CSRF-Token": csrf },
    data: { name: "Portrait test" },
  });
  const c = (await response.json()).data;
  let r = await context.request.post(base + "portrait.php?id=" + c.id, {
    multipart: {
      csrf,
      portrait: {
        name: "fake.png",
        mimeType: "image/png",
        buffer: Buffer.from('<?php echo "test"; ?>'),
      },
    },
  });
  assert(r.status() === 422);
  console.log("PASS upload MIME spoof denied");
  const png = await page.screenshot({
    clip: { x: 0, y: 0, width: 4, height: 4 },
  });
  r = await context.request.post(base + "portrait.php?id=" + c.id, {
    multipart: {
      csrf,
      portrait: { name: "portrait.php", mimeType: "image/png", buffer: png },
    },
  });
  assert(r.status() === 422);
  console.log("PASS upload extension denied");
  r = await context.request.post(base + "portrait.php?id=" + c.id, {
    multipart: {
      csrf,
      portrait: { name: "portrait.png", mimeType: "image/png", buffer: png },
    },
  });
  assert(r.status() === 200);
  r = await context.request.get(base + "portrait.php?id=" + c.id);
  assert(r.headers()["content-type"] === "image/jpeg");
  console.log("PASS portrait transcoded and private");
  const anon = await browser.newContext();
  assert(
    (await anon.request.get(base + "portrait.php?id=" + c.id)).status() === 401,
  );
  console.log("PASS anonymous portrait denied");
  const updated = (
    await (await context.request.get(base + "api.php?id=" + c.id)).json()
  ).data;
  const filepath = "storage/uploads/" + updated.portrait;
  assert(fs.existsSync(filepath));
  await context.request.delete(base + "api.php?action=delete&id=" + c.id, {
    headers: { "X-CSRF-Token": csrf },
    data: { revision: updated.revision },
  });
  assert(!fs.existsSync(filepath));
  console.log("PASS portrait removed on character deletion");
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
