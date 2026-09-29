import { useCallback, useEffect, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Card } from "../components/FormFields";
import { money, number } from "../format";

function Kpi({ label, value, hint }) {
  return (
    <div className="ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-bg-slate-50 ciwp-p-4">
      <div className="ciwp-text-xs ciwp-font-semibold ciwp-uppercase ciwp-tracking-wide ciwp-text-slate-500">{label}</div>
      <div className="ciwp-mt-1 ciwp-text-2xl ciwp-font-bold ciwp-text-slate-900">{value}</div>
      {hint && <div className="ciwp-mt-1 ciwp-text-xs ciwp-text-slate-500">{hint}</div>}
    </div>
  );
}

function Trend({ rows }) {
  if (!rows?.length) {
    return <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">Paid orders will draw the revenue trend.</p>;
  }
  const max = Math.max(...rows.map((row) => Number(row.net) || 0), 1);
  return (
    <div className="ciwp-flex ciwp-h-40 ciwp-items-end ciwp-gap-px">
      {rows.map((row) => (
        <div
          key={row.date}
          className="ciwp-min-w-0 ciwp-flex-1 ciwp-rounded-t ciwp-bg-brand-500"
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
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div>
        <h2 className="ciwp-m-0 ciwp-text-xl ciwp-font-bold ciwp-text-slate-900">Dashboard</h2>
        <p className="ciwp-m-0 ciwp-mt-1 ciwp-text-sm ciwp-text-slate-500">
          Last 30 days{data?.range ? ` · ${data.range.from} to ${data.range.to}` : ""}. Sales, orders, and revenue in one place.
        </p>
      </div>

      <div className="ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-4">
        <Kpi label="Net revenue" value={money(kpis.net_revenue?.value)} hint={kpis.net_revenue?.delta != null ? `${kpis.net_revenue.delta}% vs previous` : ""} />
        <Kpi label="Orders" value={number(kpis.orders?.value)} />
        <Kpi label="Average order value" value={money(kpis.aov?.value)} />
        <Kpi label="Customer LTV" value={money(kpis.ltv?.value)} />
        <Kpi label="Abandoned carts" value={number(data?.sales?.abandoned)} hint="Last 30 days" />
        <Kpi label="Win-back queue" value={number(data?.sales?.recovery)} hint="Quiet 60+ days" />
        <Kpi label="Churn rate" value={`${number(data?.churn?.churn_rate, 1)}%`} hint={`${number(data?.churn?.at_risk)} at risk`} />
        <Kpi label="Forecast, 30 days" value={money(data?.forecast?.revenue_30)} hint={`${number(data?.forecast?.units_30, 1)} units`} />
      </div>

      <div className="ciwp-grid ciwp-gap-5 lg:ciwp-grid-cols-3">
        <div className="lg:ciwp-col-span-2">
          <Card title="Revenue trend">
            <Trend rows={data?.trend} />
          </Card>
        </div>
        <Card title="Top products">
          {!data?.top?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">No product sales in this range.</p>}
          <ul className="ciwp-m-0 ciwp-grid ciwp-list-none ciwp-gap-3 ciwp-p-0">
            {(data?.top || []).map((row) => (
              <li key={row.name} className="ciwp-flex ciwp-items-center ciwp-justify-between ciwp-gap-3 ciwp-text-sm">
                <span className="ciwp-font-semibold ciwp-text-slate-800">{row.name}</span>
                <span className="ciwp-text-slate-500">{money(row.revenue)}</span>
              </li>
            ))}
          </ul>
        </Card>
      </div>

      <Card title="Recent orders">
        {!data?.orders?.recent?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">No orders yet.</p>}
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
        <div className="ciwp-mt-4 ciwp-flex ciwp-flex-wrap ciwp-gap-3 ciwp-text-sm">
          <a className="ciwp-font-semibold ciwp-text-brand-700 ciwp-no-underline" href={urls.sales}>Sales & conversion</a>
          <a className="ciwp-font-semibold ciwp-text-brand-700 ciwp-no-underline" href={urls.operations}>Operations</a>
          <a className="ciwp-font-semibold ciwp-text-brand-700 ciwp-no-underline" href={urls.analytics}>Analytics</a>
          <a className="ciwp-font-semibold ciwp-text-brand-700 ciwp-no-underline" href={urls.revenue}>Revenue intelligence</a>
        </div>
      </Card>
    </div>
  );
}
