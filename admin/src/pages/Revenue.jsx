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
    <div className="myrvento-grid myrvento-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="myrvento-flex myrvento-flex-wrap myrvento-items-end myrvento-justify-between myrvento-gap-3">
        <div>
          <h2 className="myrvento-m-0 myrvento-text-xl myrvento-font-bold myrvento-text-slate-900">Revenue intelligence</h2>
          <p className="myrvento-m-0 myrvento-mt-1 myrvento-text-sm myrvento-text-slate-500">Lifetime value, retention, churn, attribution, profit, and the 30-day forecast.</p>
        </div>
        <div className="myrvento-flex myrvento-flex-wrap myrvento-gap-1 myrvento-rounded-xl myrvento-bg-slate-100 myrvento-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`myrvento-rounded-lg myrvento-border-0 myrvento-px-3 myrvento-py-2 myrvento-text-sm myrvento-font-semibold ${
                tab === item.id ? "myrvento-bg-white myrvento-text-brand-700 myrvento-shadow-sm" : "myrvento-bg-transparent myrvento-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {tab === "ltv" && (
        <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-3">
          {[
            ["Average LTV", money(kpis.ltv?.value)],
            ["Repeat purchase rate", `${number(kpis.repeat_rate?.value, 1)}%`],
            ["Average order value", money(kpis.aov?.value)],
            ["First-time customers", number(kpis.first_time_customers?.value)],
            ["Returning customers", number(kpis.returning_customers?.value)],
            ["Net revenue", money(kpis.net_revenue?.value)],
          ].map(([label, value]) => (
            <div key={label} className="myrvento-rounded-xl myrvento-border myrvento-border-slate-100 myrvento-bg-white myrvento-p-4">
              <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">{label}</div>
              <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{value}</div>
            </div>
          ))}
        </div>
      )}

      {tab === "retention" && (
        <Card title="Retention" description="Share of customers who come back, and the typical gap between orders.">
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-4">
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Customers</div><div className="myrvento-text-2xl myrvento-font-bold">{number(retention.customers)}</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">2nd purchase</div><div className="myrvento-text-2xl myrvento-font-bold">{number(retention.second_purchase_rate, 1)}%</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">3rd purchase</div><div className="myrvento-text-2xl myrvento-font-bold">{number(retention.third_purchase_rate, 1)}%</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Median gap</div><div className="myrvento-text-2xl myrvento-font-bold">{number(retention.median_days_between, 1)}d</div></div>
          </div>
        </Card>
      )}

      {tab === "churn" && (
        <Card title="Churn" description="Active means an order in the last 60 days. At risk is 61–180 days. Churned is longer.">
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-4">
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Active</div><div className="myrvento-text-2xl myrvento-font-bold">{number(churn.active)}</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">At risk</div><div className="myrvento-text-2xl myrvento-font-bold">{number(churn.at_risk)}</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Churned</div><div className="myrvento-text-2xl myrvento-font-bold">{number(churn.churned)}</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Churn rate</div><div className="myrvento-text-2xl myrvento-font-bold">{number(churn.churn_rate, 1)}%</div></div>
          </div>
        </Card>
      )}

      {tab === "attribution" && (
        <div className="myrvento-grid myrvento-gap-5 md:myrvento-grid-cols-2">
          {["first_touch", "last_touch"].map((key) => (
            <Card key={key} title={key === "first_touch" ? "First touch" : "Last touch"}>
              {!(marketing?.attribution?.[key] || []).length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No attributed orders in this range.</p>}
              <table>
                <tbody>
                  {(marketing?.attribution?.[key] || []).map((row) => (
                    <tr key={row.source}>
                      <td className="myrvento-capitalize">{row.source}</td>
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
          {!(products?.profitability || []).length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">Profitability appears after products sell.</p>}
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
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-3">
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Next 30 days</div><div className="myrvento-text-2xl myrvento-font-bold">{money(forecast?.store?.forecast_30_revenue)}</div><div className="myrvento-text-sm myrvento-text-slate-500">{number(forecast?.store?.forecast_30_units, 1)} units</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Next 90 days</div><div className="myrvento-text-2xl myrvento-font-bold">{number(forecast?.store?.forecast_90_units, 1)} units</div></div>
            <div><div className="myrvento-text-xs myrvento-uppercase myrvento-text-slate-500">Confidence</div><div className="myrvento-text-2xl myrvento-font-bold">{number(forecast?.store?.confidence)}%</div></div>
          </div>
        </Card>
      )}
    </div>
  );
}
