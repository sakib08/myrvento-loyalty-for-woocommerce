import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, inputClass } from "../components/FormFields";

export default function Customers() {
  const [data, setData] = useState({ items: [], total: 0 });
  const [search, setSearch] = useState("");
  const [selected, setSelected] = useState(null);
  const [adjust, setAdjust] = useState({ amount: 0, description: "" });
  const [toast, setToast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async (term = "") => {
    try {
      const result = await api.getCustomers({ search: term, per_page: 30 });
      setData(result);
    } catch (err) {
      showToast(err.message, "error");
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function openCustomer(id) {
    try {
      setSelected(await api.getCustomer(id));
      setAdjust({ amount: 0, description: "" });
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  async function submitAdjust() {
    if (!selected || !adjust.amount) return;
    try {
      await api.adjustPoints({
        customer_id: selected.id,
        amount: Number(adjust.amount),
        description: adjust.description,
      });
      await openCustomer(selected.id);
      await load(search);
      showToast("Balance updated.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="gp-grid gp-gap-5 lg:gp-grid-cols-[1.2fr_1fr]">
      <AdminToast message={toast?.message} type={toast?.type} />
      <Card title="Customer points" description="Balances and VIP tiers from the shared ledger.">
        <Field label="Search">
          <input
            className={inputClass}
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              load(e.target.value);
            }}
            placeholder="Name or email"
          />
        </Field>
        <table className="gp-mt-4">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Available</th>
              <th>Lifetime</th>
              <th>Tier</th>
            </tr>
          </thead>
          <tbody>
            {data.items.map((row) => (
              <tr key={row.id} className="gp-cursor-pointer hover:gp-bg-slate-50" onClick={() => openCustomer(row.id)}>
                <td>
                  <strong>{row.name}</strong>
                  <div className="gp-text-xs gp-text-slate-500">{row.email}</div>
                </td>
                <td>{row.available}</td>
                <td>{row.lifetime_earned}</td>
                <td>
                  <span style={{ color: row.tier_color }}>{row.tier_name || "—"}</span>
                </td>
              </tr>
            ))}
            {data.items.length === 0 && (
              <tr><td colSpan="4" className="gp-text-slate-500">No customers with points yet.</td></tr>
            )}
          </tbody>
        </table>
      </Card>

      <Card title={selected ? selected.name : "Customer detail"} description={selected ? selected.email : "Select a customer to view history and adjust points."}>
        {!selected && <p className="gp-m-0 gp-text-sm gp-text-slate-500">Nothing selected.</p>}
        {selected && (
          <div className="gp-grid gp-gap-4">
            <div className="gp-grid gp-grid-cols-3 gp-gap-3">
              <div className="gp-rounded-xl gp-bg-brand-50 gp-p-3">
                <div className="gp-text-xs gp-text-slate-500">Available</div>
                <div className="gp-text-xl gp-font-bold">{selected.available}</div>
              </div>
              <div className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                <div className="gp-text-xs gp-text-slate-500">Lifetime</div>
                <div className="gp-text-xl gp-font-bold">{selected.lifetime_earned}</div>
              </div>
              <div className="gp-rounded-xl gp-bg-slate-50 gp-p-3">
                <div className="gp-text-xs gp-text-slate-500">Tier</div>
                <div className="gp-text-xl gp-font-bold" style={{ color: selected.tier?.color }}>{selected.tier?.name || "—"}</div>
              </div>
            </div>

            <div className="gp-grid gp-gap-2 md:gp-grid-cols-[1fr_2fr_auto] md:gp-items-end">
              <Field label="Adjust (+/−)">
                <input className={inputClass} type="number" value={adjust.amount} onChange={(e) => setAdjust({ ...adjust, amount: e.target.value })} />
              </Field>
              <Field label="Note">
                <input className={inputClass} value={adjust.description} onChange={(e) => setAdjust({ ...adjust, description: e.target.value })} />
              </Field>
              <Button onClick={submitAdjust}>Apply</Button>
            </div>

            <div>
              <h3 className="gp-mb-2 gp-mt-0 gp-text-sm gp-font-bold">Transaction history</h3>
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {(selected.history || []).map((row) => (
                    <tr key={row.id}>
                      <td>{row.created_at}</td>
                      <td>{row.description || row.source}</td>
                      <td className={Number(row.amount) < 0 ? "gp-text-red-600" : "gp-text-brand-700"}>
                        {Number(row.amount) > 0 ? `+${row.amount}` : row.amount}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
