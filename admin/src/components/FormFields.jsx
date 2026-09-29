export function Switch({ checked, onChange, className = "", ...props }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={`ciwp-switch ciwp-relative ciwp-h-6 ciwp-w-11 ciwp-shrink-0 ciwp-rounded-full ciwp-border-0 ciwp-p-0 ciwp-transition ${
        checked ? "ciwp-bg-brand-600" : "ciwp-bg-slate-300"
      } ${className}`.trim()}
      {...props}
    >
      <span
        className={`ciwp-pointer-events-none ciwp-absolute ciwp-top-0.5 ciwp-h-5 ciwp-w-5 ciwp-rounded-full ciwp-bg-white ciwp-shadow ciwp-transition ${
          checked ? "ciwp-left-5" : "ciwp-left-0.5"
        }`}
      />
    </button>
  );
}

export function Toggle({ label, description, checked, onChange }) {
  return (
    <label className="ciwp-flex ciwp-cursor-pointer ciwp-items-center ciwp-justify-between ciwp-gap-4 ciwp-rounded-xl ciwp-border ciwp-border-slate-100 ciwp-bg-slate-50 ciwp-p-4">
      <div className="ciwp-min-w-0 ciwp-flex-1">
        <span className="ciwp-block ciwp-text-sm ciwp-font-semibold ciwp-text-slate-800">{label}</span>
        {description && (
          <span className="ciwp-mt-0.5 ciwp-block ciwp-text-xs ciwp-text-slate-500">{description}</span>
        )}
      </div>
      <Switch checked={checked} onChange={onChange} />
    </label>
  );
}

export function Field({ label, description, children }) {
  return (
    <label className="ciwp-block">
      <span className="ciwp-mb-1 ciwp-block ciwp-text-sm ciwp-font-semibold ciwp-text-slate-800">{label}</span>
      {description && (
        <span className="ciwp-mb-2 ciwp-block ciwp-text-xs ciwp-text-slate-500">{description}</span>
      )}
      {children}
    </label>
  );
}

export const inputClass =
  "ciwp-w-full ciwp-rounded-lg ciwp-border ciwp-border-slate-200 ciwp-px-3 ciwp-py-2 ciwp-text-sm focus:ciwp-border-brand-400 focus:ciwp-outline-none focus:ciwp-ring-2 focus:ciwp-ring-brand-100";

export function Card({ title, description, actions, children }) {
  return (
    <section className="ciwp-rounded-2xl ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-p-5 ciwp-shadow-sm">
      {(title || actions) && (
        <div className="ciwp-mb-4 ciwp-flex ciwp-flex-wrap ciwp-items-start ciwp-justify-between ciwp-gap-3">
          <div>
            {title && <h2 className="ciwp-m-0 ciwp-text-base ciwp-font-bold ciwp-text-slate-900">{title}</h2>}
            {description && <p className="ciwp-m-0 ciwp-mt-1 ciwp-text-sm ciwp-text-slate-500">{description}</p>}
          </div>
          {actions}
        </div>
      )}
      {children}
    </section>
  );
}

export function Button({ children, variant = "primary", className = "", ...props }) {
  const styles =
    variant === "danger"
      ? "ciwp-bg-red-600 ciwp-text-white hover:ciwp-bg-red-700"
      : variant === "secondary"
        ? "ciwp-bg-slate-100 ciwp-text-slate-800 hover:ciwp-bg-slate-200"
        : "ciwp-bg-brand-600 ciwp-text-white hover:ciwp-bg-brand-700";

  return (
    <button
      type="button"
      className={`ciwp-rounded-lg ciwp-border-0 ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ciwp-transition ${styles} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
