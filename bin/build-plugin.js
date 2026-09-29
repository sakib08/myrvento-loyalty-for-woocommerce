/**
 * Pack a WordPress-installable zip of the plugin runtime only.
 *
 * Included: main file, uninstall, readme, includes, templates, built assets.
 * Left out: sources, node_modules, build tooling, the demo seeder, and editor files.
 */

const { execFileSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const header = fs.readFileSync(path.join(root, "commerce-insights-woocommerce-by-ppros.php"), "utf8");
const versionMatch = header.match(/^\s*\*\s*Version:\s*(.+)$/m);
const version = versionMatch ? versionMatch[1].trim() : "0.0.0";
const folder = "commerce-insights-woocommerce-by-ppros";
// Outside the plugin so Plugin Check does not scan the zip or a second copy of the code.
const dist = path.resolve(root, "..", "..", "ciwp-dist");
const stage = path.join(dist, folder);
const zipName = `ciwp-${version}.zip`;

const include = [
  "commerce-insights-woocommerce-by-ppros.php",
  "uninstall.php",
  "readme.txt",
  "includes",
  "templates",
  "assets",
  "languages",
];

function copyRuntime(src, dest) {
  const stat = fs.statSync(src);

  if (stat.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    fs.readdirSync(src).forEach((entry) => {
      if (entry.endsWith(".map")) {
        return;
      }
      copyRuntime(path.join(src, entry), path.join(dest, entry));
    });
    return;
  }

  fs.copyFileSync(src, dest);
}

fs.rmSync(dist, { recursive: true, force: true });
fs.mkdirSync(stage, { recursive: true });

include.forEach((item) => {
  const src = path.join(root, item);
  if (!fs.existsSync(src)) {
    throw new Error(`Missing plugin file: ${item}`);
  }
  copyRuntime(src, path.join(stage, item));
});

execFileSync("zip", ["-qr", zipName, folder], { cwd: dist, stdio: "inherit" });
fs.rmSync(stage, { recursive: true, force: true });

const zipPath = path.join(dist, zipName);
const bytes = fs.statSync(zipPath).size;
console.log(`Plugin zip: ${zipPath} (${bytes} bytes)`);
