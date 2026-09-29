export default function AdminToast({ message, type = "success" }) {
  if (!message) {
    return null;
  }

  return (
    <div
      role="status"
      className={`ciwp-fixed ciwp-bottom-6 ciwp-right-6 ciwp-z-50 ciwp-max-w-sm ciwp-rounded-xl ciwp-px-4 ciwp-py-3 ciwp-text-sm ciwp-font-medium ciwp-shadow-lg ${
        type === "error" ? "ciwp-bg-red-600 ciwp-text-white" : "ciwp-bg-emerald-600 ciwp-text-white"
      }`}
    >
      {message}
    </div>
  );
}
