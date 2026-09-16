import { useCallback, useEffect, useMemo, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card } from "../components/FormFields";

const TABS = [
  { id: "overview", label: "Overview" },
  { id: "customers", label: "Customers" },
  { id: "products", label: "Products" },
  { id: "marketing", label: "Marketing" },
];

const PRESETS = [
  { id: "7", label: "7 days", days: 7 },
  { id: "30", label: "30 days", days: 30 },
  { id: "90", label: "90 days", days: 90 },
  { id: "365", label: "12 months", days: 365 },
];

const MONEY_KPIS = new Set([
  "gross_revenue",
  "net_revenue",
  "aov",
  "ltv",
  "refunds",
  "discounts",
  "taxes",
  "shipping",
]);
const PERCENT_KPIS = new Set(["repeat_rate"]);

function isoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function daysAgo(days) {
  const date = new Date();
  date.setDate(date.getDate() - (days - 1));
  return isoDate(date);
}

function money(value) {
  const symbol = adminConfig.currency || "$";
  const amount = Number(value || 0);
  return `${symbol}${amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function number(value, digits = 0) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}

function formatKpi(key, value) {
  if (MONEY_KPIS.has(key)) return money(value);
  if (PERCENT_KPIS.has(key)) return `${number(value, 1)}%`;
  if (key === "items_per_order") return number(value, 2);
  return number(value);
}

function Delta({ value }) {
  if (value === null || value === undefined) {
    return <span className="gp-text-xs gp-text-slate-400">New</span>;
  }
  const up = value >= 0;
  return (
    <span className={`gp-text-xs gp-font-semibold ${up ? "gp-text-emerald-600" : "gp-text-rose-600"}`}>
      {up ? "▲" : "▼"} {Math.abs(value).toFixed(1)}%
    </span>
  );
}

function heatClass(pct) {
  if (!pct) return "gp-bg-slate-50 gp-text-slate-400";
  if (pct >= 60) return "gp-bg-brand-600 gp-text-white";
  if (pct >= 40) return "gp-bg-brand-400 gp-text-white";
  if (pct >= 20) return "gp-bg-brand-100 gp-text-brand-700";
  return "gp-bg-slate-100 gp-text-slate-600";
}

function Empty({ children }) {
  return <p className="gp-m-0 gp-text-sm gp-text-slate-500">{children}</p>;
}

function TrendChart({ rows }) {
  if (!rows?.length) {
    return <Empty>No revenue in this range yet. Paid orders will appear here as a trend.</Empty>;
  }

  const max = Math.max(...rows.map((row) => Number(row.net) || 0), 1);

  return (
    <div className="gp-grid gp-gap-3">
      <div className="gp-flex gp-h-44 gp-items-end gp-gap-px">
        {rows.map((row) => {
          const height = Math.max(2, (Number(row.net) / max) * 100);
          return (
            <div
              key={row.date}
              className="gp-group gp-relative gp-flex gp-min-w-0 gp-flex-1 gp-flex-col gp-items-center gp-justify-end"
              title={`${row.date}: ${money(row.net)} · ${row.orders} orders`}
            >
              <div
                className="gp-w-full gp-rounded-t gp-bg-brand-500 gp-transition group-hover:gp-bg-brand-600"
                style={{ height: `${height}%` }}
              />
            </div>
          );
        })}
      </div>
      <div className="gp-flex gp-justify-between gp-text-xs gp-text-slate-400">
        <span>{rows[0].date}</span>
        <span>{rows[rows.length - 1].date}</span>
      </div>
    </div>
  );
}

function Funnel({ funnel }) {
  const steps = funnel?.steps || [];
  if (!steps.length) return <Empty>Funnel events will appear after storefront traffic is tracked.</Empty>;

  const max = Math.max(...steps.map((step) => step.count), 1);

  return (
    <div className="gp-grid gp-gap-3">
      {steps.map((step, index) => (
        <div key={step.step}>
          <div className="gp-mb-1 gp-flex gp-items-center gp-justify-between gp-text-sm">
            <span className="gp-font-semibold gp-text-slate-800">{step.label}</span>
            <span className="gp-text-slate-500">
              {number(step.count)}
              {index > 0 ? ` · ${number(step.from_prev, 1)}% from previous` : ""}
            </span>
          </div>
          <div className="gp-h-3 gp-overflow-hidden gp-rounded-full gp-bg-slate-100">
            <div
              className="gp-h-full gp-rounded-full gp-bg-brand-500"
              style={{ width: `${Math.max(step.count ? 4 : 0, (step.count / max) * 100)}%` }}
            />
          </div>
        </div>
      ))}
      <div className="gp-flex gp-flex-wrap gp-gap-4 gp-text-sm gp-text-slate-600">
        <span>Overall conversion <strong>{number(funnel.overall_conversion, 2)}%</strong></span>
        <span>Abandoned checkouts <strong>{number(funnel.abandoned_checkout)}</strong></span>
      </div>
    </div>
  );
}

function ProductTable({ rows, empty }) {
  if (!rows?.length) return <Empty>{empty}</Empty>;

  return (
    <div className="gp-overflow-x-auto">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Units</th>
            <th>Orders</th>
            <th>Revenue</th>
            <th>Profit</th>
            <th>Margin</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.product_id}>
              <td className="gp-font-semibold">{row.name}</td>
              <td>{number(row.units)}</td>
              <td>{number(row.orders)}</td>
              <td>{money(row.revenue)}</td>
              <td>{row.profit === null || row.profit === undefined ? "—" : money(row.profit)}</td>
              <td>{row.margin === null || row.margin === undefined ? "—" : `${number(row.margin, 1)}%`}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function Analytics() {
  const [tab, setTab] = useState("overview");
  const [from, setFrom] = useState(daysAgo(30));
  const [to, setTo] = useState(isoDate(new Date()));
  const [preset, setPreset] = useState("30");
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState(null);
  const [overview, setOverview] = useState(null);
  const [customers, setCustomers] = useState(null);
  const [products, setProducts] = useState(null);
  const [marketing, setMarketing] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const applyPreset = (days, id) => {
    setPreset(id);
    setFrom(daysAgo(days));
    setTo(isoDate(new Date()));
  };

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = { from, to };
      if (tab === "overview") setOverview(await api.getAnalytics("overview", params));
      if (tab === "customers") setCustomers(await api.getAnalytics("customers", params));
      if (tab === "products") setProducts(await api.getAnalytics("products", params));
      if (tab === "marketing") setMarketing(await api.getAnalytics("marketing", params));
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      setLoading(false);
    }
  }, [from, to, tab, showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const kpis = useMemo(() => {
    const entries = Object.entries(overview?.kpis || {});
    const primary = ["net_revenue", "orders", "aov", "ltv", "repeat_rate", "returning_customers"];
    const rest = entries.filter(([key]) => !primary.includes(key));
    return {
      primary: primary.map((key) => [key, overview?.kpis?.[key]]).filter(([, row]) => row),
      rest,
    };
  }, [overview]);

  return (
    <div className="gp-grid gp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card
        title="Revenue intelligence"
        description="Ecommerce, customer, product, and marketing analytics from WooCommerce orders and GrowthPilot tracking."
        actions={
          <div className="gp-flex gp-flex-wrap gp-items-end gp-gap-2">
            {PRESETS.map((item) => (
              <Button
                key={item.id}
                variant={preset === item.id ? "primary" : "secondary"}
                onClick={() => applyPreset(item.days, item.id)}
              >
                {item.label}
              </Button>
            ))}
            <input
              className="gp-w-40 gp-rounded-lg gp-border gp-border-slate-200 gp-px-3 gp-py-2 gp-text-sm focus:gp-border-brand-400 focus:gp-outline-none focus:gp-ring-2 focus:gp-ring-brand-100"
              type="date"
              value={from}
              onChange={(e) => {
                setPreset("custom");
                setFrom(e.target.value);
              }}
            />
            <input
              className="gp-w-40 gp-rounded-lg gp-border gp-border-slate-200 gp-px-3 gp-py-2 gp-text-sm focus:gp-border-brand-400 focus:gp-outline-none focus:gp-ring-2 focus:gp-ring-brand-100"
              type="date"
              value={to}
              onChange={(e) => {
                setPreset("custom");
                setTo(e.target.value);
              }}
            />
          </div>
        }
      >
        <div className="gp-flex gp-flex-wrap gp-gap-1 gp-rounded-xl gp-bg-slate-100 gp-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`gp-rounded-lg gp-border-0 gp-px-3 gp-py-2 gp-text-sm gp-font-semibold gp-transition ${
                tab === item.id ? "gp-bg-white gp-text-brand-700 gp-shadow-sm" : "gp-bg-transparent gp-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </Card>

      {loading && <p className="gp-text-slate-500">Loading analytics…</p>}

      {!loading && tab === "overview" && overview && (
        <>
          <div className="gp-grid gp-gap-3 sm:gp-grid-cols-2 lg:gp-grid-cols-3">
            {kpis.primary.map(([key, row]) => (
              <div key={key} className="gp-rounded-2xl gp-border gp-border-slate-200 gp-bg-white gp-p-4 gp-shadow-sm">
                <div className="gp-flex gp-items-start gp-justify-between gp-gap-2">
                  <span className="gp-text-xs gp-font-semibold gp-uppercase gp-tracking-wide gp-text-slate-500">{row.label}</span>
                  <Delta value={row.delta} />
                </div>
                <div className="gp-mt-2 gp-text-2xl gp-font-bold gp-text-slate-900">{formatKpi(key, row.value)}</div>
              </div>
            ))}
          </div>

          <Card title="Revenue trend" description={`Net revenue by ${overview.interval || "day"}.`}>
            <TrendChart rows={overview.trend} />
          </Card>

          <Card title="More revenue metrics">
            <div className="gp-grid gp-gap-3 sm:gp-grid-cols-2 lg:gp-grid-cols-4">
              {kpis.rest.map(([key, row]) => (
                <div key={key} className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                  <div className="gp-flex gp-justify-between gp-gap-2">
                    <span className="gp-text-xs gp-text-slate-500">{row.label}</span>
                    <Delta value={row.delta} />
                  </div>
                  <div className="gp-mt-1 gp-text-lg gp-font-bold">{formatKpi(key, row.value)}</div>
                </div>
              ))}
            </div>
          </Card>
        </>
      )}

      {!loading && tab === "customers" && customers && (
        <>
          <div className="gp-grid gp-gap-3 md:gp-grid-cols-4">
            {[
              ["Customers", number(customers.retention?.customers)],
              ["2nd purchase", `${number(customers.retention?.second_purchase_rate, 1)}%`],
              ["3rd purchase", `${number(customers.retention?.third_purchase_rate, 1)}%`],
              ["Median days between", number(customers.retention?.median_days_between, 1)],
            ].map(([label, value]) => (
              <div key={label} className="gp-rounded-2xl gp-border gp-border-slate-200 gp-bg-white gp-p-4">
                <div className="gp-text-xs gp-font-semibold gp-uppercase gp-text-slate-500">{label}</div>
                <div className="gp-mt-1 gp-text-2xl gp-font-bold">{value}</div>
              </div>
            ))}
          </div>

          <Card title="Cohort retention" description="Share of each acquisition month that purchased again in months 0–5.">
            {!customers.cohorts?.length && <Empty>Cohorts appear after customers place their first paid order.</Empty>}
            {customers.cohorts?.length > 0 && (
              <div className="gp-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Cohort</th>
                      <th>Customers</th>
                      <th>LTV</th>
                      {[0, 1, 2, 3, 4, 5].map((offset) => (
                        <th key={offset}>M{offset}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {customers.cohorts.map((row) => (
                      <tr key={row.cohort}>
                        <td className="gp-font-semibold">{row.cohort}</td>
                        <td>{number(row.customers)}</td>
                        <td>{money(row.value)}</td>
                        {row.months.map((cell) => (
                          <td key={cell.offset}>
                            <span className={`gp-inline-block gp-min-w-[3.5rem] gp-rounded-md gp-px-2 gp-py-1 gp-text-center gp-text-xs gp-font-semibold ${heatClass(cell.retention)}`}>
                              {number(cell.retention, 0)}%
                            </span>
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>

          <div className="gp-grid gp-gap-5 lg:gp-grid-cols-2">
            <Card title="Churn" description="Active ≤ 60 days, at-risk 61–180, churned 180+.">
              <div className="gp-grid gp-grid-cols-3 gp-gap-3">
                <div className="gp-rounded-xl gp-bg-emerald-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">Active</div>
                  <div className="gp-text-xl gp-font-bold">{number(customers.churn?.active)}</div>
                </div>
                <div className="gp-rounded-xl gp-bg-amber-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">At risk</div>
                  <div className="gp-text-xl gp-font-bold">{number(customers.churn?.at_risk)}</div>
                </div>
                <div className="gp-rounded-xl gp-bg-rose-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">Churned</div>
                  <div className="gp-text-xl gp-font-bold">{number(customers.churn?.churned)}</div>
                </div>
              </div>
              <p className="gp-mt-3 gp-text-sm gp-text-slate-600">
                Churn rate <strong>{number(customers.churn?.churn_rate, 1)}%</strong>
              </p>
              {customers.churn?.winback?.length > 0 && (
                <table className="gp-mt-3">
                  <thead>
                    <tr>
                      <th>Win-back</th>
                      <th>Days since</th>
                    </tr>
                  </thead>
                  <tbody>
                    {customers.churn.winback.map((row) => (
                      <tr key={row.customer_id}>
                        <td>
                          <strong>{row.name}</strong>
                          <div className="gp-text-xs gp-text-slate-500">{row.email}</div>
                        </td>
                        <td>{row.days_since}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>

            <Card title="Segment performance" description="VIP, spend, frequency, coupons, referrals, and loyalty.">
              {!customers.segments?.length && <Empty>Segments need paid customer history.</Empty>}
              {customers.segments?.length > 0 && (
                <div className="gp-overflow-x-auto">
                  <table>
                    <thead>
                      <tr>
                        <th>Segment</th>
                        <th>Customers</th>
                        <th>Revenue</th>
                        <th>AOV</th>
                      </tr>
                    </thead>
                    <tbody>
                      {customers.segments.map((row) => (
                        <tr key={row.id}>
                          <td className="gp-font-semibold">{row.name}</td>
                          <td>{number(row.customers)}</td>
                          <td>{money(row.revenue)}</td>
                          <td>{money(row.aov)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>
          </div>
        </>
      )}

      {!loading && tab === "products" && products && (
        <>
          <Card title="Best-selling products" description="Highest net revenue in the selected range.">
            <ProductTable rows={products.best_selling} empty="No product sales in this range." />
          </Card>
          <Card title="Underperforming products" description="Unsold catalog items and the lowest-revenue sellers.">
            <ProductTable rows={products.underperforming} empty="No underperforming products to show." />
          </Card>
          <Card
            title="Product profitability"
            description="Profit and margin use cost-of-goods meta (_cogs, _wc_cog_cost, _alg_wc_cog_cost, _cog_cost) when present."
          >
            <ProductTable rows={products.profitability} empty="No product revenue in this range." />
          </Card>
        </>
      )}

      {!loading && tab === "marketing" && marketing && (
        <>
          <Card title="Funnel conversion" description="Distinct sessions from visit through purchase.">
            <Funnel funnel={marketing.funnel} />
          </Card>

          <Card title="Campaign ROI" description="Referral campaign revenue versus loyalty points issued.">
            {!marketing.campaigns?.length && <Empty>Create a referral campaign to measure ROI.</Empty>}
            {marketing.campaigns?.length > 0 && (
              <div className="gp-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Campaign</th>
                      <th>Clicks</th>
                      <th>Orders</th>
                      <th>Conversion</th>
                      <th>Revenue</th>
                      <th>Points issued</th>
                    </tr>
                  </thead>
                  <tbody>
                    {marketing.campaigns.map((row) => (
                      <tr key={row.id}>
                        <td className="gp-font-semibold">{row.name}</td>
                        <td>{number(row.clicks)}</td>
                        <td>{number(row.orders)}</td>
                        <td>{number(row.conversion, 2)}%</td>
                        <td>{money(row.revenue)}</td>
                        <td>{number(row.points_issued)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>

          <div className="gp-grid gp-gap-5 lg:gp-grid-cols-2">
            <Card title="Email performance" description="WooCommerce transactional sends, with opens from the tracking pixel.">
              <div className="gp-mb-4 gp-grid gp-grid-cols-3 gp-gap-3">
                <div className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">Sent</div>
                  <div className="gp-text-lg gp-font-bold">{number(marketing.email?.totals?.sent)}</div>
                </div>
                <div className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">Open rate</div>
                  <div className="gp-text-lg gp-font-bold">{number(marketing.email?.totals?.open_rate, 1)}%</div>
                </div>
                <div className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                  <div className="gp-text-xs gp-text-slate-500">Click rate</div>
                  <div className="gp-text-lg gp-font-bold">{number(marketing.email?.totals?.click_rate, 1)}%</div>
                </div>
              </div>
              {!marketing.email?.emails?.length && <Empty>No emails sent in this range.</Empty>}
              {marketing.email?.emails?.length > 0 && (
                <div className="gp-overflow-x-auto">
                  <table>
                    <thead>
                      <tr>
                        <th>Email</th>
                        <th>Sent</th>
                        <th>Opened</th>
                        <th>Clicked</th>
                        <th>Revenue</th>
                      </tr>
                    </thead>
                    <tbody>
                      {marketing.email.emails.map((row) => (
                        <tr key={row.key}>
                          <td className="gp-font-semibold">{row.title}</td>
                          <td>{number(row.sent)}</td>
                          <td>{number(row.open_rate, 1)}%</td>
                          <td>{number(row.click_rate, 1)}%</td>
                          <td>{money(row.revenue)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>

            <Card title="Attribution" description="First-touch, last-touch, and multi-touch sources plus referral orders.">
              <p className="gp-mt-0 gp-text-sm gp-text-slate-600">
                Referral-attributed orders <strong>{number(marketing.attribution?.referral)}</strong>
              </p>
              {["first_touch", "last_touch", "multi_touch"].map((key) => {
                const rows = marketing.attribution?.[key] || [];
                const title = key.replace("_", " ");
                return (
                  <div key={key} className="gp-mt-4">
                    <h3 className="gp-mb-2 gp-mt-0 gp-text-sm gp-font-bold gp-capitalize gp-text-slate-800">{title}</h3>
                    {!rows.length && <Empty>No {title} data yet. UTM and referral links populate this report.</Empty>}
                    {rows.length > 0 && (
                      <table>
                        <thead>
                          <tr>
                            <th>Source</th>
                            <th>{key === "multi_touch" ? "Touches" : "Orders"}</th>
                          </tr>
                        </thead>
                        <tbody>
                          {rows.map((row) => (
                            <tr key={`${key}-${row.source}`}>
                              <td>{row.source || "direct"}</td>
                              <td>{number(row.orders ?? row.touches)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>
                );
              })}
            </Card>
          </div>

          <Card title="Coupon performance">
            {!marketing.coupons?.length && <Empty>No coupons used in this range.</Empty>}
            {marketing.coupons?.length > 0 && (
              <div className="gp-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Orders</th>
                      <th>Discount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {marketing.coupons.map((row) => (
                      <tr key={row.coupon_id}>
                        <td className="gp-font-semibold">{row.code}</td>
                        <td>{number(row.orders)}</td>
                        <td>{money(row.discount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </>
      )}
    </div>
  );
}
