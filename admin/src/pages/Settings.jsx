import { useCallback, useEffect, useState } from "react";
import { api, adminConfig } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

export default function Settings() {
  const [settings, setSettings] = useState(null);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  useEffect(() => {
    api.getSettings()
      .then(setSettings)
      .catch((err) => showToast(err.message, "error"));
  }, [showToast]);

  async function save() {
    setSaving(true);
    try {
      const saved = await api.saveSettings(settings);
      setSettings(saved);
      showToast(adminConfig.i18n.saved || "Saved.");
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      setSaving(false);
    }
  }

  if (!settings) {
    return <p className="ciwp-text-slate-500">Loading settings…</p>;
  }

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />
      <Card title="Loyalty settings" description="These control when points are earned and how they appear on My Account.">
        <div className="ciwp-grid ciwp-gap-4 md:ciwp-grid-cols-2">
          <Field label="Points name">
            <input className={inputClass} value={settings.points_name} onChange={(e) => setSettings({ ...settings, points_name: e.target.value })} />
          </Field>
          <Field label="Earn on order status">
            <select className={inputClass} value={settings.earn_order_status} onChange={(e) => setSettings({ ...settings, earn_order_status: e.target.value })}>
              <option value="completed">Completed</option>
              <option value="processing">Processing</option>
            </select>
          </Field>
          <Field label="My Account — Loyalty label">
            <input className={inputClass} value={settings.myaccount_loyalty_label} onChange={(e) => setSettings({ ...settings, myaccount_loyalty_label: e.target.value })} />
          </Field>
          <Field label="My Account — Referrals label">
            <input className={inputClass} value={settings.myaccount_referrals_label} onChange={(e) => setSettings({ ...settings, myaccount_referrals_label: e.target.value })} />
          </Field>
          <Field label="Referral query parameter">
            <input className={inputClass} value={settings.referral_param} onChange={(e) => setSettings({ ...settings, referral_param: e.target.value })} />
          </Field>
          <Field label="Default cookie days">
            <input className={inputClass} type="number" value={settings.cookie_days} onChange={(e) => setSettings({ ...settings, cookie_days: Number(e.target.value) })} />
          </Field>
          <Field label="VIP downgrade window (days)">
            <input className={inputClass} type="number" value={settings.downgrade_window_days} onChange={(e) => setSettings({ ...settings, downgrade_window_days: Number(e.target.value) })} />
          </Field>
        </div>
        <div className="ciwp-mt-4 ciwp-grid ciwp-gap-3">
          <Toggle
            label="Allow VIP downgrades"
            description="If off, customers keep a higher tier even when they fall below the qualifier."
            checked={Boolean(settings.downgrade_enabled)}
            onChange={(downgrade_enabled) => setSettings({ ...settings, downgrade_enabled })}
          />
          <Toggle
            label="Social sharing points once per customer"
            description="Award social points a single time, regardless of channel."
            checked={Boolean(settings.social_once)}
            onChange={(social_once) => setSettings({ ...settings, social_once })}
          />
        </div>
        <div className="ciwp-mt-5">
          <Button onClick={save} disabled={saving}>{saving ? "Saving…" : "Save settings"}</Button>
        </div>
      </Card>

      <Card title="AI Commerce Brain" description="Local models always run from WooCommerce orders. An OpenAI-compatible key is optional and only rewrites the Brain action cards.">
        <div className="ciwp-grid ciwp-gap-3">
          <Toggle
            label="Run on-store AI models"
            description="Churn, next purchase, pricing, and demand forecasts. Daily cron refreshes the snapshot."
            checked={Boolean(settings.ai_enabled)}
            onChange={(ai_enabled) => setSettings({ ...settings, ai_enabled })}
          />
          <Toggle
            label="LLM narration"
            description="Send compact scores to an OpenAI-compatible chat API. Numbers still come from local models."
            checked={Boolean(settings.ai_llm_enabled)}
            onChange={(ai_llm_enabled) => setSettings({ ...settings, ai_llm_enabled })}
          />
        </div>
        <div className="ciwp-mt-4 ciwp-grid ciwp-gap-4 md:ciwp-grid-cols-2">
          <Field
            label="API key"
            description={settings.ai_api_key_set ? "A key is saved. Leave blank to keep it, or remove it below." : "Optional. Never shown in full after save."}
          >
            <input
              className={inputClass}
              type="password"
              autoComplete="off"
              value={settings.ai_api_key === "********" ? "" : (settings.ai_api_key || "")}
              placeholder={settings.ai_api_key_set ? "********" : ""}
              onChange={(e) => setSettings({ ...settings, ai_api_key: e.target.value, ai_clear_key: false })}
            />
          </Field>
          <Field label="Model">
            <input className={inputClass} value={settings.ai_model || ""} onChange={(e) => setSettings({ ...settings, ai_model: e.target.value })} />
          </Field>
          <Field label="API base URL">
            <input className={inputClass} value={settings.ai_api_base || ""} onChange={(e) => setSettings({ ...settings, ai_api_base: e.target.value })} />
          </Field>
        </div>
        {settings.ai_api_key_set && (
          <div className="ciwp-mt-3">
            <Button variant="secondary" onClick={() => setSettings({ ...settings, ai_clear_key: true, ai_api_key: "" })}>
              Remove API key
            </Button>
          </div>
        )}
        <div className="ciwp-mt-5">
          <Button onClick={save} disabled={saving}>{saving ? "Saving…" : "Save AI settings"}</Button>
        </div>
      </Card>
    </div>
  );
}
