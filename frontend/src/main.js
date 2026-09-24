import "./styles.css";
import QRCode from "qrcode";

const config = window.growthPilotFrontend ?? {
  apiUrl: "/wp-json/growthpilot/v1/",
  nonce: "",
  i18n: {},
};

async function request(path, options = {}) {
  const res = await fetch(`${config.apiUrl}${path}`, {
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": config.nonce,
    },
    credentials: "same-origin",
    ...options,
  });
  return res.json().catch(() => ({}));
}

document.querySelectorAll("[data-gp-ppros-copy]").forEach((button) => {
  button.addEventListener("click", async () => {
    const wrap = button.closest("[data-gp-ppros-share]");
    const input = wrap?.querySelector("[data-gp-ppros-copy-target]");
    if (!input) return;
    try {
      await navigator.clipboard.writeText(input.value);
      button.textContent = config.i18n.copied || "Copied!";
      request("account/share", {
        method: "POST",
        body: JSON.stringify({ channel: "copy" }),
      });
      window.setTimeout(() => {
        button.textContent = config.i18n.copy || "Copy link";
      }, 2000);
    } catch (err) {
      input.select();
      document.execCommand("copy");
    }
  });
});

document.querySelectorAll("[data-gp-ppros-share-channel]").forEach((link) => {
  link.addEventListener("click", () => {
    const channel = link.getAttribute("data-gp-ppros-share-channel") || "social";
    request("account/share", {
      method: "POST",
      body: JSON.stringify({ channel }),
    });
  });
});

document.querySelectorAll("[data-gp-ppros-qr]").forEach(async (canvas) => {
  const url = canvas.getAttribute("data-gp-ppros-qr");
  if (!url) return;
  try {
    await QRCode.toCanvas(canvas, url, { width: 160, margin: 1 });
  } catch (err) {
    canvas.replaceWith(document.createTextNode(""));
  }
});

document.querySelectorAll("[data-gp-ppros-redeem]").forEach((button) => {
  button.addEventListener("click", async () => {
    button.disabled = true;
    const data = await request("account/redeem", {
      method: "POST",
      body: JSON.stringify({ reward_id: Number(button.getAttribute("data-gp-ppros-redeem")) }),
    });
    if (data.coupon_code) {
      window.alert(`Coupon: ${data.coupon_code}`);
      window.location.reload();
      return;
    }
    window.alert(data.message || "Could not redeem this reward.");
    button.disabled = false;
  });
});

document.querySelectorAll("[data-gp-ppros-birthday]").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const input = form.querySelector('input[name="birthday"]');
    const data = await request("account/birthday", {
      method: "POST",
      body: JSON.stringify({ date: input?.value || "" }),
    });
    if (data.birthday) {
      window.alert("Birthday saved.");
    } else {
      window.alert(data.message || "Could not save birthday.");
    }
  });
});
