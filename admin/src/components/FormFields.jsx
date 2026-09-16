export function Switch({ checked, onChange, className = "", ...props }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={`gp-switch gp-relative gp-h-6 gp-w-11 gp-shrink-0 gp-rounded-full gp-border-0 gp-p-0 gp-transition ${
        checked ? "gp-bg-brand-600" : "gp-bg-slate-300"
      } ${className}`.trim()}
      {...props}
    >
      <span
        className={`gp-pointer-events-none gp-absolute gp-top-0.5 gp-h-5 gp-w-5 gp-rounded-full gp-bg-white gp-shadow gp-transition ${
          checked ? "gp-left-5" : "gp-left-0.5"
        }`}
      />
    </button>
  );
}

export function Toggle({ label, description, checked, onChange }) {
  return (
    <label className="gp-flex gp-cursor-pointer gp-items-center gp-justify-between gp-gap-4 gp-rounded-xl gp-border gp-border-slate-100 gp-bg-slate-50 gp-p-4">
      <div className="gp-min-w-0 gp-flex-1">
        <span className="gp-block gp-text-sm gp-font-semibold gp-text-slate-800">{label}</span>
        {description && (
          <span className="gp-mt-0.5 gp-block gp-text-xs gp-text-slate-500">{description}</span>
        )}
      </div>
      <Switch checked={checked} onChange={onChange} />
    </label>
  );
}

export function Field({ label, description, children }) {
  return (
    <label className="gp-block">
      <span className="gp-mb-1 gp-block gp-text-sm gp-font-semibold gp-text-slate-800">{label}</span>
      {description && (
        <span className="gp-mb-2 gp-block gp-text-xs gp-text-slate-500">{description}</span>
      )}
      {children}
    </label>
  );
}

export const inputClass =
  "gp-w-full gp-rounded-lg gp-border gp-border-slate-200 gp-px-3 gp-py-2 gp-text-sm focus:gp-border-brand-400 focus:gp-outline-none focus:gp-ring-2 focus:gp-ring-brand-100";

export function Card({ title, description, actions, children }) {
  return (
    <section className="gp-rounded-2xl gp-border gp-border-slate-200 gp-bg-white gp-p-5 gp-shadow-sm">
      {(title || actions) && (
        <div className="gp-mb-4 gp-flex gp-flex-wrap gp-items-start gp-justify-between gp-gap-3">
          <div>
            {title && <h2 className="gp-m-0 gp-text-base gp-font-bold gp-text-slate-900">{title}</h2>}
            {description && <p className="gp-m-0 gp-mt-1 gp-text-sm gp-text-slate-500">{description}</p>}
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
      ? "gp-bg-red-600 gp-text-white hover:gp-bg-red-700"
      : variant === "secondary"
        ? "gp-bg-slate-100 gp-text-slate-800 hover:gp-bg-slate-200"
        : "gp-bg-brand-600 gp-text-white hover:gp-bg-brand-700";

  return (
    <button
      type="button"
      className={`gp-rounded-lg gp-border-0 gp-px-3 gp-py-2 gp-text-sm gp-font-semibold gp-transition ${styles} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
