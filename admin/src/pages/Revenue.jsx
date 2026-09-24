import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Card } from "../components/FormFields";
import { money, number } from "../format";

const TABS = [
  { id: "ltv", label: "LTV" },
  { id: "retention", label: "Retention" },
  { id: "churn", label: "Churn" },
  { id: "attribution", label: "Attribution" },
  { id: "profitability", label: "Profitability" },
  { id: "forecast", label: "Revenue forecasting" },
];

export default function Revenue() {
  const [tab, setTab] = useState("ltv");
  const [overview, setOverview] = useState(null);
  const [customers, setCustomers] = useState(null);
  const [products, setProducts] = useState(null);
  const [marketing, setMarketing] = useState(null);
  const [forecast, setForecast] = useState(null);
  const [toast, setToast] = useState(null);

  const load = useCallback(async () => {
    try {
      const [overviewRow, customerRow, productRow, marketingRow, forecastRow] = await Promise.all([
        api.getAnalytics("overview"),
        api.getAnalytics("customers"),
        api.getAnalytics("products"),
        api.getAnalytics("marketing"),
        api.getAI("forecast"),
      ]);
      setOverview(overviewRow);
      setCustomers(customerRow);
      setProducts(productRow);
      setMarketing(marketingRow);
      setForecast(forecastRow);
    } catch (err) {
      setToast({ message: err.message, type: "error" });
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const kpis = overview?.kpis || {};
  const retention = customers?.retention || {};
  const churn = customers?.churn || {};

  return (
    <div className="gp-ppros-grid gp-ppros-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-items-end gp-ppros-justify-between gp-ppros-gap-3">
        <div>
          <h2 className="gp-ppros-m-0 gp-ppros-text-xl gp-ppros-font-bold gp-ppros-text-slate-900">Revenue intelligence</h2>
          <p className="gp-ppros-m-0 gp-ppros-mt-1 gp-ppros-text-sm gp-ppros-text-slate-500">Lifetime value, retention, churn, attribution, profit, and the 30-day forecast.</p>
        </div>
        <div className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-gap-1 gp-ppros-rounded-xl gp-ppros-bg-slate-100 gp-ppros-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`gp-ppros-rounded-lg gp-ppros-border-0 gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm gp-ppros-font-semibold ${
                tab === item.id ? "gp-ppros-bg-white gp-ppros-text-brand-700 gp-ppros-shadow-sm" : "gp-ppros-bg-transparent gp-ppros-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {tab === "ltv" && (
        <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-3">
          {[
            ["Average LTV", money(kpis.ltv?.value)],
            ["Repeat purchase rate", `${number(kpis.repeat_rate?.value, 1)}%`],
            ["Average order value", money(kpis.aov?.value)],
            ["First-time customers", number(kpis.first_time_customers?.value)],
            ["Returning customers", number(kpis.returning_customers?.value)],
            ["Net revenue", money(kpis.net_revenue?.value)],
          ].map(([label, value]) => (
            <div key={label} className="gp-ppros-rounded-xl gp-ppros-border gp-ppros-border-slate-100 gp-ppros-bg-white gp-ppros-p-4">
              <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">{label}</div>
              <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{value}</div>
            </div>
          ))}
        </div>
      )}

      {tab === "retention" && (
        <Card title="Retention" description="Share of customers who come back, and the typical gap between orders.">
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-4">
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Customers</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(retention.customers)}</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">2nd purchase</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(retention.second_purchase_rate, 1)}%</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">3rd purchase</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(retention.third_purchase_rate, 1)}%</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Median gap</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(retention.median_days_between, 1)}d</div></div>
          </div>
        </Card>
      )}

      {tab === "churn" && (
        <Card title="Churn" description="Active means an order in the last 60 days. At risk is 61–180 days. Churned is longer.">
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-4">
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Active</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(churn.active)}</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">At risk</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(churn.at_risk)}</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Churned</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(churn.churned)}</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Churn rate</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(churn.churn_rate, 1)}%</div></div>
          </div>
        </Card>
      )}

      {tab === "attribution" && (
        <div className="gp-ppros-grid gp-ppros-gap-5 md:gp-ppros-grid-cols-2">
          {["first_touch", "last_touch"].map((key) => (
            <Card key={key} title={key === "first_touch" ? "First touch" : "Last touch"}>
              {!(marketing?.attribution?.[key] || []).length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">No attributed orders in this range.</p>}
              <table>
                <tbody>
                  {(marketing?.attribution?.[key] || []).map((row) => (
                    <tr key={row.source}>
                      <td className="gp-ppros-capitalize">{row.source}</td>
                      <td>{number(row.orders)} orders</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Card>
          ))}
        </div>
      )}

      {tab === "profitability" && (
        <Card title="Product profitability" description="Net revenue for products sold in the selected analytics range. Unit cost is used when a product has COGS.">
          {!(products?.profitability || []).length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">Profitability appears after products sell.</p>}
          {(products?.profitability || []).length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Units</th>
                  <th>Revenue</th>
                  <th>Profit</th>
                </tr>
              </thead>
              <tbody>
                {products.profitability.map((row) => (
                  <tr key={row.product_id || row.name}>
                    <td>{row.name}</td>
                    <td>{number(row.units)}</td>
                    <td>{money(row.revenue)}</td>
                    <td>{row.profit == null ? "—" : money(row.profit)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}

      {tab === "forecast" && (
        <Card title="Revenue forecasting" description="On-store seasonal forecast. It stays directional until there is at least six months of sales.">
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-3">
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Next 30 days</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{money(forecast?.store?.forecast_30_revenue)}</div><div className="gp-ppros-text-sm gp-ppros-text-slate-500">{number(forecast?.store?.forecast_30_units, 1)} units</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Next 90 days</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(forecast?.store?.forecast_90_units, 1)} units</div></div>
            <div><div className="gp-ppros-text-xs gp-ppros-uppercase gp-ppros-text-slate-500">Confidence</div><div className="gp-ppros-text-2xl gp-ppros-font-bold">{number(forecast?.store?.confidence)}%</div></div>
          </div>
        </Card>
      )}
    </div>
  );
}
