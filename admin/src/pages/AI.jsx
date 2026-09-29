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
  return <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">{children}</p>;
}

function severityClass(severity) {
  if (severity === "warn") return "ciwp-border-rose-200 ciwp-bg-rose-50";
  if (severity === "action") return "ciwp-border-amber-200 ciwp-bg-amber-50";
  return "ciwp-border-brand-100 ciwp-bg-brand-50";
}

function statusChip(status) {
  const map = {
    high: "ciwp-bg-rose-100 ciwp-text-rose-700",
    watch: "ciwp-bg-amber-100 ciwp-text-amber-800",
    healthy: "ciwp-bg-emerald-100 ciwp-text-emerald-700",
    raise: "ciwp-bg-brand-100 ciwp-text-brand-800",
    discount: "ciwp-bg-amber-100 ciwp-text-amber-800",
    tighten_discount: "ciwp-bg-slate-200 ciwp-text-slate-700",
    hold: "ciwp-bg-slate-100 ciwp-text-slate-600",
    stockout: "ciwp-bg-rose-100 ciwp-text-rose-700",
    overstock: "ciwp-bg-amber-100 ciwp-text-amber-800",
    ok: "ciwp-bg-emerald-100 ciwp-text-emerald-700",
    untracked: "ciwp-bg-slate-100 ciwp-text-slate-500",
    peak: "ciwp-bg-brand-100 ciwp-text-brand-800",
    trough: "ciwp-bg-slate-200 ciwp-text-slate-700",
    flat: "ciwp-bg-slate-100 ciwp-text-slate-600",
  };
  return map[status] || "ciwp-bg-slate-100 ciwp-text-slate-600";
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
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card
        title="AI Commerce Brain"
        description="On-store models predict churn, next purchase, prices, seasonality, and inventory. An optional LLM only rewrites the action cards."
        actions={
          <div className="ciwp-flex ciwp-flex-wrap ciwp-gap-2">
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
        <div className="ciwp-flex ciwp-flex-wrap ciwp-gap-1 ciwp-rounded-xl ciwp-bg-slate-100 ciwp-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`ciwp-rounded-lg ciwp-border-0 ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ciwp-transition ${
                tab === item.id ? "ciwp-bg-white ciwp-text-brand-700 ciwp-shadow-sm" : "ciwp-bg-transparent ciwp-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </Card>

      {loading && <p className="ciwp-text-slate-500">Running models…</p>}

      {!loading && tab === "brain" && brain && (
        <>
          <div className="ciwp-grid ciwp-gap-3 sm:ciwp-grid-cols-2 lg:ciwp-grid-cols-3">
            {[
              ["Customers scored", number(brain.kpis?.customers)],
              ["High churn risk", number(brain.kpis?.high_churn)],
              ["High-value", number(brain.kpis?.high_value)],
              ["Price moves", number(brain.kpis?.price_moves)],
              ["Stockout risk", number(brain.kpis?.stockout)],
              ["30-day unit forecast", number(brain.kpis?.forecast_30, 1)],
            ].map(([label, value]) => (
              <div key={label} className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
                <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">{label}</div>
                <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{value}</div>
              </div>
            ))}
          </div>
          <p className="ciwp-m-0 ciwp-text-xs ciwp-text-slate-500">
            Engine: {brain.engine}
            {brain.llm?.enabled ? ` · LLM ${brain.llm.model}` : " · local models (no API key required)"}
            {brain.llm?.error ? ` · ${brain.llm.error}` : ""}
          </p>
          <div className="ciwp-grid ciwp-gap-3">
            {(brain.insights || []).map((card) => (
              <div key={card.id} className={`ciwp-rounded-2xl ciwp-border ciwp-p-4 ${severityClass(card.severity)}`}>
                <h3 className="ciwp-m-0 ciwp-text-base ciwp-font-bold ciwp-text-slate-900">{card.title}</h3>
                <p className="ciwp-mb-0 ciwp-mt-2 ciwp-text-sm ciwp-text-slate-700">{card.body}</p>
                {card.action && (
                  <button
                    type="button"
                    className="ciwp-mt-3 ciwp-rounded-lg ciwp-border-0 ciwp-bg-white ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ciwp-text-brand-700"
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
          <div className="ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-3">
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">High churn</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(predictions.summary?.high_churn)}</div>
            </div>
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">High-value</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(predictions.summary?.high_value)}</div>
            </div>
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">Median repurchase gap</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(predictions.median_gap_days, 1)} days</div>
            </div>
          </div>

          <Card title="Churn risk" description="Logistic score from recency versus each customer’s typical gap.">
            {!predictions.churn?.length && <Empty>Churn scores appear after customers place paid orders.</Empty>}
            {predictions.churn?.length > 0 && (
              <div className="ciwp-overflow-x-auto">
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
                          <div className="ciwp-text-xs ciwp-text-slate-500">{row.email}</div>
                        </td>
                        <td>
                          <span className={`ciwp-rounded-md ciwp-px-2 ciwp-py-1 ciwp-text-xs ciwp-font-semibold ${statusChip(row.status)}`}>
                            {number(row.churn_risk, 1)}%
                          </span>
                        </td>
                        <td>{row.days_since}</td>
                        <td>{number(row.confidence)}%</td>
                        <td className="ciwp-text-slate-600">{row.reason}</td>
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
              <div className="ciwp-overflow-x-auto">
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
                          <div className="ciwp-text-xs ciwp-text-slate-500">{row.email}</div>
                        </td>
                        <td>{row.next_purchase_on}</td>
                        <td>{row.days_until}</td>
                        <td>{row.likely_product || "—"}</td>
                        <td className="ciwp-text-slate-600">{row.reason}</td>
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
              <div className="ciwp-overflow-x-auto">
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
                          <div className="ciwp-text-xs ciwp-text-slate-500">{row.email}</div>
                        </td>
                        <td>{number(row.value_score, 1)}</td>
                        <td>{money(row.revenue)}</td>
                        <td>{money(row.predicted_90d)}</td>
                        <td className="ciwp-text-slate-600">{row.reason}</td>
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
            <div className="ciwp-overflow-x-auto">
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
                        <div className="ciwp-text-xs ciwp-text-slate-500">{row.reason}</div>
                      </td>
                      <td>
                        <span className={`ciwp-rounded-md ciwp-px-2 ciwp-py-1 ciwp-text-xs ciwp-font-semibold ${statusChip(row.action)}`}>
                          {row.action.replace("_", " ")}
                        </span>
                      </td>
                      <td>{money(row.current_price)}</td>
                      <td>{money(row.suggested_price)}</td>
                      <td>{row.days_of_cover === null ? "—" : `${number(row.days_of_cover, 0)}d`}</td>
                      <td>
                        <span className={`ciwp-rounded-md ciwp-px-2 ciwp-py-1 ciwp-text-xs ciwp-font-semibold ${statusChip(row.season)}`}>
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
          <div className="ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-3">
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">Next 30 days</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(forecast.store?.forecast_30_units, 1)} units</div>
              <div className="ciwp-text-sm ciwp-text-slate-500">{money(forecast.store?.forecast_30_revenue)}</div>
            </div>
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">Next 90 days</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(forecast.store?.forecast_90_units, 1)} units</div>
            </div>
            <div className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-4">
              <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-text-slate-500">Stockout risk</div>
              <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold">{number(forecast.summary?.stockout_risk)}</div>
            </div>
          </div>

          <Card title="Seasonal curve" description={forecast.seasonal?.note}>
            {!forecast.seasonal?.curve?.length && <Empty>Seasonality needs monthly sales history.</Empty>}
            {forecast.seasonal?.curve?.length > 0 && (
              <div className="ciwp-flex ciwp-h-36 ciwp-items-end ciwp-gap-1">
                {forecast.seasonal.curve.map((row) => (
                  <div key={row.month} className="ciwp-flex ciwp-min-w-0 ciwp-flex-1 ciwp-flex-col ciwp-items-center ciwp-gap-1" title={`${row.label}: ${row.index}`}>
                    <div
                      className="ciwp-w-full ciwp-rounded-t ciwp-bg-brand-500"
                      style={{ height: `${Math.max(8, Math.min(100, row.index * 50))}%` }}
                    />
                    <span className="ciwp-text-[10px] ciwp-text-slate-500">{row.label}</span>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title="Inventory forecast" description="Reorder qty = 90-day seasonal forecast minus current stock.">
            {!forecast.inventory?.length && <Empty>Inventory forecasts appear after products sell. Enable stock management for reorder quantities.</Empty>}
            {forecast.inventory?.length > 0 && (
              <div className="ciwp-overflow-x-auto">
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
                        <td className="ciwp-font-semibold">{row.name}</td>
                        <td>
                          <span className={`ciwp-rounded-md ciwp-px-2 ciwp-py-1 ciwp-text-xs ciwp-font-semibold ${statusChip(row.status)}`}>
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
