import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import "./styles.css";

const rootEl = document.getElementById("ciwp-admin-root");

if (rootEl) {
  createRoot(rootEl).render(
    <StrictMode>
      <App page={rootEl.dataset.page || "points"} />
    </StrictMode>
  );
}
