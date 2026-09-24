import { adminConfig } from "../api";

export default function AdminLayout({ page, children }) {
  const { i18n = {}, urls = {} } = adminConfig;

  const tabs = [
    { id: "dashboard", label: i18n.dashboard || "Dashboard", href: urls.dashboard },
    { id: "points", label: i18n.points || "Points", href: urls.points },
    { id: "customers", label: i18n.customers || "Customers", href: urls.customers },
    { id: "tiers", label: i18n.tiers || "VIP Tiers", href: urls.tiers },
    { id: "rewards", label: i18n.rewards || "Rewards", href: urls.rewards },
    { id: "gamification", label: i18n.gamification || "Gamification", href: urls.gamification },
    { id: "referrals", label: i18n.referrals || "Referrals", href: urls.referrals },
    { id: "sales", label: i18n.sales || "Sales", href: urls.sales },
    { id: "operations", label: i18n.operations || "Operations", href: urls.operations },
    { id: "analytics", label: i18n.analytics || "Analytics", href: urls.analytics },
    { id: "revenue", label: i18n.revenue || "Revenue", href: urls.revenue },
    { id: "ai", label: i18n.ai || "AI", href: urls.ai },
    { id: "settings", label: i18n.settings || "Settings", href: urls.settings },
    { id: "help", label: i18n.help || "Help", href: urls.help },
  ];

  return (
    <div className="growthpilot-app gp-ppros-relative gp-ppros-min-h-screen gp-ppros-bg-slate-50">
      <header className="growthpilot-app__header gp-ppros-border-b gp-ppros-border-slate-200 gp-ppros-bg-white">
        <div className="gp-ppros-mx-auto gp-ppros-flex gp-ppros-max-w-7xl gp-ppros-flex-wrap gp-ppros-items-center gp-ppros-justify-between gp-ppros-gap-4 gp-ppros-px-6 gp-ppros-py-4">
          <div className="gp-ppros-flex gp-ppros-items-center gp-ppros-gap-3">
            <span className="gp-ppros-flex gp-ppros-h-10 gp-ppros-w-10 gp-ppros-items-center gp-ppros-justify-center gp-ppros-rounded-xl gp-ppros-bg-gradient-to-br gp-ppros-from-brand-500 gp-ppros-to-brand-700 gp-ppros-text-white gp-ppros-shadow-md">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M3 17l6-6 4 4 8-8" />
                <path d="M14 7h7v7" />
              </svg>
            </span>
            <div>
              <h1 className="gp-ppros-m-0 gp-ppros-text-lg gp-ppros-font-bold gp-ppros-text-slate-900">
                {i18n.pluginName || "GrowthPilot by Ppros"}
              </h1>
              <p className="gp-ppros-m-0 gp-ppros-mt-0.5 gp-ppros-text-xs gp-ppros-text-slate-500">
                {i18n.tagline || "Loyalty & Referrals"}
              </p>
            </div>
          </div>

          <nav className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-gap-1 gp-ppros-rounded-xl gp-ppros-bg-slate-100 gp-ppros-p-1">
            {tabs.map((tab) => (
              <a
                key={tab.id}
                href={tab.href}
                className={`gp-ppros-rounded-lg gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-no-underline gp-ppros-transition ${
                  page === tab.id
                    ? "gp-ppros-bg-white gp-ppros-text-brand-700 gp-ppros-shadow-sm"
                    : "gp-ppros-text-slate-600 hover:gp-ppros-text-slate-900"
                }`}
              >
                {tab.label}
              </a>
            ))}
          </nav>
        </div>
      </header>

      <main className="gp-ppros-mx-auto gp-ppros-max-w-7xl gp-ppros-px-6 gp-ppros-py-6">{children}</main>
    </div>
  );
}
