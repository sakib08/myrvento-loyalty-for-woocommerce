export function Switch({ checked, onChange, className = "", ...props }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={`gp-ppros-switch gp-ppros-relative gp-ppros-h-6 gp-ppros-w-11 gp-ppros-shrink-0 gp-ppros-rounded-full gp-ppros-border-0 gp-ppros-p-0 gp-ppros-transition ${
        checked ? "gp-ppros-bg-brand-600" : "gp-ppros-bg-slate-300"
      } ${className}`.trim()}
      {...props}
    >
      <span
        className={`gp-ppros-pointer-events-none gp-ppros-absolute gp-ppros-top-0.5 gp-ppros-h-5 gp-ppros-w-5 gp-ppros-rounded-full gp-ppros-bg-white gp-ppros-shadow gp-ppros-transition ${
          checked ? "gp-ppros-left-5" : "gp-ppros-left-0.5"
        }`}
      />
    </button>
  );
}

export function Toggle({ label, description, checked, onChange }) {
  return (
    <label className="gp-ppros-flex gp-ppros-cursor-pointer gp-ppros-items-center gp-ppros-justify-between gp-ppros-gap-4 gp-ppros-rounded-xl gp-ppros-border gp-ppros-border-slate-100 gp-ppros-bg-slate-50 gp-ppros-p-4">
      <div className="gp-ppros-min-w-0 gp-ppros-flex-1">
        <span className="gp-ppros-block gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-text-slate-800">{label}</span>
        {description && (
          <span className="gp-ppros-mt-0.5 gp-ppros-block gp-ppros-text-xs gp-ppros-text-slate-500">{description}</span>
        )}
      </div>
      <Switch checked={checked} onChange={onChange} />
    </label>
  );
}

export function Field({ label, description, children }) {
  return (
    <label className="gp-ppros-block">
      <span className="gp-ppros-mb-1 gp-ppros-block gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-text-slate-800">{label}</span>
      {description && (
        <span className="gp-ppros-mb-2 gp-ppros-block gp-ppros-text-xs gp-ppros-text-slate-500">{description}</span>
      )}
      {children}
    </label>
  );
}

export const inputClass =
  "gp-ppros-w-full gp-ppros-rounded-lg gp-ppros-border gp-ppros-border-slate-200 gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm focus:gp-ppros-border-brand-400 focus:gp-ppros-outline-none focus:gp-ppros-ring-2 focus:gp-ppros-ring-brand-100";

export function Card({ title, description, actions, children }) {
  return (
    <section className="gp-ppros-rounded-2xl gp-ppros-border gp-ppros-border-slate-200 gp-ppros-bg-white gp-ppros-p-5 gp-ppros-shadow-sm">
      {(title || actions) && (
        <div className="gp-ppros-mb-4 gp-ppros-flex gp-ppros-flex-wrap gp-ppros-items-start gp-ppros-justify-between gp-ppros-gap-3">
          <div>
            {title && <h2 className="gp-ppros-m-0 gp-ppros-text-base gp-ppros-font-bold gp-ppros-text-slate-900">{title}</h2>}
            {description && <p className="gp-ppros-m-0 gp-ppros-mt-1 gp-ppros-text-sm gp-ppros-text-slate-500">{description}</p>}
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
      ? "gp-ppros-bg-red-600 gp-ppros-text-white hover:gp-ppros-bg-red-700"
      : variant === "secondary"
        ? "gp-ppros-bg-slate-100 gp-ppros-text-slate-800 hover:gp-ppros-bg-slate-200"
        : "gp-ppros-bg-brand-600 gp-ppros-text-white hover:gp-ppros-bg-brand-700";

  return (
    <button
      type="button"
      className={`gp-ppros-rounded-lg gp-ppros-border-0 gp-ppros-px-3 gp-ppros-py-2 gp-ppros-text-sm gp-ppros-font-semibold gp-ppros-transition ${styles} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
