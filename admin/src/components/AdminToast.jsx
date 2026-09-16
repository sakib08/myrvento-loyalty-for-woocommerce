export default function AdminToast({ message, type = "success" }) {
  if (!message) {
    return null;
  }

  return (
    <div
      role="status"
      className={`gp-fixed gp-bottom-6 gp-right-6 gp-z-50 gp-max-w-sm gp-rounded-xl gp-px-4 gp-py-3 gp-text-sm gp-font-medium gp-shadow-lg ${
        type === "error" ? "gp-bg-red-600 gp-text-white" : "gp-bg-emerald-600 gp-text-white"
      }`}
    >
      {message}
    </div>
  );
}
