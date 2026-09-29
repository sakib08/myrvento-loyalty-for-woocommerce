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
    <div className="ciwp-app ciwp-relative ciwp-min-h-screen ciwp-bg-slate-50">
      <header className="ciwp-app__header ciwp-border-b ciwp-border-slate-200 ciwp-bg-white">
        <div className="ciwp-mx-auto ciwp-flex ciwp-max-w-7xl ciwp-flex-wrap ciwp-items-center ciwp-justify-between ciwp-gap-4 ciwp-px-6 ciwp-py-4">
          <div className="ciwp-flex ciwp-items-center ciwp-gap-3">
            <span className="ciwp-flex ciwp-h-10 ciwp-w-10 ciwp-items-center ciwp-justify-center ciwp-rounded-xl ciwp-bg-gradient-to-br ciwp-from-brand-500 ciwp-to-brand-700 ciwp-text-white ciwp-shadow-md">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M3 17l6-6 4 4 8-8" />
                <path d="M14 7h7v7" />
              </svg>
            </span>
            <div>
              <h1 className="ciwp-m-0 ciwp-text-lg ciwp-font-bold ciwp-text-slate-900">
                {i18n.pluginName || "Myrvento Loyalty for WooCommerce"}
              </h1>
              <p className="ciwp-m-0 ciwp-mt-0.5 ciwp-text-xs ciwp-text-slate-500">
                {i18n.tagline || "Loyalty & Referrals"}
              </p>
            </div>
          </div>

          <nav className="ciwp-flex ciwp-flex-wrap ciwp-gap-1 ciwp-rounded-xl ciwp-bg-slate-100 ciwp-p-1">
            {tabs.map((tab) => (
              <a
                key={tab.id}
                href={tab.href}
                className={`ciwp-rounded-lg ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ciwp-no-underline ciwp-transition ${
                  page === tab.id
                    ? "ciwp-bg-white ciwp-text-brand-700 ciwp-shadow-sm"
                    : "ciwp-text-slate-600 hover:ciwp-text-slate-900"
                }`}
              >
                {tab.label}
              </a>
            ))}
          </nav>
        </div>
      </header>

      <main className="ciwp-mx-auto ciwp-max-w-7xl ciwp-px-6 ciwp-py-6">{children}</main>
    </div>
  );
}
