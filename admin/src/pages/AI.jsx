import { useCallback, useEffect, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card } from "../components/FormFields";

const TABS = [
  { id: "brain", label: "Brain" },
  { id: "predictions", label: "Predictions" },
  { id: "pricing", label: "Pricing" },
  { id: "forecast", label: "Forecast" },
];

function money(value) {
  const symbol = adminConfig.currency || "$";
  return `${symbol}${Number(value || 0).toLocaleString(undefined, {
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

function Empty({ children }) {
  return <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">{children}</p>;
}

function severityClass(severity) {
  if (severity === "warn") return "gp-ppros-border-rose-200 gp-ppros-bg-rose-50";
  if (severity === "action") return "gp-ppros-border-amber-200 gp-ppros-bg-amber-50";
  return "gp-ppros-border-brand-100 gp-ppros-bg-brand-50";
}

function statusChip(status) {
  const map = {
    high: "gp-ppros-bg-rose-100 gp-ppros-text-rose-700",
    watch: "gp-ppros-bg-amber-100 gp-ppros-text-amber-800",
    healthy: "gp-ppros-bg-emerald-100 gp-ppros-text-emerald-700",
    raise: "gp-ppros-bg-brand-100 gp-ppros-text-brand-800",
    discount: "gp-ppros-bg-amber-100 gp-ppros-text-amber-800",
    tighten_discount: "gp-ppros-bg-slate-200 gp-ppros-text-slate-700",
    hold: "gp-ppros-bg-slate-100 gp-ppros-text-slate-600",
    stockout: "gp-ppros-bg-rose-100 gp-ppros-text-rose-700",
    overstock: "gp-ppros-bg-amber-100 gp-ppros-text-amber-800",
    ok: "gp-ppros-bg-emerald-100 gp-ppros-text-emerald-700",
    untracked: "gp-ppros-bg-slate-100 gp-ppros-text-slate-500",
    peak: "gp-ppros-bg-brand-100 gp-ppros-text-brand-800",
    trough: "gp-ppros-bg-slate-200 gp-ppros-text-slate-700",
    flat: "gp-ppros-bg-slate-100 gp-ppros-text-slate-600",
  };
  return map[status] || "gp-ppros-bg-slate-100 gp-ppros-text-slate-600";
}

export default function AI() {
  const [tab, setTab] = useState("brain");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [toast, setToast] = useState(null);
  const [brain, setBrain] = useState(null);
  const [predictions, setPredictions] = useState(null);
  const [pricing, setPricing] = useState(null);
  const [forecast, setForecast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      if (tab === "brain") setBrain(await api.getAI("brain"));
      if (tab === "predictions") setPredictions(await api.getAI("predictions"));
      if (tab === "pricing") setPricing(await api.getAI("pricing"));
      if (tab === "forecast") setForecast(await api.getAI("forecast"));
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      setLoading(false);
    }
  }, [tab, showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function refresh() {
    setRefreshing(true);
    try {
      await api.refreshAI();
      setBrain(null);
      setPredictions(null);
      setPricing(null);
      setForecast(null);
      await load();
      showToast("Models rebuilt from the latest orders.");
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      setRefreshing(false);
    }
  }

  async function narrate() {
    try {
      setBrain(await api.narrateAI());
      showToast("Commerce Brain narration updated.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  async function applyPrice(row, mode) {
    if (!window.confirm(`Set ${row.name} to ${money(row.suggested_price)}?`)) return;
    try {
      await api.applyAIPrice({
        product_id: row.product_id,
        price: row.suggested_price,
        mode,
      });
      setPricing(await api.getAI("pricing"));
      showToast("Price updated.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="gp-ppros-grid gp-ppros-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card
        title="AI Commerce Brain"
        description="On-store models predict churn, next purchase, prices, seasonality, and inventory. An optional LLM only rewrites the action cards."
        actions={
          <div className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-gap-2">
            {tab === "brain" && brain?.llm?.configured && (
              <Button variant="secondary" onClick={narrate}>
                Narrate with LLM
              </Button>
            )}
            <Button onClick={refresh} disabled={refreshing}>
              {refreshing ? "Rebuilding…" : "Rebuild models"}
            </Button>
          </div>
        }
      >
        <div className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-gap-1 gp-ppros-rounded-xl gp-ppros-bg-slate-100 gp-ppros-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`gp-ppros-rounded-lg gp-ppros-border-0 gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-transition ${
                tab === item.id ? "gp-ppros-bg-white gp-ppros-text-brand-700 gp-ppros-shadow-sm" : "gp-ppros-bg-transparent gp-ppros-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </Card>

      {loading && <p className="gp-ppros-text-slate-500">Running models…</p>}

      {!loading && tab === "brain" && brain && (
        <>
          <div className="gp-ppros-grid gp-ppros-gap-3 sm:gp-ppros-grid-cols-2 lg:gp-ppros-grid-cols-3">
            {[
              ["Customers scored", number(brain.kpis?.customers)],
              ["High churn risk", number(brain.kpis?.high_churn)],
              ["High-value", number(brain.kpis?.high_value)],
              ["Price moves", number(brain.kpis?.price_moves)],
              ["Stockout risk", number(brain.kpis?.stockout)],
              ["30-day unit forecast", number(brain.kpis?.forecast_30, 1)],
            ].map(([label, value]) => (
              <div key={label} className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
                <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">{label}</div>
                <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{value}</div>
              </div>
            ))}
          </div>
          <p className="gp-ppros-m-0 gp-ppros-text-xs gp-ppros-text-slate-500">
            Engine: {brain.engine}
            {brain.llm?.enabled ? ` · LLM ${brain.llm.model}` : " · local models (no API key required)"}
            {brain.llm?.error ? ` · ${brain.llm.error}` : ""}
          </p>
          <div className="gp-ppros-grid gp-ppros-gap-3">
            {(brain.insights || []).map((card) => (
              <div key={card.id} className={`gp-ppros-rounded-2xl gp-ppros-border gp-ppros-p-4 ${severityClass(card.severity)}`}>
                <h3 className="gp-ppros-m-0 gp-ppros-text-base gp-ppros-font-bold gp-ppros-text-slate-900">{card.title}</h3>
                <p className="gp-ppros-mb-0 gp-ppros-mt-2 gp-ppros-text-sm gp-ppros-text-slate-700">{card.body}</p>
                {card.action && (
                  <button
                    type="button"
                    className="gp-ppros-mt-3 gp-ppros-rounded-lg gp-ppros-border-0 gp-ppros-bg-white gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-text-brand-700"
                    onClick={() => setTab(card.tab || "predictions")}
                  >
                    {card.action}
                  </button>
                )}
              </div>
            ))}
          </div>
        </>
      )}

      {!loading && tab === "predictions" && predictions && (
        <>
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-3">
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">High churn</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(predictions.summary?.high_churn)}</div>
            </div>
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">High-value</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(predictions.summary?.high_value)}</div>
            </div>
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">Median repurchase gap</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(predictions.median_gap_days, 1)} days</div>
            </div>
          </div>

          <Card title="Churn risk" description="Logistic score from recency versus each customer’s typical gap.">
            {!predictions.churn?.length && <Empty>Churn scores appear after customers place paid orders.</Empty>}
            {predictions.churn?.length > 0 && (
              <div className="gp-ppros-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th>Risk</th>
                      <th>Days since</th>
                      <th>Confidence</th>
                      <th>Why</th>
                    </tr>
                  </thead>
                  <tbody>
                    {predictions.churn.map((row) => (
                      <tr key={`c-${row.customer_id}`}>
                        <td>
                          <strong>{row.name}</strong>
                          <div className="gp-ppros-text-xs gp-ppros-text-slate-500">{row.email}</div>
                        </td>
                        <td>
                          <span className={`gp-ppros-rounded-md gp-ppros-px-2 gp-ppros-py-1 gp-ppros-text-xs gp-ppros-font-semibold ${statusChip(row.status)}`}>
                            {number(row.churn_risk, 1)}%
                          </span>
                        </td>
                        <td>{row.days_since}</td>
                        <td>{number(row.confidence)}%</td>
                        <td className="gp-ppros-text-slate-600">{row.reason}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>

          <Card title="Next purchase" description="Predicted date = last order + typical gap. Likely product is the last SKU bought.">
            {!predictions.next_purchase?.length && <Empty>Next-purchase dates need at least one paid order per customer.</Empty>}
            {predictions.next_purchase?.length > 0 && (
              <div className="gp-ppros-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th>Next date</th>
                      <th>Days</th>
                      <th>Likely product</th>
                      <th>Why</th>
                    </tr>
                  </thead>
                  <tbody>
                    {predictions.next_purchase.map((row) => (
                      <tr key={`n-${row.customer_id}`}>
                        <td>
                          <strong>{row.name}</strong>
                          <div className="gp-ppros-text-xs gp-ppros-text-slate-500">{row.email}</div>
                        </td>
                        <td>{row.next_purchase_on}</td>
                        <td>{row.days_until}</td>
                        <td>{row.likely_product || "—"}</td>
                        <td className="gp-ppros-text-slate-600">{row.reason}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>

          <Card title="High-value customers" description="RFM value score plus a 90-day spend projection from their run-rate.">
            {!predictions.high_value?.length && <Empty>High-value ranking needs paid customer history.</Empty>}
            {predictions.high_value?.length > 0 && (
              <div className="gp-ppros-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th>Score</th>
                      <th>Revenue</th>
                      <th>90-day forecast</th>
                      <th>Why</th>
                    </tr>
                  </thead>
                  <tbody>
                    {predictions.high_value.map((row) => (
                      <tr key={`h-${row.customer_id}`}>
                        <td>
                          <strong>{row.name}</strong>
                          <div className="gp-ppros-text-xs gp-ppros-text-slate-500">{row.email}</div>
                        </td>
                        <td>{number(row.value_score, 1)}</td>
                        <td>{money(row.revenue)}</td>
                        <td>{money(row.predicted_90d)}</td>
                        <td className="gp-ppros-text-slate-600">{row.reason}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </>
      )}

      {!loading && tab === "pricing" && pricing && (
        <Card
          title="Dynamic pricing & discount optimization"
          description="Raise when demand outruns stock, discount slow movers, tighten coupons that are eating margin. Apply writes WooCommerce sale/regular price."
        >
          {!pricing.recommendations?.length && <Empty>Pricing suggestions appear after products sell.</Empty>}
          {pricing.recommendations?.length > 0 && (
            <div className="gp-ppros-overflow-x-auto">
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Action</th>
                    <th>Current</th>
                    <th>Suggested</th>
                    <th>Cover</th>
                    <th>Season</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {pricing.recommendations.map((row) => (
                    <tr key={row.product_id}>
                      <td>
                        <strong>{row.name}</strong>
                        <div className="gp-ppros-text-xs gp-ppros-text-slate-500">{row.reason}</div>
                      </td>
                      <td>
                        <span className={`gp-ppros-rounded-md gp-ppros-px-2 gp-ppros-py-1 gp-ppros-text-xs gp-ppros-font-semibold ${statusChip(row.action)}`}>
                          {row.action.replace("_", " ")}
                        </span>
                      </td>
                      <td>{money(row.current_price)}</td>
                      <td>{money(row.suggested_price)}</td>
                      <td>{row.days_of_cover === null ? "—" : `${number(row.days_of_cover, 0)}d`}</td>
                      <td>
                        <span className={`gp-ppros-rounded-md gp-ppros-px-2 gp-ppros-py-1 gp-ppros-text-xs gp-ppros-font-semibold ${statusChip(row.season)}`}>
                          {row.season}
                        </span>
                      </td>
                      <td>
                        {row.action !== "hold" && (
                          <Button variant="secondary" onClick={() => applyPrice(row, row.action === "raise" ? "regular" : "sale")}>
                            Apply
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {!loading && tab === "forecast" && forecast && (
        <>
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-3">
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">Next 30 days</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(forecast.store?.forecast_30_units, 1)} units</div>
              <div className="gp-ppros-text-sm gp-ppros-text-slate-500">{money(forecast.store?.forecast_30_revenue)}</div>
            </div>
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">Next 90 days</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(forecast.store?.forecast_90_units, 1)} units</div>
            </div>
            <div className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">Stockout risk</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(forecast.summary?.stockout_risk)}</div>
            </div>
          </div>

          <Card title="Seasonal curve" description={forecast.seasonal?.note}>
            {!forecast.seasonal?.curve?.length && <Empty>Seasonality needs monthly sales history.</Empty>}
            {forecast.seasonal?.curve?.length > 0 && (
              <div className="gp-ppros-flex gp-ppros-h-36 gp-ppros-items-end gp-ppros-gap-1">
                {forecast.seasonal.curve.map((row) => (
                  <div key={row.month} className="gp-ppros-flex gp-ppros-min-w-0 gp-ppros-flex-1 gp-ppros-flex-col gp-ppros-items-center gp-ppros-gap-1" title={`${row.label}: ${row.index}`}>
                    <div
                      className="gp-ppros-w-full gp-ppros-rounded-t gp-ppros-bg-brand-500"
                      style={{ height: `${Math.max(8, Math.min(100, row.index * 50))}%` }}
                    />
                    <span className="gp-ppros-text-[10px] gp-ppros-text-slate-500">{row.label}</span>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title="Inventory forecast" description="Reorder qty = 90-day seasonal forecast minus current stock.">
            {!forecast.inventory?.length && <Empty>Inventory forecasts appear after products sell. Enable stock management for reorder quantities.</Empty>}
            {forecast.inventory?.length > 0 && (
              <div className="gp-ppros-overflow-x-auto">
                <table>
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Status</th>
                      <th>30d</th>
                      <th>90d</th>
                      <th>Stock</th>
                      <th>Reorder</th>
                    </tr>
                  </thead>
                  <tbody>
                    {forecast.inventory.map((row) => (
                      <tr key={row.product_id}>
                        <td className="gp-ppros-font-semibold">{row.name}</td>
                        <td>
                          <span className={`gp-ppros-rounded-md gp-ppros-px-2 gp-ppros-py-1 gp-ppros-text-xs gp-ppros-font-semibold ${statusChip(row.status)}`}>
                            {row.status}
                          </span>
                        </td>
                        <td>{number(row.forecast_30, 1)}</td>
                        <td>{number(row.forecast_90, 1)}</td>
                        <td>{row.stock === null ? "—" : number(row.stock)}</td>
                        <td>{row.reorder_qty === null ? "—" : number(row.reorder_qty)}</td>
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
