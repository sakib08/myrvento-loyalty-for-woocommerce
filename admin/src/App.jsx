import AdminLayout from "./components/AdminLayout";
import Points from "./pages/Points";
import Customers from "./pages/Customers";
import Tiers from "./pages/Tiers";
import Rewards from "./pages/Rewards";
import Gamification from "./pages/Gamification";
import Referrals from "./pages/Referrals";
import Analytics from "./pages/Analytics";
import AI from "./pages/AI";
import Settings from "./pages/Settings";

function renderPage(page) {
  switch (page) {
    case "customers":
      return <Customers />;
    case "tiers":
      return <Tiers />;
    case "rewards":
      return <Rewards />;
    case "gamification":
      return <Gamification />;
    case "referrals":
      return <Referrals />;
    case "analytics":
      return <Analytics />;
    case "ai":
      return <AI />;
    case "settings":
      return <Settings />;
    default:
      return <Points />;
  }
}

export default function App({ page }) {
  return <AdminLayout page={page}>{renderPage(page)}</AdminLayout>;
}
