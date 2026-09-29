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
    <div className="myrvento-app myrvento-relative myrvento-min-h-screen myrvento-bg-slate-50">
      <header className="myrvento-app__header myrvento-border-b myrvento-border-slate-200 myrvento-bg-white">
        <div className="myrvento-mx-auto myrvento-flex myrvento-max-w-7xl myrvento-flex-wrap myrvento-items-center myrvento-justify-between myrvento-gap-4 myrvento-px-6 myrvento-py-4">
          <div className="myrvento-flex myrvento-items-center myrvento-gap-3">
            <span className="myrvento-flex myrvento-h-10 myrvento-w-10 myrvento-items-center myrvento-justify-center myrvento-rounded-xl myrvento-bg-gradient-to-br myrvento-from-brand-500 myrvento-to-brand-700 myrvento-text-white myrvento-shadow-md">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M3 17l6-6 4 4 8-8" />
                <path d="M14 7h7v7" />
              </svg>
            </span>
            <div>
              <h1 className="myrvento-m-0 myrvento-text-lg myrvento-font-bold myrvento-text-slate-900">
                {i18n.pluginName || "Myrvento Loyalty for WooCommerce"}
              </h1>
              <p className="myrvento-m-0 myrvento-mt-0.5 myrvento-text-xs myrvento-text-slate-500">
                {i18n.tagline || "Loyalty & Referrals"}
              </p>
            </div>
          </div>

          <nav className="myrvento-flex myrvento-flex-wrap myrvento-gap-1 myrvento-rounded-xl myrvento-bg-slate-100 myrvento-p-1">
            {tabs.map((tab) => (
              <a
                key={tab.id}
                href={tab.href}
                className={`myrvento-rounded-lg myrvento-px-3 myrvento-py-2 myrvento-text-sm myrvento-font-semibold myrvento-no-underline myrvento-transition ${
                  page === tab.id
                    ? "myrvento-bg-white myrvento-text-brand-700 myrvento-shadow-sm"
                    : "myrvento-text-slate-600 hover:myrvento-text-slate-900"
                }`}
              >
                {tab.label}
              </a>
            ))}
          </nav>
        </div>
      </header>

      <main className="myrvento-mx-auto myrvento-max-w-7xl myrvento-px-6 myrvento-py-6">{children}</main>
    </div>
  );
}
