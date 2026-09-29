import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Card } from "../components/FormFields";
import { money, number } from "../format";

const TABS = [
  { id: "abandoned", label: "Abandoned cart" },
  { id: "upsell", label: "Upsell" },
  { id: "cross_sell", label: "Cross-sell" },
  { id: "recovery", label: "Recovery" },
];

function Pairs({ title, description, pairs, linked, empty }) {
  return (
    <div className="ciwp-grid ciwp-gap-5">
      <Card title={title} description={description}>
        {!pairs?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">{empty}</p>}
        {pairs?.length > 0 && (
          <table>
            <thead>
              <tr>
                <th>From</th>
                <th>Suggest</th>
                <th>Orders together</th>
                <th>Price gap</th>
              </tr>
            </thead>
            <tbody>
              {pairs.map((row) => (
                <tr key={`${row.product}-${row.related}`}>
                  <td>{row.product}</td>
                  <td>{row.related}</td>
                  <td>{number(row.orders)}</td>
                  <td>{money(row.lift)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      <Card title="Linked in WooCommerce" description="Products that already have this relationship saved on the product.">
        {!linked?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">None of the catalog products have these links yet.</p>}
        {linked?.length > 0 && (
          <ul className="ciwp-m-0 ciwp-grid ciwp-list-none ciwp-gap-2 ciwp-p-0 ciwp-text-sm">
            {linked.map((row) => (
              <li key={row.product}>
                <strong>{row.product}</strong>
                <span className="ciwp-text-slate-500"> → {row.related.join(", ")}</span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}

export default function Sales() {
  const [tab, setTab] = useState("abandoned");
  const [data, setData] = useState(null);
  const [toast, setToast] = useState(null);

  const load = useCallback(async () => {
    try {
      setData(await api.getSales());
    } catch (err) {
      setToast({ message: err.message, type: "error" });
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const abandoned = data?.abandoned;

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="ciwp-flex ciwp-flex-wrap ciwp-items-end ciwp-justify-between ciwp-gap-3">
        <div>
          <h2 className="ciwp-m-0 ciwp-text-xl ciwp-font-bold ciwp-text-slate-900">Sales & conversion</h2>
          <p className="ciwp-m-0 ciwp-mt-1 ciwp-text-sm ciwp-text-slate-500">Carts left behind, product pairs, and customers ready for a win-back.</p>
        </div>
        <div className="ciwp-flex ciwp-flex-wrap ciwp-gap-1 ciwp-rounded-xl ciwp-bg-slate-100 ciwp-p-1">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`ciwp-rounded-lg ciwp-border-0 ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ${
                tab === item.id ? "ciwp-bg-white ciwp-text-brand-700 ciwp-shadow-sm" : "ciwp-bg-transparent ciwp-text-slate-600"
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {tab === "abandoned" && (
        <Card title="Abandoned carts" description={`${number(abandoned?.count)} sessions in the last 30 days added to cart or reached checkout without paying. Listed value ${money(abandoned?.value)}.`}>
          {!abandoned?.items?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">No abandoned carts in the last 30 days.</p>}
          {abandoned?.items?.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Stage</th>
                  <th>Last product</th>
                  <th>Value</th>
                  <th>Last seen</th>
                </tr>
              </thead>
              <tbody>
                {abandoned.items.map((row) => (
                  <tr key={row.session_id}>
                    <td>
                      <strong>{row.customer}</strong>
                      {row.email && <div className="ciwp-text-xs ciwp-text-slate-500">{row.email}</div>}
                    </td>
                    <td className="ciwp-capitalize">{row.stage}</td>
                    <td>{row.product}</td>
                    <td>{money(row.value)}</td>
                    <td>{row.last_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      )}

      {tab === "upsell" && (
        <Pairs
          title="Upsell"
          description="Pairs where the suggested product costs more. Offer it on the product and cart pages."
          pairs={data?.upsell?.pairs}
          linked={data?.upsell?.linked}
          empty="Upsell pairs appear after customers buy products at different prices in the same order."
        />
      )}

      {tab === "cross_sell" && (
        <Pairs
          title="Cross-sell"
          description="Products bought together at a similar price."
          pairs={data?.cross_sell?.pairs}
          linked={data?.cross_sell?.linked}
          empty="Cross-sell pairs appear after customers buy more than one product in an order."
        />
      )}

      {tab === "recovery" && (
        <Card title="Recovery" description={`${number(data?.recovery?.count)} customers have not ordered in 60 days or more.`}>
          {!data?.recovery?.items?.length && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">No quiet customers yet.</p>}
          {data?.recovery?.items?.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Orders</th>
                  <th>Revenue</th>
                  <th>Days quiet</th>
                  <th>Next step</th>
                </tr>
              </thead>
              <tbody>
                {data.recovery.items.map((row) => (
                  <tr key={row.customer_id}>
                    <td>
                      <strong>{row.name}</strong>
                      <div className="ciwp-text-xs ciwp-text-slate-500">{row.email}</div>
                    </td>
                    <td>{number(row.orders)}</td>
                    <td>{money(row.revenue)}</td>
                    <td>{number(row.days_since)}</td>
                    <td>{row.action}</td>
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
