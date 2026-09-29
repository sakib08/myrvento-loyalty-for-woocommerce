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
    <div className="myrvento-grid myrvento-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="myrvento-flex myrvento-flex-wrap myrvento-items-end myrvento-justify-between myrvento-gap-3">
        <div>
          <h2 className="myrvento-m-0 myrvento-text-xl myrvento-font-bold myrvento-text-slate-900">WooCommerce operations</h2>
          <p className="myrvento-m-0 myrvento-mt-1 myrvento-text-sm myrvento-text-slate-500">Orders, subscriptions, coupons, and what customers just did.</p>
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

      {tab === "orders" && (
        <div className="myrvento-grid myrvento-gap-5">
          <div className="myrvento-grid myrvento-gap-3 md:myrvento-grid-cols-4">
            {(data?.orders?.summary || []).map((row) => (
              <div key={row.status} className="myrvento-rounded-xl myrvento-border myrvento-border-slate-100 myrvento-bg-white myrvento-p-4">
                <div className="myrvento-text-xs myrvento-font-semibold myrvento-uppercase myrvento-text-slate-500">{row.label}</div>
                <div className="myrvento-mt-1 myrvento-text-2xl myrvento-font-bold">{number(row.count)}</div>
              </div>
            ))}
          </div>
          <Card title="Latest orders">
            {!data?.orders?.recent?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No orders yet.</p>}
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
            <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No subscriptions yet.</p>
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
          {!data?.coupons?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No coupons yet.</p>}
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
          {!data?.activity?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">Activity appears after visits, carts, and point changes.</p>}
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
