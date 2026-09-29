import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

const emptyCampaign = {
  name: "Refer a friend",
  enabled: true,
  first_order_points: 200,
  referee_signup_points: 50,
  recurring_points: 50,
  cookie_days: 30,
  landing_page: "",
};

export default function Referrals() {
  const [campaigns, setCampaigns] = useState([]);
  const [referrals, setReferrals] = useState({ items: [] });
  const [clicks, setClicks] = useState({ items: [], total: 0 });
  const [draft, setDraft] = useState(emptyCampaign);
  const [toast, setToast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    try {
      const [campaignRows, referralRows, clickRows] = await Promise.all([
        api.getCampaigns(),
        api.getReferrals({ per_page: 30 }),
        api.getClicks(),
      ]);
      setCampaigns(campaignRows);
      setReferrals(referralRows);
      setClicks(clickRows);
    } catch (err) {
      showToast(err.message, "error");
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function save(campaign) {
    try {
      if (campaign.id) await api.updateCampaign(campaign.id, campaign);
      else {
        await api.createCampaign(campaign);
        setDraft(emptyCampaign);
      }
      await load();
      showToast("Campaign saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card title="Referral campaigns" description="Referral bonuses credit the loyalty ledger. There is no separate referral wallet.">
        {campaigns.map((campaign) => (
          <div key={campaign.id} className="ciwp-mb-4 ciwp-grid ciwp-gap-3 ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-p-4 md:ciwp-grid-cols-4">
            <Field label="Name"><input className={inputClass} value={campaign.name} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, name: e.target.value } : row))} /></Field>
            <Field label="First-order points"><input className={inputClass} type="number" value={campaign.first_order_points} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, first_order_points: Number(e.target.value) } : row))} /></Field>
            <Field label="Referee signup points"><input className={inputClass} type="number" value={campaign.referee_signup_points} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, referee_signup_points: Number(e.target.value) } : row))} /></Field>
            <Field label="Recurring points"><input className={inputClass} type="number" value={campaign.recurring_points} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, recurring_points: Number(e.target.value) } : row))} /></Field>
            <Field label="Cookie days"><input className={inputClass} type="number" value={campaign.cookie_days} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, cookie_days: Number(e.target.value) } : row))} /></Field>
            <Field label="Landing page slug"><input className={inputClass} value={campaign.landing_page || ""} onChange={(e) => setCampaigns((c) => c.map((row) => row.id === campaign.id ? { ...row, landing_page: e.target.value } : row))} /></Field>
            <Toggle label="Enabled" checked={campaign.enabled} onChange={(enabled) => save({ ...campaign, enabled })} />
            <div className="ciwp-flex ciwp-items-end ciwp-gap-2">
              <Button onClick={() => save(campaign)}>Save</Button>
              <Button variant="danger" onClick={async () => { await api.deleteCampaign(campaign.id); load(); }}>Delete</Button>
            </div>
          </div>
        ))}

        <h3 className="ciwp-mb-2 ciwp-text-sm ciwp-font-bold">New campaign</h3>
        <div className="ciwp-grid ciwp-gap-3 md:ciwp-grid-cols-4 md:ciwp-items-end">
          <Field label="Name"><input className={inputClass} value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} /></Field>
          <Field label="First-order points"><input className={inputClass} type="number" value={draft.first_order_points} onChange={(e) => setDraft({ ...draft, first_order_points: Number(e.target.value) })} /></Field>
          <Field label="Recurring points"><input className={inputClass} type="number" value={draft.recurring_points} onChange={(e) => setDraft({ ...draft, recurring_points: Number(e.target.value) })} /></Field>
          <Button onClick={() => save(draft)}>Create campaign</Button>
        </div>
      </Card>

      <Card title="Attribution" description={`${clicks.total || 0} tracked clicks`}>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Referrer</th>
              <th>Referee</th>
              <th>Code</th>
              <th>Status</th>
              <th>Order</th>
            </tr>
          </thead>
          <tbody>
            {(referrals.items || []).map((row) => (
              <tr key={row.id}>
                <td>{row.created_at}</td>
                <td>{row.referrer}<div className="ciwp-text-xs ciwp-text-slate-500">{row.referrer_email}</div></td>
                <td>{row.referee || "—"}</td>
                <td><code>{row.code}</code></td>
                <td>{row.status}</td>
                <td>{row.attributed_order_id ? `#${row.attributed_order_id}` : "—"}</td>
              </tr>
            ))}
            {(referrals.items || []).length === 0 && (
              <tr><td colSpan="6" className="ciwp-text-slate-500">No referrals yet.</td></tr>
            )}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
