import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

const TYPES = [
  { value: "coupon_percent", label: "Percentage discount" },
  { value: "coupon_fixed", label: "Fixed discount" },
  { value: "free_shipping", label: "Free shipping" },
  { value: "free_product", label: "Free product" },
  { value: "exclusive_product", label: "Exclusive product" },
  { value: "early_access", label: "Early product access" },
  { value: "special_pricing", label: "Special pricing" },
  { value: "vip_offer", label: "VIP-only offer" },
];

const emptyReward = {
  name: "",
  type: "coupon_percent",
  points_cost: 100,
  enabled: true,
  tier_id: "",
  stock: "",
  config: { amount: 10, product_id: "" },
};

export default function Rewards() {
  const [rewards, setRewards] = useState([]);
  const [tiers, setTiers] = useState([]);
  const [redemptions, setRedemptions] = useState({ items: [] });
  const [draft, setDraft] = useState(emptyReward);
  const [toast, setToast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    try {
      const [rewardRows, tierRows, redemptionRows] = await Promise.all([
        api.getRewards(),
        api.getTiers(),
        api.getRedemptions({ per_page: 20 }),
      ]);
      setRewards(rewardRows);
      setTiers(tierRows);
      setRedemptions(redemptionRows);
    } catch (err) {
      showToast(err.message, "error");
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function save(reward) {
    try {
      const payload = {
        ...reward,
        tier_id: reward.tier_id || null,
        stock: reward.stock === "" ? null : reward.stock,
      };
      if (reward.id) {
        await api.updateReward(reward.id, payload);
      } else {
        await api.createReward(payload);
        setDraft(emptyReward);
      }
      await load();
      showToast("Reward saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="gp-ppros-grid gp-ppros-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <Card title="Reward catalog" description="Redemption debits the ledger and issues a WooCommerce coupon when applicable.">
        <table>
          <thead>
            <tr>
              <th>Reward</th>
              <th>Type</th>
              <th>Cost</th>
              <th>Enabled</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {rewards.map((reward) => (
              <tr key={reward.id}>
                <td>
                  <input className={inputClass} value={reward.name} onChange={(e) => setRewards((c) => c.map((r) => r.id === reward.id ? { ...r, name: e.target.value } : r))} />
                </td>
                <td>
                  <select className={inputClass} value={reward.type} onChange={(e) => setRewards((c) => c.map((r) => r.id === reward.id ? { ...r, type: e.target.value } : r))}>
                    {TYPES.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                  </select>
                </td>
                <td>
                  <input className={inputClass} type="number" value={reward.points_cost} onChange={(e) => setRewards((c) => c.map((r) => r.id === reward.id ? { ...r, points_cost: Number(e.target.value) } : r))} />
                </td>
                <td>
                  <Toggle label="" checked={reward.enabled} onChange={(enabled) => save({ ...reward, enabled })} />
                </td>
                <td className="gp-ppros-flex gp-ppros-gap-2">
                  <Button onClick={() => save(reward)}>Save</Button>
                  <Button variant="danger" onClick={async () => { await api.deleteReward(reward.id); load(); }}>Delete</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Card title="Add reward">
        <div className="gp-ppros-grid gp-ppros-gap-3 md:gp-ppros-grid-cols-4 md:gp-ppros-items-end">
          <Field label="Name">
            <input className={inputClass} value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
          </Field>
          <Field label="Type">
            <select className={inputClass} value={draft.type} onChange={(e) => setDraft({ ...draft, type: e.target.value })}>
              {TYPES.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
            </select>
          </Field>
          <Field label="Points cost">
            <input className={inputClass} type="number" value={draft.points_cost} onChange={(e) => setDraft({ ...draft, points_cost: Number(e.target.value) })} />
          </Field>
          <Field label="Min VIP tier">
            <select className={inputClass} value={draft.tier_id} onChange={(e) => setDraft({ ...draft, tier_id: e.target.value })}>
              <option value="">Any</option>
              {tiers.map((tier) => <option key={tier.id} value={tier.id}>{tier.name}</option>)}
            </select>
          </Field>
          <Field label="Coupon amount / %">
            <input className={inputClass} type="number" value={draft.config.amount || ""} onChange={(e) => setDraft({ ...draft, config: { ...draft.config, amount: Number(e.target.value) } })} />
          </Field>
          <Field label="Product ID (free / exclusive)">
            <input className={inputClass} value={draft.config.product_id || ""} onChange={(e) => setDraft({ ...draft, config: { ...draft.config, product_id: e.target.value } })} />
          </Field>
          <Field label="Stock (optional)">
            <input className={inputClass} value={draft.stock} onChange={(e) => setDraft({ ...draft, stock: e.target.value })} />
          </Field>
          <Button onClick={() => save(draft)}>Create reward</Button>
        </div>
      </Card>

      <Card title="Redemption history">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Customer</th>
              <th>Reward</th>
              <th>Coupon</th>
              <th>Points</th>
            </tr>
          </thead>
          <tbody>
            {(redemptions.items || []).map((row) => (
              <tr key={row.id}>
                <td>{row.created_at}</td>
                <td>{row.customer}<div className="gp-ppros-text-xs gp-ppros-text-slate-500">{row.email}</div></td>
                <td>{row.reward}</td>
                <td><code>{row.coupon_code}</code></td>
                <td>{row.points_spent}</td>
              </tr>
            ))}
            {(redemptions.items || []).length === 0 && (
              <tr><td colSpan="5" className="gp-ppros-text-slate-500">No redemptions yet.</td></tr>
            )}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
