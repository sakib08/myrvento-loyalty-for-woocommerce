import AdminLayout from "./components/AdminLayout";
import Dashboard from "./pages/Dashboard";
import Points from "./pages/Points";
import Customers from "./pages/Customers";
import Tiers from "./pages/Tiers";
import Rewards from "./pages/Rewards";
import Gamification from "./pages/Gamification";
import Referrals from "./pages/Referrals";
import Sales from "./pages/Sales";
import Operations from "./pages/Operations";
import Analytics from "./pages/Analytics";
import Revenue from "./pages/Revenue";
import AI from "./pages/AI";
import Settings from "./pages/Settings";
import Help from "./pages/Help";

function renderPage(page) {
  switch (page) {
    case "points":
      return <Points />;
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
    case "sales":
      return <Sales />;
    case "operations":
      return <Operations />;
    case "analytics":
      return <Analytics />;
    case "revenue":
      return <Revenue />;
    case "ai":
      return <AI />;
    case "settings":
      return <Settings />;
    case "help":
      return <Help />;
    default:
      return <Dashboard />;
  }
}

export default function App({ page }) {
  return <AdminLayout page={page}>{renderPage(page)}</AdminLayout>;
}
