import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

const emptyTier = {
  name: "",
  slug: "",
  color: "#0d9488",
  qualifier_type: "spending",
  qualifier_value: 0,
  sort_order: 10,
  benefits: [],
  is_default: false,
};

export default function Tiers() {
  const [tiers, setTiers] = useState([]);
  const [draft, setDraft] = useState(emptyTier);
  const [toast, setToast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    try {
      setTiers(await api.getTiers());
    } catch (err) {
      showToast(err.message, "error");
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function save(tier) {
    try {
      const payload = {
        ...tier,
        benefits: Array.isArray(tier.benefits) ? tier.benefits : String(tier.benefits || "").split("\n").filter(Boolean),
      };
      if (tier.id) {
        await api.updateTier(tier.id, payload);
      } else {
        await api.createTier(payload);
        setDraft(emptyTier);
      }
      await load();
      showToast("Tier saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <Card title="VIP tiers" description="Upgrades run automatically. Downgrades respect the window in Settings.">
        <div className="ciwp-grid ciwp-gap-4">
          {tiers.map((tier) => (
            <div key={tier.id} className="ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-p-4">
              <div className="ciwp-grid ciwp-grid-cols-1 ciwp-gap-3 md:ciwp-grid-cols-[minmax(0,1.4fr)_8.5rem_minmax(0,1.2fr)_7.5rem_6rem_auto] md:ciwp-items-end">
                <Field label="Name">
                  <input className={inputClass} value={tier.name} onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, name: e.target.value } : t))} />
                </Field>
                <Field label="Color">
                  <input className={`${inputClass} ciwp-color-input`} type="color" value={tier.color} onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, color: e.target.value } : t))} />
                </Field>
                <Field label="Qualifier">
                  <select className={inputClass} value={tier.qualifier_type} onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, qualifier_type: e.target.value } : t))}>
                    <option value="spending">Spending</option>
                    <option value="order_count">Order count</option>
                    <option value="points">Lifetime points</option>
                  </select>
                </Field>
                <Field label="Threshold">
                  <input className={inputClass} type="number" value={tier.qualifier_value} onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, qualifier_value: Number(e.target.value) } : t))} />
                </Field>
                <Field label="Order">
                  <input className={inputClass} type="number" value={tier.sort_order} onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, sort_order: Number(e.target.value) } : t))} />
                </Field>
                <div className="ciwp-flex ciwp-gap-2">
                  <Button onClick={() => save(tier)}>Save</Button>
                  <Button variant="danger" onClick={async () => { await api.deleteTier(tier.id); load(); }}>Delete</Button>
                </div>
              </div>
              <div className="ciwp-mt-4 ciwp-grid ciwp-gap-3">
                <Field label="Benefits (one per line)">
                  <textarea
                    className={inputClass}
                    rows="2"
                    value={(tier.benefits || []).join("\n")}
                    onChange={(e) => setTiers((c) => c.map((t) => t.id === tier.id ? { ...t, benefits: e.target.value.split("\n") } : t))}
                  />
                </Field>
                <Toggle label="Default tier" checked={Boolean(tier.is_default)} onChange={(is_default) => save({ ...tier, is_default })} />
              </div>
            </div>
          ))}
        </div>
      </Card>

      <Card title="Add custom tier">
        <div className="ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-4 md:ciwp-items-end">
          <Field label="Name">
            <input className={inputClass} value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
          </Field>
          <Field label="Qualifier">
            <select className={inputClass} value={draft.qualifier_type} onChange={(e) => setDraft({ ...draft, qualifier_type: e.target.value })}>
              <option value="spending">Spending</option>
              <option value="order_count">Order count</option>
              <option value="points">Lifetime points</option>
            </select>
          </Field>
          <Field label="Threshold">
            <input className={inputClass} type="number" value={draft.qualifier_value} onChange={(e) => setDraft({ ...draft, qualifier_value: Number(e.target.value) })} />
          </Field>
          <Button onClick={() => save(draft)}>Create tier</Button>
        </div>
      </Card>
    </div>
  );
}
