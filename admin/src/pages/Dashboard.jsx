import { useCallback, useEffect, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Card } from "../components/FormFields";
import { money, number } from "../format";

function Kpi({ label, value, hint }) {
  return (
    <div className="gp-ppros-rounded-xl gp-ppros-border gp-ppros-border-slate-100 gp-ppros-bg-slate-50 gp-ppros-p-4">
      <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-tracking-wide gp-ppros-text-slate-500">{label}</div>
      <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold gp-ppros-text-slate-900">{value}</div>
      {hint && <div className="gp-ppros-mt-1 gp-ppros-text-xs gp-ppros-text-slate-500">{hint}</div>}
    </div>
  );
}

function Trend({ rows }) {
  if (!rows?.length) {
    return <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">Paid orders will draw the revenue trend.</p>;
  }
  const max = Math.max(...rows.map((row) => Number(row.net) || 0), 1);
  return (
    <div className="gp-ppros-flex gp-ppros-h-40 gp-ppros-items-end gp-ppros-gap-px">
      {rows.map((row) => (
        <div
          key={row.date}
          className="gp-ppros-min-w-0 gp-ppros-flex-1 gp-ppros-rounded-t gp-ppros-bg-brand-500"
          style={{ height: `${Math.max(2, (Number(row.net) / max) * 100)}%` }}
          title={`${row.date}: ${money(row.net)}`}
        />
      ))}
    </div>
  );
}

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [toast, setToast] = useState(null);
  const urls = adminConfig.urls || {};

  const load = useCallback(async () => {
    try {
      setData(await api.getDashboard());
    } catch (err) {
      setToast({ message: err.message, type: "error" });
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const kpis = data?.kpis || {};

  return (
    <div className="gp-ppros-grid gp-ppros-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div>
        <h2 className="gp-ppros-m-0 gp-ppros-text-xl gp-ppros-font-bold gp-ppros-text-slate-900">Dashboard</h2>
        <p className="gp-ppros-m-0 gp-ppros-mt-1 gp-ppros-text-sm gp-ppros-text-slate-500">
          Last 30 days{data?.range ? ` · ${data.range.from} to ${data.range.to}` : ""}. Sales, orders, and revenue in one place.
        </p>
      </div>

      <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-4">
        <Kpi label="Net revenue" value={money(kpis.net_revenue?.value)} hint={kpis.net_revenue?.delta != null ? `${kpis.net_revenue.delta}% vs previous` : ""} />
        <Kpi label="Orders" value={number(kpis.orders?.value)} />
        <Kpi label="Average order value" value={money(kpis.aov?.value)} />
        <Kpi label="Customer LTV" value={money(kpis.ltv?.value)} />
        <Kpi label="Abandoned carts" value={number(data?.sales?.abandoned)} hint="Last 30 days" />
        <Kpi label="Win-back queue" value={number(data?.sales?.recovery)} hint="Quiet 60+ days" />
        <Kpi label="Churn rate" value={`${number(data?.churn?.churn_rate, 1)}%`} hint={`${number(data?.churn?.at_risk)} at risk`} />
        <Kpi label="Forecast, 30 days" value={money(data?.forecast?.revenue_30)} hint={`${number(data?.forecast?.units_30, 1)} units`} />
      </div>

      <div className="gp-ppros-grid gp-ppros-gap-5 lg:gp-ppros-grid-cols-3">
        <div className="lg:gp-ppros-col-span-2">
          <Card title="Revenue trend">
            <Trend rows={data?.trend} />
          </Card>
        </div>
        <Card title="Top products">
          {!data?.top?.length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">No product sales in this range.</p>}
          <ul className="gp-ppros-m-0 gp-ppros-grid gp-ppros-list-none gp-ppros-gap-3 gp-ppros-p-0">
            {(data?.top || []).map((row) => (
              <li key={row.name} className="gp-ppros-flex gp-ppros-items-center gp-ppros-justify-between gp-ppros-gap-3 gp-ppros-text-sm">
                <span className="gp-ppros-font-semibold gp-ppros-text-slate-800">{row.name}</span>
                <span className="gp-ppros-text-slate-500">{money(row.revenue)}</span>
              </li>
            ))}
          </ul>
        </Card>
      </div>

      <Card title="Recent orders">
        {!data?.orders?.recent?.length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">No orders yet.</p>}
        {data?.orders?.recent?.length > 0 && (
          <table>
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {data.orders.recent.slice(0, 8).map((order) => (
                <tr key={order.id}>
                  <td>#{order.number}</td>
                  <td>{order.customer}</td>
                  <td>{order.status}</td>
                  <td>{money(order.total)}</td>
                  <td>{order.date}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        <div className="gp-ppros-mt-4 gp-ppros-flex gp-ppros-flex-wrap gp-ppros-gap-3 gp-ppros-text-sm">
          <a className="gp-ppros-font-semibold gp-ppros-text-brand-700 gp-ppros-no-underline" href={urls.sales}>Sales & conversion</a>
          <a className="gp-ppros-font-semibold gp-ppros-text-brand-700 gp-ppros-no-underline" href={urls.operations}>Operations</a>
          <a className="gp-ppros-font-semibold gp-ppros-text-brand-700 gp-ppros-no-underline" href={urls.analytics}>Analytics</a>
          <a className="gp-ppros-font-semibold gp-ppros-text-brand-700 gp-ppros-no-underline" href={urls.revenue}>Revenue intelligence</a>
        </div>
      </Card>
    </div>
  );
}
