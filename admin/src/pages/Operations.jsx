import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Card } from "../components/FormFields";
import { money, number } from "../format";

const TABS = [
  { id: "orders", label: "Orders" },
  { id: "subscriptions", label: "Subscriptions" },
  { id: "coupons", label: "Coupons" },
  { id: "activity", label: "Customer activity" },
];

export default function Operations() {
  const [tab, setTab] = useState("orders");
  const [data, setData] = useState(null);
  const [toast, setToast] = useState(null);

  const load = useCallback(async () => {
    try {
      setData(await api.getOperations());
    } catch (err) {
      setToast({ message: err.message, type: "error" });
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div className="gp-ppros-grid gp-ppros-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="gp-ppros-flex gp-ppros-flex-wrap gp-ppros-items-end gp-ppros-justify-between gp-ppros-gap-3">
        <div>
          <h2 className="gp-ppros-m-0 gp-ppros-text-xl gp-ppros-font-bold gp-ppros-text-slate-900">WooCommerce operations</h2>
          <p className="gp-ppros-m-0 gp-ppros-mt-1 gp-ppros-text-sm gp-ppros-text-slate-500">Orders, subscriptions, coupons, and what customers just did.</p>
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

      {tab === "orders" && (
        <div className="gp-ppros-grid gp-ppros-gap-5">
          <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-4">
            {(data?.orders?.summary || []).map((row) => (
              <div key={row.status} className="gp-ppros-rounded-xl gp-ppros-border gp-ppros-border-slate-100 gp-ppros-bg-white gp-ppros-p-4">
                <div className="gp-ppros-text-xs gp-ppros-font-semibold gp-ppros-uppercase gp-ppros-text-slate-500">{row.label}</div>
                <div className="gp-ppros-mt-1 gp-ppros-text-2xl gp-ppros-font-bold">{number(row.count)}</div>
              </div>
            ))}
          </div>
          <Card title="Latest orders">
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
                  {data.orders.recent.map((order) => (
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
          </Card>
        </div>
      )}

      {tab === "subscriptions" && (
        <Card title="Subscriptions" description={data?.subscriptions?.available ? "Active recurring orders." : (data?.subscriptions?.note || "Checking for WooCommerce Subscriptions…")}>
          {data?.subscriptions?.available && !data.subscriptions.items?.length && (
            <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">No subscriptions yet.</p>
          )}
          {data?.subscriptions?.items?.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Subscription</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Total</th>
                  <th>Next payment</th>
                </tr>
              </thead>
              <tbody>
                {data.subscriptions.items.map((row) => (
                  <tr key={row.id}>
                    <td>#{row.id}</td>
                    <td>{row.customer}</td>
                    <td>{row.status}</td>
                    <td>{money(row.total)}</td>
                    <td>{row.next || "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}

      {tab === "coupons" && (
        <Card title="Coupons" description="Published WooCommerce coupons and how many times each has been used.">
          {!data?.coupons?.length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">No coupons yet.</p>}
          {data?.coupons?.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Used</th>
                  <th>Expires</th>
                </tr>
              </thead>
              <tbody>
                {data.coupons.map((row) => (
                  <tr key={row.code}>
                    <td><code>{row.code}</code></td>
                    <td>{row.type}</td>
                    <td>{row.type === "percent" ? `${number(row.amount)}%` : money(row.amount)}</td>
                    <td>{number(row.used)}</td>
                    <td>{row.expiry || "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}

      {tab === "activity" && (
        <Card title="Customer activity" description="Recent storefront events and points movements.">
          {!data?.activity?.length && <p className="gp-ppros-m-0 gp-ppros-text-sm gp-ppros-text-slate-500">Activity appears after visits, carts, and point changes.</p>}
          {data?.activity?.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>When</th>
                  <th>Who</th>
                  <th>What</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
                {data.activity.map((row, index) => (
                  <tr key={`${row.at}-${index}`}>
                    <td>{row.at}</td>
                    <td>{row.who}</td>
                    <td>{row.label}</td>
                    <td>{row.kind === "points" ? `${row.extra} pts` : row.extra}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}
    </div>
  );
}
