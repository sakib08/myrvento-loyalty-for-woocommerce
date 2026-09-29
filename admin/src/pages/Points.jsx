import { useCallback, useEffect, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

const GLOBAL_SOURCES = [
  { source: "purchase", hint: "Rate is points per 1 unit of order subtotal." },
  { source: "review", hint: "Awarded when a product review is approved." },
  { source: "signup", hint: "Welcome points for new customers." },
  { source: "first_purchase", hint: "One-time bonus on the first qualifying order." },
  { source: "birthday", hint: "Awarded on the customer birthday (mm-dd)." },
  { source: "social", hint: "Awarded when a customer shares their referral link." },
];

export default function Points() {
  const [rules, setRules] = useState([]);
  const [settings, setSettings] = useState(null);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState(null);
  const [productSearch, setProductSearch] = useState("");
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [newObject, setNewObject] = useState({
    name: "",
    source: "product",
    object_type: "product",
    object_id: "",
    points: 10,
    rate: 0,
    enabled: true,
  });

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    try {
      const [ruleRows, settingRows] = await Promise.all([api.getRules(), api.getSettings()]);
      setRules(ruleRows);
      setSettings(settingRows);
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function saveRule(rule) {
    try {
      const saved = rule.id
        ? await api.updateRule(rule.id, rule)
        : await api.createRule(rule);
      setRules((current) => {
        const exists = current.some((row) => row.id === saved.id);
        return exists ? current.map((row) => (row.id === saved.id ? saved : row)) : [...current, saved];
      });
      showToast(adminConfig.i18n.saved || "Saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  async function saveExpiration(days) {
    const next = { ...settings, expiration_days: Number(days) };
    setSettings(next);
    try {
      await api.saveSettings(next);
      showToast(adminConfig.i18n.saved || "Saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  async function searchCatalog(term) {
    setProductSearch(term);
    if (term.length < 2) {
      setProducts([]);
      setCategories([]);
      return;
    }
    const [foundProducts, foundCategories] = await Promise.all([
      api.searchProducts(term),
      api.searchCategories(term),
    ]);
    setProducts(foundProducts);
    setCategories(foundCategories);
  }

  async function addObjectRule() {
    if (!newObject.object_id || !newObject.name) {
      showToast("Name and product/category are required.", "error");
      return;
    }
    await saveRule({
      ...newObject,
      object_id: Number(newObject.object_id),
      source: newObject.object_type,
    });
    setNewObject({
      name: "",
      source: "product",
      object_type: "product",
      object_id: "",
      points: 10,
      rate: 0,
      enabled: true,
    });
  }

  if (loading) {
    return <p className="ciwp-text-slate-500">Loading points rules…</p>;
  }

  const globals = GLOBAL_SOURCES.map((meta) => ({
    ...meta,
    rule: rules.find((row) => row.source === meta.source && !row.object_id),
  }));
  const objectRules = rules.filter((row) => row.object_id);
  const campaigns = rules.filter((row) => row.source === "campaign");

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card title="Earn rules" description="Every source writes to the same points ledger — including referrals.">
        <div className="ciwp-grid ciwp-gap-4">
          {globals.map(({ source, hint, rule }) => (
            <div key={source} className="ciwp-grid ciwp-gap-3 ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-p-4 md:ciwp-grid-cols-4 md:ciwp-items-end">
              <Toggle
                label={rule?.name || source.replace("_", " ")}
                description={hint}
                checked={Boolean(rule?.enabled)}
                onChange={(enabled) => saveRule({ ...(rule || { name: source, source, points: 0, rate: 0 }), enabled })}
              />
              <Field label="Fixed points">
                <input
                  className={inputClass}
                  type="number"
                  value={rule?.points ?? 0}
                  onBlur={(e) => saveRule({ ...(rule || { name: source, source }), points: Number(e.target.value) })}
                  onChange={(e) =>
                    setRules((current) =>
                      current.map((row) => (row.id === rule?.id ? { ...row, points: Number(e.target.value) } : row))
                    )
                  }
                />
              </Field>
              <Field label={source === "purchase" ? "Points per currency unit" : "Rate"}>
                <input
                  className={inputClass}
                  type="number"
                  step="0.01"
                  value={rule?.rate ?? 0}
                  onBlur={(e) => saveRule({ ...(rule || { name: source, source }), rate: Number(e.target.value) })}
                  onChange={(e) =>
                    setRules((current) =>
                      current.map((row) => (row.id === rule?.id ? { ...row, rate: Number(e.target.value) } : row))
                    )
                  }
                />
              </Field>
            </div>
          ))}
        </div>
      </Card>

      <Card title="Points expiration" description="0 means points never expire. FIFO consumes the oldest lots first.">
        <Field label="Expire unused points after (days)">
          <input
            className={`${inputClass} ciwp-max-w-xs`}
            type="number"
            min="0"
            value={settings?.expiration_days ?? 0}
            onChange={(e) => setSettings({ ...settings, expiration_days: Number(e.target.value) })}
            onBlur={(e) => saveExpiration(e.target.value)}
          />
        </Field>
      </Card>

      <Card title="Product & category points" description="Bonus points stacked on top of the global purchase rate.">
        <div className="ciwp-mb-4 ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-5 md:ciwp-items-end">
          <Field label="Name">
            <input className={inputClass} value={newObject.name} onChange={(e) => setNewObject({ ...newObject, name: e.target.value })} />
          </Field>
          <Field label="Type">
            <select
              className={inputClass}
              value={newObject.object_type}
              onChange={(e) => setNewObject({ ...newObject, object_type: e.target.value, object_id: "" })}
            >
              <option value="product">Product</option>
              <option value="category">Category</option>
            </select>
          </Field>
          <Field label="Search">
            <input className={inputClass} value={productSearch} onChange={(e) => searchCatalog(e.target.value)} placeholder="Type to search" />
            {(products.length > 0 || categories.length > 0) && (
              <div className="ciwp-mt-1 ciwp-max-h-40 ciwp-overflow-auto ciwp-rounded-lg ciwp-border ciwp-border-slate-200 ciwp-bg-white">
                {(newObject.object_type === "product" ? products : categories).map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    className="ciwp-block ciwp-w-full ciwp-border-0 ciwp-bg-transparent ciwp-px-3 ciwp-py-2 ciwp-text-left ciwp-text-sm hover:ciwp-bg-slate-50"
                    onClick={() => {
                      setNewObject({ ...newObject, object_id: item.id, name: newObject.name || `${item.name} bonus` });
                      setProductSearch(item.name);
                      setProducts([]);
                      setCategories([]);
                    }}
                  >
                    {item.name}
                  </button>
                ))}
              </div>
            )}
          </Field>
          <Field label="Points">
            <input className={inputClass} type="number" value={newObject.points} onChange={(e) => setNewObject({ ...newObject, points: Number(e.target.value) })} />
          </Field>
          <Button onClick={addObjectRule}>Add rule</Button>
        </div>

        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Target</th>
              <th>Points</th>
              <th>Rate</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {objectRules.map((rule) => (
              <tr key={rule.id}>
                <td>{rule.name}</td>
                <td>{rule.object_label || `${rule.object_type} #${rule.object_id}`}</td>
                <td>{rule.points}</td>
                <td>{rule.rate}</td>
                <td>
                  <Button variant="danger" onClick={async () => {
                    await api.deleteRule(rule.id);
                    setRules((current) => current.filter((row) => row.id !== rule.id));
                  }}>Delete</Button>
                </td>
              </tr>
            ))}
            {objectRules.length === 0 && (
              <tr><td colSpan="5" className="ciwp-text-slate-500">No product or category bonuses yet.</td></tr>
            )}
          </tbody>
        </table>
      </Card>

      <Card
        title="Campaign bonus points"
        description="Extra points on qualifying orders while the campaign window is open."
        actions={
          <Button onClick={() => saveRule({
            name: "Limited-time bonus",
            source: "campaign",
            enabled: true,
            points: 50,
            rate: 0,
            config: {},
          })}>Add campaign bonus</Button>
        }
      >
        <div className="ciwp-grid ciwp-gap-3">
          {campaigns.map((rule) => (
            <div key={rule.id} className="ciwp-grid ciwp-gap-3 ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-p-4 md:ciwp-grid-cols-5 md:ciwp-items-end">
              <Field label="Name">
                <input className={inputClass} value={rule.name} onChange={(e) => setRules((c) => c.map((r) => r.id === rule.id ? { ...r, name: e.target.value } : r))} onBlur={() => saveRule(rule)} />
              </Field>
              <Field label="Points">
                <input className={inputClass} type="number" value={rule.points} onChange={(e) => setRules((c) => c.map((r) => r.id === rule.id ? { ...r, points: Number(e.target.value) } : r))} onBlur={() => saveRule(rule)} />
              </Field>
              <Field label="Starts">
                <input className={inputClass} type="datetime-local" value={rule.config?.starts_at || ""} onChange={(e) => {
                  const next = { ...rule, config: { ...rule.config, starts_at: e.target.value } };
                  setRules((c) => c.map((r) => r.id === rule.id ? next : r));
                }} onBlur={() => saveRule(rule)} />
              </Field>
              <Field label="Ends">
                <input className={inputClass} type="datetime-local" value={rule.config?.ends_at || ""} onChange={(e) => {
                  const next = { ...rule, config: { ...rule.config, ends_at: e.target.value } };
                  setRules((c) => c.map((r) => r.id === rule.id ? next : r));
                }} onBlur={() => saveRule(rule)} />
              </Field>
              <Button variant="danger" onClick={async () => {
                await api.deleteRule(rule.id);
                setRules((current) => current.filter((row) => row.id !== rule.id));
              }}>Delete</Button>
            </div>
          ))}
          {campaigns.length === 0 && <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">No campaign bonuses.</p>}
        </div>
      </Card>
    </div>
  );
}
