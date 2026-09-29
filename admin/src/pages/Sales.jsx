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
    <div className="myrvento-grid myrvento-gap-5">
      <Card title={title} description={description}>
        {!pairs?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">{empty}</p>}
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
        {!linked?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">None of the catalog products have these links yet.</p>}
        {linked?.length > 0 && (
          <ul className="myrvento-m-0 myrvento-grid myrvento-list-none myrvento-gap-2 myrvento-p-0 myrvento-text-sm">
            {linked.map((row) => (
              <li key={row.product}>
                <strong>{row.product}</strong>
                <span className="myrvento-text-slate-500"> → {row.related.join(", ")}</span>
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
    <div className="myrvento-grid myrvento-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <div className="myrvento-flex myrvento-flex-wrap myrvento-items-end myrvento-justify-between myrvento-gap-3">
        <div>
          <h2 className="myrvento-m-0 myrvento-text-xl myrvento-font-bold myrvento-text-slate-900">Sales & conversion</h2>
          <p className="myrvento-m-0 myrvento-mt-1 myrvento-text-sm myrvento-text-slate-500">Carts left behind, product pairs, and customers ready for a win-back.</p>
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

      {tab === "abandoned" && (
        <Card title="Abandoned carts" description={`${number(abandoned?.count)} sessions in the last 30 days added to cart or reached checkout without paying. Listed value ${money(abandoned?.value)}.`}>
          {!abandoned?.items?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No abandoned carts in the last 30 days.</p>}
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
                      {row.email && <div className="myrvento-text-xs myrvento-text-slate-500">{row.email}</div>}
                    </td>
                    <td className="myrvento-capitalize">{row.stage}</td>
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
          {!data?.recovery?.items?.length && <p className="myrvento-m-0 myrvento-text-sm myrvento-text-slate-500">No quiet customers yet.</p>}
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
                      <div className="myrvento-text-xs myrvento-text-slate-500">{row.email}</div>
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
