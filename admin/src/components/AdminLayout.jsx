import { adminConfig } from "../api";

export default function AdminLayout({ page, children }) {
  const { i18n = {}, urls = {} } = adminConfig;

  const tabs = [
    { id: "points", label: i18n.points || "Points", href: urls.points },
    { id: "customers", label: i18n.customers || "Customers", href: urls.customers },
    { id: "tiers", label: i18n.tiers || "VIP Tiers", href: urls.tiers },
    { id: "rewards", label: i18n.rewards || "Rewards", href: urls.rewards },
    { id: "gamification", label: i18n.gamification || "Gamification", href: urls.gamification },
    { id: "referrals", label: i18n.referrals || "Referrals", href: urls.referrals },
    { id: "analytics", label: i18n.analytics || "Analytics", href: urls.analytics },
    { id: "ai", label: i18n.ai || "AI", href: urls.ai },
    { id: "settings", label: i18n.settings || "Settings", href: urls.settings },
  ];

  return (
    <div className="growthpilot-app gp-relative gp-min-h-screen gp-bg-slate-50">
      <header className="growthpilot-app__header gp-border-b gp-border-slate-200 gp-bg-white">
        <div className="gp-mx-auto gp-flex gp-max-w-7xl gp-flex-wrap gp-items-center gp-justify-between gp-gap-4 gp-px-6 gp-py-4">
          <div className="gp-flex gp-items-center gp-gap-3">
            <span className="gp-flex gp-h-10 gp-w-10 gp-items-center gp-justify-center gp-rounded-xl gp-bg-gradient-to-br gp-from-brand-500 gp-to-brand-700 gp-text-white gp-shadow-md">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M3 17l6-6 4 4 8-8" />
                <path d="M14 7h7v7" />
              </svg>
            </span>
            <div>
              <h1 className="gp-m-0 gp-text-lg gp-font-bold gp-text-slate-900">
                {i18n.pluginName || "GrowthPilot"}
              </h1>
              <p className="gp-m-0 gp-mt-0.5 gp-text-xs gp-text-slate-500">
                {i18n.tagline || "Loyalty & Referrals"}
              </p>
            </div>
          </div>

          <nav className="gp-flex gp-flex-wrap gp-gap-1 gp-rounded-xl gp-bg-slate-100 gp-p-1">
            {tabs.map((tab) => (
              <a
                key={tab.id}
                href={tab.href}
                className={`gp-rounded-lg gp-px-3 gp-py-2 gp-text-sm gp-font-semibold gp-no-underline gp-transition ${
                  page === tab.id
                    ? "gp-bg-white gp-text-brand-700 gp-shadow-sm"
                    : "gp-text-slate-600 hover:gp-text-slate-900"
                }`}
              >
                {tab.label}
              </a>
            ))}
          </nav>
        </div>
      </header>

      <main className="gp-mx-auto gp-max-w-7xl gp-px-6 gp-py-6">{children}</main>
    </div>
  );
}
