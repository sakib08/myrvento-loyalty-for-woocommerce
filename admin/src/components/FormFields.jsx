export function Switch({ checked, onChange, className = "", ...props }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={`myrvento-switch myrvento-relative myrvento-h-6 myrvento-w-11 myrvento-shrink-0 myrvento-rounded-full myrvento-border-0 myrvento-p-0 myrvento-transition ${
        checked ? "myrvento-bg-brand-600" : "myrvento-bg-slate-300"
      } ${className}`.trim()}
      {...props}
    >
      <span
        className={`myrvento-pointer-events-none myrvento-absolute myrvento-top-0.5 myrvento-h-5 myrvento-w-5 myrvento-rounded-full myrvento-bg-white myrvento-shadow myrvento-transition ${
          checked ? "myrvento-left-5" : "myrvento-left-0.5"
        }`}
      />
    </button>
  );
}

export function Toggle({ label, description, checked, onChange }) {
  return (
    <label className="myrvento-flex myrvento-cursor-pointer myrvento-items-center myrvento-justify-between myrvento-gap-4 myrvento-rounded-xl myrvento-border myrvento-border-slate-100 myrvento-bg-slate-50 myrvento-p-4">
      <div className="myrvento-min-w-0 myrvento-flex-1">
        <span className="myrvento-block myrvento-text-sm myrvento-font-semibold myrvento-text-slate-800">{label}</span>
        {description && (
          <span className="myrvento-mt-0.5 myrvento-block myrvento-text-xs myrvento-text-slate-500">{description}</span>
        )}
      </div>
      <Switch checked={checked} onChange={onChange} />
    </label>
  );
}

export function Field({ label, description, children }) {
  return (
    <label className="myrvento-block">
      <span className="myrvento-mb-1 myrvento-block myrvento-text-sm myrvento-font-semibold myrvento-text-slate-800">{label}</span>
      {description && (
        <span className="myrvento-mb-2 myrvento-block myrvento-text-xs myrvento-text-slate-500">{description}</span>
      )}
      {children}
    </label>
  );
}

export const inputClass =
  "myrvento-w-full myrvento-rounded-lg myrvento-border myrvento-border-slate-200 myrvento-px-3 myrvento-py-2 myrvento-text-sm focus:myrvento-border-brand-400 focus:myrvento-outline-none focus:myrvento-ring-2 focus:myrvento-ring-brand-100";

export function Card({ title, description, actions, children }) {
  return (
    <section className="myrvento-rounded-2xl myrvento-border myrvento-border-slate-200 myrvento-bg-white myrvento-p-5 myrvento-shadow-sm">
      {(title || actions) && (
        <div className="myrvento-mb-4 myrvento-flex myrvento-flex-wrap myrvento-items-start myrvento-justify-between myrvento-gap-3">
          <div>
            {title && <h2 className="myrvento-m-0 myrvento-text-base myrvento-font-bold myrvento-text-slate-900">{title}</h2>}
            {description && <p className="myrvento-m-0 myrvento-mt-1 myrvento-text-sm myrvento-text-slate-500">{description}</p>}
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
      ? "myrvento-bg-red-600 myrvento-text-white hover:myrvento-bg-red-700"
      : variant === "secondary"
        ? "myrvento-bg-slate-100 myrvento-text-slate-800 hover:myrvento-bg-slate-200"
        : "myrvento-bg-brand-600 myrvento-text-white hover:myrvento-bg-brand-700";

  return (
    <button
      type="button"
      className={`myrvento-rounded-lg myrvento-border-0 myrvento-px-3 myrvento-py-2 myrvento-text-sm myrvento-font-semibold myrvento-transition ${styles} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
