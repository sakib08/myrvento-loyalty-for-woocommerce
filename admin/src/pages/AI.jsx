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
  return <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">{children}</p>;
}

function severityClass(severity) {
  if (severity === "warn") return "myrvento-border-rose-200 myrvento-bg-rose-50";
  if (severity === "action") return "myrvento-border-amber-200 myrvento-bg-amber-50";
  return "myrvento-border-brand-100 myrvento-bg-brand-50";
}

function statusChip(status) {
  const map = {
    high: "myrvento-bg-rose-100 myrvento-text-rose-700",
    watch: "myrvento-bg-amber-100 myrvento-text-amber-800",
    healthy: "myrvento-bg-emerald-100 myrvento-text-emerald-700",
    raise: "myrvento-bg-brand-100 myrvento-text-brand-800",
    discount: "myrvento-bg-amber-100 myrvento-text-amber-800",
    tighten_discount: "myrvento-bg-slate-200 myrvento-text-slate-700",
    hold: "myrvento-bg-slate-100 myrvento-text-slate-600",
    stockout: "myrvento-bg-rose-100 myrvento-text-rose-700",
    overstock: "myrvento-bg-amber-100 myrvento-text-amber-800",
    ok: "myrvento-bg-emerald-100 myrvento-text-emerald-700",
    untracked: "myrvento-bg-slate-100 myrvento-text-slate-500",
    peak: "myrvento-bg-brand-100 myrvento-text-brand-800",
    trough: "myrvento-bg-slate-200 myrvento-text-slate-700",
    flat: "myrvento-bg-slate-100 myrvento-text-slate-600",
  };
  return map[status] || "myrvento-bg-slate-100 myrvento-text-slate-600";
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
      showToast("Narration updated.");
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
    <div className="myrvento-grid myrvento-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card
        title="Myrvento Brain"
        description="On-store models predict churn, next purchase, prices, seasonality, and inventory. An optional LLM only rewrites the action cards."
        actions={
          <div className="myrvento-flex myrvento-flex-wrap myrvento-gap-2">
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
        <div className="myrvento-flex myrvento-flex-wrap myrvento-gap-1 myrvento-rounded-xl myrvento-bg-slate-100 myrvento-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`myrvento-rounded-lg myrvento-border-0 myrvento-px-3 myrvento-py-2 myrvento-text-sm myrvento-font-semibold myrvento-transition ${
                tab === item.id ? "myrvento-bg-white myrvento-text-brand-700 myrvento-shadow-sm" : "myrvento-bg-transparent myrvento-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </Card>

      {loading && <p className="myrvento-text-slate-500">Running models…</p>}

      {!loading && tab === "brain" && brain && (
        <>
          <div className="myrvento-grid myrvento-gap-3 sm:myrvento-grid-cols-2 lg:myrvento-grid-cols-3">
            {[
              ["Customers scored", number(brain.kpis?.customers)],
              ["High churn risk", number(brain.kpis?.high_churn)],
              ["High-value", number(brain.kpis?.high_value)],
              ["Price moves", number(brain.kpis?.price_moves)],
              ["Stockout risk", number(brain.kpis?.stockout)],
              ["30-day unit forecast", number(brain.kpis?.forecast_30, 1)],
            ].map(([label, value]) => (
              <div key={label} className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
                <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">{label}</div>
                <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{value}</div>
              </div>
            ))}
          </div>
          <p className="myrvento-m-0 myrvento-text-xs myrvento-text-slate-500">
            Engine: {brain.engine}
            {brain.llm?.enabled ? " · WordPress AI narration" : " · local models"}
            {brain.llm?.error ? ` · ${brain.llm.error}` : ""}
          </p>
          <div className="myrvento-grid myrvento-gap-3">
            {(brain.insights || []).map((card) => (
              <div key={card.id} className={`myrvento-rounded-2xl myrvento-border myrvento-p-4 ${severityClass(card.severity)}`}>
                <h3 className="myrvento-m-0 myrvento-text-base myrvento-font-bold myrvento-text-slate-900">{card.title}</h3>
                <p className="myrvento-mb-0 myrvento-mt-2 myrvento-text-sm myrvento-text-slate-700">{card.body}</p>
                {card.action && (
                  <button
                    type="button"
                    className="myrvento-mt-3 myrvento-rounded-lg myrvento-border-0 myrvento-bg-white myrvento-px-3 myrvento-py-2 myrvento-text-sm myrvento-font-semibold myrvento-text-brand-700"
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
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-3">
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">High churn</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(predictions.summary?.high_churn)}</div>
            </div>
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">High-value</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(predictions.summary?.high_value)}</div>
            </div>
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">Median repurchase gap</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(predictions.median_gap_days, 1)} days</div>
            </div>
          </div>

          <Card title="Churn risk" description="Logistic score from recency versus each customer’s typical gap.">
            {!predictions.churn?.length && <Empty>Churn scores appear after customers place paid orders.</Empty>}
            {predictions.churn?.length > 0 && (
              <div className="myrvento-overflow-x-auto">
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
                          <div className="myrvento-text-xs myrvento-text-slate-500">{row.email}</div>
                        </td>
                        <td>
                          <span className={`myrvento-rounded-md myrvento-px-2 myrvento-py-1 myrvento-text-xs myrvento-font-semibold ${statusChip(row.status)}`}>
                            {number(row.churn_risk, 1)}%
                          </span>
                        </td>
                        <td>{row.days_since}</td>
                        <td>{number(row.confidence)}%</td>
                        <td className="myrvento-text-slate-600">{row.reason}</td>
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
              <div className="myrvento-overflow-x-auto">
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
                          <div className="myrvento-text-xs myrvento-text-slate-500">{row.email}</div>
                        </td>
                        <td>{row.next_purchase_on}</td>
                        <td>{row.days_until}</td>
                        <td>{row.likely_product || "—"}</td>
                        <td className="myrvento-text-slate-600">{row.reason}</td>
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
              <div className="myrvento-overflow-x-auto">
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
                          <div className="myrvento-text-xs myrvento-text-slate-500">{row.email}</div>
                        </td>
                        <td>{number(row.value_score, 1)}</td>
                        <td>{money(row.revenue)}</td>
                        <td>{money(row.predicted_90d)}</td>
                        <td className="myrvento-text-slate-600">{row.reason}</td>
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
            <div className="myrvento-overflow-x-auto">
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
                        <div className="myrvento-text-xs myrvento-text-slate-500">{row.reason}</div>
                      </td>
                      <td>
                        <span className={`myrvento-rounded-md myrvento-px-2 myrvento-py-1 myrvento-text-xs myrvento-font-semibold ${statusChip(row.action)}`}>
                          {row.action.replace("_", " ")}
                        </span>
                      </td>
                      <td>{money(row.current_price)}</td>
                      <td>{money(row.suggested_price)}</td>
                      <td>{row.days_of_cover === null ? "—" : `${number(row.days_of_cover, 0)}d`}</td>
                      <td>
                        <span className={`myrvento-rounded-md myrvento-px-2 myrvento-py-1 myrvento-text-xs myrvento-font-semibold ${statusChip(row.season)}`}>
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
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-3">
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">Next 30 days</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(forecast.store?.forecast_30_units, 1)} units</div>
              <div className="myrvento-text-sm myrvento-text-slate-500">{money(forecast.store?.forecast_30_revenue)}</div>
            </div>
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">Next 90 days</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(forecast.store?.forecast_90_units, 1)} units</div>
            </div>
            <div className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">Stockout risk</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(forecast.summary?.stockout_risk)}</div>
            </div>
          </div>

          <Card title="Seasonal curve" description={forecast.seasonal?.note}>
            {!forecast.seasonal?.curve?.length && <Empty>Seasonality needs monthly sales history.</Empty>}
            {forecast.seasonal?.curve?.length > 0 && (
              <div className="myrvento-flex myrvento-h-36 myrvento-items-end myrvento-gap-1">
                {forecast.seasonal.curve.map((row) => (
                  <div key={row.month} className="myrvento-flex myrvento-min-w-0 myrvento-flex-1 myrvento-flex-col myrvento-items-center myrvento-gap-1" title={`${row.label}: ${row.index}`}>
                    <div
                      className="myrvento-w-full myrvento-rounded-t myrvento-bg-brand-500"
                      style={{ height: `${Math.max(8, Math.min(100, row.index * 50))}%` }}
                    />
                    <span className="myrvento-text-[10px] myrvento-text-slate-500">{row.label}</span>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title="Inventory forecast" description="Reorder qty = 90-day seasonal forecast minus current stock.">
            {!forecast.inventory?.length && <Empty>Inventory forecasts appear after products sell. Enable stock management for reorder quantities.</Empty>}
            {forecast.inventory?.length > 0 && (
              <div className="myrvento-overflow-x-auto">
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
                        <td className="myrvento-font-semibold">{row.name}</td>
                        <td>
                          <span className={`myrvento-rounded-md myrvento-px-2 myrvento-py-1 myrvento-text-xs myrvento-font-semibold ${statusChip(row.status)}`}>
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
